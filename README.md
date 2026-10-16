# Attachments

CakePHP plugin for storing uploaded files against registered owner records.

## Installation

```bash
composer require uskur/attachments
```

Load the plugin and attach the behavior to the owner table:

```php
$this->addBehavior('Uskur/Attachments.Attachments');
```

## What It Provides

- attachment rows stored in `attachments`
- behavior-driven owner integration via `hasMany('Attachments')`
- file and image delivery routes under `/attachments`
- optional S3-backed storage
- image resizing for previews and frontend delivery

## Usage

The behavior reads uploaded files from `attachment_uploads` by default and stores them
after the owning entity has been saved. It accepts PSR-7 uploaded files. Use the native request upload objects rather than
legacy upload arrays.

## Routing

The plugin exposes:

- `/attachments/file/*`
- `/attachments/image/*`

JSON extensions are scoped to the plugin routes only. The package no longer registers a
global `Router::extensions()` hook.

## Storage

Local files are stored under `Configure::read('Attachment.path')`, sharded by MD5
prefix. If S3 settings are configured, files are also pushed to object storage and can
be fetched back to local disk when needed.

## Authorization and upgrades

See [the host integration guide](docs/authorization.md) for owner registration,
permissions, CSRF, writable fields, replacement, and retryable storage cleanup.

## Testing

The package suite uses CakePHP fixtures and the enclosing host application's test
bootstrap and migrations. Run from the host root:

```bash
ddev exec vendor/bin/phpunit -c vendor/uskur/attachments/phpunit.xml.dist
```

The configured test database must be named `test`. The host bootstrap must guard
that database before rebuilding its schema. Package tests use a generic owner
table and isolated temporary storage; S3 operations use the AWS SDK mock handler
and make no network requests. Host-specific permissions and routes belong in the
host application's suite.

## CakePHP compatibility notes

- event callbacks use `EventInterface`
- plugin routes use the CakePHP 4 route callback style
- the reverse owner association points at the actual owner table

## Deferred Work

- hosts must configure permissions for attachment owners; see the
  [authorization and storage upgrade guide](docs/authorization.md)
- template line-length warnings remain even though the package can be made `phpcs`
  error-clean
