<?php

namespace Spatie\GoogleCloudStorageAdapter\Tests;

use Google\Cloud\Storage\Acl;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\StorageObject;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use Mockery;
use Psr\Http\Message\StreamInterface;
use Spatie\GoogleCloudStorageAdapter\GoogleCloudStorageAdapter;

class GoogleCloudStorageAdapterClassTest extends Mockery\Adapter\Phpunit\MockeryTestCase
{
    /** @test */
    public function test_get_storage_client()
    {
        $storageClient = Mockery::mock(StorageClient::class);
        $bucket = Mockery::mock(Bucket::class);
        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket);

        $this->assertSame($storageClient, $adapter->getStorageClient());
    }

    /** @test */
    public function test_get_bucket()
    {
        $storageClient = Mockery::mock(StorageClient::class);
        $bucket = Mockery::mock(Bucket::class);
        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket);

        $this->assertSame($bucket, $adapter->getBucket());
    }

    /** @test */
    public function test_write()
    {
        $bucket = Mockery::mock(Bucket::class);

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('name')
            ->once()
            ->andReturn('prefix/file1.txt');
        $storageObject->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'text/plain',
                'size' => 5,
            ]);

        $bucket->shouldReceive('upload')
            ->withArgs([
                'This is the file contents.',
                [
                    'name' => 'prefix/file1.txt',
                    'predefinedAcl' => 'projectPrivate',
                ],
            ])
            ->once()
            ->andReturn($storageObject);

        $storageClient = Mockery::mock(StorageClient::class);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $data = $adapter->write('file1.txt', 'This is the file contents.', new Config());

        $expected = [
            'type' => 'file',
            'dirname' => '',
            'path' => 'file1.txt',
            'timestamp' => 1474901082,
            'mimetype' => 'text/plain',
            'size' => 5,
        ];
        $this->assertEquals($expected, $data);
    }

    /** @test */
    public function test_write_with_private_visibility()
    {
        $bucket = Mockery::mock(Bucket::class);

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('name')
            ->once()
            ->andReturn('prefix/file1.txt');
        $storageObject->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'text/plain',
                'size' => 5,
            ]);

        $bucket->shouldReceive('upload')
            ->withArgs([
                'This is the file contents.',
                [
                    'name' => 'prefix/file1.txt',
                    'predefinedAcl' => 'projectPrivate',
                ],
            ])
            ->once()
            ->andReturn($storageObject);

        $storageClient = Mockery::mock(StorageClient::class);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $data = $adapter->write('file1.txt', 'This is the file contents.', new Config(['visibility' => AdapterInterface::VISIBILITY_PRIVATE]));

        $expected = [
            'type' => 'file',
            'dirname' => '',
            'path' => 'file1.txt',
            'timestamp' => 1474901082,
            'mimetype' => 'text/plain',
            'size' => 5,
        ];
        $this->assertEquals($expected, $data);
    }

    /** @test */
    public function test_write_with_public_visibility()
    {
        $bucket = Mockery::mock(Bucket::class);

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('name')
            ->once()
            ->andReturn('prefix/file1.txt');
        $storageObject->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'text/plain',
                'size' => 5,
            ]);

        $bucket->shouldReceive('upload')
            ->withArgs([
                'This is the file contents.',
                [
                    'name' => 'prefix/file1.txt',
                    'predefinedAcl' => 'publicRead',
                ],
            ])
            ->once()
            ->andReturn($storageObject);

        $storageClient = Mockery::mock(StorageClient::class);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $data = $adapter->write('file1.txt', 'This is the file contents.', new Config(['visibility' => AdapterInterface::VISIBILITY_PUBLIC]));

        $expected = [
            'type' => 'file',
            'dirname' => '',
            'path' => 'file1.txt',
            'timestamp' => 1474901082,
            'mimetype' => 'text/plain',
            'size' => 5,
        ];
        $this->assertEquals($expected, $data);
    }

    /** @test */
    public function test_write_stream()
    {
        $stream = tmpfile();

        $bucket = Mockery::mock(Bucket::class);

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('name')
            ->once()
            ->andReturn('prefix/file1.txt');
        $storageObject->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'text/plain',
                'size' => 5,
            ]);

        $bucket->shouldReceive('upload')
            ->withArgs([
                $stream,
                [
                    'name' => 'prefix/file1.txt',
                    'predefinedAcl' => 'projectPrivate',
                ],
            ])
            ->once()
            ->andReturn($storageObject);

        $storageClient = Mockery::mock(StorageClient::class);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $data = $adapter->writeStream('file1.txt', $stream, new Config());

        fclose($stream);

        $expected = [
            'type' => 'file',
            'dirname' => '',
            'path' => 'file1.txt',
            'timestamp' => 1474901082,
            'mimetype' => 'text/plain',
            'size' => 5,
        ];
        $this->assertEquals($expected, $data);
    }

    /** @test */
    public function test_rename()
    {
        $bucket = Mockery::mock(Bucket::class);

        $oldStorageObjectAcl = Mockery::mock(Acl::class);
        $oldStorageObjectAcl->shouldReceive('get')
            ->with(['entity' => 'allUsers'])
            ->once()
            ->andReturn([
                'role' => Acl::ROLE_OWNER,
            ]);

        $oldStorageObject = Mockery::mock(StorageObject::class);
        $oldStorageObject->shouldReceive('acl')
            ->once()
            ->andReturn($oldStorageObjectAcl);
        $oldStorageObject->shouldReceive('copy')
            ->withArgs([
                $bucket,
                [
                    'name' => 'prefix/new_file.txt',
                    'predefinedAcl' => 'projectPrivate',
                ],
            ])
            ->once();
        $oldStorageObject->shouldReceive('delete')
            ->once();

        $bucket->shouldReceive('object')
            ->with('prefix/old_file.txt')
            ->times(3)
            ->andReturn($oldStorageObject);

        $storageClient = Mockery::mock(StorageClient::class);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $adapter->rename('old_file.txt', 'new_file.txt');
    }

    /** @test */
    public function test_copy()
    {
        $bucket = Mockery::mock(Bucket::class);

        $oldStorageObjectAcl = Mockery::mock(Acl::class);
        $oldStorageObjectAcl->shouldReceive('get')
            ->with(['entity' => 'allUsers'])
            ->once()
            ->andReturn([
                'role' => Acl::ROLE_OWNER,
            ]);

        $oldStorageObject = Mockery::mock(StorageObject::class);
        $oldStorageObject->shouldReceive('acl')
            ->once()
            ->andReturn($oldStorageObjectAcl);
        $oldStorageObject->shouldReceive('copy')
            ->withArgs([
                $bucket,
                [
                    'name' => 'prefix/new_file.txt',
                    'predefinedAcl' => 'projectPrivate',
                ],
            ])
            ->once();

        $bucket->shouldReceive('object')
            ->with('prefix/old_file.txt')
            ->times(2)
            ->andReturn($oldStorageObject);

        $storageClient = Mockery::mock(StorageClient::class);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $adapter->copy('old_file.txt', 'new_file.txt');
    }

    /** @test */
    public function test_copy_when_original_file_is_public()
    {
        $storageClient = Mockery::mock(StorageClient::class);
        $bucket = Mockery::mock(Bucket::class);

        $oldStorageObjectAcl = Mockery::mock(Acl::class);
        $oldStorageObjectAcl->shouldReceive('get')
            ->with(['entity' => 'allUsers'])
            ->once()
            ->andReturn([
                'role' => Acl::ROLE_READER,
            ]);

        $oldStorageObject = Mockery::mock(StorageObject::class);
        $oldStorageObject->shouldReceive('acl')
            ->once()
            ->andReturn($oldStorageObjectAcl);
        $oldStorageObject->shouldReceive('copy')
            ->withArgs([
                $bucket,
                [
                    'name' => 'prefix/new_file.txt',
                    'predefinedAcl' => 'publicRead',
                ],
            ])
            ->once();

        $bucket->shouldReceive('object')
            ->with('prefix/old_file.txt')
            ->times(2)
            ->andReturn($oldStorageObject);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $adapter->copy('old_file.txt', 'new_file.txt');
    }

    /** @test */
    public function test_delete()
    {
        $storageClient = Mockery::mock(StorageClient::class);
        $bucket = Mockery::mock(Bucket::class);

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('delete')
            ->once();

        $bucket->shouldReceive('object')
            ->with('prefix/file.txt')
            ->once()
            ->andReturn($storageObject);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $adapter->delete('file.txt');
    }

    /** @test */
    public function test_delete_dir()
    {
        $storageClient = Mockery::mock(StorageClient::class);
        $bucket = Mockery::mock(Bucket::class);

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('exists')->times(3)->andReturn(true);
        $storageObject->shouldReceive('delete')
            ->times(3);
        $storageObject->shouldReceive('name')
            ->once()
            ->andReturn('prefix/dir_name/directory1/file1.txt');
        $storageObject->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'text/plain',
                'size' => 5,
            ]);

        $bucket->shouldReceive('object')
            ->with('prefix/dir_name/directory1/file1.txt/')
            ->once()
            ->andReturn($storageObject);

        $bucket->shouldReceive('object')
            ->with('prefix/dir_name/directory1/file1.txt')
            ->once()
            ->andReturn($storageObject);

        $bucket->shouldReceive('object')
            ->with('prefix/dir_name/directory1/')
            ->twice()
            ->andReturn($storageObject);

        $bucket->shouldReceive('object')
            ->with('prefix/dir_name/')
            ->twice()
            ->andReturn($storageObject);

        $bucket->shouldReceive('objects')
            ->with([
                'prefix' => 'prefix/dir_name/',
            ])->once()
            ->andReturn([$storageObject]);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $adapter->deleteDir('dir_name');
    }

    /** @test */
    public function test_delete_dir_with_trailing_slash()
    {
        $storageClient = Mockery::mock(StorageClient::class);
        $bucket = Mockery::mock(Bucket::class);

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('exists')->times(3)->andReturn(true);
        $storageObject->shouldReceive('delete')
            ->times(3);

        $storageObject->shouldReceive('name')
            ->once()
            ->andReturn('prefix/dir_name/directory1/file1.txt');
        $storageObject->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'text/plain',
                'size' => 5,
            ]);

        $bucket->shouldReceive('object')
            ->with('prefix/dir_name/directory1/file1.txt/')
            ->once()
            ->andReturn($storageObject);

        $bucket->shouldReceive('object')
            ->with('prefix/dir_name/directory1/file1.txt')
            ->once()
            ->andReturn($storageObject);

        $bucket->shouldReceive('object')
            ->with('prefix/dir_name/directory1/')
            ->twice()
            ->andReturn($storageObject);

        $bucket->shouldReceive('object')
            ->with('prefix/dir_name/')
            ->twice()
            ->andReturn($storageObject);

        $bucket->shouldReceive('objects')
            ->with([
                'prefix' => 'prefix/dir_name/',
            ])->once()
            ->andReturn([$storageObject]);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $adapter->deleteDir('dir_name//');
    }

    /** @test */
    public function test_set_visibility_private()
    {
        $bucket = Mockery::mock(Bucket::class);

        $storageObjectAcl = Mockery::mock(Acl::class);
        $storageObjectAcl->shouldReceive('delete')
            ->with('allUsers')
            ->once();

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('acl')
            ->once()
            ->andReturn($storageObjectAcl);
        $storageObject->shouldReceive('name')
            ->once()
            ->andReturn('prefix/file.txt');
        $storageObject->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'text/plain',
                'size' => 5,
            ]);

        $bucket->shouldReceive('object')
            ->with('prefix/file1.txt')
            ->once()
            ->andReturn($storageObject);

        $storageClient = Mockery::mock(StorageClient::class);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $data = $adapter->setVisibility('file1.txt', AdapterInterface::VISIBILITY_PRIVATE);
        $this->assertArrayHasKey('visibility', $data);
        $this->assertEquals(AdapterInterface::VISIBILITY_PRIVATE, $data['visibility']);
    }

    /** @test */
    public function test_set_visibility_public()
    {
        $bucket = Mockery::mock(Bucket::class);

        $storageObjectAcl = Mockery::mock(Acl::class);
        $storageObjectAcl->shouldReceive('add')
            ->withArgs([
                'allUsers',
                Acl::ROLE_READER,
            ])
            ->once();

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('acl')
            ->once()
            ->andReturn($storageObjectAcl);
        $storageObject->shouldReceive('name')
            ->once()
            ->andReturn('prefix/file.txt');
        $storageObject->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'text/plain',
                'size' => 5,
            ]);

        $bucket->shouldReceive('object')
            ->with('prefix/file1.txt')
            ->once()
            ->andReturn($storageObject);

        $storageClient = Mockery::mock(StorageClient::class);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $data = $adapter->setVisibility('file1.txt', AdapterInterface::VISIBILITY_PUBLIC);
        $this->assertArrayHasKey('visibility', $data);
        $this->assertEquals(AdapterInterface::VISIBILITY_PUBLIC, $data['visibility']);
    }

    /** @test */
    public function test_has()
    {
        $storageClient = Mockery::mock(StorageClient::class);
        $bucket = Mockery::mock(Bucket::class);

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('exists')
            ->twice()
            ->andReturn(false);

        $bucket->shouldReceive('object')
            ->with('prefix/file.txt/')
            ->once()
            ->andReturn($storageObject);

        $bucket->shouldReceive('object')
            ->with('prefix/file.txt')
            ->once()
            ->andReturn($storageObject);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $adapter->has('file.txt');
    }

    /** @test */
    public function test_read()
    {
        $storageClient = Mockery::mock(StorageClient::class);
        $bucket = Mockery::mock(Bucket::class);

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('downloadAsString')
            ->once()
            ->andReturn('This is the file contents.');
        $storageObject->shouldReceive('name')
            ->once()
            ->andReturn('prefix/file.txt');
        $storageObject->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'text/plain',
                'size' => 5,
            ]);

        $bucket->shouldReceive('object')
            ->with('prefix/file.txt')
            ->once()
            ->andReturn($storageObject);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $data = $adapter->read('file.txt');

        $this->assertArrayHasKey('contents', $data);
        $this->assertEquals('This is the file contents.', $data['contents']);
    }

    /** @test */
    public function test_read_stream()
    {
        $storageClient = Mockery::mock(StorageClient::class);
        $bucket = Mockery::mock(Bucket::class);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('isReadable')
            ->once()
            ->andReturn(true);
        $stream->shouldReceive('isWritable')
            ->once()
            ->andReturn(false);

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('downloadAsStream')
            ->once()
            ->andReturn($stream);
        $storageObject->shouldReceive('name')
            ->once()
            ->andReturn('prefix/file.txt');
        $storageObject->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'text/plain',
                'size' => 5,
            ]);

        $bucket->shouldReceive('object')
            ->with('prefix/file.txt')
            ->once()
            ->andReturn($storageObject);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $data = $adapter->readStream('file.txt');

        $this->assertArrayHasKey('stream', $data);
        $this->assertTrue(is_resource($data['stream']));
    }

    /** @test */
    public function test_list_contents()
    {
        $storageClient = Mockery::mock(StorageClient::class);
        $bucket = Mockery::mock(Bucket::class);

        $prefix = 'prefix/';

        $bucket->shouldReceive('objects')
            ->once()
            ->with([
                'prefix' => $prefix,
            ])
            ->andReturn($this->getMockDirObjects($prefix));

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $listing = $adapter->listContents();

        $expected = [
            [
                'type' => 'dir',
                'dirname' => '',
                'path' => 'directory1',
                'timestamp' => 1474901082,
                'mimetype' => 'application/octet-stream',
                'size' => 0,
            ],
            [
                'type' => 'file',
                'dirname' => 'directory1',
                'path' => 'directory1/file1.txt',
                'timestamp' => 1474901082,
                'mimetype' => 'text/plain',
                'size' => 5,
            ],
            [
                'type' => 'file',
                'dirname' => 'directory2',
                'path' => 'directory2/file1.txt',
                'timestamp' => 1474901082,
                'mimetype' => 'text/plain',
                'size' => 5,
            ],
            [
                'dirname' => '',
                'basename' => 'directory2',
                'filename' => 'directory2',
                'path' => 'directory2',
                'type' => 'dir',
            ],
        ];

        $this->assertEquals($expected, $listing);
    }

    protected function getMockDirObjects(string $prefix = ''): array
    {
        $dir1 = Mockery::mock(StorageObject::class);
        $dir1->shouldReceive('name')
            ->once()
            ->andReturn($prefix . 'directory1/');
        $dir1->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'application/octet-stream',
                'size' => 0,
            ]);

        $dir1file1 = Mockery::mock(StorageObject::class);
        $dir1file1->shouldReceive('name')
            ->once()
            ->andReturn($prefix . 'directory1/file1.txt');
        $dir1file1->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'text/plain',
                'size' => 5,
            ]);

        $dir2file1 = Mockery::mock(StorageObject::class);
        $dir2file1->shouldReceive('name')
            ->once()
            ->andReturn($prefix . 'directory2/file1.txt');
        $dir2file1->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'text/plain',
                'size' => 5,
            ]);

        return [
            $dir1,
            $dir1file1,
            $dir2file1,
        ];
    }

    /** @test */
    public function test_get_metadata_for_file()
    {
        $storageClient = Mockery::mock(StorageClient::class);
        $bucket = Mockery::mock(Bucket::class);

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('name')
            ->once()
            ->andReturn('prefix/file.txt');
        $storageObject->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'text/plain',
                'size' => 5,
            ]);

        $bucket->shouldReceive('object')
            ->with('prefix/file.txt')
            ->once()
            ->andReturn($storageObject);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $metadata = $adapter->getMetadata('file.txt');

        $expected = [
            'type' => 'file',
            'dirname' => '',
            'path' => 'file.txt',
            'timestamp' => 1474901082,
            'mimetype' => 'text/plain',
            'size' => 5,
        ];

        $this->assertEquals($expected, $metadata);
    }

    /** @test */
    public function test_get_metadata_for_dir()
    {
        $storageClient = Mockery::mock(StorageClient::class);
        $bucket = Mockery::mock(Bucket::class);

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('name')
            ->once()
            ->andReturn('prefix/directory/');
        $storageObject->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'application/octet-stream',
                'size' => 0,
            ]);

        $bucket->shouldReceive('object')
            ->with('prefix/directory')
            ->once()
            ->andReturn($storageObject);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $metadata = $adapter->getMetadata('directory');

        $expected = [
            'type' => 'dir',
            'dirname' => '',
            'path' => 'directory',
            'timestamp' => 1474901082,
            'mimetype' => 'application/octet-stream',
            'size' => 0,
        ];

        $this->assertEquals($expected, $metadata);
    }

    /** @test */
    public function test_get_size()
    {
        $storageClient = Mockery::mock(StorageClient::class);
        $bucket = Mockery::mock(Bucket::class);

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('name')
            ->once()
            ->andReturn('prefix/file.txt');
        $storageObject->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'text/plain',
                'size' => 5,
            ]);

        $bucket->shouldReceive('object')
            ->with('prefix/file.txt')
            ->once()
            ->andReturn($storageObject);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $metadata = $adapter->getMetadata('file.txt');

        $this->assertArrayHasKey('size', $metadata);
        $this->assertEquals(5, $metadata['size']);
    }

    /** @test */
    public function test_get_mimetype()
    {
        $storageClient = Mockery::mock(StorageClient::class);
        $bucket = Mockery::mock(Bucket::class);

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('name')
            ->once()
            ->andReturn('prefix/file.txt');
        $storageObject->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'text/plain',
                'size' => 5,
            ]);

        $bucket->shouldReceive('object')
            ->with('prefix/file.txt')
            ->once()
            ->andReturn($storageObject);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $metadata = $adapter->getMetadata('file.txt');

        $this->assertArrayHasKey('mimetype', $metadata);
        $this->assertEquals('text/plain', $metadata['mimetype']);
    }

    /** @test */
    public function test_get_timestamp()
    {
        $storageClient = Mockery::mock(StorageClient::class);
        $bucket = Mockery::mock(Bucket::class);

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('name')
            ->once()
            ->andReturn('prefix/file.txt');
        $storageObject->shouldReceive('info')
            ->once()
            ->andReturn([
                'updated' => '2016-09-26T14:44:42+00:00',
                'contentType' => 'text/plain',
                'size' => 5,
            ]);

        $bucket->shouldReceive('object')
            ->with('prefix/file.txt')
            ->once()
            ->andReturn($storageObject);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $metadata = $adapter->getMetadata('file.txt');

        $this->assertArrayHasKey('timestamp', $metadata);
        $this->assertEquals(1474901082, $metadata['timestamp']);
    }

    /** @test */
    public function test_get_visibility_when_visibility_is_private()
    {
        $bucket = Mockery::mock(Bucket::class);

        $storageObjectAcl = Mockery::mock(Acl::class);
        $storageObjectAcl->shouldReceive('get')
            ->with(['entity' => 'allUsers'])
            ->once()
            ->andReturn([
                'role' => Acl::ROLE_OWNER,
            ]);

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('acl')
            ->once()
            ->andReturn($storageObjectAcl);

        $bucket->shouldReceive('object')
            ->with('prefix/file.txt')
            ->once()
            ->andReturn($storageObject);

        $storageClient = Mockery::mock(StorageClient::class);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $visibility = $adapter->getVisibility('file.txt');
        $this->assertEquals(['visibility' => AdapterInterface::VISIBILITY_PRIVATE], $visibility);
    }

    /** @test */
    public function test_get_visibility_when_visibility_is_public()
    {
        $bucket = Mockery::mock(Bucket::class);

        $storageObjectAcl = Mockery::mock(Acl::class);
        $storageObjectAcl->shouldReceive('get')
            ->with(['entity' => 'allUsers'])
            ->once()
            ->andReturn([
                'role' => Acl::ROLE_READER,
            ]);

        $storageObject = Mockery::mock(StorageObject::class);
        $storageObject->shouldReceive('acl')
            ->once()
            ->andReturn($storageObjectAcl);

        $bucket->shouldReceive('object')
            ->with('prefix/file.txt')
            ->once()
            ->andReturn($storageObject);

        $storageClient = Mockery::mock(StorageClient::class);

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, 'prefix');

        $visibility = $adapter->getVisibility('file.txt');
        $this->assertEquals(['visibility' => AdapterInterface::VISIBILITY_PUBLIC], $visibility);
    }

    /** @test */
    public function test_set_get_storage_api_uri()
    {
        $storageClient = Mockery::mock(StorageClient::class);
        $bucket = Mockery::mock(Bucket::class);
        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket);

        $this->assertEquals('https://storage.googleapis.com', $adapter->getStorageApiUri());

        $adapter->setStorageApiUri('http://my.custom.domain.com');
        $this->assertEquals('http://my.custom.domain.com', $adapter->getStorageApiUri());

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket, null, 'http://this.is.my.base.com');
        $this->assertEquals('http://this.is.my.base.com', $adapter->getStorageApiUri());
    }

    /** @test */
    public function test_get_url()
    {
        $storageClient = Mockery::mock(StorageClient::class);

        $bucket = Mockery::mock(Bucket::class);
        $bucket->shouldReceive('name')
            ->andReturn('my-bucket');

        $adapter = new GoogleCloudStorageAdapter($storageClient, $bucket);
        $this->assertEquals('https://storage.googleapis.com/my-bucket/file.txt', $adapter->getUrl('file.txt'));
        $this->assertEquals('https://storage.googleapis.com/my-bucket/test%20folder/file%281%29.txt', $adapter->getUrl('test folder/file(1).txt'));

        $adapter->setPathPrefix('prefix');
        $this->assertEquals('https://storage.googleapis.com/my-bucket/prefix/file.txt', $adapter->getUrl('file.txt'));

        $adapter->setStorageApiUri('http://my-domain.com/');
        $adapter->setPathPrefix('another-prefix');
        // no bucket name on custom domain
        $this->assertEquals('http://my-domain.com/another-prefix/dir/file.txt', $adapter->getUrl('dir/file.txt'));
    }
}
