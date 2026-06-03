# quizaccess_proview — Talview Proview Proctoring

[![CI](https://github.com/talview/moodle-quizaccess_v7_proview/actions/workflows/ci.yml/badge.svg?branch=develop)](https://github.com/talview/moodle-quizaccess_v7_proview/actions/workflows/ci.yml)
[![Latest Release](https://img.shields.io/github/v/release/talview/moodle-quizaccess_v7_proview)](https://github.com/talview/moodle-quizaccess_v7_proview/releases/latest)
[![Moodle](https://img.shields.io/badge/Moodle-4.5--5.1-orange)](https://moodle.org)
[![PHP](https://img.shields.io/badge/PHP-8.1--8.3-blue)](https://www.php.net)
[![License](https://img.shields.io/badge/License-GPL%20v3-green)](LICENSE)

A Moodle quiz access rule sub-plugin that integrates [Talview Proview](https://www.talview.com) proctoring into any Moodle quiz.

**Supports:** Moodle 4.5 – 5.1+ · PHP 8.1+

---

## Features

- **Per-quiz proctoring configuration** — enable proctoring, choose type, configure candidate and invigilator instructions, allow/blocklists
- **Three proctoring modes** — AI-based review, record & review, live invigilator
- **Talview Secure Browser (TSB)** — optional locked-down browser enforcement
- **Proview CDN script injection** — session initialised automatically on quiz attempt start
- **Recordings page** — teachers can view and play back all proctored sessions for a quiz
- **Token-based auth** — Proview session token fetched at runtime via LMS Connector API, never stored
- **GDPR compliant** — privacy provider included

---

## Requirements

| Requirement | Version |
|-------------|---------|
| Moodle | 4.5+ (build `2024042200`) |
| PHP | 8.1+ |
| Talview LMS Connector | Active subscription + API credentials |

---

## Installation

### Via Moodle admin UI (recommended)

1. Download `quizaccess_proview-vX.Y.Z.zip` from the [Releases](../../releases) page.
2. Go to **Site administration → Plugins → Install plugins**.
3. Upload the ZIP and follow the on-screen prompts.

### Via command line

```bash
cd <moodleroot>/mod/quiz/accessrule/
git clone https://github.com/talview/moodle-quizaccess_v7_proview.git proview
php admin/cli/upgrade.php
```

---

## Configuration

### Admin settings

Go to **Site administration → Plugins → Quiz access rules → Talview Proview proctoring**.

**Proview connection**

| Setting | Description |
|---------|-------------|
| CDN URL | URL of the Proview JS script injected into quiz attempt pages |
| Account name | Talview account name associated with this Moodle site |
| Admin URL | Base URL of the Proview admin/API service (e.g. `https://appv7.proview.io/embedded`) |

**Callback authentication**

| Setting | Description |
|---------|-------------|
| Admin username | Username provided by Talview to authenticate callback requests |
| Admin password | Password provided by Talview to authenticate callback requests |
| Root directory | Moodle root directory path (use `/` for default site root) |
| Callback URL | URL that Talview will POST proctoring event callbacks to. Leave blank to use the default plugin endpoint |

### Per-quiz settings

In any quiz's **Edit settings** page, expand the **Proview Proctoring** section.

| Setting | Description |
|---------|-------------|
| Enable Proview proctoring | Master toggle for this quiz |
| Proctoring type | `No proctoring`, `Recorded`, `Record and review`, `Live proctoring` |
| Proview token | Token configuration fetched from LMS Connector |
| Enable Talview Secure Browser | Launches quiz inside TSB |
| Candidate instructions | Shown to candidate before session starts |
| Proctor instructions | Shown to invigilator before session starts |
| Reference links | Links shown to candidate (markdown format) |
| Blacklisted Windows/macOS apps | Comma-separated list of blocked applications |
| Whitelisted Windows/macOS apps | Comma-separated list of allowed applications |
| Allow minimise | Whether candidate can minimise the browser |
| Screen capture protection | Blocks screen recording tools during the quiz |

> **Note:** When `Live proctoring` is selected, both quiz open and close times must be set.

---

## Viewing recordings

Teachers and managers can view proctored sessions via the **Proview Recordings** button on the quiz page, or by navigating directly to:

```
/mod/quiz/accessrule/proview/recordings.php?cmid=<cmid>
```

---

## Migration from legacy plugins

This plugin replaces:

| Legacy plugin | Status |
|---------------|--------|
| `quizaccess_proctor` | Deprecated — replaced by `quizaccess_proview` |
| `local_proview` | Deprecated — replaced by `quizaccess_proview` |

Running the Moodle upgrade with legacy plugins present will automatically migrate existing per-quiz settings.

---

## Development

See [CONTRIBUTING.md](CONTRIBUTING.md) for setup instructions, coding standards, and the PR process.

### Running tests

```bash
# PHPUnit
vendor/bin/phpunit --group quizaccess_proview

# Single file
vendor/bin/phpunit mod/quiz/accessrule/proview/tests/api_test.php
```

### Building AMD modules

Moodle's grunt pipeline is required to build `amd/build/*.min.js` — plain terser/rollup won't produce the correct named AMD format (`define("quizaccess_proview/...", ...)`).

**Prerequisites:** Node 22, a local Moodle 5.x checkout.

```bash
# 1. Copy the plugin into the Moodle tree (symlinks won't work — grunt resolves
#    them with fs.realpathSync, which breaks component name lookup)
cp -r /path/to/moodle-quizaccess_v7_proview \
      /path/to/moodle/public/mod/quiz/accessrule/proview

# 2. Install Moodle's node dependencies (once)
cd /path/to/moodle
npm install

# 3. Build AMD files for the plugin only
npx grunt amd --root=public/mod/quiz/accessrule/proview

# 4. Copy the built files back
cp public/mod/quiz/accessrule/proview/amd/build/proview_launch.min.js \
   /path/to/moodle-quizaccess_v7_proview/amd/build/proview_launch.min.js
cp public/mod/quiz/accessrule/proview/amd/build/proview_launch.min.js.map \
   /path/to/moodle-quizaccess_v7_proview/amd/build/proview_launch.min.js.map
```

Commit both `amd/src/` and `amd/build/` files together. The CI `grunt` step will verify the build is up to date.

### CI

GitHub Actions runs the full `moodle-plugin-ci` matrix against Moodle 4.5 and 5.1 on every PR to `develop`. Releases are created automatically on merge to `master`.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

## Security

To report a security vulnerability, see [SECURITY.md](SECURITY.md).

---

## License

GNU General Public License v3 or later — see [LICENSE](LICENSE).

Copyright 2026 [Talview Inc.](https://www.talview.com)
