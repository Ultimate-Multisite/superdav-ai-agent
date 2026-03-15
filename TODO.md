# Gratis AI Agent - Task Management

## Ready

- [x] t090 Rename plugin to "Gratis AI Agent" with slug "gratis-ai-agent" for WP.org guidelines @dave #chore ~4h logged:2026-03-15 pr:#194 completed:2026-03-15
  - Rename main file, text domain, namespace, constants, REST namespace, DB tables, options, CSS classes, ability names, CLI command, build assets, config files, CI/docs
  - Add activation hook migration for existing installs (detect old option/table names and rename)

## Backlog

### Onboarding & First-Run Experience (P0)

- [ ] t060 Detect fresh WordPress install and trigger site builder mode @dave #feature ~3h logged:2026-03-15
  - Check post count, theme, and whether any real content exists
  - Set `site_builder_mode` flag; floating widget opens automatically in expanded mode
- [ ] t061 Site builder conversation flow: interview user then generate full site @dave #feature ~12h logged:2026-03-15 blocked-by:t060
  - System prompt interviews user: business name, type, goals, pages needed
  - Agent creates pages via BlockAbilities, content via ContentAbilities, images via StockImageAbilities
  - Agent sets up nav menu, site title, tagline, basic SEO
  - Target: 5-page site from prompt in < 3 minutes
- [ ] t062 Full-screen site builder widget mode for fresh installs @dave #ui ~6h logged:2026-03-15 blocked-by:t060
  - Centered full-screen overlay instead of FAB; progress indicators; "Skip" option
- [ ] t063 Smart onboarding: agent scans existing site on first activation @dave #feature ~8h logged:2026-03-15
  - Background wp-cron scan: plugins, theme, post types, post count, categories, WooCommerce status
  - Store results as memories; detect site type; index first 50 posts into knowledge base
- [ ] t064 Onboarding interview: ask user about site goals after scan @dave #feature ~4h logged:2026-03-15 blocked-by:t063
  - Agent asks targeted questions based on scan results
  - Store answers as memories; suggest relevant automations
- [ ] t065 Integrate provider setup into onboarding conversation @dave #ui ~4h logged:2026-03-15
  - Replace 4-step wizard with conversational flow; agent guides provider setup in chat

### Floating Widget & Frontend (P0)

- [ ] t066 Show floating widget on frontend for logged-in admins @dave #feature ~3h logged:2026-03-15
  - Register `wp_enqueue_scripts` hook with capability check
  - Frontend page context: current URL, post ID, template, query vars
  - Setting to enable/disable (default: enabled for admins)
- [ ] t067 Improve floating widget UX: resizable panel, keyboard shortcut @dave #ui ~4h logged:2026-03-15
  - Resize handle, Cmd/Ctrl+Shift+A toggle, localStorage persistence, mobile responsiveness

### Streaming Responses (P0)

- [ ] t054 Add streaming response support via SSE @dave #feature ~8h logged:2026-03-15
  - Custom REST endpoint with `Content-Type: text/event-stream`, output buffer bypass
  - Frontend EventSource API with reconnection; typing indicator; stop button
  - Fall back to current polling pattern if SSE fails

### AI Image Generation & Selection (P1)

- [ ] t068 Add AI image generation ability (DALL-E / Stable Diffusion) @dave #feature ~6h logged:2026-03-15
  - New ability: `generate-image` from text prompt; upload to media library; return attachment ID
- [ ] t069 Enhance StockImageAbilities: auto-select images during content creation @dave #feature ~3h logged:2026-03-15
  - Auto-attach featured image when creating posts/pages; search by style/mood

### WooCommerce Integration (P1)

- [ ] t070 WooCommerce abilities: product CRUD, order queries, store stats @dave #feature ~8h logged:2026-03-15
  - Conditionally register on WooCommerce detection
  - create-product, list-products, get-orders, store-stats, update-product via Woo REST API
- [ ] t071 WooCommerce onboarding: detect store and offer AI product creation @dave #feature ~3h logged:2026-03-15 blocked-by:t063,t070

### Rich Output & Artifacts (P1)

- [ ] t072 Render data tables in chat responses (sortable, filterable) @dave #ui ~6h logged:2026-03-15
- [ ] t073 Render charts in chat responses (Chart.js) @dave #ui ~4h logged:2026-03-15
- [ ] t074 Action cards for confirmable operations @dave #ui ~4h logged:2026-03-15

### Code Quality & Dev Environment (P1)

- [x] t006 Fix npm audit vulnerabilities (21 issues: 7 moderate, 14 high) @dave #security ~2h logged:2026-03-14 pr:#262 completed:2026-03-15
- [x] t007 Add pre-commit hooks via husky + lint-staged for PHP/JS/CSS @dave #quality ~2h logged:2026-03-14 pr:#84 completed:2026-03-15
- [x] t008 Make CI workflows non-continue-on-error @dave #ci ~1h logged:2026-03-14 pr:#83 completed:2026-03-15
- [x] t009 Tighten PHPCS: re-enable EscapeOutput, add nonce verification rules @dave #quality ~4h logged:2026-03-14 pr:#87 completed:2026-03-15
- [ ] t010 Raise PHPStan level from 5 to 6, fix new errors @dave #quality ~4h logged:2026-03-14
- [x] t011 Add PHPDoc blocks to all classes/methods @dave #quality ~8h logged:2026-03-14 pr:#89 completed:2026-03-15

### Testing Infrastructure (P1)

- [x] t012 Set up wp-env based test runner for local and CI @dave #testing ~4h logged:2026-03-14 pr:#88 completed:2026-03-15
- [ ] t013 Add integration tests for all Abilities classes @dave #testing ~8h logged:2026-03-14 blocked-by:t012
- [x] t014 Add integration tests for AgentLoop (mock AI responses) @dave #testing ~6h logged:2026-03-14 blocked-by:t012 pr:#274 completed:2026-03-15
- [ ] t015 Add integration tests for RestController endpoints @dave #testing ~4h logged:2026-03-14 blocked-by:t012
- [x] t016 Add integration tests for Database schema and migrations @dave #testing ~3h logged:2026-03-14 blocked-by:t012 pr:#273 completed:2026-03-15
- [ ] t017 Add integration tests for Automations system @dave #testing ~4h logged:2026-03-14 blocked-by:t012
- [x] t018 Add JS unit tests with @wordpress/scripts test-unit-js @dave #testing ~6h logged:2026-03-14 pr:#272 completed:2026-03-15
- [x] t019 Add code coverage reporting to CI @dave #ci ~2h logged:2026-03-14 blocked-by:t012 pr:#264 completed:2026-03-15
- [ ] t020 Add E2E tests with wp-env + Playwright for chat UI @dave #testing ~8h logged:2026-03-14 blocked-by:t012

### Ability Quality Improvements (P1)

- [x] t029 Standardize error handling: return WP_Error not arrays with 'error' key @dave #refactor ~4h logged:2026-03-14 pr:#269 completed:2026-03-15
- [ ] t030 Add output_schema to all abilities @dave #quality ~4h logged:2026-03-14
- [ ] t031 Add meta.annotations (readonly, destructive, idempotent) to all abilities @dave #quality ~3h logged:2026-03-14
- [ ] t032 Add meta.show_in_rest = true where appropriate @dave #quality ~2h logged:2026-03-14

### Git Change Tracking & Undo (P1)

- [ ] t033 Port GitTracker class: store original files as git blobs, track changes @dave #feature ~6h logged:2026-03-14
- [ ] t034 Port GitTrackerManager: manage trackers across plugins/themes @dave #feature ~3h logged:2026-03-14 blocked-by:t033
- [ ] t035 Add Changes Admin page: view diffs, revert changes, export patches @dave #feature ~8h logged:2026-03-14 blocked-by:t033,t034
- [ ] t036 Add plugin download links for AI-modified plugins @dave #feature ~2h logged:2026-03-14 blocked-by:t033

### Tool Permission System (P1)

- [ ] t037 Add per-tool WordPress capabilities (gratis_ai_agent_tool_{name}) @dave #feature ~3h logged:2026-03-14
- [ ] t038 Add YOLO mode toggle (skip all confirmations) @dave #feature ~1h logged:2026-03-14

### AI SDK Alignment (P1)

- [ ] t041 Update compat layer to match WordPress/ai Abstract_Ability pattern @dave #refactor ~4h logged:2026-03-14
- [ ] t042 Port abilities from WordPress/ai experiments plugin @dave #feature ~6h logged:2026-03-14 blocked-by:t041
- [ ] t043 Add Abilities Explorer admin page @dave #feature ~6h logged:2026-03-14
- [x] t044 Support all three official AI providers @dave #feature ~3h logged:2026-03-14 pr:#271 ref=GH#236 completed:2026-03-15

### White-Label & Resale (P2)

- [ ] t075 White-label support: custom branding, colors, greeting, agent name @dave #feature ~6h logged:2026-03-15
- [ ] t076 Resale API: proxy endpoint for managed AI with usage tracking @dave #feature ~8h logged:2026-03-15

### Architecture & Modernization (P2)

- [ ] t046 Extract send_prompt_direct() to dedicated OpenAI proxy class @dave #refactor ~4h logged:2026-03-14
- [ ] t047 Extract credential management to CredentialResolver class @dave #refactor ~3h logged:2026-03-14
- [ ] t048 Replace hardcoded model fallback with configurable default @dave #refactor ~1h logged:2026-03-14
- [ ] t049 Add proper dependency injection instead of static method calls @dave #refactor ~4h logged:2026-03-14
- [ ] t050 Add event/hook system for ability execution (before/after hooks) @dave #refactor ~3h logged:2026-03-14

### Frontend/UI Improvements (P2)

- [ ] t051 Add screen-meta integration (chat in WP admin Help/Screen Options) @dave #ui ~4h logged:2026-03-14
- [ ] t053 Add CodeMirror integration for code display in chat @dave #ui ~2h logged:2026-03-14
- [ ] t055 Add proper error boundaries in React components @dave #quality ~3h logged:2026-03-14
- [ ] t056 Add TypeScript types or JSDoc to JS codebase @dave #quality ~8h logged:2026-03-14

### Multi-User & Collaboration (P2)

- [ ] t077 Shared conversations: multiple admins can view/continue same session @dave #feature ~6h logged:2026-03-15
- [ ] t078 Role-based AI permissions: restrict abilities by WordPress user role @dave #feature ~4h logged:2026-03-15
- [ ] t079 Conversation templates: pre-built prompts for common tasks @dave #feature ~3h logged:2026-03-15

### Proactive Site Intelligence (P2)

- [ ] t080 Daily site health automation: check plugins, errors, performance, security @dave #feature ~6h logged:2026-03-15
- [ ] t081 Proactive alerts: surface issues as notification badge on FAB @dave #feature ~4h logged:2026-03-15

### Custom Agent Builder (P2)

- [ ] t082 Agent builder UI: specialized agents with custom prompts, tools, models @dave #feature ~8h logged:2026-03-15

### Voice Interface (P3)

- [ ] t083 Push-to-talk: speech-to-text via browser Web Speech API @dave #feature ~4h logged:2026-03-15
- [ ] t084 Text-to-speech for AI responses (optional) @dave #feature ~3h logged:2026-03-15

### Integration Hub (P3)

- [ ] t085 Google Analytics integration: traffic analysis ability @dave #feature ~4h logged:2026-03-15
- [ ] t086 Google Search Console integration: SEO insights ability @dave #feature ~4h logged:2026-03-15
- [ ] t087 Slack/Discord notification forwarding for automation results @dave #feature ~3h logged:2026-03-15
- [ ] t088 Webhook API: trigger AI conversations from external systems @dave #feature ~4h logged:2026-03-15
- [ ] t089 MCP server: expose abilities as MCP tools for external AI clients @dave #feature ~6h logged:2026-03-15

### Documentation & Packaging (P2)

- [x] t057 Add CONTRIBUTING.md with dev setup, testing, and PR guidelines @dave #docs ~1h logged:2026-03-14 pr:#167 completed:2026-03-15
- [ ] t058 Add WordPress Playground blueprint for instant demo @dave #devops ~2h logged:2026-03-14
- [x] t059 Update .distignore for clean plugin packaging @dave #devops ~1h logged:2026-03-14 pr:#168 completed:2026-03-15

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
