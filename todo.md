# Zero Framework TODO

## Auth & Sessions

- [x] Design authentication flow (login, logout, registration) leveraging `Session` and `Cookie` utilities
- [x] Implement middleware for guarding routes and redirecting guests/authenticated users
- [ ] Add CSRF protection hooks (token generation, verification, helper integration)
- [ ] Document usage patterns and update examples/controllers accordingly

## CLI Tooling

- [x] Extend `zero` CLI with subcommands (migrate, make:model, make:controller, serve options)
- [x] Introduce command dispatcher structure for registering new commands cleanly
- [x] Provide scaffolding stubs for generated resources and update docs

## Database Layer

- [x] Build lightweight migration system (table schema builder, migration runner CLI)
- [x] Add seeder support and sample seeds for common models
- [ ] Enhance DBML/Model with eager loading helpers (e.g., `with`, `load`) and relationship batching
- [x] Implement pagination helpers for DBML and Model (e.g., `paginate`, `simplePaginate`)
- [ ] Write integration tests covering migrations/model behaviours
- [x] Implement subquery relationship (e.g `whereHas`, `has`, `whereDoesntHave`)

## Library Refactor

- [x] Modularize HTTP layer (split `Http\Request`/`Http\Response` into smaller concern-specific classes)
- [x] Break query builder into focused components (clauses, compilers, relations) under `Zero\\Lib\\DB`
- [x] Extract router route collection and middleware pipeline into dedicated classes
- [x] Restructure CLI commands into separate command classes with a dispatcher
- [x] Move helper utilities into grouped support modules (e.g., `Support/Arr`, `Support/Str`, `Support/Collection`, `Support/Number`)
- [x] Introduce validation layer (validator factory, rule classes, request helpers)

## Error Handling

- [x] Create centralized exception handler for HTTP and CLI contexts
- [x] Implement configurable error pages / JSON responses for common HTTP status codes
- [x] Add logging hooks (file and optional external integrations) and configuration knobs
- [ ] Update documentation to describe troubleshooting and error handling workflow

## Mailable

- [ ] Create mailable custom services

## Queue

- [x] Job contract + JSON payload serializer with model rehydration
- [x] `sync` and `database` drivers with retry/backoff and `failed_jobs` storage
- [x] `queue:work` worker (with `--once`, multi-queue priority, graceful SIGTERM/SIGINT)
- [x] Operator commands: `make:job`, `queue:retry`, `queue:forget`, `queue:flush`, `queue:table`
- [x] `Dispatchable` trait + `dispatch()` global helper
- [x] `dispatchAfterResponse()` for fire-after-response work without a worker
- [ ] Job middleware (rate-limit, unique)
- [ ] Redis driver
- [ ] Per-job timezone overrides

## I18n / Translation

- [x] Add core i18n config (`config/i18n.php`) plus separate email translation config
- [x] Implement locale resolver with ordered strategies (URL prefix, session/cookie, header, custom callback/DB)
- [x] Add fallback locale chain (e.g., `en-GB` -> `en` -> default)
- [x] Support YAML and JSON translation loaders
- [x] Support inline `@i18n()` blocks in all templates (including child components)
- [x] Support external translation files at `resources/i18n/{lang}/<page>` and explicit `@i18n('file-name')` selection
- [x] Add translation helpers/aliases: `__()` and `@t()` with variable interpolation
- [x] Define missing-key behavior (return original text)
- [x] Document dot-notation and nested key access for YAML/JSON
