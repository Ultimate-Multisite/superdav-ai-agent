# Gratis AI Agent - WordPress Plugin Development Guide

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

## Code Style & Architecture

### PHP (PSR-4 + PHP 8.2+)
- **Namespace**: PSR-4 namespaces under `GratisAiAgent\` (e.g., `namespace GratisAiAgent\Core;`)
- **Class names**: PascalCase (e.g., `AgentLoop`, `RestController`)
- **File naming**: `{ClassName}.php` matching the class name exactly
- **Directory structure**:
  - `includes/Core/` - Core classes (Database, Settings, AgentLoop)
  - `includes/Models/` - Data models (Memory, Skill, Chunker)
  - `includes/Abilities/` - WordPress Abilities API implementations (30 ability classes)
  - `includes/Knowledge/` - Knowledge base system
  - `includes/Tools/` - Custom tools and profiles
  - `includes/Automations/` - Scheduled and event-driven automations
  - `includes/Benchmark/` - Performance benchmarking
  - `includes/REST/` - REST API controller
  - `includes/Admin/` - Admin pages and widgets
  - `includes/CLI/` - WP-CLI commands
  - `includes/Enums/` - PHP 8.1+ enums
- **Constants**: SCREAMING_SNAKE_CASE (e.g., `DB_VERSION`, `PAGE_SLUG`)
- **Methods**: camelCase (PSR convention, e.g., `getSession()`, `createSession()`)
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
- **File structure**: React components in `src/components/`, entry points in `src/{admin-page,floating-widget,screen-meta,settings-page,changes-page,abilities-explorer}/`
- **Styling**: CSS modules in same directory as component (`style.css`)
- **i18n**: Always use `__( 'text', 'gratis-ai-agent' )` for translatable strings
- **Hooks**: Use WordPress data hooks (`useSelect`, `useDispatch`) consistently
- **Build**: Webpack via `@wordpress/scripts` targeting 6 entry points (admin-page, floating-widget, screen-meta, settings-page, changes-page, abilities-explorer)

### Naming Conventions
- **Variables**: camelCase in both JS and PHP
- **Functions/Methods**: camelCase in both JS and PHP
- **Classes**: PascalCase (e.g., `AgentLoop`, `MemoryAbilities`)
- **Components**: PascalCase (e.g., `ChatPanel`, `MessageList`)
- **Enums**: PascalCase with PascalCase cases (e.g., `MemoryCategory::SiteInfo`)
- **Database tables**: Prefixed with `{$wpdb->prefix}gratis_ai_agent_` (10 tables total)
- **REST routes**: `/gratis-ai-agent/v1/{endpoint}` namespace

### Class Mapping Reference
| Old Name | New Location | New Class Name |
|----------|--------------|----------------|
| `Database` | `Core/Database.php` | `GratisAiAgent\Core\Database` |
| `Settings` | `Core/Settings.php` | `GratisAiAgent\Core\Settings` |
| `Agent_Loop` | `Core/AgentLoop.php` | `GratisAiAgent\Core\AgentLoop` |
| `Memory` | `Models/Memory.php` | `GratisAiAgent\Models\Memory` |
| `Skill` | `Models/Skill.php` | `GratisAiAgent\Models\Skill` |
| `Rest_Controller` | `REST/RestController.php` | `GratisAiAgent\REST\RestController` |
| `Memory_Abilities` | `Abilities/MemoryAbilities.php` | `GratisAiAgent\Abilities\MemoryAbilities` |

## WordPress SDK Integration
- Use `wp_ai_client_prompt()` for AI calls (WordPress 7.0+ AI Client SDK)
- Register abilities via `wp_register_ability()` (Abilities API)
- All tool schemas follow OpenAI function-calling JSON schema format
- Provider/model selection via WordPress Connectors API
