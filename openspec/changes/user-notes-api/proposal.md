## Why

WP-ERP users currently have no first-class place to capture personal notes (meeting jottings, follow-ups, reminders) tied to their account. Adding a lightweight, per-user notes REST API gives the companion mobile/admin clients a backbone for note capture, organization (GitHub-style colored labels, pin, archive), search/filter, attachments, and export — without coupling notes to any specific HR/CRM record.

## What Changes

- New REST namespace `wp-erp-app-helper/v1` with CRUD endpoints for personal notes scoped to the authenticated WP user.
- **Labels (GitHub-style tags)**: each user maintains their own label library — a label has `id`, `name`, `color` (hex), and optional `description`. Notes are linked to labels via a many-to-many join. Replaces the original free-text `flags` string.
- Note model: `id`, `user_id`, `title`, `content`, `labels[]` (label objects), `is_pinned`, `is_archived`, `attachments[]` (WP media IDs), timestamps.
- Label management endpoints: list / create / update / delete the current user's labels (like GitHub's per-repo labels page).
- Note listing endpoint with filters: `label` (id, repeatable for AND filter), `date_from`, `date_to`, `pinned`, `archived`, and `search` against title.
- Pin / unpin and archive / unarchive endpoints.
- Attachment handling via WP Media Library — clients upload through `wp/v2/media` and attach by ID; the notes API stores and validates ownership of attachment IDs.
- Export endpoint that returns the current user's notes as a downloadable file (JSON and CSV formats); CSV serializes labels as `name (#color)` joined by `|`.
- New DB tables `erp_app_helper_notes`, `erp_app_helper_note_labels` (label library), `erp_app_helper_note_label_relationships` (join), and `erp_app_helper_note_attachments` (join); activation hook creates them.

## Capabilities

### New Capabilities
- `user-notes`: Per-user personal notes — CRUD, GitHub-style colored labels (with their own CRUD endpoints), pin, archive, list with filters and search, attachments via WP media, and export.

### Modified Capabilities
<!-- None -->

## Impact

- Affected code: new `includes/Notes/` classes (`NotesController`, `LabelsController`, `NotesRepository`, `LabelsRepository`, `NotesSchema`), wiring in `WpErpAppHelper::init_classes()` and `register_rest_route()`.
- Affected DB: four new tables created on plugin activation; uninstall leaves data (no destructive uninstall in scope).
- Dependencies: WordPress core REST API + Media Library; no new composer packages.
- Auth: requires logged-in user (`is_user_logged_in()`); no new capability — every user manages their own notes and label library only.
- No breaking changes to existing payment-request features.
