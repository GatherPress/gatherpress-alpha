# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pending entries for the next release live as individual files under
[`.github/changelog/`](.github/changelog/) and get rolled up into a new
version section by `composer changelog:write` at release time.

GatherPress Alpha tracks the GatherPress core plugin's version in lockstep —
every GatherPress release has a matching GatherPress Alpha release that ships
the migration code needed to bridge breaking changes between versions. This
plugin is a temporary developer companion that goes away at GatherPress 1.0.0.

## [0.34.0] - 2026-07-10
### Security
- Removed malicious files that had been injected into the repository: a JavaScript loader disguised as `public/fonts/fa-solid-400.woff2` (detected by antivirus as `Trojan:JS/PolinRider`), a `.vscode` task that auto-ran it when the folder was opened in VS Code, and a cover-story `public/fonts/README.md`. The payload only executed in a developer's editor on folder open and was never loaded or run by WordPress at runtime. The original 0.34.0-alpha.2 build was affected; this is a clean re-cut. [#40] [#40]

### Added
- Add settings migration so legacy GatherPress settings storage upgrades cleanly into the 0.34.0 flat / config-driven Settings API. [#29] [#36]
- Add `SECURITY.md` documenting the vulnerability reporting process. [#40]
- Adopt the Jetpack changelogger workflow with per-PR `.github/changelog/*` entry files and a rolled-up `CHANGELOG.md`. Add tag-driven release automation that builds a versioned distro zip and attaches it to a GitHub Release (or Pre-Release for alpha/beta/rc tags). Backfills release history from 0.29.0. See `docs/release-process.md`. [#35]
- Migrate legacy venue address data into the WordPress Geodata standard meta keys (`geo_latitude` / `geo_longitude` / `geo_address`) that core GatherPress 0.34.0 now publishes. [#30] [#36]
- Migrate the legacy `gatherpress_venue_information` JSON blob into the individual post-meta keys (address, phone, website, latitude, longitude, plus the structured-address fields) that GatherPress 0.34.0 expects for block bindings. [#32] [#36]

### Changed
- Only render the Alpha tab on the Network admin when WordPress is running in multisite mode. Single-site installs see it under the regular Events settings as before. [#31] [#36]
- Ship `SECURITY.md` in the distributed plugin. The release workflow now copies it into the GitHub release zip, and a `package.json` `files` list adds it to the `npm run plugin-zip` output (`wp-scripts plugin-zip` ignores `.distignore`). [#44]

### Fixed
- Distro zip now extracts as `gatherpress-alpha/` instead of `gatherpress-alpha.<version>/` so WordPress installs the plugin to the correct directory and Alpha's version-match guard against core GatherPress works as expected. wp-scripts plugin-zip was producing a flat zip without the wrapping folder; the release workflow now constructs the zip manually with the expected layout. [#37]
- Fix the self-closing `gatherpress/venue` block migration silently skipping blocks that carry non-default attributes (e.g. `mapZoomLevel`, `mapType`), by broadening the SQL pre-filter and letting the existing parsed-block check do the real matching. [#45]
- Fix the `Commands\\Cli` autoload path so `wp gatherpress alpha fix` and other WP-CLI commands resolve correctly. [#34] [#36]
- Refuse activation when a duplicate GatherPress Alpha folder is on disk, mirroring the coexistence guard core GatherPress ships. Prevents silent class collisions when WordPress includes both copies during activation. [#33] [#36]

## [0.33.3] - 2026-02-16

Maintenance release tracking GatherPress 0.33.3.

## [0.33.2] - 2026-02-08

Maintenance release tracking GatherPress 0.33.2.

## [0.33.1] - 2026-01-30

Maintenance release tracking GatherPress 0.33.1.

## [0.33.0] - 2026-01-10
### Added
- Migration scripts for the Add to Calendar block update shipping in GatherPress 0.33.0. [#13]
- Anonymous and guest block migration scripts so events created against pre-0.33 RSVP blocks pick up the new V2 templates. [#15]
- HTML class-name migration script to align legacy block markup with the new component-style class naming. [#16]
- Plugin version-tracking system so the Alpha migration runner knows which migration steps have already been applied. [#19]
- `uninstall.php` so removing the plugin cleans up its option storage. [#20]

### Changed
- Drop the standalone `gatherpress` option in favor of namespaced storage. [#14]

## [0.32.3] - 2025-07-09

Maintenance release tracking GatherPress 0.32.3.

## [0.32.2] - 2025-05-01

Maintenance release tracking GatherPress 0.32.2.

## [0.32.1] - 2025-04-23

Maintenance release tracking GatherPress 0.32.1. [#12]

## [0.32.0] - 2025-04-12
### Changed
- Remove hardcoded test links from the Login and Registration URL handling; switch to dynamic resolution. [#9]
- Add URL placeholders for login and registration so the migration script can rewrite legacy values. [#10]

### Fixed
- Version-string correction so the plugin reports the right version in its header. [#11]

## [0.31.0] - 2024-10-04
### Added
- Backfill `datetime` meta on legacy events so the new datetime storage in GatherPress 0.31.0 sees the right values without manual intervention. [#7]

## [0.30.0] - 2024-08-15
### Changed
- Refactor GatherPress Alpha around a `Setup` singleton and the gatherpress autoloader filter — first release of the plugin in its current shape. [#1]

### Fixed
- Guard against fatal errors when GatherPress isn't installed or its version doesn't match Alpha's. [#2]

## [0.29.3] - 2024-06-27

Maintenance release tracking GatherPress 0.29.3.

## [0.29.2] - 2024-06-21

Maintenance release tracking GatherPress 0.29.2.

## [0.29.1] - 2024-06-11

Maintenance release tracking GatherPress 0.29.1.

## [0.29.0] - 2024-06-04

First public release of the GatherPress Alpha companion plugin. Provides a small migration runner that fixes legacy data when GatherPress ships breaking changes — designed to be activated alongside core GatherPress in lockstep with matching version numbers.

[0.34.0]: https://github.com/GatherPress/gatherpress-alpha/compare/0.33.3...0.34.0
[0.33.3]: https://github.com/GatherPress/gatherpress-alpha/compare/0.33.2...0.33.3
[0.33.2]: https://github.com/GatherPress/gatherpress-alpha/compare/0.33.1...0.33.2
[0.33.1]: https://github.com/GatherPress/gatherpress-alpha/compare/0.33.0...0.33.1
[0.33.0]: https://github.com/GatherPress/gatherpress-alpha/compare/0.32.3...0.33.0
[0.32.3]: https://github.com/GatherPress/gatherpress-alpha/compare/0.32.2...0.32.3
[0.32.2]: https://github.com/GatherPress/gatherpress-alpha/compare/0.32.1...0.32.2
[0.32.1]: https://github.com/GatherPress/gatherpress-alpha/compare/0.32.0...0.32.1
[0.32.0]: https://github.com/GatherPress/gatherpress-alpha/compare/0.31.0...0.32.0
[0.31.0]: https://github.com/GatherPress/gatherpress-alpha/compare/0.30.0...0.31.0
[0.30.0]: https://github.com/GatherPress/gatherpress-alpha/compare/0.29.3...0.30.0
[0.29.3]: https://github.com/GatherPress/gatherpress-alpha/compare/0.29.2...0.29.3
[0.29.2]: https://github.com/GatherPress/gatherpress-alpha/compare/0.29.1...0.29.2
[0.29.1]: https://github.com/GatherPress/gatherpress-alpha/compare/0.29.0...0.29.1
[0.29.0]: https://github.com/GatherPress/gatherpress-alpha/releases/tag/0.29.0
