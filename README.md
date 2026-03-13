# quizaccess_proview

Unified Talview Proview proctoring plugin for Moodle — replaces `quizaccess_proctor` and `local_proview` with a single `quizaccess_` sub-plugin.

**Supports:** Moodle 4.5, 5.0, 5.1+

---

## Overview

`quizaccess_proview` is a Moodle quiz access rule sub-plugin that integrates Talview Proview proctoring into any Moodle quiz. It handles:

- Per-quiz proctoring configuration (proctoring type, secure browser, allow-/blocklists)
- Proview session launch via CDN script injection at quiz start
- Token-based authentication via the Talview LMS Connector API
- Quiz and enrolment event callbacks to the Talview API
- GDPR-compliant privacy provider

---

## Requirements

| Requirement | Version |
|-------------|---------|
| Moodle | 4.5+ (build 2024042200) |
| PHP | 8.1+ |
| Talview LMS Connector | Active subscription + API credentials |

---

## Installation

1. Download or clone this repository into `<moodleroot>/mod/quiz/accessrule/proview/`.

   ```bash
   git clone https://github.com/talview/moodle-quizaccess_v7_proview.git mod/quiz/accessrule/proview
   ```

2. Log in to Moodle as an administrator and navigate to **Site administration → Notifications** to trigger the database upgrade.

3. Go to **Site administration → Plugins → Quiz → Proview proctoring** and enter your Talview LMS Connector credentials.

4. In any quiz's **Edit settings** page, scroll to the **Proview proctoring** section to enable and configure proctoring for that quiz.

---

## Admin Configuration

| Setting | Description |
|---------|-------------|
| LMS Connector URL | Base URL for the Talview LMS Connector API |
| Organisation | Organisation selected from your Talview account (auto-populated via API) |

The plugin derives `app_id` automatically from `md5($CFG->wwwroot)` — no manual token input required.

---

## Migration from Legacy Plugins

This plugin replaces two legacy Talview plugins:

| Legacy plugin | Status |
|---------------|--------|
| `quizaccess_proctor` | **Deprecated** — replaced by `quizaccess_proview` |
| `local_proview` | **Deprecated** — replaced by `quizaccess_proview` |

Running the Moodle upgrade with both legacy plugins present will automatically migrate all existing per-quiz proctoring settings to `quizaccess_proview`. After a successful migration, the legacy plugins can be uninstalled.

---

## Development

### Prerequisites

- PHP 8.1+, Composer
- A local Moodle 4.5 instance
- [`moodle-plugin-ci`](https://github.com/moodlehq/moodle-plugin-ci) for CI

### Running Tests

```bash
# PHPUnit
vendor/bin/phpunit --testsuite quizaccess_proview

# Behat
php admin/tool/behat/cli/run.php --tags=@quizaccess_proview
```

### CI

GitHub Actions runs the full `moodle-plugin-ci` matrix against Moodle 4.5, 5.0, and 5.1 on every pull request to `develop`.

---

## License

This plugin is licensed under the [GNU GPL v3 or later](https://www.gnu.org/copyleft/gpl.html).

Copyright 2026 Talview Inc.
