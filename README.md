# Flysystem adapter for Google Cloud Storage

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/flysystem-google-cloud-storage.svg?style=flat-square)](https://packagist.org/packages/spatie/flysystem-google-cloud-storage)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/spatie/flysystem-google-cloud-storage/Tests/main?label=tests)](https://github.com/spatie/flysystem-google-cloud-storage/actions?query=workflow%3ATests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/spatie/flysystem-google-cloud-storage/Check%20&%20fix%20styling/main?label=code%20style)](https://github.com/spatie/flysystem-google-cloud-storage/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/flysystem-google-cloud-storage.svg?style=flat-square)](https://packagist.org/packages/spatie/flysystem-google-cloud-storage)

This package contains a [Google Cloud Storage](https://cloud.google.com/storage) driver for [Flysystem](https://flysystem.thephpleague.com/v2/docs/).

## Notice

This package is a fork from [superbalist/flysystem-google-cloud-storage](https://github.com/Superbalist/flysystem-google-cloud-storage). Changes include:

- PHP 8 only
- merged random open PRs from Superbalist's package


## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/flysystem-google-cloud-storage.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/flysystem-google-cloud-storage)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source)
. You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are
using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received
postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require spatie/flysystem-google-cloud-storage
```

## Authentication

Before you can use the package, you'll need to authenticate with Google. When possible, the credentials will be auto-loaded by the Google Cloud Client.

1. The client will first look at the GOOGLE_APPLICATION_CREDENTIALS env var. You can use `putenv('GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account.json');` to set the location of your credentials file.
2. The client will look for the credentials file at the following paths:
    - Windows: `%APPDATA%/gcloud/application_default_credentials.json`
    - MacOS/Unix: `$HOME/.config/gcloud/application_default_credentials.json`
3. If running in Google App Engine, Google Compute Engine or GKE, the built-in service account associated with the app, instance or cluster will be used.

Using this in a Kubernetes cluster? Take a look at [Workload Identity](https://cloud.google.com/kubernetes-engine/docs/how-to/workload-identity).

## Usage

Here's an example that shows you have you can call the various functions to manipulate files on Google Cloud.

```php
use Google\Cloud\Storage\StorageClient;
use League\Flysystem\Filesystem;
use Spatie\GoogleCloudStorageAdapter\GoogleCloudStorageAdapter;

$storageClient = new StorageClient([
    'projectId' => 'your-project-id',
    // The credentials can manually be specified by passing in a keyFilePath.
    // 'keyFilePath' => '/path/to/service-account.json',
]);

$bucket = $storageClient->bucket('your-bucket-name');

$adapter = new GoogleCloudStorageAdapter($storageClient, $bucket);

$filesystem = new Filesystem($adapter);

$filesystem->write('path/to/file.txt', 'contents');
$filesystem->update('path/to/file.txt', 'new contents');
$contents = $filesystem->read('path/to/file.txt');
$exists = $filesystem->has('path/to/file.txt');
$filesystem->delete('path/to/file.txt');
$filesystem->rename('filename.txt', 'newname.txt');
$filesystem->copy('filename.txt', 'duplicate.txt');
$filesystem->deleteDir('path/to/directory');
```

See [https://flysystem.thephpleague.com/v1/docs/usage/filesystem-api/](https://flysystem.thephpleague.com/v1/docs/usage/filesystem-api/) for full list of available functionality

### Using a custom storage URI

You can configure this adapter to use a custom storage URI. Read more about configuring a custom domain for Google Cloud Storage [here](https://cloud.google.com/storage/docs/request-endpoints#cname).

When using a custom storage URI, the bucket name will not prepended to the file path:

```php
use Google\Cloud\Storage\StorageClient;
use League\Flysystem\Filesystem;
use Spatie\GoogleCloudStorageAdapter\GoogleCloudStorageAdapter;

$storageClient = new StorageClient([
    'projectId' => 'your-project-id',
]);
$bucket = $storageClient->bucket('your-bucket-name');
$adapter = new GoogleCloudStorageAdapter($storageClient, $bucket);

// URI defaults to "https://storage.googleapis.com":
$filesystem = new Filesystem($adapter);
$filesystem->getUrl('path/to/file.txt');
// "https://storage.googleapis.com/your-bucket-name/path/to/file.txt"

// Using custom storage uri:
$adapter->setStorageApiUri('http://example.com');
$filesystem = new Filesystem($adapter);
$filesystem->getUrl('path/to/file.txt');
// "http://example.com/path/to/file.txt"

// You can also prefix the file path if needed:
$adapter->setStorageApiUri('http://example.com');
$adapter->setPathPrefix('extra-folder/another-folder/');
$filesystem = new Filesystem($adapter);
$filesystem->getUrl('path/to/file.txt');
// "http://example.com/extra-folder/another-folder/path/to/file.txt"
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Alex Vanderbist](https://github.com/alexvanderbist)
- [All Contributors](../../contributors)
- [Superbalist](https://github.com/Superbalist) for the initial package

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
