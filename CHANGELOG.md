# Changelog

All notable changes to `filament-saved-views` are documented in this file. Each section is written automatically from the GitHub release notes when a release is published — do not edit it by hand.

## v1.6.1 - 2026-09-24

<!-- Release notes generated using configuration in .github/release.yml at 1.x -->
### What's Changed

#### Other

* ci: adopt the Happenv package standard by @webard in https://github.com/happenv-com/filament-saved-views/pull/7
* fix: keep the open view highlighted in the sub-navigation by @webard in https://github.com/happenv-com/filament-saved-views/pull/9
* feat: translate the manager into every language Filament ships by @webard in https://github.com/happenv-com/filament-saved-views/pull/8
* fix: give the drag handle and the sub-navigation checkbox accessible names by @webard in https://github.com/happenv-com/filament-saved-views/pull/10

**Full Changelog**: https://github.com/happenv-com/filament-saved-views/compare/v1.6.0...v1.6.1

## v1.6.0 - 2026-08-27

## What's Changed

* feat: the manager trigger is a SavedViewManagerAction by @webard in https://github.com/happenv-com/filament-saved-views/pull/6

**Full Changelog**: https://github.com/happenv-com/filament-saved-views/compare/v1.5.1...v1.6.0

## v1.5.1 - 2026-08-27

## What's Changed

* fix: the unsaved-changes tooltip never rendered by @webard in https://github.com/happenv-com/filament-saved-views/pull/5

**Full Changelog**: https://github.com/happenv-com/filament-saved-views/compare/v1.5.0...v1.5.1

## v1.5.0 - 2026-08-27

## What's Changed

* feat: say when the open view has unsaved changes by @webard in https://github.com/happenv-com/filament-saved-views/pull/4

**Full Changelog**: https://github.com/happenv-com/filament-saved-views/compare/v1.4.0...v1.5.0

## v1.4.0 - 2026-08-27

## What's Changed

* feat: update the saved view you have open by @webard in https://github.com/happenv-com/filament-saved-views/pull/3

**Full Changelog**: https://github.com/happenv-com/filament-saved-views/compare/v1.3.0...v1.4.0

## v1.3.0 - 2026-08-27

## What's Changed

* feat: capture the complete table state in a saved view by @webard in https://github.com/happenv-com/filament-saved-views/pull/2

**Full Changelog**: https://github.com/happenv-com/filament-saved-views/compare/v1.2.2...v1.3.0

## v1.2.2 - 2026-07-24

### Fixed

- Submenu checkbox tooltip is now rendered server-side via a plain `title` attribute instead of `:title="__(...)"`. On a plain `<input>` Blade left the `:title` bind untouched, so Alpine evaluated the server-only `__()` helper client-side, throwing `ReferenceError: __ is not defined` on every page rendering the saved-views control (Sentry SELLERO-GUI-N, 108× on production).

## v1.2.1 - 2026-07-22

**Full Changelog**: https://github.com/happenv-com/filament-saved-views/compare/v1.2.0...v1.2.1

## v1.2.0 - 2026-07-22

**Full Changelog**: https://github.com/happenv-com/filament-saved-views/compare/v1.1.0...v1.2.0

## v1.1.0 - 2026-07-22

**Full Changelog**: https://github.com/happenv-com/filament-saved-views/compare/v1.0.1...v1.1.0

## v1.0.1 - 2026-07-22

**Full Changelog**: https://github.com/happenv-com/filament-saved-views/compare/v1.0.0...v1.0.1

## v1.0.0 - 2026-07-22

**Full Changelog**: https://github.com/happenv-com/filament-saved-views/commits/v1.0.0
