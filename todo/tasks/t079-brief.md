# t079 Brief: Conversation templates — pre-built prompts for common tasks

**Task ID:** t079  
**Issue:** GH#389  
**Estimate:** ~3h  
**Status:** in-progress  

## Session origin

Dispatched via `/full-loop` from the main `ai-agent` repo, 2026-03-16.

## What

Add a "Templates" button to the chat message input that opens a panel of
pre-built prompt templates. Users can select a template to insert its prompt
text into the input, then edit and send it.

## Why

Users frequently start conversations with the same types of tasks (summarise
a page, fix grammar, explain code, write a blog post). Without templates they
must type these prompts from scratch every time. Templates reduce friction and
help new users discover what the agent can do.

## How

### PHP

- `includes/Models/ConversationTemplate.php` — model class with:
  - 10 built-in templates across 5 categories (general, content, writing,
    development, seo)
  - `get_all()`, `get()`, `create()`, `update()`, `delete()` static methods
  - `seed_builtins()` called from `Database::install()`
  - Built-in templates cannot be deleted (guarded in `delete()`)
- `includes/Core/Database.php` — new `sd_ai_agent_conversation_templates`
  table, DB version bumped to `9.0.0`, `ConversationTemplate::seed_builtins()`
  called on install
- `includes/REST/RestController.php` — four new endpoints:
  - `GET  /sd-ai-agent/v1/conversation-templates[?category=X]`
  - `POST /sd-ai-agent/v1/conversation-templates`
  - `PATCH /sd-ai-agent/v1/conversation-templates/{id}`
  - `DELETE /sd-ai-agent/v1/conversation-templates/{id}`

### JavaScript

- `src/store/index.js` — `conversationTemplates` + `conversationTemplatesLoaded`
  state; `setConversationTemplates` action; `fetchConversationTemplates`,
  `createConversationTemplate`, `updateConversationTemplate`,
  `deleteConversationTemplate` thunks; `getConversationTemplates`,
  `getConversationTemplatesLoaded` selectors; `SET_CONVERSATION_TEMPLATES`
  reducer case
- `src/components/conversation-template-menu.js` — new React component:
  - Fetches templates on mount (lazy, only if not already loaded)
  - Category filter tabs
  - Grid of `TemplateCard` buttons
  - Selecting a template calls `onSelect(prompt)` and closes the panel
- `src/components/message-input.js` — "Templates" button added to input area;
  selecting a template inserts the prompt text into the textarea
- `src/admin-page/style.css` — CSS for `.sd-ai-agent-template-menu`,
  `.sd-ai-agent-template-card`, `.ai-agent-input-row`,
  `.ai-agent-templates-btn`
- `src/floating-widget/style.css` — compact overrides for the floating panel

## Acceptance criteria

- [ ] "Templates" button appears in the chat input area (both admin page and
      floating widget)
- [ ] Clicking the button opens a panel listing all built-in templates
- [ ] Templates are grouped/filterable by category
- [ ] Selecting a template inserts its prompt text into the message input
- [ ] The panel closes after selection
- [ ] Built-in templates are seeded on plugin install/upgrade
- [ ] REST API returns templates at `GET /sd-ai-agent/v1/conversation-templates`
- [ ] Built-in templates cannot be deleted via the API
- [ ] User-created templates can be created, updated, and deleted via the API
- [ ] Build passes (`npm run build`)

## Context

The existing `SlashCommandMenu` component was used as a reference for the
overlay pattern. The `Skill` model was used as a reference for the DB/model
pattern. The `Automations::get_templates()` pattern was used as a reference
for the REST handler pattern.
