# Contributing to quizaccess_proview

Thank you for your interest in contributing. This plugin is maintained by the Attendee Experience team at Talview.

## Reporting issues

Use the GitHub issue templates:
- **Bug report** — for unexpected behaviour
- **Feature request** — for new functionality

For internal Talview work, raise a Linear issue in the [ATX project](https://linear.app/talview/project/moodle-quizaccess-proview-7b35a6bcfe60).

## Development setup

### Prerequisites

- PHP 8.1+
- Composer
- Node.js 22+ (for AMD/Grunt builds)
- A local Moodle 4.5+ instance
- [`moodle-plugin-ci`](https://github.com/moodlehq/moodle-plugin-ci)

### Install dependencies

```bash
composer install
npm install
```

### Install the plugin

Place (or symlink) this directory at `<moodleroot>/mod/quiz/accessrule/proview/`, then visit `/admin/index.php` to run the DB upgrade.

## Branching

| Branch | Purpose |
|--------|---------|
| `develop` | Main development branch — all PRs target this |
| `master` | Release branch — triggers GitHub Release on merge |
| `atx-XXXX-*` | Feature / fix branches off `develop` |

Branch naming: `atx-{LINEAR_ID}-{short-description}` (e.g. `atx-2366-live-proctoring-time-validation`).

## Submitting a pull request

1. Branch off `develop`
2. Make your changes
3. Ensure all checks pass locally:
   ```bash
   vendor/bin/phpcs --standard=moodle --no-cache .
   vendor/bin/parallel-lint --exclude vendor .
   ```
4. Push and open a PR against `develop`
5. Fill in the PR template checklist

PRs require at least one approval from `@rohansharmasitoula` before merge.

## Coding standards

- Moodle coding style (PHPCS Moodle standard)
- All UI strings through `get_string()` — no hardcoded text
- No PHP warnings with `$CFG->debug = E_ALL`
- PHPDoc on all public methods
- Tests for all new logic (`tests/*_test.php`)

## Running tests

```bash
# PHPUnit
vendor/bin/phpunit --group quizaccess_proview

# Single file
vendor/bin/phpunit mod/quiz/accessrule/proview/tests/api_test.php
```

## Commit messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
feat(ATX-2366): add live proctoring time validation
fix(ATX-2370): correct token expiry handling
chore: bump version to v0.2.0
```

## License

By contributing, you agree your contributions will be licensed under the [GNU GPL v3 or later](LICENSE).
