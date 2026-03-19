# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] - 2026-03-19

### Added
- Support all three official AI providers: OpenAI, Anthropic, and Google Gemini
- Image and file upload support in chat messages
- Spending limits and budget caps to control AI costs
- Live streaming token counter and cost display during responses
- Tiered model pricing display in the Settings model selector
- GPT-4.1 family models (GPT-4.1, GPT-4.1-mini, GPT-4.1-nano); GPT-4.1-nano is now the default OpenAI model
- Graceful fallback when tool calls exhaust max iterations
- Visible scroll affordance for settings tabs on touch devices
- Suggestion cards on chat empty state
- Search, category grouping, and collapsible sections in the Abilities settings tab
- Auto-title sessions from the first message using AI
- Settings tab bar horizontal scroll with fade indicators
- Shared conversations — multiple admins can view and continue the same session
- Text-to-speech for AI responses (optional)
- White-label branding support — custom agent name, colors, and logo
- Google Search Console SEO insights abilities
- Google Analytics 4 traffic analysis ability
- Resale API proxy endpoint with usage tracking
- YOLO mode toggle to skip all confirmations
- Abilities Explorer admin page
- Screen-meta Help tab chat panel
- Editorial and image AI abilities ported from WordPress/ai experiments
- AI image generation ability (DALL-E 3)
- Agent builder UI — create specialized agents with custom prompts, tools, and models
- Role-based AI permissions — restrict abilities by WordPress user role
- Floating widget shown on frontend for logged-in admins
- Resizable floating widget panel with keyboard shortcut
- WooCommerce onboarding — detect store and offer AI product creation
- WooCommerce abilities — product CRUD, order queries, and store stats
- Slack and Discord notification forwarding for automation results
- Sortable, filterable DataTable rendering for tabular chat responses
- Chart.js chart rendering in chat responses
- Per-tool WordPress capabilities (`gratis_ai_agent_tool_{name}`)
- Site builder conversation flow — interview user then generate a full site
- Site builder mode triggered on fresh WordPress installs
- Onboarding interview — ask user about site goals after site scan
- Provider setup integrated into the onboarding wizard
- Changes Admin page — view diffs, revert changes, and export patches
- Plugin download links for AI-modified plugins
- CodeMirror 6 syntax highlighting in chat code blocks
- Output schema on all abilities
- Conversation templates — pre-built prompts for common tasks
- Webhook API — trigger AI conversations from external systems
- Push-to-talk speech-to-text via browser Web Speech API
- Daily site health automation (SiteHealthAbilities)
- Proactive alerts with notification badge on the floating action button
- Action cards for confirmable operations
- MCP server — expose abilities as MCP tools for external AI clients
- Playwright E2E tests for chat UI with wp-env
- E2E tests for auto-title sessions and abilities search/filter
- PHPUnit integration tests for McpController, RestController, AgentLoop, Database, and all 13 Abilities classes
- JS unit tests with @wordpress/scripts
- PHPUnit coverage reporting with pcov and Codecov
- Integration tests for the Automations system
- TypeScript types and JSDoc annotations across the JavaScript codebase
- WordPress Playground blueprint for instant demo
- Comprehensive CONTRIBUTING.md
- Husky + lint-staged pre-commit hooks for PHP, JS, and CSS
- Consolidate duplicate save buttons on Permissions and Integrations tabs
- Mobile slide-out drawer replacing stacked sidebar layout

### Changed
- PHPStan level raised from 6 to 7 with all new errors resolved
- Improved multi-step agentic workflows
- Model benchmark suite added
- Plugin renamed to Gratis AI Agent across all JS sources
- Configurable default model (replaces hardcoded fallback)
- Constructor injection for Settings and Database dependencies
- Credential management extracted to dedicated `CredentialResolver` class
- `send_prompt_direct()` extracted to dedicated `OpenAIProxy` class
- Error handling standardised — return `WP_Error` instead of arrays with `error` key
- Event/hook system added for ability execution (before/after hooks)
- PHPDoc blocks added to all classes and methods
- PHPCS rules tightened — EscapeOutput and NonceVerification re-enabled
- GitHub Actions workflows updated to Node.js 24 with standardised action refs
- wp-env Docker replaced with direct MariaDB for PHPUnit CI
- ESLint and Stylelint violations resolved; CI steps no longer use `continue-on-error`
- `meta.show_in_rest = true` added to all `wp_register_ability()` calls
- `meta.annotations` completed on all abilities
- AbilityDiscoveryAbilities registered at priority 999
- ChangeLogger wraps `execute_abilities()` in try/finally to guarantee `end()` is always called
- Secrets and PII redacted in ChangeLogger `before_value`/`after_value`
- `allSelected` header checkbox logic corrected for cross-page selections
- Object-level permission check added in `permission_edit_posts`
- Stream error handling, timeout, and retry added
- Site builder overlay restricted to main AI Agent page
- `@wordpress/components` Badge replaced with custom inline Badge component
- `$instance` creation moved before `/stream` route to fix non-static permission callback
- Discovery mode confusion resolved in ToolDiscovery
- Double `/wp-admin/` URL in screen meta context fixed; discovery abilities gated correctly
- Empty args array normalised; follow-up injected on empty AI response
- Chat bubble max-width increased on large screens
- Focus keyword fields added to `analyze_post_seo` output schema
- `handle_delete_change` checks delete result before returning success
- `ChangesLog::record()` return type fixed to `int|false`
- Hard-coded versioned Chrome path replaced with env var fallback
- `sanitize_textarea_field()` used to preserve line breaks in summary output
- WP_Error return from `get_the_terms()` handled in WooCommerceAbilities
- ShellCheck violations resolved across all shell scripts (SC2015, SC1091, SC2086, SC2046, SC2148)
- npm audit vulnerabilities fixed (26 issues: 12 moderate, 14 high)
- Compat layer class check updated; dual WP version testing (6.9 + trunk)
- wp-env override JSON passed via env var to avoid shell quote stripping
- PHPUnit (WP trunk) fatal resolved — `ClientWithOptionsInterface` namespace mismatch

[1.2.0]: https://github.com/Ultimate-Multisite/gratis-ai-agent/compare/v1.1.0...v1.2.0
