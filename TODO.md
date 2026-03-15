# AI Agent - Task Management

## Active

- [ ] t006 Fix npm audit vulnerabilities (21 issues: 7 moderate, 14 high) @dave #security ~2h
- [ ] t007 Add pre-commit hooks via husky + lint-staged for PHP/JS/CSS @dave #quality ~2h

## Backlog

### Onboarding & First-Run Experience (P0)

Every competitor (10Web, GoDaddy Airo, ZipWP) leads with "describe your site, get a site." Our
onboarding wizard currently only asks for provider + ability toggles. We need two distinct flows:

#### New Site: AI Site Builder Flow

When WordPress is freshly installed (few/no posts, default theme), the floating widget should be
front and center -- not a small FAB in the corner. It should greet the user and ask what kind of
site they want to build, then orchestrate full site creation using existing abilities (BlockAbilities,
ContentAbilities, StockImageAbilities, SeoAbilities).

- [ ] t060 Detect fresh WordPress install (< 3 posts, default theme) and trigger site builder mode @dave #feature ~3h
  - Check post count, theme, and whether any real content exists
  - Set a flag like `site_builder_mode` in settings
  - When active, floating widget opens automatically in expanded mode on first admin page load
- [ ] t061 Site builder conversation flow: ask business type, name, goals, then generate full site @dave #feature ~12h
  - System prompt instructs agent to interview the user: business name, type, goals, pages needed
  - Agent plans site structure (homepage, about, services/products, contact, blog)
  - Agent generates content for each page via ContentAbilities
  - Agent creates pages with Gutenberg blocks via BlockAbilities (markdown-to-blocks + create-block-content)
  - Agent sets up navigation menu, site title, tagline via WordPress options
  - Agent selects/imports relevant images via StockImageAbilities
  - Agent configures basic SEO via SeoAbilities
  - Progress shown in chat as each page is created
  - Target: 5-page site from prompt in < 3 minutes
- [ ] t062 Full-screen site builder widget mode for fresh installs @dave #ui ~6h
  - When site_builder_mode is active, render the chat as a centered full-screen overlay instead of FAB
  - Show site creation progress with visual indicators (pages created, images added, etc.)
  - After site is built, transition to normal floating widget mode
  - "Skip" option to go straight to normal WordPress admin

#### Existing Site: Smart Onboarding Flow

When the plugin is installed on an existing site with content, the agent should learn about the
site before asking the user to start chatting. This replaces the current basic wizard (provider +
abilities toggles).

- [ ] t063 Smart onboarding: agent scans existing site on first activation @dave #feature ~8h
  - Auto-run site scan on activation: installed plugins, active theme, post types, post count, categories, users, WooCommerce status
  - Store scan results as memories via MemoryAbilities (site info category)
  - Detect site type: blog, ecommerce, portfolio, business, membership, etc.
  - Index existing content into knowledge base via KnowledgeAbilities (first 50 posts/pages)
  - All of this happens in background via wp-cron, not blocking the wizard
- [ ] t064 Onboarding interview: ask user about site goals and context after scan @dave #feature ~4h
  - After scan completes, agent has context and asks targeted questions:
    "I see you're running a WooCommerce store with 45 products. What are your main goals?"
    "You have 120 blog posts -- do you want me to help with content strategy?"
    "I noticed you don't have an SEO plugin -- want me to help with that?"
  - Store answers as memories (user preferences, workflows, goals)
  - Suggest relevant automations based on site type (e.g., daily health check, content moderation)
- [ ] t065 Integrate provider setup into onboarding conversation instead of separate wizard steps @dave #ui ~4h
  - Replace the current 4-step wizard (welcome → provider → abilities → done) with a conversational flow
  - Agent detects if no provider is configured and guides user through setup in chat
  - Keep ability toggles in settings page, not in onboarding (too technical for first-run)

### Floating Widget & Frontend (P0)

The floating widget already works on all admin pages. It needs to also work on the frontend for
logged-in admins, and the widget itself needs UX improvements.

- [ ] t066 Show floating widget on frontend for logged-in admins @dave #feature ~3h
  - Register `wp_enqueue_scripts` hook (not just `admin_enqueue_scripts`) with `is_user_logged_in()` + capability check
  - Ensure REST API endpoints work from frontend context (nonce handling)
  - Add setting to enable/disable frontend widget (default: enabled for admins)
  - Page context on frontend: current URL, post ID if singular, template, query vars
- [ ] t067 Improve floating widget UX: resizable panel, keyboard shortcut to open @dave #ui ~4h
  - Add resize handle to panel (currently fixed size)
  - Cmd/Ctrl+Shift+A keyboard shortcut to toggle widget open/closed
  - Remember panel size and position in localStorage
  - Improve mobile/tablet responsiveness

### Streaming Responses (P0)

Every competitor streams responses. Our async polling pattern works but feels slow -- users see a
spinner for 10-30 seconds with no feedback. SSE is possible via a custom REST endpoint that
bypasses the standard WP REST response cycle (UpdraftPlus, WP Rocket, and ai-assistant all do this).

- [ ] t054 Add streaming response support via SSE @dave #feature ~8h
  - Custom REST endpoint that sets `Content-Type: text/event-stream`, disables output buffering
  - Stream token-by-token as they arrive from the AI provider
  - Fall back to current polling pattern if SSE connection fails
  - Frontend: EventSource API with reconnection logic
  - Show typing indicator with partial text as it streams
  - Stop generation button during streaming

### AI Image Generation & Selection (P1)

10Web offers unlimited AI images. Divi AI has unlimited generation + editing. We have
StockImageAbilities (Unsplash/Pexels search + import) but no AI generation.

- [ ] t068 Add AI image generation ability (DALL-E / Stable Diffusion) @dave #feature ~6h
  - New ability: `ai-agent/generate-image` -- generate image from text prompt
  - Use OpenAI DALL-E API (same API key as chat provider) or configurable endpoint
  - Upload generated image to WordPress media library automatically
  - Return attachment ID and URL for use in content creation
  - Integrate into site builder flow: agent generates hero images, feature images, etc.
- [ ] t069 Enhance StockImageAbilities: auto-select images during content creation @dave #feature ~3h
  - When agent creates a blog post or page, automatically search and attach a featured image
  - Use post title/content as search query for stock image APIs
  - Add ability to search by style/mood, not just keywords

### WooCommerce Integration (P1)

WooCommerce powers 28% of online stores. 10Web and GoDaddy both have ecommerce AI. We should
detect WooCommerce and register additional abilities.

- [ ] t070 WooCommerce abilities: product CRUD, order queries, store stats @dave #feature ~8h
  - Detect WooCommerce on activation, conditionally register abilities
  - `woo/create-product` -- create products from description (title, price, description, categories, images)
  - `woo/list-products` -- search/filter products
  - `woo/get-orders` -- query recent orders, filter by status/date/customer
  - `woo/store-stats` -- revenue, order count, top products, conversion rate
  - `woo/update-product` -- modify existing products
  - Use WooCommerce REST API internally (not direct DB queries)
- [ ] t071 WooCommerce onboarding: detect store and offer AI product creation @dave #feature ~3h
  - During smart onboarding (t063), detect WooCommerce and product count
  - If store has few products, offer: "Want me to help create product listings?"
  - If store has products, offer analytics: "Your top seller is X with Y orders this month"

### Rich Output & Artifacts (P1)

Claude.ai has artifacts, OpenWebUI has interactive artifacts. We render plain markdown only.

- [ ] t072 Render data tables in chat responses (sortable, filterable) @dave #ui ~6h
  - Detect markdown tables or structured data in AI responses
  - Render as interactive React table component (sort columns, filter rows)
  - Export to CSV button
- [ ] t073 Render charts in chat responses (Chart.js) @dave #ui ~4h
  - AI can return chart data in a structured format (JSON with type, labels, datasets)
  - Render as Chart.js component inline in chat
  - Support: bar, line, pie, doughnut charts
  - Use case: "Show me my traffic this month" → renders a chart
- [ ] t074 Action cards for confirmable operations @dave #ui ~4h
  - When agent proposes a destructive or multi-step action, render as a card with:
    summary of what will happen, affected items, confirm/reject buttons
  - Replace current basic confirmation dialog with richer preview

### Code Quality & Dev Environment (P1)

- [ ] t008 Make CI workflows non-continue-on-error (ESLint/Stylelint currently soft-fail) @dave #ci ~1h
- [ ] t009 Tighten PHPCS: re-enable EscapeOutput, add nonce verification rules @dave #quality ~4h
- [ ] t010 Raise PHPStan level from 5 to 6, fix new errors @dave #quality ~4h
- [ ] t011 Add PHPDoc blocks to all classes/methods (re-enable Squiz.Commenting rules) @dave #quality ~8h

### Testing Infrastructure (P1)

- [ ] t012 Set up wp-env based test runner that works locally and in CI @dave #testing ~4h
- [ ] t013 Add integration tests for all Abilities classes @dave #testing ~8h
- [ ] t014 Add integration tests for AgentLoop (mock AI responses) @dave #testing ~6h
- [ ] t015 Add integration tests for RestController endpoints @dave #testing ~4h
- [ ] t016 Add integration tests for Database schema and migrations @dave #testing ~3h
- [ ] t017 Add integration tests for Automations system @dave #testing ~4h
- [ ] t018 Add JS unit tests with @wordpress/scripts test-unit-js @dave #testing ~6h
- [ ] t019 Add code coverage reporting to CI (Codecov or similar) @dave #ci ~2h
- [ ] t020 Add E2E tests with wp-env + Playwright for chat UI @dave #testing ~8h

### Ability Quality Improvements (P1)

- [ ] t029 Standardize error handling: return WP_Error not arrays with 'error' key @dave #refactor ~4h
- [ ] t030 Add output_schema to all abilities (currently missing on most) @dave #quality ~4h
- [ ] t031 Add meta.annotations (readonly, destructive, idempotent) to all abilities @dave #quality ~3h
- [ ] t032 Add meta.show_in_rest = true where appropriate @dave #quality ~2h

### Git Change Tracking & Undo (P1)

All AI file modifications tracked in git structure within wp-content. Enables undo/revert for
any change the agent makes.

- [ ] t033 Port GitTracker class: store original files as git blobs, track changes @dave #feature ~6h
  - Per-plugin/theme .git directories
  - main branch = original, ai-changes branch = modifications
  - Commit messages from AI reasoning + conversation_id
- [ ] t034 Port GitTrackerManager: manage trackers across plugins/themes @dave #feature ~3h
- [ ] t035 Add Changes Admin page: view diffs, revert changes, export patches @dave #feature ~8h
- [ ] t036 Add plugin download links for AI-modified plugins @dave #feature ~2h

### Tool Permission System (P1)

- [ ] t037 Add per-tool WordPress capabilities (ai_agent_tool_{name}) @dave #feature ~3h
  - Map to Settings page UI for enabling/disabling tools
  - Three levels: auto, confirm, disabled (already partially in AgentLoop)
  - map_meta_cap filter for custom capability checks
- [ ] t038 Add YOLO mode toggle (skip all confirmations) @dave #feature ~1h

### AI SDK Alignment (P1)

Align with latest WordPress/ai plugin (v0.5.0) and php-ai-client (v1.3.0).

- [ ] t041 Update compat layer to match WordPress/ai Abstract_Ability pattern @dave #refactor ~4h
- [ ] t042 Port abilities from WordPress/ai experiments plugin @dave #abilities ~6h
  - Excerpt_Generation, Alt_Text_Generation, Image generation
  - Review_Notes, Summarization, Title_Generation
  - Posts utility ability
- [ ] t043 Add Abilities Explorer admin page (from WordPress/ai) @dave #feature ~6h
  - List all registered abilities with details
  - Test/execute abilities from admin UI
  - Show input/output schemas
- [ ] t044 Support all three official AI providers @dave #feature ~3h
  - wordpress/anthropic-ai-provider
  - wordpress/google-ai-provider
  - wordpress/openai-ai-provider

### White-Label & Resale (P2)

10Web and ZipWP both offer white-label + API for hosting providers and agencies. This opens the
B2B market.

- [ ] t075 White-label support: custom branding, colors, greeting, agent name @dave #feature ~6h
  - Filter-based branding: `ai_agent_branding` filter for name, logo URL, accent color
  - Settings UI for branding customization
  - Remove "AI Agent" branding when white-label is configured
- [ ] t076 Resale API: proxy endpoint for managed AI with usage tracking @dave #feature ~8h
  - Endpoint that accepts API calls from sub-sites, proxies to AI provider
  - Per-site usage tracking and rate limiting
  - Admin dashboard showing usage across all sub-sites
  - Configurable model and token limits per site/plan

### Architecture & Modernization (P2)

- [x] t045 Extract Simple_AI_Result from AgentLoop.php into own file completed:2026-03-14
- [ ] t046 Extract send_prompt_direct() to dedicated OpenAI proxy class @dave #refactor ~4h
- [ ] t047 Extract credential management to CredentialResolver class @dave #refactor ~3h
- [ ] t048 Replace hardcoded model fallback 'claude-sonnet-4' with configurable default @dave #refactor ~1h
- [ ] t049 Add proper dependency injection instead of static method calls @dave #refactor ~4h
- [ ] t050 Add event/hook system for ability execution (before/after hooks) @dave #refactor ~3h

### Frontend/UI Improvements (P2)

- [ ] t051 Add screen-meta integration (chat in WP admin Help/Screen Options area) @dave #ui ~4h
- [ ] t053 Add CodeMirror integration for code display in chat @dave #ui ~2h
- [ ] t055 Add proper error boundaries in React components @dave #quality ~3h
- [ ] t056 Add TypeScript types or JSDoc to JS codebase @dave #quality ~8h

### Multi-User & Collaboration (P2)

- [ ] t077 Shared conversations: multiple admins can view/continue the same session @dave #feature ~6h
- [ ] t078 Role-based AI permissions: restrict abilities by WordPress user role @dave #feature ~4h
- [ ] t079 Conversation templates: pre-built prompts for common tasks @dave #feature ~3h

### Proactive Site Intelligence (P2)

No competitor has an AI that lives inside WordPress with access to error logs, database, plugins,
and cron. This is our unfair advantage.

- [ ] t080 Daily site health automation: agent checks plugins, errors, performance, security @dave #feature ~6h
  - Pre-built automation template that runs daily via wp-cron
  - Checks: outdated plugins, PHP errors in log, failed cron jobs, disk usage, SSL expiry
  - Stores report as a conversation viewable in chat history
  - Optional email summary to admin
- [ ] t081 Proactive alerts: agent notices issues and surfaces them in chat @dave #feature ~4h
  - On widget open, check for pending alerts (failed payments, plugin updates, errors)
  - Show as a notification badge on the FAB button
  - Agent mentions them naturally: "By the way, 3 plugins have updates available"

### Custom Agent Builder (P2)

- [ ] t082 Agent builder UI: create specialized agents with custom system prompts, tools, models @dave #feature ~8h
  - Admin page to create named agents (Support Agent, Content Agent, DevOps Agent)
  - Each agent has: name, avatar, system prompt, allowed abilities, preferred model
  - Agent selector in chat to switch between agents
  - Per-agent knowledge base scoping

### Voice Interface (P3)

- [ ] t083 Push-to-talk: speech-to-text via browser Web Speech API @dave #feature ~4h
- [ ] t084 Text-to-speech for AI responses (optional, via Web Speech API) @dave #feature ~3h

### Integration Hub (P3)

- [ ] t085 Google Analytics integration: traffic analysis ability @dave #feature ~4h
- [ ] t086 Google Search Console integration: SEO insights ability @dave #feature ~4h
- [ ] t087 Slack/Discord notification forwarding for automation results @dave #feature ~3h
- [ ] t088 Webhook API: trigger AI conversations from external systems @dave #feature ~4h
- [ ] t089 MCP server: expose AI Agent abilities as MCP tools for external AI clients @dave #feature ~6h

### Documentation & Packaging (P2)

- [ ] t057 Add CONTRIBUTING.md with dev setup, testing, and PR guidelines @dave #docs ~1h
- [ ] t058 Add WordPress Playground blueprint for instant demo @dave #devops ~2h
- [ ] t059 Update .distignore for clean plugin packaging @dave #devops ~1h

## Completed

- [x] t000 Install dependencies and verify code quality baseline verified:2026-03-14
  - PHPCS: 0 errors, 0 warnings (12 files scanned)
  - PHPStan level 5: 0 errors
  - JS build: compiles (3 webpack performance warnings only)
- [x] t001 Set up dev environment: composer install, npm ci, wp-env config verified:2026-03-14
- [x] t002 php-ai-client already at ^1.3 (latest) verified:2026-03-14
- [x] t003 .wp-env.json created with WP 6.9, PHP 8.2, multisite, debug config verified:2026-03-14
- [x] t021 FileAbilities: 7 abilities (read/write/edit/delete/list/search-files/search-content) verified:2026-03-14
- [x] t023 DatabaseAbilities: db_query with SELECT-only guard and {prefix} placeholder verified:2026-03-14
- [x] t024 WordPressAbilities: get_plugins, get_themes, install_plugin, run_php verified:2026-03-14
- [x] t026 NavigationAbilities: navigate (URL validation), get_page_html (CSS selector) verified:2026-03-14
- [x] t028 AbilityDiscoveryAbilities: 3 meta-tools (discovery-list, discovery-get, discovery-execute) verified:2026-03-14
- [x] t045 SimpleAiResult extracted to own file, PHPDoc reference fixed verified:2026-03-14
