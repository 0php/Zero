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
- [ ] Move helper utilities into grouped support modules (e.g., `Support/Arr`, `Support/Str`)
- [x] Introduce validation layer (validator factory, rule classes, request helpers)

## Error Handling

- [x] Create centralized exception handler for HTTP and CLI contexts
- [x] Implement configurable error pages / JSON responses for common HTTP status codes
- [x] Add logging hooks (file and optional external integrations) and configuration knobs
- [ ] Update documentation to describe troubleshooting and error handling workflow

## Mailable

- [x] Create mailable custom services
