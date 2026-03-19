# Gratis AI Agent - Task Management

## Ready

- [ ] t127 Submit Gratis AI Agent to WordPress.org plugin directory via SVN @superdav42 #devops ~2h ref=GH#597
  - WP.org prep complete (t124): GPL headers, readme.txt, screenshots, sanitization audit. Next: request SVN access, checkout, copy trunk/, tag v1.2.0

- [ ] t128 Add Gemini 2.5 Flash and Gemini 2.5 Flash Lite to model selector @superdav42 #feature ~2h ref=GH#598
  - Gemini 2.5 Flash ($0.30/1M input), Flash Lite ($0.10/1M). Needs OpenRouter/LiteLLM adapter. Update CostCalculator + Settings UI tier grouping

- [x] t124 Prepare plugin for WordPress.org submission (review checklist) @superdav42 #devops ~4h ref=GH#591 pr:#596 completed:2026-03-19
  - Run wp plugin check, audit sanitization/nonces/capabilities, add GPL headers, create screenshots, submit via SVN

- [x] t125 Raise PHPStan level from 7 to 8 and fix new errors @superdav42 #quality ~3h ref=GH#592 pr:#595 completed:2026-03-19
  - Continue quality ladder: t010 (5→6), t110 (6→7), now 7→8. Level 8 adds stricter generics/template type checks.

- [x] t126 Add Claude 3.5 Haiku and Gemini 2.0 Flash to model selector @superdav42 #feature ~2h ref=GH#593 pr:#594 completed:2026-03-19
  - claude-3-5-haiku-20241022 ($0.80/1M), gemini-2.0-flash ($0.10/1M), gemini-2.0-flash-lite ($0.075/1M)
  - Update CostCalculator pricing and Settings UI tier grouping

- [x] t120 Fix readme.txt PHP requirement: update 7.4 to 8.2 to match plugin header and CI @superdav42 #bug ~0.5h ref=GH#581 pr:#582 completed:2026-03-19
  - readme.txt says "Requires PHP: 7.4" but plugin header says PHP 8.2 and CI runs PHP 8.2
  - t109 was incorrectly linked to PR #560 (image upload) — the actual fix was never applied
  - Fix: update readme.txt "Requires PHP: 7.4" → "Requires PHP: 8.2"

- [x] t121 Bump version to 1.2.0 and update CHANGELOG.md @superdav42 #chore ~2h ref=GH#583 pr:#586 completed:2026-03-19
  - Plugin at v1.1.0 but 100+ PRs merged since — streaming, image upload, spending limits, mobile UX, a11y, etc.
  - Update CHANGELOG.md [Unreleased] → [1.2.0], bump Version in gratis-ai-agent.php, update readme.txt Stable tag

- [x] t122 Add E2E tests for image/file upload in chat (t109 feature) @superdav42 #testing ~3h ref=GH#584 pr:#587 completed:2026-03-19
  - PR #560 added upload but no E2E tests — paperclip button, drag-drop, thumbnail preview, remove button

- [x] t123 Fix ShellCheck violations in .agents/scripts/ and bin/ @superdav42 #quality ~1h ref=GH#585 pr:#588 completed:2026-03-19
  - 1 error (SC2148 missing shebang in husky.sh), 6 warnings (SC2015, SC1091, SC2086, SC2046, SC2001)

- [x] t109 Fix readme.txt PHP requirement: update 7.4 to 8.2 to match plugin header and CI @superdav42 #bug ~0.5h ref=GH#550 pr:#560 completed:2026-03-19
  - readme.txt says "Requires PHP: 7.4" but plugin header says PHP 8.2 and CI runs PHP 8.2
  - Codebase uses PHP 8.1 enums (ToolType, MemoryCategory, Schedule, HttpMethod) — 7.4 is impossible
  - Fix: update readme.txt "Requires PHP: 7.4" → "Requires PHP: 8.2"

- [x] t110 Raise PHPStan level from 6 to 7 and fix new errors @superdav42 #quality ~3h ref=GH#6 pr:#559 completed:2026-03-19
  - Current level: 6 (set in t010). Level 7 adds stricter type inference and union type checks.
  - Run `vendor/bin/phpstan analyse --level=7` to see new errors, then fix them
  - Update phpstan.neon level from 6 to 7

- [x] t111 Update GitHub Actions to Node.js 24 compatible versions @superdav42 #ci ~1h ref=GH#24 pr:#558 completed:2026-03-19
  - CI warns: "actions/cache@v4 and actions/checkout@v4 running on Node.js 20 — deprecated"
  - Node.js 24 becomes default on June 2, 2026 (~2.5 months away)
  - Update all workflow files to use latest v4 patch versions that support Node.js 24
  - Affected workflows: tests.yml, e2e.yml, code-quality.yml, release.yml, issue-sync.yml, todo-integrity.yml
  - Also add FORCE_JAVASCRIPT_ACTIONS_TO_NODE24=true env var to opt in early

- [x] t112 Add E2E tests for auto-title sessions and abilities search/filter @superdav42 #testing ~3h ref=GH#551 pr:#553 completed:2026-03-19
  - t099 (auto-title) and t098 (abilities search) shipped without E2E coverage
  - Add to chat-interactions.spec.js: verify session gets auto-titled after first AI response
  - Add to admin-page.spec.js: verify abilities search filters the list, categories collapse/expand
  - These are user-visible features that should be regression-tested

- [x] t106 Update default model to GPT-4.1-nano and add GPT-4.1 model family to selector @superdav42 #feature ~2h ref=GH#539 pr:#542 completed:2026-03-19
  - Update Settings::DIRECT_PROVIDERS openai default_model from gpt-4o to gpt-4.1-nano
  - Add GPT-4.1-nano, GPT-4.1-mini, GPT-4.1 to OpenAI model list in Settings
  - CostCalculator already has pricing — no changes needed there
  - Existing installs with gpt-4o configured must not be affected (setting persists)

- [x] t107 Fix persistent ShellCheck violations in .agents/scripts/ and bin/ @superdav42 #quality ~1h ref=GH#540 pr:#544 completed:2026-03-19

- [x] t108 Add tiered model pricing display to Settings UI @superdav42 #ui ~3h ref=GH#541 pr:#543 completed:2026-03-19
  - Show pricing hints next to each model (e.g. GPT-4.1-nano — $0.10/M input, best value)
  - Group models by provider and tier (Budget / Standard / Premium)
  - Estimated cost per session based on average token usage

- [x] t090 Rename plugin to "Gratis AI Agent" with slug "gratis-ai-agent" for WP.org guidelines @superdav42 #chore ~4h logged:2026-03-15 pr:#194 completed:2026-03-15
  - Rename main file, text domain, namespace, constants, REST namespace, DB tables, options, CSS classes, ability names, CLI command, build assets, config files, CI/docs
  - Add activation hook migration for existing installs (detect old option/table names and rename)

- [x] t091 Shared conversations: multiple admins can view/continue same session @superdav42 #feature ~6h logged:2026-03-16 ref=GH#387 pr:#474 completed:2026-03-16

### Browser Review Findings (2026-03-18)

#### Critical Bugs (P0)

- [x] t092 Fix incomplete plugin rename: 34 JS API paths still use old `/ai-agent/v1/` namespace instead of `/gratis-ai-agent/v1/` @superdav42 #bug ~2h ref=GH#508 completed:2026-03-19
  - Affected files: usage-dashboard.js, knowledge-manager.js, automations-manager.js, events-manager.js, custom-tools-manager.js, tool-profiles-manager.js, message-input.js (memory slash commands)
  - Also 384 instances of old text domain `'ai-agent'` instead of `'gratis-ai-agent'` across 20+ JS files
  - Root cause: t090 rename was incomplete — store/index.js and some settings files were updated but others were missed
  - Impact: Usage tab, Knowledge tab, Automations, Events, Custom Tools, Tool Profiles, and /remember /forget slash commands are ALL broken

- [x] t093 Fix SSE stream endpoint fatal error: non-static method called statically @superdav42 #bug ~1h ref=GH#509 completed:2026-03-19
  - `RestController.php` line 99: `'permission_callback' => [ __CLASS__, 'check_chat_permission' ]`
  - `check_chat_permission()` is a non-static instance method but registered with `__CLASS__` (static context)
  - The `$instance = new self()` is created on line 151, AFTER the `/stream` route registration on line 93
  - Fix: move `$instance` creation before the `/stream` route, use `[ $instance, 'check_chat_permission' ]`
  - Impact: 100% of chat messages fail with fatal PHP error — no AI responses work at all

- [x] t094 Fix Abilities Explorer crash: Badge component not exported from @wordpress/components @superdav42 #bug ~1h ref=GH#510 completed:2026-03-19
  - `abilities-explorer-app.js` imports `Badge` from `@wordpress/components` but Badge is not in the package's public exports (it exists in src/badge/ but is not re-exported from index.ts)
  - Badge is `undefined` at runtime, causing 2,185+ React errors and the error boundary to trigger
  - Fix: replace Badge with a simple styled span/div, or use a custom Badge component
  - Impact: Abilities Explorer page is completely non-functional

#### High Priority Bugs (P1)

- [x] t095 Fix "Build Your Site" overlay blocking other admin pages on fresh install @superdav42 #bug ~2h ref=GH#511 completed:2026-03-19
  - The site builder overlay renders on ALL admin pages when `isFreshInstall` is true, not just the AI Agent page
  - On Changes and Abilities pages, the overlay covers the actual page content
  - Fix: only render the site builder overlay on the main AI Agent page, or check the current admin page before rendering

- [x] t096 Add proper error handling for chat stream failures @superdav42 #bug ~2h pr:#517 completed:2026-03-18
  - When the stream endpoint returns a fatal PHP error (or any non-200 response), the frontend shows "Thinking..." indefinitely
  - No timeout mechanism exists — users wait forever with no feedback
  - Fix: add a configurable timeout (e.g., 120s), detect non-SSE responses, show error messages to the user
  - Also handle EventSource errors and display meaningful error messages

- [x] t097 Fix Knowledge tab "Failed to load collections" error @superdav42 #bug ~1h ref=GH#519 completed:2026-03-19
  - The Knowledge manager uses old API path `/ai-agent/v1/knowledge` (part of t092 namespace issue)
  - Red error banner appears even though the feature is enabled
  - Will be fixed as part of t092 namespace fix

#### UX Improvements (P2)

- [x] t098 Abilities settings tab: add categories/search/collapsible sections for 40+ tools @superdav42 #ui ~3h pr:#530 completed:2026-03-19
  - The Settings > Abilities tab shows a flat list of 40+ tools with dropdowns (Auto/Confirm/Disabled)
  - Very long page, hard to find specific tools
  - Add: grouping by category, search/filter input, collapsible category sections

- [x] t099 Auto-title sessions from first message content @superdav42 #ui ~2h ref=GH#521 completed:2026-03-19
  - All sessions show as "Untitled" — no auto-naming from conversation content
  - After the first AI response, generate a short title from the conversation topic
  - Could use the AI to generate a 3-5 word title, or extract keywords from the first user message

- [x] t100 Consolidate duplicate save buttons on Permissions and Integrations tabs @superdav42 #ui ~1h pr:#527 completed:2026-03-19
  - Permissions tab has both "Save Permissions" and "Save Settings" buttons
  - Integrations tab has both "Save GA Credentials" and "Save Settings" buttons
  - Confusing for users — consolidate into a single save action or clarify the difference

- [x] t101 Fix Integrations tab "Save GA Credentials" button red text styling @superdav42 #ui ~0.5h pr:#529 completed:2026-03-19
  - The button has red text which looks like a destructive/danger action
  - Should use standard primary/secondary button styling

- [x] t102 Settings tab bar overflow handling for narrow viewports @superdav42 #ui ~1h pr:#528 completed:2026-03-19
  - 18 tabs in the settings page — rightmost tabs (Integrations, Advanced) may be cut off at narrower viewports
  - Add horizontal scroll indicators or a responsive tab layout (dropdown on mobile)

- [x] t103 Frontend floating widget not rendering for logged-in admins @superdav42 #bug ~2h pr:#526 completed:2026-03-19
  - Settings > General has "Show Widget on Frontend" toggle (currently off)
  - Even when the setting exists, the widget was not found in the DOM on the frontend
  - Verify the frontend enqueue hook fires correctly and the widget renders when enabled

### CI / WP Trunk Compatibility

- [x] t104 Fix PHPUnit (WP trunk) fatal: WP_AI_Client_HTTP_Client::sendRequestWithOptions() interface incompatibility @superdav42 #bug ~1h ref=GH#535 pr:#538 completed:2026-03-19
  - WP trunk updated ClientWithOptionsInterface to use non-namespaced Psr\Http\Message\RequestInterface
  - Plugin compat layer still uses vendored WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface
  - Fix: align WP_AI_Client_HTTP_Client::sendRequestWithOptions() signature with current WP trunk interface

- [x] t105 Fix Playwright E2E (WP trunk) CI: invalid JSON in wp-env override script @superdav42 #bug ~0.5h ref=GH#536 pr:#537 completed:2026-03-19
  - CI script passes {core:WordPress/WordPress#master} (unquoted keys) to JSON.parse — not valid JSON
  - Fix: quote the key in the inline Node.js script or write JSON directly without JSON.parse

## Backlog

### Onboarding & First-Run Experience (P0)

- [x] t060 Detect fresh WordPress install and trigger site builder mode @superdav42 #feature ~3h logged:2026-03-15 pr:#454 completed:2026-03-16
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
- [x] t065 Integrate provider setup into onboarding conversation @superdav42 #ui ~4h logged:2026-03-15 pr:#456 completed:2026-03-16
  - Replace 4-step wizard with conversational flow; agent guides provider setup in chat

### Floating Widget & Frontend (P0)

- [x] t066 Show floating widget on frontend for logged-in admins @superdav42 #feature ~3h logged:2026-03-15 pr:#458 completed:2026-03-16
  - Register `wp_enqueue_scripts` hook with capability check
  - Frontend page context: current URL, post ID, template, query vars
  - Setting to enable/disable (default: enabled for admins)
- [x] t067 Improve floating widget UX: resizable panel, keyboard shortcut @superdav42 #ui ~4h logged:2026-03-15 pr:#446 completed:2026-03-16
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
- [x] t020 Add E2E tests with wp-env + Playwright for chat UI @superdav42 #testing ~8h logged:2026-03-14 blocked-by:t012 pr:#445 completed:2026-03-16

### Ability Quality Improvements (P1)

- [x] t029 Standardize error handling: return WP_Error not arrays with 'error' key @superdav42 #refactor ~4h logged:2026-03-14 pr:#269 completed:2026-03-15
- [x] t030 Add output_schema to all abilities @superdav42 #quality ~4h logged:2026-03-14 pr:#402 completed:2026-03-16
- [x] t031 Add meta.annotations (readonly, destructive, idempotent) to all abilities @superdav42 #quality ~3h logged:2026-03-14 pr:#438 completed:2026-03-16
- [x] t032 Add meta.show_in_rest = true where appropriate @superdav42 #quality ~2h logged:2026-03-14 pr:#298 completed:2026-03-15

### Git Change Tracking & Undo (P1)

- [x] t033 Port GitTracker class: store original files as git blobs, track changes @superdav42 #feature ~6h logged:2026-03-14 ref=GH#431 pr:#442 verified:2026-03-16
- [x] t034 Port GitTrackerManager: manage trackers across plugins/themes @superdav42 #feature ~3h logged:2026-03-14 ref=GH#432 pr:#451 verified:2026-03-16
- [x] t035 Add Changes Admin page: view diffs, revert changes, export patches @superdav42 #feature ~8h logged:2026-03-14 blocked-by:t033,t034 ref=GH#433 pr:#447 pr:#447 completed:2026-03-16
- [x] t036 Add plugin download links for AI-modified plugins @superdav42 #feature ~2h logged:2026-03-14 ref=GH#434 pr:#441 pr:#441 completed:2026-03-16

### Tool Permission System (P1)

- [x] t037 Add per-tool WordPress capabilities (gratis_ai_agent_tool_{name}) @superdav42 #feature ~3h logged:2026-03-14 ref=GH#435 pr:#448 pr:#448 completed:2026-03-16
- [x] t038 Add YOLO mode toggle (skip all confirmations) @superdav42 #feature ~1h logged:2026-03-14 pr:#444 completed:2026-03-16

### AI SDK Alignment (P1)

- [x] t041 Update compat layer to match WordPress/ai Abstract_Ability pattern @superdav42 #refactor ~4h logged:2026-03-14 pr:#405 completed:2026-03-15
- [x] t042 Port abilities from WordPress/ai experiments plugin @superdav42 #feature ~6h logged:2026-03-14 blocked-by:t041 pr:#472 completed:2026-03-16
- [x] t043 Add Abilities Explorer admin page @superdav42 #feature ~6h logged:2026-03-14 pr:#471 completed:2026-03-16
- [x] t044 Support all three official AI providers @superdav42 #feature ~3h logged:2026-03-14 pr:#271 ref=GH#236 completed:2026-03-15

### White-Label & Resale (P2)

- [x] t075 White-label support: custom branding, colors, greeting, agent name @superdav42 #feature ~6h logged:2026-03-15 pr:#470 completed:2026-03-16
- [x] t076 Resale API: proxy endpoint for managed AI with usage tracking @superdav42 #feature ~8h logged:2026-03-15 pr:#475 completed:2026-03-16

### Architecture & Modernization (P2)

- [x] t046 Extract send_prompt_direct() to dedicated OpenAI proxy class @superdav42 #refactor ~4h logged:2026-03-14 ref=GH#239 pr:#281 pr:#281 completed:2026-03-15
- [x] t047 Extract credential management to CredentialResolver class @superdav42 #refactor ~3h logged:2026-03-14 pr:#280 completed:2026-03-15
- [x] t048 Replace hardcoded model fallback with configurable default @superdav42 #refactor ~1h logged:2026-03-14 pr:#278 completed:2026-03-15
- [x] t049 Add proper dependency injection instead of static method calls @superdav42 #refactor ~4h logged:2026-03-14 pr:#344 ref=GH#320 pr:#344 completed:2026-03-15
- [x] t050 Add event/hook system for ability execution (before/after hooks) @superdav42 #refactor ~3h logged:2026-03-14 pr:#337 completed:2026-03-15

### Frontend/UI Improvements (P2)

- [x] t051 Add screen-meta integration (chat in WP admin Help/Screen Options) @superdav42 #ui ~4h logged:2026-03-14 pr:#473 completed:2026-03-16
- [x] t053 Add CodeMirror integration for code display in chat @superdav42 #ui ~2h logged:2026-03-14 pr:#460 completed:2026-03-16
- [x] t055 Add proper error boundaries in React components @superdav42 #quality ~3h logged:2026-03-14 pr:#279 completed:2026-03-15
- [x] t056 Add TypeScript types or JSDoc to JS codebase @superdav42 #quality ~8h logged:2026-03-14 pr:#341 completed:2026-03-15

### Multi-User & Collaboration (P2)

- [x] t077 Shared conversations: multiple admins can view/continue same session @superdav42 #feature ~6h logged:2026-03-15 ref=GH#387 pr:#474 pr:#474 completed:2026-03-16
- [x] t078 Role-based AI permissions: restrict abilities by WordPress user role @superdav42 #feature ~4h logged:2026-03-15 pr:#462 completed:2026-03-16
- [x] t079 Conversation templates: pre-built prompts for common tasks @superdav42 #feature ~3h logged:2026-03-15 ref=GH#389 pr:#413 pr:#413 completed:2026-03-16

### Proactive Site Intelligence (P2)

- [x] t080 Daily site health automation: check plugins, errors, performance, security @superdav42 #feature ~6h logged:2026-03-15 pr:#411 completed:2026-03-16
- [x] t081 Proactive alerts: surface issues as notification badge on FAB @superdav42 #feature ~4h logged:2026-03-15 pr:#410 completed:2026-03-16

### Custom Agent Builder (P2)

- [x] t082 Agent builder UI: specialized agents with custom prompts, tools, models @superdav42 #feature ~8h logged:2026-03-15 ref=GH#392 pr:#437 pr:#437 completed:2026-03-16

### Voice Interface (P3)

- [x] t083 Push-to-talk: speech-to-text via browser Web Speech API @superdav42 #feature ~4h logged:2026-03-15 pr:#408 completed:2026-03-16
- [x] t084 Text-to-speech for AI responses (optional) @superdav42 #feature ~3h logged:2026-03-15 pr:#469 completed:2026-03-16

### Integration Hub (P3)

- [x] t085 Google Analytics integration: traffic analysis ability @superdav42 #feature ~4h logged:2026-03-15 pr:#465 completed:2026-03-16
- [x] t086 Google Search Console integration: SEO insights ability @superdav42 #feature ~4h logged:2026-03-15 pr:#464 completed:2026-03-16
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
