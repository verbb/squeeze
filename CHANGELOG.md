# Changelog

## 2.1.0 - 2026-08-14
> {warning} Anonymous downloads that pass raw asset IDs (`files[]`) will stop working until you mint a signed token in Twig. Update frontend templates before upgrading — see [Upgrading to signed downloads](https://verbb.io/craft-plugins/squeeze/docs/get-started/upgrading).

### Added
- Added access control for downloads: anonymous requests require a signed token; raw asset IDs are only allowed for logged-in users who can view each asset.
- Added `allowedVolumes` and `defaultTokenDuration` settings (CP and `config/squeeze.php`).
- Added signed download tokens via `craft.squeeze.createToken()` and `craft.squeeze.getDownloadUrl()` (accept asset IDs or Asset elements).

### Changed
- Archive filenames are now sanitized before use.
- Security: the legacy anonymous `files[]=<id>` download URL no longer authorizes downloads on its own.

## 2.0.0 - 2024-10-25
> {note} The plugin’s package name has changed to `verbb/squeeze`. Squeeze will need be updated to 2.0 from a terminal, by running `composer require verbb/squeeze && composer remove olivierbon/craft-squeeze`.

### Changed
- Migration to `verbb/squeeze`.
- Now requires Craft 4.0+.

## 1.1.2 - 2019-05-29
- Fixed an issue where the temporary zip wasn't being deleted

## 1.1.0 - 2019-01-17
- Added ability to trigger download via URL

## 1.0.0 - 2019-01-17
- Initial release
