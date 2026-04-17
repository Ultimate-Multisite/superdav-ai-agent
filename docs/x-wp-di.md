# x-wp/di — Dependency Injection Reference

> **Package:** [`x-wp/di`](https://github.com/x-wp/di) ^1.9  
> **Docs source:** Learned through the 6-PR migration (PRs #983–#986, #990).  
> **Load this skill** when working on DI container configuration, adding new handlers, converting legacy `add_action` wiring, or debugging handler loading issues.

## Architecture

```
gratis-ai-agent.php
  └─ xwp_load_app()              # schedules container on plugins_loaded:PHP_INT_MIN
       └─ xwp_create_app()       # builds PHP-DI container, processes Module
            └─ Plugin.php        # #[Module] — lists all handlers
                 ├─ AbilitySchemaFilter        # #[Handler] — filter hooks
                 ├─ MemoryController           # #[REST_Handler] — single-basename REST
                 ├─ SessionController          # #[Handler] + #[Action] — multi-basename REST
                 ├─ AbilitiesHandler           # #[Handler] + #[Action] — abilities
                 ├─ AdminHandler               # #[Handler] CTX_ADMIN — admin hooks
                 ├─ CoreServicesHandler        # #[Handler] — on_initialize() delegation
                 ├─ FrontendAssetsHandler      # #[Handler] CTX_FRONTEND — public assets
                 └─ ... (24 handlers total)
```

## Core Concepts

### Container Bootstrap

```php
// gratis-ai-agent.php — the ONLY thing needed in the plugin file
xwp_load_app([
    'id'            => 'gratis-ai-agent',
    'module'        => Plugin::class,
    'autowiring'    => true,
    'compile'       => 'production' === wp_get_environment_type(),
    'compile_class' => 'CompiledContainerGratisAiAgent', // REQUIRED — hyphens in ID break class names
    'compile_dir'   => GRATIS_AI_AGENT_DIR . '/build/di-cache/' . GRATIS_AI_AGENT_VERSION,
]);
```

### Module (Plugin.php)

```php
#[Module(
    container: 'gratis-ai-agent',
    hook: 'plugins_loaded',
    priority: 1,              // Must be > PHP_INT_MIN (xwp_load_app's default)
    imports: [],
    handlers: [
        MyHandler::class,     // Listed here = registered by the DI system
    ],
    extendable: true,         // Allows companion plugins to add handlers
)]
final class Plugin {
    public static function configure(): array {
        return [
            'plugin.version' => \DI\value( GRATIS_AI_AGENT_VERSION ),
        ];
    }
}
```

### Context Constants

| Constant | Value | Detected by |
|----------|-------|-------------|
| `CTX_FRONTEND` | 1 | `!is_admin() && !cron && !rest && !cli` |
| `CTX_ADMIN` | 2 | `is_admin() && !DOING_AJAX` |
| `CTX_AJAX` | 4 | `DOING_AJAX` constant |
| `CTX_CRON` | 8 | `DOING_CRON` constant |
| `CTX_REST` | 16 | `$_SERVER['REQUEST_URI']` contains `wp-json/` |
| `CTX_CLI` | 32 | `WP_CLI` constant |
| `CTX_GLOBAL` | 63 | Always matches (all bits set) |

Context is cached in `XWP_Context::$current` on first call to `get()`. The match evaluates **in order**: admin → ajax → cron → rest → cli → frontend. If `is_admin()` is true, rest is never checked.

### Handler Strategies

| Strategy | When hooks attach | Use case |
|----------|-------------------|----------|
| `INIT_DEFFERED` (default) | When `tag` fires | REST_Handler default — hooks attach on `rest_api_init` |
| `INIT_IMMEDIATELY` | During Module processing on `plugins_loaded` | Most custom handlers — hooks attach immediately |
| `INIT_ON_DEMAND` | When a dependent hook fires | Lazy loading |
| `INIT_JUST_IN_TIME` | Same as ON_DEMAND | Lazy loading variant |
| `INIT_DYNAMICALY` | Already loaded at construction | Pre-instantiated handlers |

## Two REST Patterns

### Pattern 1: `#[REST_Handler]` — Single-basename controllers

Use when a controller has ONE REST basename (e.g., `/memory`, `/skills`).

```php
use XWP\DI\Decorators\REST_Handler;
use XWP\DI\Decorators\REST_Route;

#[REST_Handler(
    namespace: 'gratis-ai-agent/v1',
    basename: 'memory',
    container: 'gratis-ai-agent',
)]
final class MemoryController extends XWP_REST_Controller {

    use PermissionTrait;

    #[REST_Route(
        route: '',                              // = /memory
        methods: WP_REST_Server::READABLE,
        vars: 'get_list_args',                  // method name returning args array
        guard: 'check_manage_options',          // PermissionTrait method
    )]
    public function handle_list( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        // ...
    }

    #[REST_Route(
        route: '(?P<id>\d+)',                   // = /memory/123
        methods: WP_REST_Server::DELETABLE,
        guard: 'check_manage_options',
    )]
    public function handle_delete( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        // ...
    }

    public function get_list_args(): array {
        return [ 'per_page' => [ 'type' => 'integer', 'default' => 20 ] ];
    }
}
```

**Key rules:**
- Class MUST extend `XWP_REST_Controller` (which extends `WP_REST_Controller`)
- `REST_Handler` hardcodes `context: CTX_REST` — cannot override
- `REST_Route::vars` is either an inline array OR a string naming a method
- `REST_Route::guard` names a permission_callback method (use `PermissionTrait`)
- `REST_Route::invoke()` calls `register_rest_route()` directly — no context re-check

### Pattern 2: `#[Handler]` + `#[Action]` — Multi-basename controllers

Use when a controller has MULTIPLE REST basenames (e.g., `/abilities` + `/custom-tools`).

```php
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

#[Handler(
    container: 'gratis-ai-agent',
    context: Handler::CTX_REST,
    strategy: Handler::INIT_IMMEDIATELY,
)]
final class ToolController {

    #[Action( tag: 'rest_api_init', priority: 10 )]
    public function register_routes(): void {
        register_rest_route( RestController::NAMESPACE, '/abilities', [ /* ... */ ] );
        register_rest_route( RestController::NAMESPACE, '/custom-tools', [ /* ... */ ] );
    }
}
```

**Conversion pattern** (mechanical, from legacy static `register_routes()`):
1. Add `use XWP\DI\Decorators\{Action, Handler}` imports
2. Add ABSPATH guard
3. Add `#[Handler(container, context: CTX_REST, strategy: INIT_IMMEDIATELY)]`
4. Make class `final`
5. Change `public static function register_routes()` → `public function register_routes()`
6. Add `#[Action(tag: 'rest_api_init', priority: 10)]` on it
7. Replace `self::NAMESPACE` with `RestController::NAMESPACE`
8. Replace `$instance` with `$this`, remove `$instance = new self()` line
9. Remove `const NAMESPACE` (shared via `RestController::NAMESPACE`)

## Non-REST Handler Patterns

### Action/Filter hooks

```php
#[Handler(container: 'gratis-ai-agent', strategy: Handler::INIT_IMMEDIATELY)]
final class AbilitySchemaFilter {

    #[Filter( tag: 'wp_register_ability_args', priority: 10 )]
    public function normalize_schema( array $args ): array {
        // ...
        return $args;
    }
}
```

### on_initialize() delegation

For wrapping existing static `register()` methods that internally add many hooks:

```php
#[Handler(container: 'gratis-ai-agent', strategy: Handler::INIT_IMMEDIATELY)]
final class CoreServicesHandler {

    public function on_initialize(): void {
        ChangeLogger::register();      // adds 5 hooks internally
        ProviderTraceLogger::register(); // adds 2 filters internally
        // ...
    }
}
```

`on_initialize()` is called automatically by the DI system during handler loading. It works on ANY handler class (not just `XWP_REST_Controller` subclasses).

### Context-scoped handlers

```php
// Only loads on admin pages — zero overhead on frontend/REST/CLI
#[Handler(
    container: 'gratis-ai-agent',
    context: Handler::CTX_ADMIN,
    strategy: Handler::INIT_IMMEDIATELY,
)]
final class AdminHandler {
    #[Action( tag: 'admin_menu', priority: 10 )]
    public function register_menus(): void { /* ... */ }

    #[Action( tag: 'admin_init', priority: 10 )]
    public function on_admin_init(): void { /* ... */ }
}
```

## Gotchas & Hard-Won Lessons

### 1. `compile_class` is REQUIRED when container ID has hyphens

The default `compile_class` is `CompiledContainer` + uppercased ID. For `gratis-ai-agent`, this produces `CompiledContainerGratis-ai-agent` — an invalid PHP class name. Always set `compile_class` explicitly.

### 2. Module priority must differ from `xwp_load_app()` priority

`xwp_load_app()` defaults to `plugins_loaded:PHP_INT_MIN`. If the `#[Module]` also uses `plugins_loaded:PHP_INT_MIN`, PHP's foreach snapshot-iteration means the Module callback queued by `xwp_create_app()` never fires. Use `priority: 1` (or any value > PHP_INT_MIN) on the Module.

### 3. `INIT_IMMEDIATELY` handlers with `plugins_loaded` tag hit the same snapshot issue

If a handler has `tag: 'plugins_loaded'` and `priority: 1` (same as the Module), it may be queued during the Module's processing but miss the current iteration. Fix: use `strategy: INIT_IMMEDIATELY` which bypasses deferred queuing entirely.

### 4. `REST_Handler` supports only ONE basename per class

The `#[REST_Handler(namespace, basename, container)]` decorator maps to a single `rest_base`. For controllers with multiple basenames (e.g., `/abilities` + `/custom-tools`), use `#[Handler(context: CTX_REST, strategy: INIT_IMMEDIATELY)]` + `#[Action(tag: 'rest_api_init')]` instead.

### 5. `CTX_REST` handlers don't load in PHPUnit

`XWP_Context::get()` checks `admin()` BEFORE `rest()`. The WP test bootstrap defines `WP_ADMIN=true`, so context resolves to Admin even when `$_SERVER['REQUEST_URI']` contains `/wp-json/`. The context is cached — setting REQUEST_URI alone doesn't help.

**Current workaround:** Reflection override in `tests/bootstrap.php`:
```php
$refl = new ReflectionProperty( XWP_Context::class, 'current' );
$refl->setValue( null, XWP_Context::REST );
```

**Outstanding issue:** Even with correct context, deferred handlers mark hooks as `$loaded` after first fire. When test `setUp()` creates a fresh `WP_REST_Server` and re-fires `rest_api_init`, routes don't re-register on the new server. Tracked in GitHub issues.

### 6. PHPStan cache corruption on PHP 8.4

Occasionally `/tmp/phpstan/cache/` fatals with class-not-found errors. Fix: `rm -rf /tmp/phpstan && composer install` before re-running.

### 7. Making classes `final` tightens PHPStan return-type analysis

When converting controllers to `final`, PHPStan may flag return types that were previously acceptable. E.g., a method declared `WP_REST_Response|WP_Error` that always returns `WP_REST_Response` — PHPStan now knows the `WP_Error` branch is unreachable.

### 8. DI cache must be cleared after handler changes

```bash
rm -rf build/di-cache/
```

The compiled container caches handler metadata. Stale cache = handlers not found or wrong hooks.

### 9. Verifying REST routes in non-test environments

`CTX_REST` handlers don't load during CLI or admin. Use HTTP requests to verify:

```bash
# 401 = route exists + auth required, 404 = route missing
curl -s -o /dev/null -w "%{http_code}" "http://wordpress.local:8080/wp-json/gratis-ai-agent/v1/memory"
```

### 10. Pre-commit vendor dance

The pre-commit hook runs `composer install --no-dev` before staging `vendor/`, then `composer install` afterwards. This ensures only production deps are committed. Don't manually `git add vendor/` — let the hook handle it.

## File Map

```
includes/
├── Plugin.php                          # #[Module] — handler registry (24 handlers)
├── Bootstrap/
│   ├── LifecycleHandler.php            # Activation/deactivation (pre-DI, static)
│   ├── CliHandler.php                  # WP-CLI context handler
│   ├── AbilitiesHandler.php            # 35 ability registrations
│   ├── AdminHandler.php                # Admin menus, capabilities, admin assets
│   ├── CoreServicesHandler.php         # Background services (logging, automations, etc.)
│   └── FrontendAssetsHandler.php       # Frontend widget assets
├── Infrastructure/
│   ├── AiClient/
│   │   └── RequestTimeoutFilter.php    # AI client timeout filter
│   ├── Schema/
│   │   └── SchemaNormalizer.php        # Pure class (no DI decorator)
│   └── WordPress/Abilities/
│       ├── AbilitySchemaFilter.php     # Schema normalization filter
│       ├── AbilityCategoryRegistrar.php # Category registration action
│       └── UsageInstructionsFilter.php  # Usage instructions filter
└── REST/
    ├── MemoryController.php            # #[REST_Handler] — single basename
    ├── SkillController.php             # #[REST_Handler] — single basename
    ├── SessionController.php           # #[Handler] — multi basename
    ├── SettingsController.php          # #[Handler] — multi basename
    ├── RestController.php              # #[Handler] — shared NAMESPACE constant
    └── ... (16 controllers total)
```

## Adding a New Handler

1. Create class in appropriate directory (`Bootstrap/`, `Infrastructure/`, etc.)
2. Add `declare(strict_types=1)`, namespace, ABSPATH guard
3. Decorate with `#[Handler(container: 'gratis-ai-agent', ...)]`
4. Add `#[Action]` / `#[Filter]` on methods, or use `on_initialize()`
5. Add the class to `Plugin.php`'s `handlers` array
6. Run `composer dump-autoload`
7. Clear DI cache: `rm -rf build/di-cache/`
8. Verify: `curl` for REST, admin page load for admin handlers
