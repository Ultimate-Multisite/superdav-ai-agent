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
