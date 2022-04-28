<?php

namespace Spatie\GoogleCloudStorageAdapter;

use Google\Cloud\Core\Exception\NotFoundException;
use Google\Cloud\Storage\Acl;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\StorageObject;
use GuzzleHttp\Psr7\StreamWrapper;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\CanOverwriteFiles;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util;

class GoogleCloudStorageAdapter extends AbstractAdapter implements CanOverwriteFiles
{
    public const STORAGE_API_URI_DEFAULT = 'https://storage.googleapis.com';

    protected StorageClient $storageClient;

    protected Bucket $bucket;

    protected string $storageApiUri;

    public function __construct(
        StorageClient $storageClient,
        Bucket $bucket,
        string $pathPrefix = null,
        string $storageApiUri = null
    ) {
        $this->storageClient = $storageClient;
        $this->bucket = $bucket;

        if ($pathPrefix) {
            $this->setPathPrefix($pathPrefix);
        }

        $this->storageApiUri = ($storageApiUri) ?: self::STORAGE_API_URI_DEFAULT;
    }

    public function getStorageClient(): StorageClient
    {
        return $this->storageClient;
    }

    public function getBucket(): Bucket
    {
        return $this->bucket;
    }

    public function setStorageApiUri(string $uri)
    {
        $this->storageApiUri = $uri;
    }

    public function getStorageApiUri(): string
    {
        return $this->storageApiUri;
    }

    public function write($path, $contents, Config $config): array
    {
        return $this->upload($path, $contents, $config);
    }

    public function writeStream($path, $resource, Config $config): array
    {
        return $this->upload($path, $resource, $config);
    }

    public function update($path, $contents, Config $config): array
    {
        return $this->upload($path, $contents, $config);
    }

    public function updateStream($path, $resource, Config $config): array
    {
        return $this->upload($path, $resource, $config);
    }

    protected function getOptionsFromConfig(Config $config): array
    {
        $options = [];

        if (! $this->uniformBucketLevelAccessEnabled()) {
            if ($visibility = $config->get('visibility')) {
                $options['predefinedAcl'] = $this->getPredefinedAclForVisibility($visibility);
            } else {
                // if a file is created without an acl, it isn't accessible via the console
                // we therefore default to private
                $options['predefinedAcl'] = $this->getPredefinedAclForVisibility(AdapterInterface::VISIBILITY_PRIVATE);
            }
        }

        if ($mimetype = $config->get('mimetype')) {
            $options['mimetype'] = $mimetype;
            $options['metadata']['contentType'] = $mimetype;
        }

        if ($metadata = $config->get('metadata')) {
            $options['metadata'] = $metadata;
        }

        if ($chunkSize = $config->get('chunkSize')) {
            $options['chunkSize'] = $chunkSize;
        }

        if ($uploadProgressCallback = $config->get('uploadProgressCallback')) {
            $options['uploadProgressCallback'] = $uploadProgressCallback;
        }

        return $options;
    }

    /**
     * @param string $path
     * @param string|resource $contents
     * @param Config $config
     *
     * @return array
     */
    protected function upload(string $path, mixed $contents, Config $config): array
    {
        $path = $this->applyPathPrefix($path);

        $options = $this->getOptionsFromConfig($config);

        $options['name'] = $path;

        if (! $this->isDirectory($path)) {
            if (! isset($options['metadata']['contentType'])) {
                $options['metadata']['contentType'] = Util::guessMimeType($path, $contents);
            }
        }

        $object = $this->bucket->upload($contents, $options);

        return $this->normaliseObject($object);
    }

    protected function normaliseObject(StorageObject $object): array
    {
        $name = $this->removePathPrefix($object->name());
        $info = $object->info();

        $isDirectory = $this->isDirectory($name);
        if ($isDirectory) {
            $name = rtrim($name, '/');
        }

        return [
            'type' => $isDirectory ? 'dir' : 'file',
            'dirname' => Util::dirname($name),
            'path' => $name,
            'timestamp' => strtotime($info['updated']),
            'mimetype' => $info['contentType'] ?? '',
            'size' => $info['size'],
        ];
    }

    public function rename($path, $newpath): bool
    {
        if (! $this->copy($path, $newpath)) {
            return false;
        }

        return $this->delete($path);
    }

    public function copy($path, $newpath): bool
    {
        $newpath = $this->applyPathPrefix($newpath);

        // we want the new file to have the same visibility as the original file
        $visibility = $this->getRawVisibility($path);

        $options = [
            'name' => $newpath,
            'predefinedAcl' => $this->getPredefinedAclForVisibility($visibility),
        ];
        $this->getObject($path)->copy($this->bucket, $options);

        return true;
    }

    public function delete($path): bool
    {
        $this->getObject($path)->delete();

        return true;
    }

    public function deleteDir($dirname): bool
    {
        $dirname = $this->normaliseDirectoryName($dirname);
        $objects = $this->listContents($dirname, true);

        // We first delete the file, so that we can delete
        // the empty folder at the end.
        uasort($objects, function ($_a, $b) {
            return $b['type'] === 'file' ? 1 : -1;
        });

        // We remove all objects that should not be deleted.
        $filtered_objects = [];
        foreach ($objects as $object) {
            // normalise directories path
            if ($object['type'] === 'dir') {
                $object['path'] = $this->normaliseDirectoryName($object['path']);
            }

            if (strpos($object['path'], $dirname) !== false) {
                $filtered_objects[] = $object;
            }
        }

        // Execute deletion for each object (if it still exists at this point).
        foreach ($filtered_objects as $object) {
            if ($this->has($object['path'])) {
                $this->delete($object['path']);
            }
        }

        return true;
    }

    public function createDir($dirname, Config $config): array
    {
        return $this->upload($this->normaliseDirectoryName($dirname), '', $config);
    }

    protected function normaliseDirectoryName(string $dirname): string
    {
        return rtrim($dirname, '/') . '/';
    }

    public function setVisibility($path, $visibility): array
    {
        $object = $this->getObject($path);

        if ($visibility === AdapterInterface::VISIBILITY_PRIVATE) {
            try {
                $object->acl()->delete('allUsers');
            } catch (NotFoundException) {
                // Not actually an exception, no ACL to delete.
            }
        } elseif ($visibility === AdapterInterface::VISIBILITY_PUBLIC) {
            $object->acl()->add('allUsers', Acl::ROLE_READER);
        }

        $normalised = $this->normaliseObject($object);
        $normalised['visibility'] = $visibility;

        return $normalised;
    }

    public function has($path): bool
    {
        // Path has a `/` suffix. We are definitely checking for a directory.
        if ($this->isDirectory($path)) {
            return $this->getObject($path)->exists();
        }

        // Path might be directory with missing `/` suffix. Add `/` suffix first to check.
        return $this->getObject($path . '/')->exists()
            || $this->getObject($path)->exists();
    }

    public function read($path): array
    {
        $object = $this->getObject($path);
        $contents = $object->downloadAsString();

        $data = $this->normaliseObject($object);
        $data['contents'] = $contents;

        return $data;
    }

    public function readStream($path): array
    {
        $object = $this->getObject($path);

        $data = $this->normaliseObject($object);
        $data['stream'] = StreamWrapper::getResource($object->downloadAsStream());

        return $data;
    }

    /**
     * @param string $directory
     * @param bool $recursive
     *
     * @psalm-suppress TooManyTemplateParams
     *
     * @return array
     */
    public function listContents($directory = '', $recursive = false): array
    {
        $directory = $this->applyPathPrefix($directory);

        $objects = $this->bucket->objects(['prefix' => $directory]);

        $normalised = [];
        foreach ($objects as $object) {
            $normalised[] = $this->normaliseObject($object);
        }

        return Util::emulateDirectories($normalised);
    }

    public function getMetadata($path): array
    {
        $object = $this->getObject($path);

        return $this->normaliseObject($object);
    }

    public function getSize($path): array
    {
        return $this->getMetadata($path);
    }

    public function getMimetype($path): array
    {
        return $this->getMetadata($path);
    }

    public function getTimestamp($path): array
    {
        return $this->getMetadata($path);
    }

    public function getVisibility($path): array
    {
        return [
            'visibility' => $this->getRawVisibility($path),
        ];
    }

    /**
     * Return a public url to a file.
     *
     * Note: The file must have `AdapterInterface::VISIBILITY_PUBLIC` visibility.
     *
     * @param string $path
     *
     * @return string
     */
    public function getUrl(string $path): string
    {
        $uri = rtrim($this->storageApiUri, '/');
        $path = $this->applyPathPrefix($path);

        // Generating an uri with whitespaces or any other characters besides alphanumeric characters or "-_.~" will
        // not be RFC 3986 compliant. They will work in most browsers because they are automatically encoded but
        // may fail when passed to other software modules which are not doing automatic encoding.
        $path = implode('/', array_map('rawurlencode', explode('/', $path)));

        // Only prepend bucket name if no custom storage uri specified
        // Default: "https://storage.googleapis.com/{my_bucket}/{path_prefix}"
        // Custom: "https://example.com/{path_prefix}"
        if ($this->getStorageApiUri() === self::STORAGE_API_URI_DEFAULT) {
            $path = $this->bucket->name() . '/' . $path;
        }

        return $uri . '/' . $path;
    }

    public function getTemporaryUrl(
        string $path,
        \DateTimeInterface | int $expiration,
        array $options = []
    ): string {
        $object = $this->getObject($path);

        $signedUrl = $object->signedUrl($expiration, $options);

        if ($this->getStorageApiUri() !== self::STORAGE_API_URI_DEFAULT) {
            [, $params] = explode('?', $signedUrl, 2);

            $signedUrl = $this->getUrl($path) . '?' . $params;
        }

        return $signedUrl;
    }

    protected function getRawVisibility(string $path): string
    {
        if ($this->uniformBucketLevelAccessEnabled()) {
            return AdapterInterface::VISIBILITY_PRIVATE;
        }

        try {
            $acl = $this->getObject($path)->acl()->get(['entity' => 'allUsers']);

            return $acl['role'] === Acl::ROLE_READER
                ? AdapterInterface::VISIBILITY_PUBLIC
                : AdapterInterface::VISIBILITY_PRIVATE;
        } catch (NotFoundException $e) {
            // object may not have an acl entry, so handle that gracefully
            return AdapterInterface::VISIBILITY_PRIVATE;
        }
    }

    protected function getObject(string $path): StorageObject
    {
        $path = $this->applyPathPrefix($path);

        return $this->bucket->object($path);
    }

    protected function getPredefinedAclForVisibility(string $visibility): string
    {
        return $visibility === AdapterInterface::VISIBILITY_PUBLIC ? 'publicRead' : 'projectPrivate';
    }

    protected function isDirectory(string $path): bool
    {
        return substr($path, -1) === '/';
    }

    public function uniformBucketLevelAccessEnabled(): bool
    {
        return $this->bucket->info()['iamConfiguration']['uniformBucketLevelAccess']['enabled'] ?? false;
    }
}
