# t077: Shared Conversations

**Session origin:** Full-loop dispatch from issue #387  
**Task ID:** t077  
**Estimate:** ~6h  
**Status:** in_progress

## What

Allow multiple WordPress admins to view and continue the same AI conversation session. A session owner can share a session with all other admins; shared sessions appear in a "Shared" tab in the sidebar for all admins who have chat access.

## Why

In multi-admin WordPress sites, admins need to collaborate on AI-assisted tasks. Currently each admin has isolated sessions. Sharing enables handoff, review, and collaborative continuation of conversations.

## How

1. **Database**: Add `sd_ai_agent_shared_sessions` table (`session_id`, `shared_by`, `shared_at`). Bump DB version to `11.0.0`.
2. **REST permission**: Update `check_session_permission` to also allow access when the session is shared (any admin can read/write shared sessions).
3. **REST endpoints**:
   - `POST /sessions/{id}/share` — share a session (owner only)
   - `DELETE /sessions/{id}/share` — unshare (owner only)
   - `GET /sessions/shared` — list sessions shared with the current user
4. **Session responses**: Include `is_shared` and `shared_by` fields in session list/get responses.
5. **JS store**: Add `shareSession`, `unshareSession`, `fetchSharedSessions` thunks; add `sharedSessions` state.
6. **UI**: 
   - "Shared" tab in session sidebar filter tabs
   - Share/Unshare option in session context menu (owner only)
   - Visual indicator (badge) on shared sessions in the list

## Acceptance Criteria

- [ ] Admin A can share a session; it appears in Admin B's "Shared" tab
- [ ] Admin B can open and continue a shared session (send messages)
- [ ] Admin A can unshare; session disappears from Admin B's Shared tab
- [ ] Non-owners cannot share/unshare others' sessions
- [ ] Session ownership check still enforced for delete/archive/trash
- [ ] DB migration runs cleanly on upgrade (no data loss)
- [ ] Build passes with no JS errors

## Context

- Sessions table: `user_id` column is the owner. Currently `check_session_permission` enforces `session->user_id === current_user_id`.
- `list_sessions` filters by `user_id` — shared sessions need a separate query path.
- The `check_permission` (admin-only) gate already ensures only admins reach these endpoints.
