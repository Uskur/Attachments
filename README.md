# Attachments

CakePHP plugin for storing uploaded files against arbitrary owner records.

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
after the owning entity has been saved. It accepts both PSR-7 uploaded files and legacy
upload arrays.

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

## CakePHP 4 Notes

- event callbacks use `EventInterface`
- plugin routes use the CakePHP 4 route callback style
- the reverse owner association points at the actual owner table

## Deferred Work

- file/image/download authorization is still controller-driven and should be reviewed
  separately if private files are involved
- template line-length warnings remain even though the package can be made `phpcs`
  error-clean
