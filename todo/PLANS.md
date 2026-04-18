# Gratis AI Agent - Plans & Strategy

## Positioning

**"The AI that doesn't just build your site -- it runs it."**

Every competitor generates a site and then you're on your own. We build AND manage. The agent
lives inside WordPress with access to the database, hooks, cron, plugins, and business context
that no external AI tool can match.

### What we have that nobody else does

- **True agentic architecture** -- autonomous multi-step tool calling via WordPress 6.9 Abilities API
- **Provider independence (BYOK)** -- users bring their own API key, pay direct, no markup
- **Extensible via Abilities API** -- any plugin can register abilities the agent discovers automatically
- **Event-driven + scheduled automations** -- 20+ WordPress/WooCommerce triggers, cron-based tasks
- **Knowledge base / RAG** -- document indexing, PDF upload, auto-index on publish
- **Persistent memory** -- cross-session memory with categories, auto-memory mode
- **Admin context awareness** -- agent knows which page you're on and adapts
- **No vendor lock-in** -- standard WordPress plugin, works on any host, any theme, any builder

### Where competitors beat us (gaps to close)

| Gap | Who does it | Priority | TODO refs |
|-----|-------------|----------|-----------|
| AI site generation from prompt | 10Web, GoDaddy, ZipWP, Divi | P0 | t060-t062 |
| Streaming responses | Everyone | P0 | t054 |
| Smart onboarding for existing sites | 10Web (site scan) | P0 | t063-t065 |
| Frontend widget for logged-in admins | N/A (our own gap) | P0 | t066 |
| AI image generation | 10Web, Divi (unlimited) | P1 | t068-t069 |
| WooCommerce deep integration | 10Web, GoDaddy | P1 | t070-t071 |
| Rich artifacts in chat | Claude.ai, OpenWebUI | P1 | t072-t074 |
| White-label / resale API | 10Web, ZipWP | P2 | t075-t076 |
| Multi-user collaboration | 10Web (agency workspaces) | P2 | t077-t079 |

## Current Focus

P0 tasks: onboarding flows (t060-t065), frontend widget (t066), streaming (t054). These are the
features that determine whether a user stays past the first 5 minutes.

## Competitive Landscape (March 2026)

### AI Site Generation Platforms (hosted)

**10Web** -- Hosted WordPress + AI builder. Full site from 1 prompt in <60s. Elementor-based
editor. $10-23/mo with hosting. 2M+ sites generated. 4.5/5 Trustpilot (2,300 reviews). Strengths:
speed, support, all-in-one. Weaknesses: AI struggles with complex prompts, billing complaints,
medium lock-in. White-label API for B2B.

**GoDaddy Airo** -- Proprietary builder (NOT WordPress). Site in <30s. $11-24/mo. 20M+ customers,
$4.6B revenue. Strengths: fastest setup, cheapest, marketing suite (email, social, SEO). Weaknesses:
generic designs, no plugins/apps, full vendor lock-in, no export. Not a direct competitor since
it's not WordPress, but sets user expectations for AI site building speed.

**ZipWP** -- WordPress-native AI builder by Brainstorm Force (Astra). Full site from prompt in
<60s. $0-33/mo. Real WordPress install, fully portable. Strengths: no lock-in, agency features,
Chrome extension for profile-to-site. Weaknesses: Astra/Spectra only, free plan sites expire in
24h. Closest competitor to our approach.

### AI-Enhanced Page Builders (plugins)

**Divi AI** -- AI built into Divi visual builder. Unlimited text/image/code generation at flat
$16-23/mo. Full site generation via Quick Sites. Fine-tuned on Divi codebase. Strengths: unlimited
usage, deep builder integration. Weaknesses: Divi-only, proprietary shortcode format.

**Elementor AI + Angie** -- AI in Elementor editor (credit-based) plus Angie (free agentic plugin).
Angie creates widgets, CPTs, admin snippets -- closest to our agentic approach. 21M+ sites.
Strengths: massive market share, Angie is truly agentic. Weaknesses: credit-based pricing, complex
tiers, Angie is new.

### AI Code Assistants

**CodeWP/Telex** -- AI code generation for WordPress, acquired by Automattic. In transition/rebrand.
WordPress-specific training data. Wildcard -- Automattic has deep WP knowledge and resources.

## Model Strategy

Default: GPT-4.1-nano ($0.10/1M input). Premium: GPT-4.1-mini ($0.40/1M input). Both native
OpenAI format, 1M context. Session cost ~$0.007 on nano. See ROADMAP.md for full analysis.

## Success Metrics

| Metric | 3-Month | 6-Month | 12-Month |
|--------|---------|---------|----------|
| WordPress.org active installs | 500 | 2,000 | 10,000 |
| Sites generated via AI builder | 200 | 2,000 | 15,000 |
| Daily active users | 100 | 500 | 3,000 |
| Avg sessions/user/day | 2 | 3 | 5 |
| Time to first value (install to useful output) | < 5 min | < 3 min | < 2 min |
| AI site generation success rate | > 75% | > 85% | > 90% |
| User retention (30-day) | 35% | 50% | 65% |
| NPS score | 30 | 45 | 60 |
| White-label partners | 0 | 3 | 10 |

## Key Workflows We Must Support

Ranked by user frequency and competitive necessity:

1. **"Build me a website for X"** -- full site generation from prompt (P0, t060-t062)
2. **"Write content about X"** -- blog post / page content generation (have ContentAbilities, need orchestration)
3. **Chat with streaming responses** -- real-time token streaming (P0, t054)
4. **"What's wrong with my site?"** -- site health analysis + auto-fix (P2, t080-t081)
5. **"Create a product for X"** -- WooCommerce product creation (P1, t070)
6. **"Fix my SEO"** -- SEO audit + auto-fix (have SeoAbilities)
7. **"Update all my plugins"** -- already works via WordPressAbilities
8. **"Show me my stats"** -- rich charts/dashboards (P1, t072-t073)
9. **"Set up email marketing"** -- integration hub (P3, t085-t088)
10. **"Answer customer questions"** -- frontend chatbot with RAG (P2, needs frontend widget t066 first)

---

### [2026-04-09] Complete Site Building Abilities

**Status:** Planning
**Estimate:** ~40h (ai:30h test:8h read:2h)
**Benchmark target:** ac-016 (Restaurant website) as first end-to-end validation

#### Purpose

The agent can create pages and content but cannot build a complete website autonomously.
It lacks abilities for custom post types, taxonomies, menus, global styles, and form
handling. The benchmark suite (agent-capabilities-v1, ac-016 through ac-024) defines
what "complete site building" means -- the agent should be able to execute every step
those questions describe.

The hybrid approach: build only what doesn't exist elsewhere, leverage the WordPress
plugin ecosystem for everything else, and make the agent smart enough to discover and
install the plugins it needs.

#### Architecture: Smart Plugin Discovery

**Core principle:** The agent should never re-implement what a plugin already does well.
Instead of building a form plugin, a menu manager, or an SEO tool, the agent should:

1. Detect what capabilities are missing for the current task
2. Search for plugins that provide those capabilities (preferring plugins with
   Abilities API support, then block-based plugins, then popular plugins)
3. Recommend or auto-install the best option (with user confirmation)
4. Discover and use the newly available abilities/blocks

This requires a new **Ability Discovery & Plugin Recommendation** system:

```
User: "Add a contact form to the homepage"
Agent: [checks registered abilities -- no form ability found]
Agent: [checks installed plugins -- no form plugin found]
Agent: [searches WordPress.org + known ability-providing plugins]
Agent: "I can install WPForms Lite (has blocks) or Formidable Forms
        (has Abilities API support). Which do you prefer?"
User: "WPForms"
Agent: [installs + activates WPForms via existing install_plugin ability]
Agent: [discovers new blocks: wp:wpforms/form-selector]
Agent: [creates contact page with form block]
```

**Known plugins that register abilities (preferred):**
- `mcp-expose-abilities` (bjornfix) -- 66 core abilities: menus, widgets, options, plugins, content, users, media, comments, taxonomy
- `mcp-abilities-elementor` -- 40 Elementor abilities
- `mcp-abilities-rankmath` -- 23 SEO abilities
- `mcp-abilities-toolset` -- 38 CPT/field/taxonomy/relationship abilities
- `filter-abilities` -- taxonomy CRUD, Gravity Forms, Yoast SEO, ACF, media, redirects
- `designsetgo` -- block insertion, configuration, batch updates, CSS
- ACF Pro -- `register-custom-post-type`, `register-custom-taxonomy`

**Curated plugin recommendations by need (maintained in code):**
- Forms: WPForms Lite, Formidable Forms, Gravity Forms, Contact Form 7
- SEO: Yoast SEO, Rank Math (has abilities add-on)
- E-commerce: WooCommerce (has abilities)
- Page builders: Elementor (has abilities add-on), GeneratePress (has abilities add-on)
- Custom fields: ACF (has abilities), Meta Box
- Backup: UpdraftPlus
- Security: Wordfence (has abilities add-on)

#### Progress

- [ ] Phase 1: Smart Plugin Discovery & Recommendation ~8h
- [ ] Phase 2: Core Site Building Abilities (what no plugin provides) ~10h
- [ ] Phase 3: Site Builder Orchestration (system prompt + multi-step) ~10h
- [ ] Phase 4: Design System & Styling Abilities ~6h
- [ ] Phase 5: Benchmark Validation (ac-016 through ac-024) ~6h

#### Phase 1: Smart Plugin Discovery & Recommendation

**Goal:** The agent can find, recommend, and install plugins to gain capabilities
it doesn't have, preferring plugins that register abilities or provide blocks.

Tasks:
- [ ] `search-plugin-directory` ability -- search WordPress.org by keyword, return
  results with active installs, rating, block support, last updated. We already have
  `install_plugin` (WordPressAbilities) so this completes the discover-then-install flow.
- [ ] `list-installable-abilities` ability -- maintain a curated registry of plugins
  known to register abilities (slug, ability count, categories). When the agent needs
  a capability it doesn't have, it can check this registry first. Registry stored as
  a PHP array constant, updated with plugin releases.
- [ ] `recommend-plugin` ability -- given a need category (forms, seo, ecommerce,
  page-builder, custom-fields, security, backup), return ranked recommendations from
  the curated list with reasoning. Factors: has abilities > has blocks > popular.
- [ ] `list-available-blocks` ability -- list all registered Gutenberg block types
  on the current site, so the agent knows what blocks it can use after installing a
  plugin. We have `list-block-types` in BlockAbilities already -- verify it returns
  enough detail (supports, attributes, category).
- [ ] Agent loop system prompt update -- teach the agent the discovery workflow:
  "If you need a capability you don't have, search for a plugin that provides it.
  Prefer plugins with Abilities API support. Ask the user before installing."

#### Phase 2: Core Site Building Abilities

**Goal:** Build abilities that no existing plugin provides well, or that are too
fundamental to depend on a third-party plugin for.

Tasks:
- [ ] `register-custom-post-type` ability -- register a CPT with labels, supports,
  menu icon, REST API visibility, and rewrite rules. Persist registration across
  page loads via an option (so CPTs survive without our plugin being the registrar
  on every request -- store in a `gratis_ai_agent_custom_post_types` option that
  fires on `init`).
- [ ] `register-custom-taxonomy` ability -- register a taxonomy, associate with
  post types, set hierarchical flag, labels. Same persistence pattern as CPTs.
- [ ] `manage-nav-menu` ability -- create menus, add/update/remove items, assign
  to theme locations. This is the biggest gap in the ecosystem (only bjornfix has
  it). We build our own because menus are too fundamental to site building to
  depend on a third-party plugin.
- [ ] `manage-options` ability -- get/set WordPress options (site title, tagline,
  permalink structure, timezone, date format, etc.) with a blocklist for dangerous
  options (siteurl, home, active_plugins, etc.).
- [ ] `manage-global-styles` ability -- read and update theme.json global styles
  (colors, typography, spacing, layout). Uses the `wp_global_styles` CPT that
  WordPress uses internally for user-customized styles.

#### Phase 3: Site Builder Orchestration

**Goal:** The agent can execute a multi-step site build from a single prompt,
using all available abilities (built-in + discovered from plugins).

Tasks:
- [ ] Site builder system prompt v2 -- rewrite the site builder interview prompt
  to use the new abilities. The prompt should guide the agent through: (1) interview
  the user, (2) plan the site structure, (3) install needed plugins, (4) register
  CPTs/taxonomies if needed, (5) create pages with block content, (6) set up
  navigation, (7) configure global styles, (8) set site identity (title, tagline,
  logo), (9) verify the result.
- [ ] Site build plan generation -- before executing, the agent should output a
  structured plan: pages to create, plugins to install, CPTs to register, menu
  structure. User confirms before execution begins.
- [ ] Progress tracking -- during multi-step builds, report progress to the user
  ("Creating About page... 3/6 pages done"). Use the existing streaming response
  infrastructure.
- [ ] Error recovery -- if a step fails (plugin install fails, page creation fails),
  the agent should report the error and continue with remaining steps, then
  summarize what succeeded and what needs manual attention.

#### Phase 4: Design System & Styling Abilities

**Goal:** The agent can apply cohesive visual design, not just create content.

Tasks:
- [ ] `inject-custom-css` ability -- add custom CSS to the site via the Customizer
  additional CSS or a custom stylesheet. Scoped to avoid conflicts.
- [ ] Block pattern library -- curate a set of block patterns for common site
  sections (hero, features grid, pricing table, testimonials, CTA, footer).
  The agent selects and customizes patterns rather than building blocks from scratch.
- [ ] `set-site-logo` ability -- upload and set the site logo via `custom_logo`
  theme mod.
- [ ] Theme.json presets -- curated design presets for common site types (restaurant,
  portfolio, law firm, SaaS, nonprofit). Each preset defines colors, typography,
  spacing. The agent selects a preset based on the site type, then customizes.

#### Phase 5: Benchmark Validation

**Goal:** Validate that the agent can complete ac-016 (restaurant website) end-to-end,
then create follow-up tasks for ac-017 through ac-024.

Tasks:
- [ ] Manual test: ac-016 restaurant website -- run the full prompt through the agent
  on a fresh WordPress install. Document what works, what fails, what's missing.
- [ ] Create follow-up issues for each remaining benchmark question (ac-017 through
  ac-024) based on gaps found during ac-016 testing.
- [ ] Update benchmark scoring criteria if needed -- the keyword-based scoring may
  need adjustment after seeing real agent responses vs the benchmark prompts.

#### Context from Discussion

**Research findings (April 2026):**
- WordPress Abilities API ecosystem has 285+ abilities across community plugins
- bjornfix/mcp-expose-abilities is the most complete: 66 core + 12 add-ons
- Biggest ecosystem gaps: form creation, CPT registration (without ACF), theme.json
- Our agent already has: PostAbilities, BlockAbilities, ContentAbilities, MediaAbilities,
  StockImageAbilities, SiteBuilderAbilities, WordPressAbilities (install_plugin),
  NavigationAbilities, SeoAbilities, WooCommerceAbilities
- Community plugins that register abilities: mcp-expose-abilities, ACF Pro, WooCommerce,
  Yoast SEO, designsetgo, filter-abilities, elementor-mcp, wp-agentic-admin
- Agent capabilities benchmark (ac-016 through ac-024) defines the target: restaurant
  site, portfolio, nonprofit events, Shopify migration, law firm design, blog readability,
  SaaS landing page, learning platform, healthcare accessibility

**Key design decisions:**
- Hybrid approach: build core abilities + smart plugin discovery, don't duplicate
  what plugins do well
- Agent should prefer plugins with Abilities API support > blocks > popularity
- Never build a form plugin -- install one and use its blocks/abilities
- Menus are too fundamental to outsource -- build our own ability
- CPT/taxonomy registration must persist without our plugin being the sole registrar
- Global styles manipulation uses the wp_global_styles CPT (WordPress internal)

#### Decision Log

(To be populated during implementation)

#### Surprises & Discoveries

(To be populated during implementation)

---

### [2026-04-14] Customer Feedback & Issue Reporting System

**Status:** In Progress (Phase 1 complete)
**Estimate:** ~25h (ai:20h test:3h read:2h)
**Repo (receiving plugin):** [Ultimate-Multisite/gratis-ai-feedback](https://github.com/Ultimate-Multisite/gratis-ai-feedback)

#### Purpose

There is no mechanism for customers to report when the AI agent fails, loops, or
produces bad results. The agent already detects spin (`exit_reason: spin_detected`)
and timeout (`exit_reason: timeout`) but nothing acts on these signals. Without
feedback from real-world usage, agent quality improvements are guesswork.

This system has three layers: detection (in-plugin), transport (REST API on a
central WordPress site), and processing (AI-assisted triage to GitHub issues).
Every report requires explicit user consent — no silent telemetry.

#### Architecture

```
Customer Site (gratis-ai-agent)         Central Site (gratis-ai-feedback)
┌─────────────────────────────┐        ┌──────────────────────────────┐
│ Detection triggers:          │        │ POST /gratis-feedback/v1/    │
│  - exit_reason spin/timeout  │──POST──│      reports                 │
│  - agent self-report ability │  +key  │  ↓                           │
│  - /report-issue command     │        │ ReportSanitizer (defense     │
│  - thumbs-down on message    │        │   in depth)                  │
│                              │        │  ↓                           │
│ ReportSanitizer (sender-side)│        │ DB: _feedback_reports        │
│ Consent UI (per-report)      │        │  ↓                           │
└─────────────────────────────┘        │ Triage automation            │
                                        │  → GitHub issue creation     │
                                        └──────────────────────────────┘
```

#### Progress

- [x] (2026-04-14) Phase 1: Receiving plugin — REST endpoint, DB, sanitizer, API key CLI
- [ ] Phase 2: Sender — settings UI, report builder, consent flow, auto-prompt on failures ~8h
- [ ] Phase 3: Sender — /report-issue command, report-inability ability, thumbs-down UI ~6h
- [ ] Phase 4: Triage automation — LLM review, dedup, GitHub issue creation ~6h

#### Task Breakdown

**Phase 1 — Receiving Plugin (COMPLETE)**
Shipped to `Ultimate-Multisite/gratis-ai-feedback`:
- Plugin bootstrap with PSR-4 autoloader (`GratisAiFeedback\` namespace)
- `_feedback_reports` table + `_feedback_api_keys` table (dbDelta migrations)
- `POST /gratis-feedback/v1/reports` — API key auth via `X-Feedback-Api-Key`, rate-limited
- `GET/PATCH /reports` — admin listing and triage status updates
- `ReportSanitizer` — strips API keys, passwords, emails, IPs, server paths, DB creds, auth headers
- `strip_tool_results()` — aggressive mode that removes all tool output
- WP-CLI: `wp gratis-feedback api-key generate/list/revoke`
- `uninstall.php` for clean removal

**Phase 2 — Sender: Settings + Consent + Auto-Prompt (t180-t183)**
- t180: Settings UI — feedback endpoint URL + API key fields in Settings > Advanced
- t181: Report payload builder — collects conversation, tool calls, env, sanitizes sender-side
- t182: Consent UI component — reusable modal/banner with preview of what gets sent
- t183: Auto-prompt on exit_reason — frontend reacts to spin_detected/timeout/max_iterations

**Phase 3 — Sender: Manual Triggers (t184-t186)**
- t184: `/report-issue` slash command in chat input
- t185: `report-inability` ability — agent self-flags when it cannot complete a task
- t186: Thumbs-down button on assistant messages

**Phase 4 — Triage Automation (t187)**
- t187: AI-assisted triage — LLM reviews new reports, classifies, deduplicates, creates GitHub issues

#### Context from Discussion

**Key design decisions:**
- Receiving plugin lives on an existing WordPress site (not a standalone service)
- Receiving plugin designed for expansion — `Plugin.php` singleton wires subsystems,
  future services (license validation, usage analytics) register there
- API key model: generated via WP-CLI on the receiving site, configured in the sender
  plugin's settings. SHA-256 hashed in DB. `gaf_` prefix for easy identification.
- Rate limiting: per API key, per hour (default 10, configurable per key)
- Sensitive data stripping runs on BOTH sides: sender strips before transmission,
  receiver strips again as defense-in-depth
- `strip_tool_results` option: user can choose to send conversation flow without any
  tool output (aggressive privacy mode). Keeps tool names/args but redacts responses.
- Consent is per-report, not a blanket opt-in. Auto-detected failures show a banner;
  user must click "Send Report" each time.
- Environment allowlist: only safe keys are transmitted (wp_version, php_version,
  plugin_version, theme, site_locale, is_multisite, active_plugins, etc.)
- Active plugins list strips paths, keeps only folder names (slug-level granularity)
- Site URL stripped to scheme+host only (no path)

**Existing infrastructure leveraged:**
- AgentLoop already returns `exit_reason` for `spin_detected` and `timeout` (lines 426, 609)
- RestController already passes `exit_reason` to the frontend (line 485-486)
- Frontend has slash command infrastructure in message input (`/remember`, `/forget`)
- `IdenticalFailureTracker` already nudges the model on spin — the report triggers after
  the nudge fails and the loop bails

#### Decision Log

- 2026-04-14: Chose WordPress plugin over Cloudflare Worker for receiving endpoint — keeps
  everything in the ecosystem, simpler deployment, can leverage WP-CLI for key management
- 2026-04-14: Chose API key auth over site-level tokens — allows per-site rate limiting and
  revocation without affecting other sites
- 2026-04-14: Chose separate `gratis-ai-feedback` repo over adding to `gratis-ai-agent` —
  the receiving plugin runs on a different site (the central server), not on customer sites

#### Surprises & Discoveries

(To be populated during implementation)

---

### [2026-04-16] Post-DI Code Quality & Structure Improvements

**Status:** Planning
**Estimate:** ~40h (ai:35h test:3h read:2h)
**Tasks:** t189–t197

#### Purpose

The x-wp/di container migration (PRs 1–6, merged 2026-04-16) moved all hook wiring
into `#[Handler]` classes and eliminated the 400-line bootstrap block from
`gratis-ai-agent.php`. But the DI layer is currently a thin veneer over the original
static architecture — handlers call `XxxClass::register()` statics, the `Database`
class is a 1,490-line God class, `AgentLoop` handles 8+ concerns, `Settings` is
entirely static, and `phpstan.neon` has 300 lines of suppressions largely caused by
untyped `stdClass` returns from `wpdb`.

This plan systematically addresses the structural debt exposed by the DI migration,
in dependency order so each improvement compounds the next.

#### Progress

- [ ] (t189) Phase 1: Split Database God Class into domain repositories ~6h
- [ ] (t190) Phase 2: Remove dead `register()` methods from ability classes ~1h
- [ ] (t191) Phase 3: Typed DTOs for database rows ~4h
- [ ] (t192) Phase 4: Convert Settings to injectable DI service ~3h
- [ ] (t193) Phase 5: Extract AgentLoop subresponsibilities ~8h
- [ ] (t194) Phase 6: Complete DI migration — CoreServicesHandler → real handlers ~4h
- [ ] (t195) Phase 7: Clean up phpstan.neon — dedup ignores + WP 7.0 AI Client stubs ~3h
- [ ] (t196) Phase 8: Move domain logic out of REST controllers ~2h
- [ ] (t197) Phase 9: Add interfaces for key contracts ~4h

#### Phase 1: Split Database God Class (t189)

**Goal:** Replace the 1,490-line `Database` class with focused repository classes.

Current `Database` handles 7 unrelated domains:
- Schema install + migration → `Infrastructure/Database/SchemaManager`
- Session CRUD + listing → `Models/SessionRepository`
- Usage logging + summaries → `Models/UsageRepository`
- Generated plugins CRUD → `PluginBuilder/GeneratedPluginRepository`
- Modified files tracking → `Models/ModifiedFileRepository`
- Shared sessions → `Models/SharedSessionRepository`
- Paused state → inline in `SessionRepository`

Each repository owns its table name constant, CRUD methods, and query methods.
`Database` becomes `SchemaManager` — only invoked on install/upgrade.

All existing callers (`RestController`, `SessionController`, `AgentLoop`, etc.)
update their references. PHPStan verifies no stale references remain.

#### Phase 2: Remove Dead register() Methods (t190)

**Goal:** Clean up ~35 ability classes that still have `register()` methods.

With the DI `AbilitiesHandler` calling `register_abilities()` directly on the
`wp_abilities_api_init` hook, the `register()` methods that internally do
`add_action('wp_abilities_api_init', [__CLASS__, 'register_abilities'])` are
dead code — never called by anything.

Remove all `register()` stubs. Verify with `git grep '::register()' includes/Abilities/`.

#### Phase 3: Typed DTOs for Database Rows (t191)

**Goal:** Eliminate 30-50% of phpstan.neon ignores by replacing `stdClass` returns.

Currently `wpdb::get_row()` returns `stdClass|null` and every caller accesses
`$row->field` with `@phpstan-ignore-next-line`. Create typed DTOs:

- `Models/DTO/SessionRow` — id, user_id, title, provider_id, model_id, status, etc.
- `Models/DTO/UsageRow` — id, user_id, session_id, tokens, cost
- `Models/DTO/MemoryRow` — id, category, content, timestamps
- `Models/DTO/AutomationRow` — id, name, schedule, config
- Additional DTOs as needed per repository

Each DTO has a static `from_row(object $row): self` factory. Repository methods
return typed DTOs instead of `object|null`.

Benefits from t189 (repositories own the mapping). Eliminates `Cannot access
property on mixed`, `Cannot cast mixed to int/string`, etc.

#### Phase 4: Injectable Settings Service (t192)

**Goal:** Make `Settings` a DI-injectable service instead of a static utility.

Currently `Settings::get()` is called statically everywhere. `AgentLoop` already
accepts `?Settings $settings_service` in its constructor — but the class has no
useful instance methods.

- Add instance methods: `get()`, `update()`, `get_defaults()`, `get_default_model()`
- Register as a singleton in the DI container via `Plugin::configure()`
- Inject into `AgentLoop`, `RestController`, `SessionController`, etc.
- Keep static methods as deprecated wrappers during transition

#### Phase 5: Extract AgentLoop Subresponsibilities (t193)

**Goal:** Reduce `AgentLoop` from ~1,500 lines to ~400 lines.

Extract into focused classes:
- `Core/SystemInstructionBuilder` — `build_system_instruction()` + memory/skill/context assembly
- `Core/ProviderCredentialLoader` — `ensure_provider_credentials_static()`
- `Core/ToolPermissionResolver` — `get_tools_needing_confirmation()`, `classify_ability()`, `set_always_allow()`
- `Core/SpinDetector` — `build_tool_signature()`, idle round tracking
- `Core/ClientAbilityRouter` — `partition_tool_calls()`, `build_client_ability_stubs()`, `get_client_ability_names()`
- `Core/ConversationSerializer` — `serialize_history()`, `deserialize_history()`

`AgentLoop` becomes a thin orchestrator composing these services.

#### Phase 6: Complete DI Migration (t194)

**Goal:** Convert `CoreServicesHandler` static `::register()` calls into real DI handlers.

Currently `CoreServicesHandler::on_initialize()` calls 10 static methods, each
internally doing `add_action()`. Convert each to a `#[Handler]` class with
`#[Action]` decorators, then remove `CoreServicesHandler`.

#### Phase 7: phpstan.neon Cleanup (t195)

**Goal:** Reduce 300-line ignoreErrors to <100 lines.

1. Deduplicate ~40 duplicate patterns
2. Write WP 7.0 AI Client stubs in `stubs/wordpress-7-runtime.php`
3. Remove ignores fixed by t191 (DTOs)

Benefits from t191 (DTOs) and t193 (AgentLoop typed returns).

#### Phase 8: Move Domain Logic from REST Controllers (t196)

**Goal:** Controllers only validate input → call service → format response.

- `upload_attachments_to_media_library()` → `Infrastructure/WordPress/MediaUploader`
- `generate_session_title()` → `Core/SessionTitleGenerator`
- Fix stale `AdminPage::SLUG` reference in `ScreenMetaPanel`

#### Phase 9: Interfaces for Key Contracts (t197)

**Goal:** Formalize dependency contracts for testing with mocks.

- `SessionRepositoryInterface` — from t189
- `SettingsProviderInterface` — from t192
- `BudgetCheckerInterface` — wraps `BudgetManager`

Blocked by t189 + t192.

#### Context from Discussion

**Analysis session (2026-04-16) findings:**
- DI migration successful: 24 handlers, 73-line bootstrap, Infrastructure layer started
- PHPStan level 10, 127 test files, strict types — good baseline
- Key debt: Database God class (1,490 lines), AgentLoop (1,500+ lines), static Settings,
  300-line phpstan.neon, dead register() stubs on all ability classes
- DI is a thin veneer — handlers call static `::register()` methods instead of using
  real dependency injection

**Dependency order:**
t189/t190 (standalone) → t191 (needs t189) → t192 (standalone) → t193 (needs t192) →
t194 (standalone) → t195 (needs t191) → t196 (standalone) → t197 (needs t189+t192)

#### Decision Log

(To be populated during implementation)

#### Surprises & Discoveries

(To be populated during implementation)

---

### [2026-04-17] Resumable Background Jobs & Multi-Session Chat

**Status:** Decomposed → TODO.md (t200-t208)
**Estimate:** ~10d (ai:8d test:1.5d read:4h)
**Tasks:** t200-t208 in TODO.md (9 auto-dispatch tasks across 5 phases + bundled improvements)

#### Purpose

Users should be able to send a prompt, navigate away (close tab, switch admin pages, open a different chat), and come back to see all progress made while they were gone. Permission prompts that fire while the user is away should persist and notify them via browser notifications. Multiple conversations should be manageable concurrently with tab-based UI.

The server-side architecture already supports this — `fastcgi_finish_request()` decouples processing from the browser, and messages are persisted to the DB on completion. The gaps are all on the frontend reconnection and notification layers.

#### Architecture Analysis (from discussion)

**What already works:**
- Server processes independently of browser (`fastcgi_finish_request`, `ignore_user_abort`)
- Job progress stored in transients (polling via `/job/{id}`)
- Messages persisted to DB on completion (`append_to_session`)
- Tool confirmation pause/resume (`awaiting_confirmation` state)
- Per-session job tracking in Redux (`sessionJobs` map — but in-memory only)
- All three chat surfaces (admin page, floating widget, screen-meta) share the **same** `ChatPanel` component and the **same** `@wordpress/data` Redux store singleton

**Code sharing between surfaces:**
- `src/components/` (7,541 lines) — 100% shared across all surfaces
- `src/store/` (3,403 lines) — singleton, shared across all surfaces on same page
- Unique to admin page: ~600 lines (sidebar, onboarding, shortcuts)
- Unique to floating widget: ~400 lines (tabs, drag/resize, FAB)
- Unique to screen-meta: ~80 lines (context builder)
- Any fix to `ChatPanel`, `message-list`, or the store works everywhere automatically

#### Progress

- [ ] (2026-04-17) Phase 1: DB-backed active job tracking — **foundation** ~2-3d
  - NEW: `_active_jobs` table (session_id, job_id UUID, user_id, status, pending_tools JSON, tool_calls JSON, timestamps)
  - `handle_run()` writes row on job creation
  - `handle_process()` updates row on status changes (alongside transient)
  - `handle_job_status()` falls back to DB when transient is gone
  - NEW: `GET /sessions/{id}/active-job` endpoint for reconnection
  - Make transient the **cache layer**, DB the **source of truth** (fixes lost-result-on-network-blip bug)
  - Frontend: `openSession()` calls active-job endpoint, resumes polling if job exists

- [ ] (2026-04-17) Phase 2: Session-scoped polling & visibility-aware throttling ~2d
  - Refactor `pollJob()` to be session-independent (don't check `currentJobId`)
  - Multiple concurrent poll loops — one per active session job
  - Exponential backoff: 1s → 5s (after 10 polls) → 10s (after 30 polls), reset on progress
  - `document.visibilitychange` listener: hidden → slow to 15-30s, visible → immediate poll + resume normal
  - NEW: `restoreActiveJobs()` thunk — `GET /sessions/active-jobs` on mount, start poll loops

- [ ] (2026-04-17) Phase 3: Browser notifications for permission prompts ~1d
  - Request `Notification.permission` on first tool confirmation (or via settings)
  - When `pollJob()` detects `awaiting_confirmation` and `document.hidden`:
    - Fire `new Notification()` with `requireInteraction: true`
    - Flash document title: "Approval needed — WP Admin"
  - Session sidebar: visual badge on sessions with pending confirmations
  - Clear notification on focus or when confirmation is resolved

- [ ] (2026-04-17) Phase 4: Cross-page navigation survival ~0.5d
  - Persist active job IDs to `sessionStorage` on poll start
  - On `FloatingWidget` mount: read sessionStorage, restore poll loops
  - Combined with Phase 1 DB endpoint gives full resilience:
    - `sessionStorage` → fast reconnect on same-tab wp-admin navigation
    - DB → reconnect after tab close/reopen or browser restart

- [ ] (2026-04-17) Phase 5: Tabbed multi-session chat UI ~2-3d
  - NEW: `ChatTabBar` component — open sessions as tabs above ChatPanel
  - Tabs show status indicators: spinner (processing), warning (needs confirmation), idle
  - Close tab removes from open set (session persists in sidebar)
  - `+` button creates new session tab
  - Store `openTabs: [sessionId, ...]` in Redux + localStorage
  - Works in both admin page and floating widget (already shares ChatPanel)

#### Bundled Code Improvements (on the path of phases 1-5)

These touch the same files and are natural to include:

1. **Split job logic from sessionsSlice** (2,042 lines) — extract `jobSlice.js` owning poll/confirm/reject lifecycle. Needed for Phase 2.
2. **Remove dead SSE state** — `streamingText`, `isStreaming`, `streamAbortController`, `APPEND_STREAMING_TEXT` (~80 lines of unused reducer code). Declutter before adding new state.
3. **Extract `useActiveToolCalls(sessionId)` hook** — replaces IIFE in message-list.js thinking bubble. Needed for Phase 5 multi-tab.
4. **Normalize session IDs on ingest** — cast to number once in `setSessions()`/`setCurrentSession()`, eliminate 8+ scattered `parseInt(session.id, 10)` calls.
5. **Dynamic context windows from provider API** — replace hardcoded `MODEL_CONTEXT_WINDOWS` (7 models) with `context_window` field from `/providers` response.
6. **Adaptive poll interval** — exponential backoff (part of Phase 2) saves server load especially with multiple concurrent session polls.

#### Context from Discussion

**Key decisions:**
- Transient becomes cache layer, DB becomes source of truth (fixes silent result loss on network blip or transient eviction)
- `JOB_TTL = 600` stays as transient cache duration but is no longer the authoritative timeout
- All notification/reconnection logic goes into shared components — automatic 3-surface coverage
- Tab bar UI (Phase 5) preferred over split-pane — simpler, more intuitive
- Dead SSE streaming code to be removed (the production path is exclusively job+poll)

**Open questions:**
- Should we add a WordPress admin notification (wp-admin notice bar) for pending confirmations in addition to browser notifications?
- Should the active-jobs table clean up completed rows on a schedule or keep them for audit?
- Should tab state persist across browser sessions (localStorage) or just within a session (sessionStorage)?

**Implementation order:**
Phase 1 (foundation) → Phase 2 (polling refactor) → Phase 4 (sessionStorage, trivial add-on) → Phase 3 (notifications, independent) → Phase 5 (tab UI, polish layer)

#### Decision Log

(To be populated during implementation)

#### Surprises & Discoveries

(To be populated during implementation)

---

### [2026-04-17] Improve Block Editor Content Quality — Auto-inject Skills + Rich Gutenberg Skill {#improve-block-editor-content-quality}

**Status:** Planning
**Estimate:** ~20h (ai:16h test:3h read:1h)

#### Purpose

Pages created by the AI agent look awful — raw markdown mixed with block markup, no layout sophistication, no columns/groups/covers/buttons. The root cause is threefold: (1) skills are opt-in and the LLM never loads them, (2) the gutenberg-blocks skill is too thin to be useful even if loaded (73 lines, zero block markup examples), and (3) the system prompt actively steers the LLM toward markdown-only output. This plan fixes the skill delivery mechanism, enriches the skill content, and fixes the mixed-content rendering bug.

#### Progress

- [ ] (2026-04-17) Phase 1: Fix `maybe_convert_markdown()` mixed-content bug ~2h
- [ ] (2026-04-17) Phase 2: Auto-inject skills based on task intent ~4h
- [ ] (2026-04-17) Phase 3: Rewrite gutenberg-blocks skill with comprehensive block markup reference ~6h
- [ ] (2026-04-17) Phase 4: Unhide block abilities + update system prompt + add validate-block-content ability ~8h

#### Context from Discussion

**Investigation findings (raising-cows-for-beef page):**
- Page content is a mix of proper `<!-- wp:image -->` blocks and raw markdown (`## headings`, `**bold**`, `- lists`)
- `PostAbilities::maybe_convert_markdown()` short-circuits when it finds `<!-- wp:` anywhere in content — even if 90% of the content is still raw markdown
- The LLM produced hybrid content (image blocks from stock image ability + markdown for text) and the converter never touched the markdown portions

**Skills system architecture gaps identified:**
1. **Skills are passive/opt-in** — `Skill::get_index_for_prompt()` injects a one-line index telling the LLM to "use skill-load tool when a request matches." The LLM ignores this because the system prompt already says "just write markdown."
2. **Block tools are hidden** — `markdown-to-blocks` and `create-block-content` have `'ai_hidden' => true`, so `resolve_abilities()` filters them out. The LLM literally cannot call them.
3. **System prompt contradiction** — Default system instruction (line 173) says "Write content directly using markdown" which conflicts with any skill that says "use block tools for layouts."
4. **Skill content is too thin** — `gutenberg-blocks.md` is 73 lines with zero actual serialized block markup examples. Without examples, even a loaded skill doesn't teach the LLM how to produce correct output.
5. **No skill auto-injection** — Knowledge base has RAG-based auto-injection (`Knowledge::get_context_for_query()`). Skills have nothing equivalent — they're purely manual.

**Key files:**
- `includes/Core/SystemInstructionBuilder.php` — builds system prompt, injects skill index
- `includes/Models/Skill.php` — skill model, `get_index_for_prompt()` produces the passive index
- `includes/Abilities/BlockAbilities.php` — block tools (markdown-to-blocks, create-block-content, etc.)
- `includes/Abilities/PostAbilities.php` — `maybe_convert_markdown()` bug location (line 775-813)
- `includes/Models/MarkdownToBlocks.php` — markdown-to-blocks converter (text-only, no layout blocks)
- `includes/Models/skills/gutenberg-blocks.md` — current skill content (73 lines, too thin)

**Design decisions:**
- Auto-inject skills into system prompt (like knowledge RAG) rather than relying on LLM to call skill-load
- Keyword matching for skill injection is sufficient — no need for semantic search
- Keep `create-block-content` as the primary tool for complex layouts, teach raw block markup for simple content
- `maybe_convert_markdown()` should handle the "freeform blocks between real blocks" case gracefully

#### Decision Log

(To be populated during implementation)

#### Surprises & Discoveries

(To be populated during implementation)
