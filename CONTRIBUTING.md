# Contributing to OpenCalendar

Thank you for your interest in contributing to OpenCalendar! This document explains how to get started, what we expect from contributions, and how to submit changes.

## Code of Conduct

This project follows the [Contributor Covenant](CODE_OF_CONDUCT.md). By participating, you agree to uphold a respectful and inclusive environment.

## Getting Started

### Prerequisites

- PHP 8.2 or higher with extensions: `pdo`, `pdo_sqlite`, `json`, `mbstring`
- Composer 2.x
- A local Grav installation (recommended for integration testing)

### Local Setup

1. Fork and clone the repository:

   ```bash
   git clone https://github.com/TimUx/grav-plugin-opencalendar.git
   cd grav-plugin-opencalendar
   ```

2. Install dependencies:

   ```bash
   composer install
   ```

3. Symlink or copy the plugin into your Grav instance:

   ```bash
   ln -s "$(pwd)" /path/to/grav/user/plugins/opencalendar
   ```

4. Clear Grav cache after configuration changes:

   ```bash
   bin/grav cache
   ```

### Development Commands

| Command | Description |
|---------|-------------|
| `composer test` | Run PHPUnit unit tests |
| `composer phpstan` | Static analysis (level 8) |
| `composer phpcs` | PSR-12 code style check |
| `composer check` | Run all quality gates |

See [docs/Development.md](docs/Development.md) for architecture notes and coding conventions.

## How to Contribute

### Reporting Bugs

Use the [bug report template](.github/ISSUE_TEMPLATE/bug_report.md) and include:

- Grav version and PHP version
- Plugin version
- Steps to reproduce
- Expected vs. actual behavior
- Relevant log excerpts from `logs/grav.log` or `logs/opencalendar.log`

### Suggesting Features

Use the [feature request template](.github/ISSUE_TEMPLATE/feature_request.md). Describe the problem you are solving and any alternatives you considered.

### Pull Requests

1. Create a feature branch from `develop` (or `main` if no develop branch exists).
2. Keep changes focused — one logical change per pull request.
3. Add or update tests when behavior changes.
4. Run `composer check` before submitting.
5. Update `CHANGELOG.md` under the appropriate section (`new`, `improved`, `bugfix`).
6. Fill out the pull request template completely.

### Commit Messages

Write clear, imperative commit messages:

```
Add CalDAV sync retry with exponential backoff

Explain why the change is needed in the body when non-obvious.
```

### Coding Standards

- Follow PSR-12 for PHP code (`composer phpcs`).
- Target PHPStan level 8 (`composer phpstan`).
- Place implementation classes under `classes/` using the `Grav\Plugin\OpenCalendar\` namespace.
- Do not commit secrets, credentials, or real calendar URLs from production systems.

### Documentation

Update relevant files under `docs/` when you change user-facing behavior, configuration options, or API contracts. Keep `README.md` in sync with major feature additions.

## Release Process

Maintainers follow [Semantic Versioning](https://semver.org/):

- **Patch** — bug fixes, no API changes
- **Minor** — backward-compatible features
- **Major** — breaking changes

Each release updates `CHANGELOG.md`, bumps version in `blueprints.yaml` and `opencalendar.yaml`, and tags the commit.

## Questions

Open a [GitHub Discussion](https://github.com/TimUx/grav-plugin-opencalendar/discussions) or issue if you need guidance before starting significant work.
