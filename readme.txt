=== Gratis AI Agent ===
Contributors: developer-dave
Tags: ai, chatbot, assistant, automation, tools
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 1.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An AI assistant in your dashboard. Chat with it, teach it about your site, and let it manage tasks — using your own API key.

== Description ==

Gratis AI Agent adds a powerful AI assistant directly inside your WordPress admin. Ask it questions, give it tasks, and it will use your site's tools to get the job done — creating posts, managing users, checking site health, calling external APIs, and more.

**You bring your own API key.** Gratis AI Agent connects directly to your chosen AI provider (OpenAI, Anthropic, or any OpenAI-compatible service). There is no middleman, no markup on API costs, and no data routed through third-party servers. You pay only what your provider charges, and you can see every cent in their dashboard.

= Built on WordPress Core =

Gratis AI Agent is built on official WordPress APIs shipping in version 6.9:

* **AI Client SDK** — One interface for all AI providers. Install a connector plugin for OpenAI, Anthropic, Ollama, or any compatible service and Gratis AI Agent works immediately.
* **Abilities API** — The WordPress-native tool registry. Every tool registered by any plugin on your site is automatically available to the agent. As your site grows, so does the agent's capabilities.

This means no fragile custom API wrappers, no vendor lock-in, and automatic improvements as WordPress core evolves.

= Two Ways to Chat =

* **Full-page chat** at Tools > Gratis AI Agent — A complete workspace with session history, folder organization, search, and export.
* **Floating widget** — A small button on every admin page that expands into a chat panel. Always available, never in the way.

= It Remembers =

The agent has persistent memory across sessions. It learns your preferences, site details, and workflows over time. You can also teach it manually or let it save knowledge automatically.

= It Knows Your Content =

Index your posts, pages, and uploaded documents into a searchable knowledge base. The agent searches this knowledge automatically when it needs context to answer your questions.

= Custom Tools Without Code =

Create tools the agent can use — no plugin development needed:

* **HTTP tools** — Connect to any external API (weather services, Zapier, Slack, CRMs)
* **WordPress action tools** — Trigger any WordPress hook
* **WP-CLI tools** — Run command-line operations

Five example tools are included to get you started.

= Scheduled Automations =

Set up AI tasks that run automatically on a schedule:

* Daily site health reports
* Weekly plugin update checks
* Content moderation
* Broken link scanning
* Database cleanup

Pick a schedule, write a prompt, and the agent handles the rest. View logs for every run.

= Event-Driven Automations =

The agent can react to things happening on your site in real time:

* A new post is published — auto-generate tags and a social media summary
* A user registers — send a personalized welcome sequence
* A WooCommerce order is placed — check inventory and notify your team
* A plugin is activated — run a compatibility check

20+ WordPress and WooCommerce triggers are included, with placeholder templates for dynamic data.

= Tool Profiles =

Control what the agent can access. Six built-in profiles (Read Only, Full Management, Content, Users, Maintenance, Developer) let you quickly scope permissions. Create your own custom profiles for specific use cases.

= Smart and Efficient =

* **Tool discovery** — On sites with many tools, the agent discovers what it needs instead of loading everything upfront. Saves tokens and money.
* **Conversation trimming** — Long conversations are automatically trimmed at safe boundaries to prevent context overflow.
* **Suggestion chips** — Clickable follow-up suggestions after each response keep the conversation flowing.
* **Usage tracking** — See exactly how many tokens each session uses and what it costs.

= Skills =

Create reusable instruction guides for the agent. Write a "content publishing checklist" or "image optimization workflow" once, and the agent follows it whenever the task comes up.

= Export and Import =

Export any conversation to JSON (for backup and reimport) or Markdown (for sharing and documentation). Import conversations from JSON backups.

= Extending with Ability Plugins =

The Gratis AI Agent discovers abilities at runtime from any plugin that registers them via `wp_register_ability()`. The more abilities installed, the more capable the agent.

**Recommended ability plugins:**

* **WP-CLI Abilities Bridge** — Exposes all WP-CLI commands as abilities. Gives the agent 500+ tools covering posts, users, plugins, themes, WooCommerce, and more.
* **Ultimate Multisite Core** — Registers abilities for multisite management: customers, memberships, sites, payments, products, domains, and email accounts.
* **Any plugin using the Abilities API** — Tools registered by any plugin are automatically available. No configuration needed.

== Installation ==

1. Upload the `gratis-ai-agent` folder to `/wp-content/plugins/` or install through the WordPress plugin screen.
2. Activate the plugin.
3. Go to **Settings > AI Credentials** and configure a connector for your AI provider (OpenAI, Anthropic, etc.). You will need an API key from your provider.
4. Visit **Tools > Gratis AI Agent Settings** to choose your default provider and model.
5. Open **Tools > Gratis AI Agent** and start chatting.

= Requirements =

* WordPress 6.9 or higher
* PHP 7.4 or higher
* An AI provider connector plugin registered through the WordPress Connectors API
* An API key from your chosen AI provider (OpenAI, Anthropic, etc.)

== Frequently Asked Questions ==

= Which AI providers are supported? =

Any provider that has a connector plugin for the WordPress AI Client SDK. This currently includes OpenAI (GPT-4o, GPT-4.1), Anthropic (Claude Opus 4, Sonnet 4, Haiku 4), and any OpenAI-compatible API (Ollama, Azure OpenAI, Groq, Together AI, etc.).

= How much does it cost to use? =

The plugin itself is free. You pay only for the API usage from your chosen provider at their published rates. There is no markup, subscription, or usage fee from Gratis AI Agent. The Usage tab in settings tracks your token consumption and estimated costs.

= Is my data sent to a third party? =

Your conversations go directly from your WordPress site to your configured AI provider. Nothing is routed through any intermediary server. The plugin stores conversation history, memories, and knowledge locally in your WordPress database.

= Can I use a local AI model? =

Yes. If you run a local model through Ollama or any OpenAI-compatible server, configure it as a provider through the WordPress Connectors API and Gratis AI Agent will use it. All inference happens on your hardware with zero API costs.

= What can the agent actually do? =

The agent can use any tool (ability) registered on your WordPress site. Out of the box this includes managing posts, pages, users, comments, media, site options, and more. With custom tools you can extend it to call external APIs, trigger WordPress hooks, or run WP-CLI commands. Any plugin that registers abilities through the WordPress Abilities API automatically makes those tools available to the agent.

= Is it safe? Will the AI break my site? =

The agent has a built-in confirmation system. Potentially destructive tool calls pause and ask for your approval before executing. You can configure each tool as "auto" (always allow), "confirm" (ask first), or "disabled" in the Abilities tab. Tool profiles let you restrict the agent to read-only access.

= Can I use this on a multisite network? =

Yes, the plugin works on both single-site and multisite WordPress installations. Each site has its own settings, sessions, memories, and automations.

== Screenshots ==

1. Full-page chat interface with session sidebar and folder organization
2. Floating widget available on every admin page
3. Custom Tools tab — create HTTP, ACTION, and CLI tools without code
4. Tool Profiles — restrict what the agent can access
5. Scheduled Automations with quick-start templates
6. Event-Driven Automations with WordPress and WooCommerce triggers
7. Knowledge Base management for RAG
8. Settings page with 12 configuration tabs

== Changelog ==

= 1.2.0 =
* New: Support all three official AI providers — OpenAI, Anthropic, and Google Gemini
* New: Image and file upload support in chat messages
* New: Spending limits and budget caps to control AI costs
* New: Live streaming token counter and cost display during responses
* New: Tiered model pricing display in the Settings model selector
* New: GPT-4.1 family models (GPT-4.1, GPT-4.1-mini, GPT-4.1-nano); GPT-4.1-nano is now the default OpenAI model
* New: Graceful fallback when tool calls exhaust max iterations
* New: Suggestion cards on chat empty state
* New: Search, category grouping, and collapsible sections in the Abilities settings tab
* New: Auto-title sessions from the first message using AI
* New: Shared conversations — multiple admins can view and continue the same session
* New: Text-to-speech for AI responses (optional)
* New: White-label branding support — custom agent name, colors, and logo
* New: Google Search Console SEO insights abilities
* New: Google Analytics 4 traffic analysis ability
* New: Resale API proxy endpoint with usage tracking
* New: YOLO mode toggle to skip all confirmations
* New: Abilities Explorer admin page
* New: AI image generation ability (DALL-E 3)
* New: Agent builder UI — create specialized agents with custom prompts, tools, and models
* New: Role-based AI permissions — restrict abilities by WordPress user role
* New: Floating widget shown on frontend for logged-in admins
* New: Resizable floating widget panel with keyboard shortcut
* New: WooCommerce abilities — product CRUD, order queries, and store stats
* New: Slack and Discord notification forwarding for automation results
* New: Sortable, filterable DataTable rendering for tabular chat responses
* New: Chart.js chart rendering in chat responses
* New: Per-tool WordPress capabilities (gratis_ai_agent_tool_{name})
* New: Site builder conversation flow — interview user then generate a full site
* New: Site builder mode triggered on fresh WordPress installs
* New: Changes Admin page — view diffs, revert changes, and export patches
* New: CodeMirror 6 syntax highlighting in chat code blocks
* New: Webhook API — trigger AI conversations from external systems
* New: Push-to-talk speech-to-text via browser Web Speech API
* New: MCP server — expose abilities as MCP tools for external AI clients
* New: Mobile slide-out drawer replacing stacked sidebar layout
* New: Visible scroll affordance for settings tabs on touch devices
* Enhancement: PHPStan level raised from 6 to 7
* Enhancement: Improved multi-step agentic workflows
* Enhancement: Configurable default model (replaces hardcoded fallback)
* Enhancement: Secrets and PII redacted in change log before/after values
* Enhancement: Stream error handling, timeout, and retry
* Enhancement: Object-level permission check in post editing
* Fix: Double /wp-admin/ URL in screen meta context
* Fix: Discovery mode confusion in ToolDiscovery
* Fix: Site builder overlay restricted to main AI Agent page
* Fix: Non-static permission callback on /stream route
* Fix: WP_Error return from get_the_terms() in WooCommerceAbilities
* Fix: ShellCheck violations across all shell scripts
* Fix: npm audit vulnerabilities (26 issues: 12 moderate, 14 high)

= 1.1.0 =
* New: Gutenberg block content generation — markdown-to-blocks converter, block discovery, and structured block creation
* New: 7 block abilities — markdown-to-blocks, list-block-types, get-block-type, list-block-patterns, list-block-templates, create-block-content, parse-block-content
* New: Stock image import ability for keyword-based image imports into the media library
* New: SEO abilities — URL auditing and content SEO analysis
* New: Content analysis and performance reporting abilities
* New: Marketing abilities — URL fetching and HTTP header analysis
* New: WP-CLI command (`wp gratis-ai-agent`) for running the agent from the command line
* New: Block editor context provider — reports block theme status, registered blocks, and pattern counts
* New: Content Creator tool profile — scoped set of block, media, and post management tools
* New: Gutenberg Blocks built-in skill (enabled by default)
* New: Full Site Editing built-in skill (opt-in)
* New: Weekly SEO Health Report and Monthly Content Performance Report automation templates
* Enhancement: Improved agent system prompt — action-oriented with common workflow guidance
* Enhancement: Priority tool loading — key WP-CLI tools (post/create, site/create, media/import, etc.) load directly without discovery
* Enhancement: Streamlined tool discovery prompt — less verbose, more actionable
* Enhancement: Max iterations default increased from 10 to 25
* Enhancement: Agent returns tool call log and token usage when max iterations is reached
* Enhancement: Empty input schema properties serialize as JSON objects instead of arrays for OpenAI compatibility

= 1.0.0 =
* Initial stable release
* Agentic chat with autonomous tool-calling loop
* Full-page admin panel and floating widget chat interfaces
* Session management with folders, search, export/import
* Persistent memory across sessions with auto-memory mode
* Skills system for reusable instruction guides
* Knowledge Base (RAG) with collections, document upload, and full-text search
* Custom Tools — create HTTP, ACTION, and CLI tools without code
* Tool Profiles — 6 built-in profiles to scope AI access
* Scheduled Automations with cron integration and execution logging
* Event-Driven Automations with 20+ WordPress and WooCommerce triggers
* Tool Discovery meta-tools for sites with many abilities
* Smart Conversation Trimming to prevent context overflow
* Suggestion Chips for contextual follow-ups
* Usage Dashboard with token counts and cost estimates
* Context Providers for page-aware AI responses
* WordPress 6.9 compatibility layer (bundles AI Client SDK)

== Upgrade Notice ==

= 1.2.0 =
Major feature release: adds Google/Anthropic provider support, image uploads, spending limits, white-label branding, agent builder, role-based permissions, WooCommerce abilities, MCP server, and many more features. Database will upgrade automatically.

= 1.1.0 =
Adds Gutenberg block content generation, SEO/content/marketing abilities, WP-CLI command, and improved agent behavior. Database will upgrade automatically.

= 1.0.0 =
Initial release. Requires WordPress 6.9+ and an AI provider connector plugin.
