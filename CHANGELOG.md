# Changelog

All notable changes to Lean Redirects will be documented in this file.

This project follows [Semantic Versioning](https://semver.org/):

- **MAJOR** (X.0.0) — breaking changes (table schema, removed features)
- **MINOR** (0.X.0) — new features, backwards compatible
- **PATCH** (0.0.X) — bug fixes, performance improvements, translations

## [1.0.2] - 2026-02-25

### Fixed
- All Plugin Check warnings resolved with proper `phpcs:ignore` annotations for custom table queries
- Template variables now use full `lean_redirects_` prefix (was `lean_`)
- Added NonceVerification ignore for read-only GET params (search/pagination — no state change)
- Cleaner uninstall.php with consolidated phpcs annotations

## [1.0.1] - 2026-02-25

### Fixed
- Escape output in admin header using `absint()` for active/total counts (Plugin Check error)
- Prefix all template global variables (`$r` → `$lean_r`, `$toggle_url` → `$lean_toggle_url`, etc.)
- Tested up to WordPress 6.9

## [1.0.0] - 2026-02-25

### Added
- 301, 302, 307 redirects with dedicated indexed database table
- Admin UI under **Settings → Redirects** (native WordPress styles)
- Search and pagination (50 per page)
- Hit counter per redirect
- Toggle active/inactive without deleting
- Notes field for context (migration batch, ticket number, etc.)
- CSV import/export (format: `from,to,code`)
- REST API: `GET/POST/DELETE /wp-json/lean-redirects/v1/redirects`
- "Manage" link on Plugins page
- Clean uninstall (drops table on plugin deletion)
- Full i18n support with POT file

[1.0.2]: https://github.com/ctala/lean-redirects/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/ctala/lean-redirects/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/ctala/lean-redirects/releases/tag/v1.0.0
