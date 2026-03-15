# AI Agent

[![Tests](https://github.com/Ultimate-Multisite/ai-agent/actions/workflows/tests.yml/badge.svg)](https://github.com/Ultimate-Multisite/ai-agent/actions/workflows/tests.yml)
[![Code Quality](https://github.com/Ultimate-Multisite/ai-agent/actions/workflows/code-quality.yml/badge.svg)](https://github.com/Ultimate-Multisite/ai-agent/actions/workflows/code-quality.yml)
[![PHP 8.2+](https://img.shields.io/badge/php-%3E%3D%208.2-blue.svg)](https://www.php.net/)
[![WordPress 6.9+](https://img.shields.io/badge/WordPress-%3E%3D%206.9-blue.svg)](https://wordpress.org/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Try in Playground](https://img.shields.io/badge/Try%20in-WordPress%20Playground-3858e9?logo=wordpress&logoColor=white)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/Ultimate-Multisite/ai-agent/main/.wordpress-org/blueprints/blueprint.json)

[Documentation](https://github.com/Ultimate-Multisite/ai-agent/wiki)

An agentic AI assistant that lives inside your WordPress dashboard. It can chat, remember context across sessions, call WordPress tools autonomously, run scheduled tasks, react to WordPress events, and manage your site — all powered by the AI provider of your choice.

Built on the **WordPress AI Client SDK** and **Abilities API** (WordPress 6.9+), AI Agent avoids vendor lock-in entirely. Bring your own API key, swap providers at any time, and keep every token charge on your own account.

## Why Bring Your Own Key?

Most WordPress AI plugins lock you into a proprietary proxy that marks up API costs 2-10x. AI Agent takes a different approach:

- **Direct API calls** — Your WordPress talks directly to OpenAI, Anthropic, or any OpenAI-compatible endpoint. No middleman, no markup.
- **Full model choice** — Use GPT-4o, Claude Opus 4, a local Ollama instance, or anything the WordPress AI Client connector supports. Switch models per-session.
- **Transparent pricing** — You see exactly what you spend in your provider's dashboard. The plugin's Usage tab tracks token counts and estimated costs locally.
- **Privacy** — Conversations go directly to your chosen provider. Nothing routes through a third-party relay.

## Why the WordPress SDK?

AI Agent is built entirely on WordPress core APIs shipping in 6.9:

- **AI Client SDK** (`wp_ai_client_prompt`) — Unified interface for all AI providers. Any connector plugin (OpenAI, Anthropic, Ollama, etc.) registered through the WordPress Connectors API works automatically.
- **Abilities API** (`wp_register_ability`) — The WordPress-native tool/function-calling registry. Every ability registered by any plugin is automatically available to the agent. This means AI Agent gets smarter as your site grows — install a SEO plugin that registers abilities and the agent can use them without any configuration.
- **Future-proof** — As WordPress core evolves its AI infrastructure, AI Agent inherits improvements for free. No fragile custom API wrappers to maintain.

## Features

### Agentic Chat
- Autonomous tool-calling loop — the AI plans, executes tools, reads results, and iterates until the task is done
- Configurable max iterations (1-50) to control how many tool calls per request
- Tool confirmation system — approve or reject destructive actions before they run, with "always allow" memory
- Full markdown rendering with syntax-highlighted code blocks
- Message actions: regenerate, edit & resend, delete

### Two Chat Interfaces
- **Full-page admin panel** (Tools > AI Agent) — two-column layout with session sidebar, folder organization, search, and filtering
- **Floating widget** — draggable FAB button on every admin page, expandable chat panel with multi-tab sessions

### Session Management
- Persistent conversation history stored in a dedicated database table
- Organize sessions into folders, pin favorites, archive old conversations
- Search across all sessions by title or content
- Export sessions to JSON (reimportable) or Markdown (human-readable)
- Import sessions from JSON backups
- Per-session token usage tracking with cost estimates

### Memory
- Persistent knowledge base the AI retains across sessions
- Categories: site info, user preferences, technical notes, workflows, general
- Auto-memory mode — the AI proactively saves important facts it learns
- Full CRUD through both the settings UI and the AI itself (via abilities)

### Skills
- Reusable instruction guides the AI can load on demand
- Define complex workflows once ("how to optimize images", "content publishing checklist") and the agent follows them when relevant
- Enable/disable per skill, manage through settings UI or chat

### Knowledge Base (RAG)
- Index WordPress posts, pages, and custom post types into searchable collections
- Upload PDF documents and external URLs
- Automatic chunking with configurable size and overlap
- Full-text search across indexed content
- Auto-index on post publish/update (optional)
- The AI searches knowledge automatically when relevant context is needed

### Custom Tools
Create tools the AI can use without writing plugin code:

- **HTTP tools** — Call any external API (weather, Zapier, Slack, etc.) with `{{placeholder}}` substitution in URLs, headers, and body
- **ACTION tools** — Fire any WordPress `do_action()` hook with arguments
- **CLI tools** — Run WP-CLI commands with argument schemas

5 example tools are seeded on first activation (Weather API, Zapier Webhook, Clear Object Cache, Maintenance Mode, Site Health Check). Test any tool directly from the settings UI.

### Tool Profiles
Named sets of tools that restrict what the AI can access:

- 6 built-in profiles: WP Read Only, WP Full Management, Content Management, User Management, Maintenance, Developer
- Create custom profiles with tool name prefix matching
- Set an active profile globally, or per-automation/event
- Useful for security (read-only mode for public-facing agents) and token savings (fewer tools = smaller system prompt)

### Scheduled Automations
Cron-based AI tasks that run unattended:

- Define a prompt, pick a schedule (hourly / twice daily / daily / weekly), and let the agent work
- 5 quick-start templates: Daily Site Health Report, Weekly Plugin Update Check, Content Moderation, Broken Link Check, Database Optimization
- Per-automation tool profile restrictions and max iteration limits
- Run any automation manually with "Run Now"
- Execution logs with duration, token usage, status, and full response

### Event-Driven Automations
React to WordPress hooks in real time:

- 20+ pre-registered triggers across WordPress core, WooCommerce, and form plugins
- **WordPress**: post status changed, user registered, login, comment posted, plugin activated, theme switched, media uploaded, and more
- **WooCommerce**: new order, order status changed, low stock, payment complete, refund created
- Prompt templates with `{{placeholder}}` substitution from hook arguments (e.g., `{{post.title}}`, `{{user.email}}`, `{{order.total}}`)
- Conditional execution — only fire when conditions match (post type, status, role, etc.)
- Shared logging infrastructure with scheduled automations

### Tool Discovery
When your site has many registered abilities (20+), the agent uses meta-tools to discover what's available instead of loading every tool definition into the system prompt:

- `list-tools` — Browse tools by category with pagination
- `execute-tool` — Run a discovered tool by name
- Configurable threshold and mode (auto / always / never)
- Dramatically reduces token usage on sites with dozens of tools

### Smart Conversation Trimming
Prevents context window overflow in long agentic loops:

- Counts tool-call/response cycles and trims at safe boundaries
- Never cuts mid-tool-cycle or drops the first turn
- Configurable max history turns (default: 20)
- Transparent trimming marker injected so the AI knows context was compressed

### Suggestion Chips
After each AI response, clickable follow-up suggestions appear:

- AI generates 2-4 contextual suggestions
- One click sends the suggestion as your next message
- Configurable count (0-5) in settings

### Usage Dashboard
Track token consumption and estimated costs:

- Per-session and aggregate token counts (prompt + completion)
- Cost estimates based on current model pricing
- Breakdown by model and time period

### Context Providers
The AI automatically receives relevant context about the current page:

- Current admin page URL and title
- Site information (name, URL, WordPress version)
- Extensible — plugins can register additional context providers

## Requirements

- WordPress 6.9+ (for AI Client SDK and Abilities API)
- PHP 7.4+
- An AI provider connector plugin (e.g., WordPress AI: OpenAI, or any OpenAI-compatible connector)
- An API key from your chosen provider

## Installation

1. Upload the `ai-agent` folder to `/wp-content/plugins/`
2. Activate through the Plugins screen
3. Configure an AI provider in **Settings > AI Credentials** (this is part of WordPress core's Connectors API)
4. Go to **Tools > AI Agent Settings** to select your default provider and model
5. Open **Tools > AI Agent** to start chatting

## Configuration

All settings live under **Tools > AI Agent Settings** with these tabs:

| Tab | What it controls |
|-----|-----------------|
| General | Default provider, model, max iterations, greeting message |
| System Prompt | Custom system instructions (leave empty for built-in default) |
| Memory | Auto-memory toggle, memory CRUD |
| Skills | Create and manage instruction guides |
| Knowledge | Enable/configure RAG, manage collections and sources |
| Custom Tools | Create HTTP/ACTION/CLI tools |
| Tool Profiles | Manage and activate tool restriction profiles |
| Automations | Scheduled cron-based AI tasks |
| Events | Event-driven hook-based automations |
| Abilities | Per-tool permission controls (auto / confirm / disabled) |
| Usage | Token counts and cost tracking |
| Advanced | Temperature, max tokens, context window, tool discovery settings |

## Architecture

```
ai-agent/
├── ai-agent.php                    # Bootstrap, requires, hooks
├── includes/
│   ├── class-agent-loop.php        # Core agentic loop (plan → tool call → iterate)
│   ├── class-rest-controller.php   # REST API (async job pattern)
│   ├── class-database.php          # Schema + migrations (10 tables)
│   ├── class-settings.php          # Settings model (wp_option backed)
│   ├── class-memory.php            # Persistent memory CRUD
│   ├── class-skill.php             # Skills CRUD
│   ├── class-knowledge*.php        # RAG pipeline (collections, sources, chunks)
│   ├── class-custom-tools.php      # Custom tool model
│   ├── class-custom-tool-executor.php  # HTTP/ACTION/CLI execution
│   ├── class-tool-profiles.php     # Named tool sets
│   ├── class-automations.php       # Scheduled automation model
│   ├── class-automation-runner.php  # Cron handler
│   ├── class-event-automations.php # Event automation model
│   ├── class-event-trigger-*.php   # Hook registry + handler
│   ├── class-conversation-trimmer.php  # Context overflow prevention
│   ├── class-tool-discovery.php    # Meta-tool system
│   ├── class-cost-calculator.php   # Token cost estimates
│   └── class-context-providers.php # Page context injection
├── src/
│   ├── admin-page/                 # Full-page chat React app
│   ├── floating-widget/            # Draggable widget React app
│   ├── settings-page/              # Settings React app (12 tabs)
│   ├── components/                 # Shared React components
│   ├── store/                      # Redux store (actions, selectors)
│   └── utils/                      # Keyboard shortcuts, helpers
└── build/                          # Compiled assets
```

### REST API Pattern

The agent uses an **async job + polling** pattern to handle long-running inference:

1. `POST /ai-agent/v1/run` — Starts a background job, returns `job_id`
2. `GET /ai-agent/v1/job/{id}` — Poll until `status: completed` (or `awaiting_confirmation` for tool approval)
3. `POST /ai-agent/v1/job/{id}/confirm` or `/reject` — Handle tool confirmations

This avoids HTTP timeout issues with multi-step agentic loops that can take 30+ seconds.

### Extending

**Register custom abilities** that the agent can use:

```php
add_action( 'wp_abilities_api_init', function() {
    wp_register_ability( 'my_custom_tool', [
        'label'       => 'My Custom Tool',
        'description' => 'Does something useful',
        'category'    => 'my-plugin',
        'callback'    => 'my_tool_callback',
        'schema'      => [
            'type'       => 'object',
            'properties' => [
                'param' => [ 'type' => 'string', 'description' => 'A parameter' ],
            ],
        ],
    ] );
} );
```

The agent discovers and uses any registered ability automatically.

**Add context providers:**

```php
add_filter( 'ai_agent_context_providers', function( $providers ) {
    $providers[] = [
        'label'    => 'My Plugin Context',
        'priority' => 10,
        'callback' => function() {
            return "Current inventory count: " . get_inventory_count();
        },
    ];
    return $providers;
} );
```

## Development

```bash
# Install dependencies
npm install
composer install

# Development build with watch
npm start

# Production build
npm run build

# Run linters
composer phpcs      # PHP CodeSniffer
composer phpcbf     # Auto-fix PHPCS issues
composer phpstan    # Static analysis
composer test       # PHPUnit tests
```

The plugin builds three entry points: `admin-page`, `floating-widget`, and `settings-page`.

### WordPress Playground Testing

Test the plugin instantly in your browser without any local setup:

- **Latest release**: [Open in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/Ultimate-Multisite/ai-agent/main/.wordpress-org/blueprints/blueprint.json) (login: `admin` / `password`)
- **Development branch**: [Open dev version](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/Ultimate-Multisite/ai-agent/main/playground/blueprint-dev.json)

The canonical blueprint lives at `.wordpress-org/blueprints/blueprint.json` — this is the path WordPress.org uses when displaying the plugin in the plugin directory. The `playground/` directory contains additional variants (debug mode, dev builds).

## License

GPL-2.0-or-later
