# Changelog

All notable changes to `quizaccess_proview` are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versions follow [Semantic Versioning](https://semver.org/).

---

## [Unreleased]

### Added
- Sentry error capture and performance tracing (`classes/sentry.php`)
- Release CI/CD workflow — ZIP + GitHub Release on merge to `master`
- Live proctoring validation — `timeopen` and `timeclose` required when `proctoringtype = live`
- Proview recordings page (`recordings.php`) with search and playback links
- `description()` method — recordings button shown to managers on quiz view page
- `get_playback_sessions()` and `get_playback_token()` API methods

### Changed
- Removed `eventschedulingtype` field — `scheduling_type` hardcoded to `bulk`
- Token selection required when proctoring or TSB is enabled

---

## [0.1.0] — 2026-03-24

### Added
- Initial plugin release
- Per-quiz proctoring configuration form (`rule.php`)
- LMS Connector API client (`classes/api.php`)
- Token manager with Moodle application cache (`classes/token_manager.php`)
- Admin settings page (`settings.php`)
- Proview CDN script injection into quiz attempt pages
- Talview Secure Browser (TSB) launch flow via wrapper URL
- GDPR privacy provider (`classes/privacy/provider.php`)
- DB schema (`db/install.xml`) — `mdl_quizaccess_proview` table
- Backup and restore sub-plugin classes
- PHPUnit test suite
- GitHub Actions CI pipeline (Moodle 4.5/5.1 × pgsql/mariadb)
- Language strings (`lang/en/quizaccess_proview.php`)
