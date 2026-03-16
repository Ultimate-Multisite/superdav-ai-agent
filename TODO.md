# Gratis AI Agent - Task Management

## Ready

- [x] t090 Rename plugin to "Gratis AI Agent" with slug "gratis-ai-agent" for WP.org guidelines @superdav42 #chore ~4h logged:2026-03-15 pr:#194 completed:2026-03-15
  - Rename main file, text domain, namespace, constants, REST namespace, DB tables, options, CSS classes, ability names, CLI command, build assets, config files, CI/docs
  - Add activation hook migration for existing installs (detect old option/table names and rename)

- [ ] t091 Shared conversations: multiple admins can view/continue same session @superdav42 #feature ~6h logged:2026-03-16 ref=GH#387

## Backlog

### Onboarding & First-Run Experience (P0)

- [ ] t060 Detect fresh WordPress install and trigger site builder mode @superdav42 #feature ~3h logged:2026-03-15
  - Check post count, theme, and whether any real content exists
  - Set `site_builder_mode` flag; floating widget opens automatically in expanded mode
- [x] t061 Site builder conversation flow: interview user then generate full site @superdav42 #feature ~12h logged:2026-03-15 blocked-by:t060 pr:#455 completed:2026-03-16
  - System prompt interviews user: business name, type, goals, pages needed
  - Agent creates pages via BlockAbilities, content via ContentAbilities, images via StockImageAbilities
  - Agent sets up nav menu, site title, tagline, basic SEO
  - Target: 5-page site from prompt in < 3 minutes
- [x] t062 Full-screen site builder widget mode for fresh installs @superdav42 #ui ~6h logged:2026-03-15 blocked-by:t060 pr:#452 completed:2026-03-16
  - Centered full-screen overlay instead of FAB; progress indicators; "Skip" option
- [x] t063 Smart onboarding: agent scans existing site on first activation @superdav42 #feature ~8h logged:2026-03-15 pr:#453 completed:2026-03-16
  - Background wp-cron scan: plugins, theme, post types, post count, categories, WooCommerce status
  - Store results as memories; detect site type; index first 50 posts into knowledge base
- [x] t064 Onboarding interview: ask user about site goals after scan @superdav42 #feature ~4h logged:2026-03-15 blocked-by:t063 pr:#457 completed:2026-03-16
  - Agent asks targeted questions based on scan results
  - Store answers as memories; suggest relevant automations
- [ ] t065 Integrate provider setup into onboarding conversation @superdav42 #ui ~4h logged:2026-03-15
  - Replace 4-step wizard with conversational flow; agent guides provider setup in chat

### Floating Widget & Frontend (P0)

- [x] t066 Show floating widget on frontend for logged-in admins @superdav42 #feature ~3h logged:2026-03-15 pr:#458 completed:2026-03-16
  - Register `wp_enqueue_scripts` hook with capability check
  - Frontend page context: current URL, post ID, template, query vars
  - Setting to enable/disable (default: enabled for admins)
- [ ] t067 Improve floating widget UX: resizable panel, keyboard shortcut @superdav42 #ui ~4h logged:2026-03-15
  - Resize handle, Cmd/Ctrl+Shift+A toggle, localStorage persistence, mobile responsiveness

### Streaming Responses (P0)

- [x] t054 Add streaming response support via SSE @superdav42 #feature ~8h logged:2026-03-15 pr:#268 completed:2026-03-15
  - Custom REST endpoint with `Content-Type: text/event-stream`, output buffer bypass
  - Frontend EventSource API with reconnection; typing indicator; stop button
  - Fall back to current polling pattern if SSE fails

### AI Image Generation & Selection (P1)

- [x] t068 Add AI image generation ability (DALL-E / Stable Diffusion) @superdav42 #feature ~6h logged:2026-03-15 pr:#459 completed:2026-03-16
  - New ability: `generate-image` from text prompt; upload to media library; return attachment ID
- [x] t069 Enhance StockImageAbilities: auto-select images during content creation @superdav42 #feature ~3h logged:2026-03-15 pr:#449 completed:2026-03-16
  - Auto-attach featured image when creating posts/pages; search by style/mood

### WooCommerce Integration (P1)

- [x] t070 WooCommerce abilities: product CRUD, order queries, store stats @superdav42 #feature ~8h logged:2026-03-15 pr:#450 completed:2026-03-16
  - Conditionally register on WooCommerce detection
  - create-product, list-products, get-orders, store-stats, update-product via Woo REST API
- [x] t071 WooCommerce onboarding: detect store and offer AI product creation @superdav42 #feature ~3h logged:2026-03-15 blocked-by:t063,t070 pr:#443 completed:2026-03-16

### Rich Output & Artifacts (P1)

- [x] t072 Render data tables in chat responses (sortable, filterable) @superdav42 #ui ~6h logged:2026-03-15 pr:#439 completed:2026-03-16
- [x] t073 Render charts in chat responses (Chart.js) @superdav42 #ui ~4h logged:2026-03-15 pr:#440 pr:#440 completed:2026-03-16
- [x] t074 Action cards for confirmable operations @superdav42 #ui ~4h logged:2026-03-15 pr:#277 completed:2026-03-16

### Code Quality & Dev Environment (P1)

- [x] t006 Fix npm audit vulnerabilities (21 issues: 7 moderate, 14 high) @superdav42 #security ~2h logged:2026-03-14 pr:#262 completed:2026-03-15
- [x] t007 Add pre-commit hooks via husky + lint-staged for PHP/JS/CSS @superdav42 #quality ~2h logged:2026-03-14 pr:#84 completed:2026-03-15
- [x] t008 Make CI workflows non-continue-on-error @superdav42 #ci ~1h logged:2026-03-14 pr:#83 completed:2026-03-15
- [x] t009 Tighten PHPCS: re-enable EscapeOutput, add nonce verification rules @superdav42 #quality ~4h logged:2026-03-14 pr:#87 completed:2026-03-15
- [x] t010 Raise PHPStan level from 5 to 6, fix new errors @superdav42 #quality ~4h logged:2026-03-14 pr:#266 completed:2026-03-15
- [x] t011 Add PHPDoc blocks to all classes/methods @superdav42 #quality ~8h logged:2026-03-14 pr:#89 completed:2026-03-15

### Testing Infrastructure (P1)

- [x] t012 Set up wp-env based test runner for local and CI @superdav42 #testing ~4h logged:2026-03-14 pr:#88 completed:2026-03-15
- [x] t013 Add integration tests for all Abilities classes @superdav42 #testing ~8h logged:2026-03-14 blocked-by:t012 pr:#276 completed:2026-03-15
- [x] t014 Add integration tests for AgentLoop (mock AI responses) @superdav42 #testing ~6h logged:2026-03-14 blocked-by:t012 pr:#274 completed:2026-03-15
- [x] t015 Add integration tests for RestController endpoints @superdav42 #testing ~4h logged:2026-03-14 blocked-by:t012 pr:#275 completed:2026-03-15
- [x] t016 Add integration tests for Database schema and migrations @superdav42 #testing ~3h logged:2026-03-14 blocked-by:t012 pr:#273 completed:2026-03-15
- [x] t017 Add integration tests for Automations system @superdav42 #testing ~4h logged:2026-03-14 blocked-by:t012 pr:#270 completed:2026-03-15
- [x] t018 Add JS unit tests with @wordpress/scripts test-unit-js @superdav42 #testing ~6h logged:2026-03-14 pr:#272 completed:2026-03-15
- [x] t019 Add code coverage reporting to CI @superdav42 #ci ~2h logged:2026-03-14 blocked-by:t012 pr:#264 completed:2026-03-15
- [ ] t020 Add E2E tests with wp-env + Playwright for chat UI @superdav42 #testing ~8h logged:2026-03-14 blocked-by:t012

### Ability Quality Improvements (P1)

- [x] t029 Standardize error handling: return WP_Error not arrays with 'error' key @superdav42 #refactor ~4h logged:2026-03-14 pr:#269 completed:2026-03-15
- [ ] t030 Add output_schema to all abilities @superdav42 #quality ~4h logged:2026-03-14
- [x] t031 Add meta.annotations (readonly, destructive, idempotent) to all abilities @superdav42 #quality ~3h logged:2026-03-14 pr:#438 completed:2026-03-16
- [x] t032 Add meta.show_in_rest = true where appropriate @superdav42 #quality ~2h logged:2026-03-14 pr:#298 completed:2026-03-15

### Git Change Tracking & Undo (P1)

- [x] t033 Port GitTracker class: store original files as git blobs, track changes @superdav42 #feature ~6h logged:2026-03-14 ref=GH#431 pr:#442 verified:2026-03-16
- [x] t034 Port GitTrackerManager: manage trackers across plugins/themes @superdav42 #feature ~3h logged:2026-03-14 ref=GH#432 pr:#451 verified:2026-03-16
- [ ] t035 Add Changes Admin page: view diffs, revert changes, export patches @superdav42 #feature ~8h logged:2026-03-14 blocked-by:t033,t034 ref=GH#433 pr:#447
- [ ] t036 Add plugin download links for AI-modified plugins @superdav42 #feature ~2h logged:2026-03-14 ref=GH#434 pr:#441

### Tool Permission System (P1)

- [x] t037 Add per-tool WordPress capabilities (gratis_ai_agent_tool_{name}) @superdav42 #feature ~3h logged:2026-03-14 ref=GH#435 pr:#448 pr:#448 completed:2026-03-16
- [x] t038 Add YOLO mode toggle (skip all confirmations) @superdav42 #feature ~1h logged:2026-03-14 pr:#444 completed:2026-03-16

### AI SDK Alignment (P1)

- [x] t041 Update compat layer to match WordPress/ai Abstract_Ability pattern @superdav42 #refactor ~4h logged:2026-03-14 pr:#405 completed:2026-03-15
- [ ] t042 Port abilities from WordPress/ai experiments plugin @superdav42 #feature ~6h logged:2026-03-14 blocked-by:t041
- [ ] t043 Add Abilities Explorer admin page @superdav42 #feature ~6h logged:2026-03-14
- [x] t044 Support all three official AI providers @superdav42 #feature ~3h logged:2026-03-14 pr:#271 ref=GH#236 completed:2026-03-15

### White-Label & Resale (P2)

- [ ] t075 White-label support: custom branding, colors, greeting, agent name @superdav42 #feature ~6h logged:2026-03-15
- [ ] t076 Resale API: proxy endpoint for managed AI with usage tracking @superdav42 #feature ~8h logged:2026-03-15

### Architecture & Modernization (P2)

- [x] t046 Extract send_prompt_direct() to dedicated OpenAI proxy class @superdav42 #refactor ~4h logged:2026-03-14 ref=GH#239 pr:#281 pr:#281 completed:2026-03-15
- [x] t047 Extract credential management to CredentialResolver class @superdav42 #refactor ~3h logged:2026-03-14 pr:#280 completed:2026-03-15
- [x] t048 Replace hardcoded model fallback with configurable default @superdav42 #refactor ~1h logged:2026-03-14 pr:#278 completed:2026-03-15
- [x] t049 Add proper dependency injection instead of static method calls @superdav42 #refactor ~4h logged:2026-03-14 pr:#344 ref=GH#320 pr:#344 completed:2026-03-15
- [x] t050 Add event/hook system for ability execution (before/after hooks) @superdav42 #refactor ~3h logged:2026-03-14 pr:#337 completed:2026-03-15

### Frontend/UI Improvements (P2)

- [ ] t051 Add screen-meta integration (chat in WP admin Help/Screen Options) @superdav42 #ui ~4h logged:2026-03-14
- [ ] t053 Add CodeMirror integration for code display in chat @superdav42 #ui ~2h logged:2026-03-14
- [x] t055 Add proper error boundaries in React components @superdav42 #quality ~3h logged:2026-03-14 pr:#279 completed:2026-03-15
- [x] t056 Add TypeScript types or JSDoc to JS codebase @superdav42 #quality ~8h logged:2026-03-14 pr:#341 completed:2026-03-15

### Multi-User & Collaboration (P2)

- [ ] t077 Shared conversations: multiple admins can view/continue same session @superdav42 #feature ~6h logged:2026-03-15
- [ ] t078 Role-based AI permissions: restrict abilities by WordPress user role @superdav42 #feature ~4h logged:2026-03-15
- [x] t079 Conversation templates: pre-built prompts for common tasks @superdav42 #feature ~3h logged:2026-03-15 ref=GH#389 pr:#413 pr:#413 completed:2026-03-16

### Proactive Site Intelligence (P2)

- [x] t080 Daily site health automation: check plugins, errors, performance, security @superdav42 #feature ~6h logged:2026-03-15 pr:#411 completed:2026-03-16
- [x] t081 Proactive alerts: surface issues as notification badge on FAB @superdav42 #feature ~4h logged:2026-03-15 pr:#410 completed:2026-03-16

### Custom Agent Builder (P2)

- [ ] t082 Agent builder UI: specialized agents with custom prompts, tools, models @superdav42 #feature ~8h logged:2026-03-15 ref=GH#392 pr:#437

### Voice Interface (P3)

- [x] t083 Push-to-talk: speech-to-text via browser Web Speech API @superdav42 #feature ~4h logged:2026-03-15 pr:#408 completed:2026-03-16
- [ ] t084 Text-to-speech for AI responses (optional) @superdav42 #feature ~3h logged:2026-03-15

### Integration Hub (P3)

- [ ] t085 Google Analytics integration: traffic analysis ability @superdav42 #feature ~4h logged:2026-03-15
- [ ] t086 Google Search Console integration: SEO insights ability @superdav42 #feature ~4h logged:2026-03-15
- [x] t087 Slack/Discord notification forwarding for automation results @superdav42 #feature ~3h logged:2026-03-15 pr:#412 completed:2026-03-16
- [x] t088 Webhook API: trigger AI conversations from external systems @superdav42 #feature ~4h logged:2026-03-15 pr:#409 completed:2026-03-16
- [x] t089 MCP server: expose abilities as MCP tools for external AI clients @superdav42 #feature ~6h logged:2026-03-15 pr:#404 completed:2026-03-15

### Documentation & Packaging (P2)

- [x] t057 Add CONTRIBUTING.md with dev setup, testing, and PR guidelines @superdav42 #docs ~1h logged:2026-03-14 pr:#167 completed:2026-03-15
- [x] t058 Add WordPress Playground blueprint for instant demo @superdav42 #devops ~2h logged:2026-03-14 pr:#403 ref=GH#400
- [x] t059 Update .distignore for clean plugin packaging @superdav42 #devops ~1h logged:2026-03-14 pr:#168 completed:2026-03-15

## Done

- [x] t000 Install dependencies and verify code quality baseline verified:2026-03-14 completed:2026-03-14
- [x] t001 Set up dev environment: composer install, npm ci, wp-env config verified:2026-03-14 completed:2026-03-14
- [x] t002 php-ai-client already at ^1.3 (latest) verified:2026-03-14 completed:2026-03-14
- [x] t003 .wp-env.json created with WP 6.9, PHP 8.2, multisite, debug config verified:2026-03-14 completed:2026-03-14
- [x] t021 FileAbilities: 7 abilities (read/write/edit/delete/list/search-files/search-content) verified:2026-03-14 completed:2026-03-14
- [x] t023 DatabaseAbilities: db_query with SELECT-only guard and {prefix} placeholder verified:2026-03-14 completed:2026-03-14
- [x] t024 WordPressAbilities: get_plugins, get_themes, install_plugin, run_php verified:2026-03-14 completed:2026-03-14
- [x] t026 NavigationAbilities: navigate (URL validation), get_page_html (CSS selector) verified:2026-03-14 completed:2026-03-14
- [x] t028 AbilityDiscoveryAbilities: 3 meta-tools (discovery-list, discovery-get, discovery-execute) verified:2026-03-14 completed:2026-03-14
- [x] t045 SimpleAiResult extracted to own file, PHPDoc reference fixed verified:2026-03-14 completed:2026-03-14

## Declined
