# Gratis AI Agent - WordPress Plugin Development Guide

**Requires:** WordPress 7.0+, PHP 8.2+

## Build Commands
- **Build**: `npm run build` or `npx wp-scripts build` (production)
- **Dev**: `npm start` or `npx wp-scripts start` (watch mode)
- **Install**: `npm install && composer install`
- **Autoload**: `composer dump-autoload` (after adding/moving PHP classes)
- **Lint JS**: `npm run lint:js` (ESLint with `@wordpress/eslint-plugin`)
- **Lint CSS**: `npm run lint:css` (Stylelint)
- **Lint PHP**: `npm run lint:php` or `composer phpcs` (WordPress Coding Standards via PHPCS)
- **Fix lint**: `npm run lint:js:fix`, `npm run lint:css:fix`, `npm run lint:php:fix`
- **Static analysis**: `composer phpstan` (PHPStan with WordPress extensions)
- **Test JS**: `npm run test:js` (Jest via `@wordpress/scripts`)
- **Test PHP**: `npm run test:php` (PHPUnit via `@wordpress/env`)
- **Test E2E**: `npm run test:e2e:playwright` (Playwright)
- **Pre-commit**: Husky + lint-staged runs lint fixes on staged files
- **Dev environment**: `npx wp-env start` (WordPress 7.0 via `.wp-env.json`)

## Code Style & Architecture

### PHP (PSR-4 + PHP 8.2+)
- **Namespace**: PSR-4 namespaces under `GratisAiAgent\` (e.g., `namespace GratisAiAgent\Core;`)
- **Class names**: PascalCase (e.g., `AgentLoop`, `RestController`)
- **File naming**: `{ClassName}.php` matching the class name exactly
- **Directory structure**:
  - `includes/Core/` - Core classes (Database, Settings, AgentLoop, BudgetManager)
  - `includes/Models/` - Data models (Memory, Skill, Agent, ConversationTemplate, Chunker)
  - `includes/Abilities/` - WordPress Abilities API implementations (30+ ability classes)
  - `includes/Knowledge/` - Knowledge base system (collections, sources, chunks, RAG search)
  - `includes/Tools/` - Custom tools, tool profiles, and tool discovery
  - `includes/Automations/` - Scheduled and event-driven automations
  - `includes/Benchmark/` - Model benchmarking (runner, suite, scoring)
  - `includes/REST/` - REST API controllers (RestController, WebhookController, McpController, ResaleApiController, BenchmarkController)
  - `includes/Admin/` - Admin pages (UnifiedAdminMenu, ModelBenchmarkPage)
  - `includes/CLI/` - WP-CLI commands
  - `includes/Enums/` - PHP 8.1+ enums
- **Constants**: SCREAMING_SNAKE_CASE (e.g., `DB_VERSION`, `PAGE_SLUG`)
- **Methods**: snake_case (e.g., `get_session()`, `create_session()`, `list_sessions()`)
- **Properties**: camelCase with typed declarations
- **Hooks**: Use `add_action()`, `add_filter()` with priority 10 by default
- **Autoloading**: Composer PSR-4 from `includes/` directory
- **Type declarations**: Required for all parameters and return types
- **Strict types**: All files must declare `declare(strict_types=1);`
- **Error handling**: Return `WP_Error` objects; never throw exceptions in hooks

### JavaScript (React + WordPress Components)
- **Framework**: React 18 with `@wordpress/element` and `@wordpress/components`
- **State**: Redux via `@wordpress/data` store (see `src/store/index.js`)
- **Imports**: WordPress packages first, then internal dependencies
- **File structure**: React components in `src/components/`, entry points in `src/`
- **Styling**: CSS files in same directory as component (`style.css`), prefix all classes with `gratis-ai-agent-`
- **i18n**: Always use `__( 'text', 'gratis-ai-agent' )` for translatable strings
- **Hooks**: Use WordPress data hooks (`useSelect`, `useDispatch`) consistently
- **Build**: Webpack via `@wordpress/scripts` with entry points defined in `webpack.config.js`

### Naming Conventions
- **Variables**: camelCase in both JS and PHP
- **Functions/Methods**: snake_case in PHP, camelCase in JS
- **Classes**: PascalCase (e.g., `AgentLoop`, `MemoryAbilities`)
- **Components**: PascalCase (e.g., `ChatPanel`, `MessageList`)
- **Enums**: PascalCase with PascalCase cases (e.g., `MemoryCategory::SiteInfo`)
- **Database tables**: Prefixed with `{$wpdb->prefix}gratis_ai_agent_` (23 tables across 4 schema files)
- **REST routes**: `/gratis-ai-agent/v1/{endpoint}` namespace
- **CSS classes**: Prefixed with `gratis-ai-agent-` (e.g., `gratis-ai-agent-chat-panel`)

## WordPress SDK Integration
- Use `wp_ai_client_prompt()` for AI calls (WordPress 7.0+ AI Client SDK)
- Register abilities via `wp_register_ability()` (Abilities API)
- All tool schemas follow OpenAI function-calling JSON schema format
- Provider/model selection via WordPress Connectors API (Settings > Connectors)
- Abilities extend `AbstractAbility` which extends core `WP_Ability`

## Local Development Environment

The shared WordPress dev install for testing this plugin is at `../wordpress` (relative to this repo root).

- **URL**: http://wordpress.local:8080
- **Admin**: http://wordpress.local:8080/wp-admin — `admin` / `admin`
- **WordPress version**: 7.0-RC2
- **This plugin**: symlinked into `../wordpress/wp-content/plugins/$(basename $PWD)`
- **Reset to clean state**: `cd ../wordpress && ./reset.sh`

WP-CLI is configured via `wp-cli.yml` in this repo root — run `wp` commands directly from here without specifying `--path`.

```bash
wp plugin activate $(basename $PWD)   # activate this plugin
wp plugin deactivate $(basename $PWD) # deactivate
wp db reset --yes && cd ../wordpress && ./reset.sh  # full reset
```
