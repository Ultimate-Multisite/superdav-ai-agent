# Contributing to ai-agent

## Prerequisites

| Tool | Version | Install |
|------|---------|---------|
| Node.js | 20+ | [nodejs.org](https://nodejs.org/) |
| PHP | 8.2+ | [php.net](https://www.php.net/) |
| Composer | 2+ | [getcomposer.org](https://getcomposer.org/) |
| Docker | latest | Required for `wp-env` test environment |

## Dev Setup

```bash
git clone https://github.com/Ultimate-Multisite/ai-agent.git
cd ai-agent
npm install
composer install
```

Start the local WordPress environment (Docker must be running):

```bash
npm run wp-env:start
```

This spins up a multisite WordPress instance at `http://localhost:8888` with the plugin activated. First run downloads Docker images — allow a few minutes.

Stop when done:

```bash
npm run wp-env:stop
```

## Running Tests

### PHP (PHPUnit via wp-env)

```bash
# Run all PHP tests
npm run test:php

# Human-readable output
npm run test:php:testdox

# With coverage report
npm run test:php:coverage
```

PHPUnit runs inside the `tests-wordpress` container. The plugin is mounted at `/var/www/html/wp-content/plugins/ai-agent`. Environment config is in `.wp-env.json`.

### JavaScript (Jest)

```bash
npm run test:unit
```

## Linting

```bash
# Run all linters (JS + CSS + PHP)
npm run lint

# Individual linters
npm run lint:js        # ESLint (@wordpress/eslint-plugin)
npm run lint:css       # Stylelint (@wordpress/stylelint-config)
npm run lint:php       # PHPCS (WordPress Coding Standards)

# Auto-fix
npm run lint:js:fix
npm run lint:css:fix
npm run lint:php:fix   # PHPCBF
```

Lint runs automatically on staged files via Husky pre-commit hook.

## Code Style

### PHP

- Standard: [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) via PHPCS
- Namespace: PSR-4 under `AiAgent\` (e.g., `AiAgent\Core\AgentLoop`)
- All files: `declare(strict_types=1);`
- Type declarations required on all parameters and return types
- Error handling: return `WP_Error`; never throw exceptions in hooks

### JavaScript

- Config: `@wordpress/eslint-plugin` (extends WordPress JS standards)
- Components: React 18 with `@wordpress/components` and `@wordpress/element`
- i18n: always wrap strings with `__( 'text', 'ai-agent' )`
- State: `@wordpress/data` store — use `useSelect` / `useDispatch` hooks

## PR Guidelines

### Branch naming

```
feature/short-description
bugfix/short-description
docs/short-description
```

### Commit messages

[Conventional Commits](https://www.conventionalcommits.org/) format is required:

```
feat: add memory search endpoint
fix: resolve session timeout on multisite
docs: update setup instructions
refactor: extract chunker logic into separate class
chore: bump @wordpress/scripts to 31.x
```

Types: `feat`, `fix`, `docs`, `refactor`, `chore`, `perf`, `test`

### PR title

Include the task ID if one exists:

```
t057: Add CONTRIBUTING.md with dev setup and PR guidelines
```

### PR body

- Link to the related issue: `Closes #78`
- Describe what changed and why
- Note any manual testing steps if automated tests don't cover the change

### Checklist before opening a PR

- [ ] `npm run lint` passes with no errors
- [ ] `npm run test:php` passes (requires Docker)
- [ ] New behaviour has test coverage where practical
- [ ] Commit messages follow Conventional Commits
