# API alignment plan

## Purpose

`PhotoAuthApp` is the single source of truth for the API used by `SkudConsole` and `SmartPass`.
The migration removes the non-canonical device-pairs route and makes settings behaviour explicit without changing the supported canonical endpoints.

## Confirmed scope

- Canonical endpoints are `/api/device-pairs/active`, `/api/settings`, `/api/settings/table`, `/api/users`, and `/api/users/auth`.
- `/device-pairs/active` is not referenced by the three known codebases and will be removed.
- Both `format=v2` and `format=v1` remain supported on `/api/device-pairs/active`; v2 is preferred by clients.
- `SkudConsole` must respect `photo.storage_type` and must report incomplete settings by section.
- `SmartPass` and `SkudConsole` must not share identically named API client classes with different responsibilities.

## Assumptions and constraints

- The three reviewed projects are the only known consumers. An unknown external consumer of `/device-pairs/active` would need a separate migration.
- The API token, request correlation, and audit middleware remain mandatory for every canonical API route.
- No database schema change is required for this API-alignment phase.
- Existing deployed client configurations using `/api/device-pairs/active` remain compatible.

## Implementation sequence

1. Remove the legacy web route and apply the Laravel `api` middleware only once.
2. Update the canonical API documentation and copy the device-pairs contract to `SkudConsole`.
3. Align `SkudConsole` settings consumption with the `/api/settings` response, including `share` versus `ftps` storage.
4. Rename the distinct API client implementations by their actual responsibility, preserving namespaces and behaviour.
5. Add server and client contract tests using shared JSON fixtures.
6. Run focused verification: route list, PHP tests, and C# build/tests where their runtime is available.

## Decision log

| Decision | Alternatives considered | Reason |
| --- | --- | --- |
| Remove `/device-pairs/active` now | Keep an unaudited legacy alias for one release | No known consumer references it; the alias bypasses correlation and audit behaviour. |
| Keep v1 and v2 on the canonical route | Remove v1 immediately | Both C# clients have migration fallback logic and v1 removal is not required to remove route ambiguity. |
| Treat `PhotoAuthApp` as the contract owner | Copy client behaviour into the server | The Laravel project exposes all shared routes and controls authentication, errors, and audit. |
| Rename client classes by responsibility | Copy one implementation over the other | `SmartPass` uses its client to load device pairs while `SkudConsole` uses its similarly named client for runtime settings. |
