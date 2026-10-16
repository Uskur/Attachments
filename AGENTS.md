# Attachments repository instructions

## Project context
- The canonical package name is **Attachments**, the Composer package is `uskur/attachments`, and the CakePHP plugin name is `Uskur/Attachments`.
- This repository is a first-party CakePHP 5 plugin, not a standalone application. It manages attachments, owner associations, uploads, file and image delivery, previews, and local/S3 storage.
- This is a public, reusable package. Do not introduce private host names, domains, operating-system accounts, paths, deployment details, audit findings, or application-specific ownership rules into package code or documentation.
- The package depends on `cakephp/cakephp` `^5.3`, ADmad Sequence, Gumlet ImageResize, Imagick, and the AWS SDK. Prefer solutions that fit the existing CakePHP and plugin architecture.
- When developing through a host application, the existing package Git checkout under the host application's `vendor/uskur/attachments/` directory is the authoritative development checkout. Make package changes directly in that checkout; do not create or use a second working copy elsewhere.
- Follow the host application's environment and safety instructions when running this plugin through that application.

## Default approach
- Prioritize CakePHP-native solutions over framework-agnostic ones.
- Use CakePHP conventions for controllers, tables, entities, commands, components, helpers, behaviors, middleware, events, mailers, and validation.
- Prefer extending existing CakePHP or plugin abstractions instead of introducing standalone services or custom infrastructure unless the current architecture clearly requires it.
- When querying or mutating data, prefer CakePHP ORM patterns over raw SQL unless there is a clear performance or compatibility reason.
- When handling request data, forms, validation, authentication, authorization, caching, i18n, routing, and CLI tasks, check for an existing CakePHP mechanism first.

## Implementation preferences
- Follow established CakePHP naming and file placement conventions: package code in `src/`, templates in `templates/`, migrations in `config/Migrations/`, and tests in `tests/`.
- Reuse existing plugin code and shared behaviors before adding new abstractions.
- Prefer `bin/cake` commands, Bake-friendly structure, and CakePHP configuration patterns where relevant.
- Keep changes compatible with the current CakePHP version, declared dependencies, and supported host integrations.
- If a generic PHP solution and a CakePHP solution are both viable, choose the CakePHP solution unless the user asks otherwise.
- Keep changes focused on the requested behavior. Preserve unrelated working-tree changes and existing UI behavior; make only necessary changes to existing code, and do not rewrite implementations or bundle unrelated cleanup or refactoring.
- Use four spaces, LF line endings, a final newline, and no trailing whitespace. Use CakePHP helpers, escape user-controlled template output, and follow the package's `__d('Uskur/Attachments', ...)` translation convention.
- Add schema changes in a new migration; do not rewrite deployed migration history unless explicitly requested.

## Git workflow
- Never create a commit or push automatically after making changes. First present the completed changes and validation results to the user for review, then wait.
- Create a commit only after the user reviews the current completed changes and explicitly asks to commit them in a new message.
- Push only when that post-review request explicitly includes pushing, or when the user makes a separate explicit push request afterward.
- A commit or push instruction applies only to the changes already presented for review at that time. It does not carry forward to later fixes, follow-up changes, or future turns.
- Do not create tags or releases, publish packages, or deploy unless explicitly requested.

## Authorization assumption
- Treat the host application's `config/permissions.php` as the primary authorization layer where the host uses CakeDC permissions.
- Do not add duplicate controller-level ownership or role checks unless explicitly requested or there is clear evidence that the permission rule is insufficient for the path being changed.
- When reviewing controllers, do not raise missing controller authorization as a finding if the action is already correctly constrained by the host's permission rules.
- Preserve attachment ownership through the stored `model` and `foreign_key`, registered `Attachment.models` aliases, and model rules. Authorize the actual attachment or consumed owner pair; never load arbitrary request-selected tables. Model registration and owner existence checks do not grant access.
- Preserve HTTP method restrictions and CSRF protection for state-changing actions. AJAX callers must send the allowed method and CSRF token.

## Storage safety
- Preserve shared hash-based storage: deleting one attachment must not remove bytes referenced by another attachment.
- Keep database rollback and filesystem/S3 recovery separate. Use CakePHP transaction and commit callbacks where appropriate; do not claim that database rollback restores deleted files.
- Use inspected upload bytes for MIME type, size, and hash. Keep owner and storage fields server-managed, and validate hashes before building paths.
- Keep temporary-file cleanup and storage failure handling explicit. Test against isolated directories and stubbed storage, never a live configured bucket.
- Storage cleanup must remain a dry run by default, with deletion explicitly requested and reference checks preserved. Do not run cleanup against shared or production storage without explicit authorization.

## Authentication migration
- Authentication is supplied by the host application; preserve compatibility with its supported identity and controller APIs.
- Where a host provides `AppController::currentUser()`, treat it as a transitional compatibility layer from the old `Auth->user()` pattern to CakePHP Authentication identities.
- Do not do bulk repository refactors just to remove `currentUser()` or legacy authentication calls.
- When already changing controller code for a real task, prefer direct Authentication identity usage if it keeps the change clear, low-risk, and compatible with supported hosts.
- Update touched code opportunistically; leave untouched areas alone.

## Logging
- Log every state-changing application action added or modified going forward, including successful saves, updates, deletes, workflow transitions, background jobs, and failed mutation attempts.
- Add specific `Log::write()` statements at the point where each mutation succeeds or fails. Describe the concrete operation, for example `Attachment uploaded.` or `Attachment could not be uploaded.`
- Do not satisfy this requirement with a generic controller lifecycle logger, shared logging trait, blanket `beforeFilter()`/`afterFilter()` logging, or a single generic message reused for unrelated operations.
- Do not add routine logs merely for opening index, view, add, or edit pages when no state change was attempted.
- Use `Cake\Log\Log` conventions and useful structured context such as entity IDs, the acting identity ID when available, the `foreign_key` scope, action or event name, and relevant status changes.
- Log successful mutations at an appropriate level. Log failed mutation attempts with validation errors or exception context where useful, including failures that are rethrown or presented to the user.
- Never log passwords, authentication tokens, secrets, uploaded file contents, or unnecessarily sensitive personal data. Avoid logging entire import rows or request payloads.

## When giving options
- Present the CakePHP-first option first.
- Call out when a suggestion is generic PHP and why it is being used instead of a CakePHP-native approach.
- If CakePHP imposes a tradeoff, explain it briefly, but still optimize for consistency with the codebase.

## Testing rules
- Use the host project's DDEV environment for app-runtime commands: `php`, `bin/cake`, Composer, PHPUnit, and commands that depend on PHP extensions, php.ini, environment variables, database/cache services, or installed Composer packages.
- Prefer `ddev exec ...` and `ddev composer ...`. When using a host checkout, run commands from the host application root and follow its supported runtime. The package declares PHP 8.2 or newer.
- Host commands are acceptable for repository inspection and file editing that do not depend on the app runtime. If intentionally running an app-runtime command on the host, state the specific reason first.
- Place tests for Attachments-owned behavior in this package's `tests/` suite. Use a host application's suite only when testing host-specific modifications, overrides, routes, configuration, or other application-owned behavior.
- For controller/feature tests, prefer CakePHP `IntegrationTestTrait` and built-in assertions (`assertResponse*`, `assertRedirect*`, `assertSession`, `assertFlashMessage*`, `assertMail*`).
- Prefer fixture-driven test data for required domain records instead of runtime inserts or record-creation helpers. Keep fixtures schema-aligned with current migrations and use CakePHP's fixture lifecycle.
- Inspect the test bootstrap and migration setup before running PHPUnit. Require a dedicated test database before any schema rebuild. The package bootstrap uses the enclosing host application and its migrations, checks that the database is named `test`, and creates a generic owner table using CakePHP schema APIs. The host bootstrap must guard the dedicated test database before rebuilding its schema.
- Never point tests at a development or production database. Do not print, copy, or commit database credentials, S3 credentials, security salts, or other secrets.
- Run the narrowest relevant test first, then broaden validation when warranted. Use its `phpunit.xml.dist` and the specific test file through the supported host runtime, for example:
  `ddev exec vendor/bin/phpunit -c vendor/uskur/attachments/phpunit.xml.dist vendor/uskur/attachments/tests/TestCase/Model/Table/AttachmentsTableTest.php`
- Check coding standards only for files in scope, and review any fixer diff. Report existing failures outside changed code and run narrower checks where possible.
- Do not change production/application code only to make tests pass. Fix tests or fixtures first; change application behavior only when the user requests it or confirms a bug fix.
- Do not introduce custom controller stubs, mocks, or test doubles when CakePHP testing APIs cover the scenario. Use custom doubles only when explicitly requested or native APIs cannot cover the case; explain that limitation in the summary.
- Do not access production deployments or databases, run destructive rollbacks, or clean shared development data unless explicitly requested.
