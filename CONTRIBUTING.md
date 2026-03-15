# Contributing to ai-agent

Thanks for your interest in contributing!

## Quick Start

1. Fork the repository
2. Create a branch: `git checkout -b feature/your-feature`
3. Make your changes
4. Commit with conventional commits: `git commit -m "feat: add new feature"`
5. Push and open a PR

## Development Setup

```bash
npm install
composer install
```

## Running Tests

Tests use [`@wordpress/env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (wp-env), which provides a Docker-based WordPress environment. Docker must be running.

```bash
# Start the wp-env test environment (first run downloads Docker images)
npm run wp-env:start

# Run PHPUnit tests inside wp-env
npm run test:php

# Run with testdox output
npm run test:php:testdox

# Stop the environment when done
npm run wp-env:stop
```

The test environment is configured in `.wp-env.json` and runs WordPress in multisite mode matching production. PHPUnit runs inside the `tests-wordpress` container where the plugin is mounted at `/var/www/html/wp-content/plugins/ai-agent`.

## Commit Messages

We use [Conventional Commits](https://www.conventionalcommits.org/):

- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation only
- `refactor:` - Code change that neither fixes a bug nor adds a feature
- `chore:` - Maintenance tasks
