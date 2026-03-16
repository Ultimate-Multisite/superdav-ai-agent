# t064 — Onboarding interview: ask user about site goals after scan

**Session origin:** 2026-03-16 — /full-loop dispatch  
**Blocked by:** t063 (smart onboarding site scan)  
**Estimate:** ~4h  
**Tags:** feature

---

## What

After the site scan (t063) completes, present the user with a short conversational
interview asking targeted questions about their site goals, audience, and preferred
automations. Store the answers as agent memories so the AI has immediate, labelled
context from the first conversation.

## Why

The site scan (t063) collects technical facts (plugins, theme, post types, WooCommerce
status). It cannot infer intent. Without knowing the site's primary goal, target
audience, or content tone, the AI gives generic suggestions. The interview bridges
this gap: 3–6 questions, tailored to the detected site type, answered once, stored
permanently as memories.

## How

### PHP

- `includes/Core/OnboardingInterview.php` — new class:
  - `is_ready()` — scan complete AND interview not done
  - `is_done()` — complete or skipped option set
  - `get_questions()` — returns ordered question list tailored to `site_type`
    (ecommerce, lms, membership, blog, portfolio, brochure)
  - `save_answers(array $answers)` — stores each answer as a labelled memory
    in the appropriate category (site_info, user_preferences, workflows)
  - `mark_complete()`, `mark_skipped()`, `reset()`

- `includes/Core/OnboardingManager.php` — extended:
  - `GET /gratis-ai-agent/v1/onboarding/interview` — returns `{ready, done, questions}`
  - `POST /gratis-ai-agent/v1/onboarding/interview` — saves answers or marks skipped
  - `GET /gratis-ai-agent/v1/onboarding/status` — now includes `interview_ready` and
    `interview_done` fields
  - `rest_rescan()` — now also resets interview state

### JavaScript

- `src/components/onboarding-interview.js` — new React component:
  - Fetches questions from REST API on mount
  - One question per card, with previous answers shown as a summary above
  - Progress dots matching the wizard pattern
  - Required vs optional questions (required blocks Next until answered)
  - Enter key advances (Shift+Enter for newline)
  - "Skip all" dismisses without saving
  - Submits answers via POST on Finish

- `src/admin-page/index.js` — updated:
  - After wizard completes, polls `/onboarding/interview` until scan is done
  - Shows `OnboardingInterview` before the main chat UI
  - Polls every 3 s, gives up after 2 min (40 attempts)

- `src/admin-page/style.css` — interview CSS added

## Acceptance criteria

1. After the onboarding wizard completes on a new install, the interview is shown
   once the site scan finishes (or immediately if scan already done).
2. Questions are tailored to the detected site type (ecommerce gets product/sales
   questions; blog gets topic/frequency questions; etc.).
3. Answers are stored as agent memories with labelled prefixes
   (e.g. "Site primary goal: generate leads for my consulting business").
4. Skipping the interview (via "Skip all") marks it done and proceeds to chat.
5. Re-scanning via `/onboarding/rescan` resets the interview state.
6. `GET /onboarding/status` includes `interview_ready` and `interview_done` fields.
7. Build passes with zero new errors.
8. PHPCS passes with zero violations on new/modified PHP files.

## Context

- t063 stores scan results in `gratis_ai_agent_onboarding_scan` option with
  `site_type`, `post_count`, `woocommerce_active` fields.
- Memory categories: `site_info`, `user_preferences`, `technical_notes`,
  `workflows`, `general`.
- The floating widget does NOT show the interview — it uses the site builder
  overlay (t062) for fresh installs. The interview is admin-page only.
