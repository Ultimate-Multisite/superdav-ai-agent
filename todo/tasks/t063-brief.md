# t063 Brief: Smart onboarding — agent scans existing site on first activation

**Task ID:** t063
**Status:** in_progress
**Estimate:** ~8h
**Logged:** 2026-03-15
**Session origin:** 2026-03-16 full-loop dispatch via GitHub issue #417

---

## What

On first plugin activation (or when no memories exist), automatically run a
background WP-Cron job that scans the WordPress site and stores the results as
agent memories. Also seeds the knowledge base with the first 50 published posts.

## Why

Without site context, the agent starts every conversation from zero. A one-time
scan gives the agent immediate awareness of the site's identity, plugins, theme,
post types, content volume, categories, and WooCommerce status — enabling
smarter, more relevant responses from the very first interaction.

## How

Two new classes in `includes/Core/`:

### `SiteScanner`
- WP-Cron hook: `gratis_ai_agent_site_scan` (single event, fires 10s after activation)
- Collects: site name/URL/tagline, WP version, language, active theme, active plugins,
  public post types, published post count, top-level categories, WooCommerce status
- Detects site type: `ecommerce`, `lms`, `membership`, `portfolio`, `blog`, `brochure`
- Stores results as `site_info` and `technical_notes` memories via `Memory::create()`
- Seeds knowledge base: creates `onboarding-site-content` collection (if knowledge
  feature enabled) and indexes up to 50 recent published posts
- Status tracked in `gratis_ai_agent_onboarding_scan` option

### `OnboardingManager`
- Registers `SiteScanner` cron handler
- `on_activation()` — called on plugin activation hook, triggers scan
- `maybe_trigger()` — called on `admin_init`, triggers scan if no memories exist
  and scan has never run (handles upgrades from pre-onboarding versions)
- REST endpoints:
  - `GET /gratis-ai-agent/v1/onboarding/status` — poll scan progress
  - `POST /gratis-ai-agent/v1/onboarding/rescan` — reset and re-run scan

### `gratis-ai-agent.php` changes
- `use` imports for `OnboardingManager` and `SiteScanner`
- `register_activation_hook` → `OnboardingManager::on_activation`
- `register_deactivation_hook` → `SiteScanner::unschedule`
- `OnboardingManager::register()` call in bootstrap

## Acceptance criteria

- [ ] On fresh plugin activation, a WP-Cron event fires within 10 seconds
- [ ] After the scan, `site_info` and `technical_notes` memories are populated
- [ ] Site type is correctly detected and stored as a memory
- [ ] Knowledge base is seeded with up to 50 posts (when knowledge feature enabled)
- [ ] `GET /gratis-ai-agent/v1/onboarding/status` returns scan status
- [ ] `POST /gratis-ai-agent/v1/onboarding/rescan` resets and re-triggers the scan
- [ ] Scan does not re-run on subsequent activations (idempotent)
- [ ] Existing installs with memories are not re-scanned automatically

## Context

Blocked by: nothing (this is the blocker for t064 and t071)
Blocks: t064 (onboarding interview), t071 (WooCommerce onboarding)

Files created:
- `includes/Core/SiteScanner.php`
- `includes/Core/OnboardingManager.php`

Files modified:
- `gratis-ai-agent.php` (activation hooks + bootstrap registration)
