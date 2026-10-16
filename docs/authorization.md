# Host authorization and storage integration

This package stores files; the host determines who may access their owners. Do not grant wildcard access to the Attachments controller. Put authorization in the host permission policy, rather than adding ownership branches to this controller.

## Register owners

Configure an exact map of stored model names to trusted ORM aliases before handling requests. Names containing dots are literal array keys:

```php
Configure::write('Attachment.models', [
    'Articles' => 'Articles',
    'Attachments' => 'Uskur/Attachments.Attachments',
    'Uskur/Attachments.Attachments' => 'Uskur/Attachments.Attachments',
]);
```

The behavior also registers its trusted table alias when initialized. Explicit host registration is necessary for requests that arrive before the owner table is loaded. Register legacy aliases only when they actually belong to a supported owner. Route model names replace `/` with `-`; they normalize back before lookup. Unknown names cannot select arbitrary ORM tables, including for administrators. `getOwnerTable()` resolves a registered alias; it does not authorize the identity.

## Permission contract

| Actions | Resource consumed | Methods |
| --- | --- | --- |
| file, image, download, stream | Attachment ID in `pass.0` | GET, HEAD |
| edit | Attachment ID in `pass.0` | GET, POST, PATCH, PUT |
| delete | Attachment ID in `pass.0` | POST, DELETE |
| fileAttribute | Attachment ID in `pass.0` | GET, POST, PATCH, PUT |
| editImage | Attachment ID in `pass.0` | GET, POST, PATCH, PUT |
| updatePosition | Attachment ID in `pass.0` | POST |
| add | Model and owner in `pass.0`, `pass.1`; fall back separately to body `model`, `fk` only when the corresponding route argument is null | POST |
| list | Model and owner in `pass.0`, `pass.1` | GET |
| reorder | Model and owner in `pass.0`, `pass.1` | POST |

For record actions, load the attachment's stored `model` and `foreign_key`, resolve the registered owner, then authorize that actual owner. Query/body scope must never replace record ownership. For owner actions, authorize the model/owner pair that the controller consumes. Do not check ignored alternate fields. Nested attachments resolve through their parent until reaching an authorized owner; deny cycles and unregistered owners. The host must define each registered owner's ownership path; registration alone must never confer access.

Define ownership checks using your application's identity and owner relationships. The package's model rules validate owner existence and immutability independently of permissions.

Do not bypass authentication for cached images or files. Authorize cached-image requests in the host permission policy before the controller serves them. The controller loads the attachment record only when generating a missing image variant. Delivery retains the existing six-month cache lifetime and shared caching, with WebP responses marked private. Permission checks apply to requests that reach the application; they do not invalidate copies already stored in browser or proxy caches. Changes to that cache policy require an explicit host decision. `file` retains inline delivery and `download` retains forced download, with their original response headers. `stream` accepts audio/video only. Hosts that need publicly accessible published media must expose a separate policy scoped to published owners, rather than restore a broad admin-package grant.

## Forms and Ajax

Install CakePHP CSRF middleware after routing on all cookie-authenticated attachment mutations. Form helpers supply tokens; Ajax sends `X-CSRF-Token`. Update clients together with permissions. Sorting now sends POST, and the reorder route includes both model and owner; an owner ID alone is insufficient. Mutation clients should expect JSON success and handle failed or redirected requests visibly. Existing package widgets retain their menus, icons, and loading indicators.

`edit` accepts filename, details, and tags only. `fileAttribute` permits title, description, and link. Ownership, ID, sequence, timestamps, hash, MIME type, file size, and filesystem paths cannot be assigned through forms. Sequence changes use the dedicated validated action. Trusted server code should use `addUpload()`, `addFile()`, `copyAttachment()`, or `replaceFile()` rather than constructing storage fields from request data. Uploads use actual staged bytes for size, MIME, and hash, and strip client path components from the filename.

The behavior accepts PSR-7 upload objects and an optional `allowedTypes` MIME list. The behavior retains its existing append workflow. `replaceFile()` retains its existing delete-and-create workflow: the replacement has a new ID, keeps the owner and filename, and receives fresh sequence/details/tag defaults. The delete and save run in one transaction; failed replacement restores the original row and leaves its bytes intact. Existing image-cache keys remain unchanged.

## Storage and recovery

Apply the new attachment MD5 index migration before deploying. Use transactional tables and CakePHP 5.3 or newer for native connection `afterCommit()` support. Cleanup relies on InnoDB gap locking under REPEATABLE READ. It uses an indexed ORM query with the static `FOR UPDATE` locking suffix before deleting unreferenced hashes; other database engines/isolation levels require equivalent concurrency validation before enabling deletion.

Uploads write a temporary local file and rename it into the existing hash-based storage path. Lazy S3 downloads also stage bytes and verify their hash before rename, so interrupted reads do not leave a partial file at the final path. Shared hashes are retained while any attachment row references them. Required S3 writes happen before the save completes. A failed save compensates unreferenced new bytes where possible. Deletion and replacement of old bytes wait for the outer database commit; rollback preserves the old referenced file. Cleanup errors are logged and do not reverse an already committed logical deletion. Database transactions do not make filesystem or S3 operations transactional.

A crash, outer rollback after a successful upload, or unavailable storage can leave unreferenced bytes. The native command inspects known hash paths and staging prefixes; it defaults to a dry run:

```bash
bin/cake cleanup_attachment_storage
bin/cake cleanup_attachment_storage --delete --older-than 3600
```

The minimum grace period is 300 seconds; use a period longer than the longest upload/import/preview operation. Only explicit `--delete` removes eligible files. Referenced hashes are checked again under a database lock before removal. Remote deletion runs before local deletion so a remote failure leaves a retryable local file. Listing or storage failures must be resolved before treating a run as complete. No automatic schedule or new background service is installed. Review the dry run and schedule maintenance only according to the host's storage policy. This command requires trusted shell access and operates on the currently configured database, directory, and bucket.

## Upgrade verification

Test real HTTP permissions and CSRF for every owner, foreign records, unknown models, nested owners/cycles, protected metadata, actual upload MIME/size/hash, model-scoped ordering, replacement identity, shared hashes, outer and nested rollback, post-commit failure and retry, multi-file failure, and orphan inspection/removal. Inject storage errors against isolated directories and the AWS SDK mock handler; never point failure tests at production storage. Run a separate integration check against the host's actual storage backend and manually verify upload, sorting, deletion, and image-editor UI before deployment.

## Browser integration checks

Enable the host's native CSRF middleware for browser mutations and send its token with forms and Ajax/file-upload requests. Deploy client and method changes together; avoid broad plugin exemptions. Test missing-token rejection through the actual HTTP middleware as well as owned/foreign resource permissions.

Failed uploads must report an error and reset progress. Failed metadata saves must retain the input for retry. Keep existing editor/tree behavior, icons, and menus when adapting a host. Verify denied and network-failed requests in a browser as well as successful writes against the configured storage backend. These checks do not require a change to the existing attachment cache or file/download policy.

Trusted folder transfers may save a folder attachment with `allowFolderTransfer => true` after the host authorizes both its stored source owner and destination owner. This option only permits a folder's `foreign_key` transition with an unchanged model; registered-owner, file-integrity, and other save rules still run. Set `sequence` to null to append in the destination through SequenceBehavior. Never pass request data as ORM save options or enable this for ordinary metadata edits. `copyAttachment($id, $owner, $sequence)` accepts an optional insertion position; omitting it retains append behavior.

New attachment positions are checked by a native create rule: a supplied position must be between 1 and the destination owner’s attachment count plus 1. A null position retains automatic appending. This also protects direct `copyAttachment()` callers; failed copies leave existing rows and ordering unchanged.
