## Context

The wp-erp-app-helper plugin already exposes a REST surface (`PaymentRequestController`) layered on top of WP-ERP. Mobile/admin clients need a lightweight personal-notes feature that is independent of HR/CRM records. There is no existing notes capability anywhere in WP-ERP, so this is greenfield within the plugin. Authentication piggybacks on standard WP REST nonce / application-password / JWT (already used by the companion app) — the plugin only enforces "user is logged in and owns the resource."

Per latest scope update, organization is done via **GitHub-style labels**: each user has their own label library (name + color + optional description), and notes are linked to labels via a many-to-many relationship. This replaces the original free-text comma-separated "flags" approach.

## Goals / Non-Goals

**Goals:**
- A clean REST API under `wp-erp-app-helper/v1/notes` for full note lifecycle.
- A sibling `/labels` resource giving each user a personal label library (CRUD, color-coded, like GitHub issue labels).
- Per-user isolation enforced server-side (a user can never read or modify another user's note or label).
- Filterable/searchable list (label id(s), date range, pinned, archived, title search) with pagination.
- Reuse WordPress Media Library for attachments — do not invent a new upload pipeline.
- Export the current user's notes as JSON or CSV.

**Non-Goals:**
- No sharing of notes or labels between users, no roles/teams, no comments.
- No system / preset labels in v1 (every label is user-created).
- No rich-text sanitization beyond `wp_kses_post`; no markdown rendering server-side.
- No version history, no soft-delete trash beyond the `is_archived` flag.
- No bulk import.
- No native mobile push for note reminders.

## Decisions

### 1. Storage: custom tables, not CPT
- **Choice**: Four custom tables — `{$wpdb->prefix}erp_app_helper_notes`, `{$wpdb->prefix}erp_app_helper_note_labels`, `{$wpdb->prefix}erp_app_helper_note_label_relationships`, and `{$wpdb->prefix}erp_app_helper_note_attachments`.
- **Why**: Notes need fast per-user filtering on indexed columns and joins for label-based queries. CPT + postmeta + taxonomy would force `meta_query`/`tax_query` joins, surface notes in admin Posts UI, and bind label colors awkwardly to term meta.
- **Alternatives considered**:
  - CPT + custom taxonomy: rejected — taxonomies are global by default, scoping per-user requires hacks; term color requires termmeta and rebuilding GitHub-style UX from scratch.
  - Single denormalized JSON column for labels on the note row: rejected — kills the "list all notes with label X" query and prevents safe rename/recolor.

### 2. Labels as a first-class resource
- **Choice**: A `note_labels` table (`id`, `user_id`, `name`, `color`, `description`, `created_at`, `updated_at`) with a unique index on `(user_id, lower(name))`. A `note_label_relationships` join table (`note_id`, `label_id`) links them many-to-many.
- **Why**: Mirrors GitHub's labels model — labels are reusable, renaming a label updates every linked note instantly, deleting a label cleanly detaches it from notes (ON DELETE CASCADE on the join). Color lives on the label, not the note.
- **Validation**:
  - Name: 1–50 chars, trimmed, case-insensitively unique per user.
  - Color: required, 6-char hex (`#RRGGBB`); server normalizes to lowercase. Reject 3-char shorthand and named colors for storage consistency.
  - Description: optional, ≤ 200 chars.
- **Cap**: max 100 labels per user (sanity bound, not a hard product limit).
- **Attaching to a note**: client sends `label_ids[]` of integer IDs. Server validates each label belongs to the current user; foreign IDs → `400`. Max 20 labels per note (matches GitHub's practical UX).

### 3. Attachments via WP Media Library
- **Choice**: Clients upload to `POST /wp/v2/media` and pass attachment IDs to the notes endpoints. We persist the join in `erp_app_helper_note_attachments(note_id, attachment_id)`.
- **Why**: Avoids re-implementing upload, MIME validation, and image sizing.
- **Validation**: On attach, verify each attachment's `post_author === current_user_id` — reject foreign attachment IDs.

### 4. Pin & Archive as boolean columns + dedicated POST actions
- **Choice**: `is_pinned` and `is_archived` are tinyint columns. Expose `POST /notes/{id}/pin`, `/unpin`, `/archive`, `/unarchive`.
- **Why**: Mobile clients prefer intent-named endpoints; under the hood it is one UPDATE.

### 5. Search
- **Choice**: Title-only `LIKE %term%` for v1. Sanitize with `$wpdb->esc_like()` and `prepare()`.

### 6. Label filtering semantics
- **Choice**: `?label=12&label=34` filters notes that have **all** specified labels (AND, GitHub-style). Implemented via `JOIN ... GROUP BY note_id HAVING COUNT(DISTINCT label_id) = N`.
- **Why**: Matches user expectation when stacking labels in a UI; OR-style filtering can be added later via a dedicated `?label_any=` param if needed.

### 7. Export
- **Choice**: `GET /notes/export?format=json|csv` streams a download. Honors current filters. Attachments → URLs only. Labels in CSV: `bug (#d73a4a)|enhancement (#a2eeef)`. Labels in JSON: full label objects.

### 8. Pagination & response shape
- **Choice**: `page` + `per_page` (default 20, max 100). Return `X-WP-Total` / `X-WP-TotalPages` headers like core REST.

### 9. Label deletion behavior
- **Choice**: Deleting a label removes it from the user's library and detaches it from every linked note (FK with `ON DELETE CASCADE` on `note_label_relationships`). Notes themselves are preserved.
- **Why**: Matches GitHub's "delete label" UX. Users do not expect notes to be deleted alongside labels.

## Risks / Trade-offs

- **Label rename collisions** → Renaming label "work" to "Work" on a user who already has "work" elsewhere → unique index conflict. Mitigation: case-insensitive uniqueness check returns `409 rest_label_name_conflict` with the conflicting id.
- **Color contrast / accessibility** → We do not enforce contrast ratios; clients are responsible for readable foreground. Accept any valid hex.
- **Attachment ownership drift** → If a user deletes a media item, note still references the ID. Mitigation: list endpoint silently drops missing attachments.
- **Export of large note sets** → Sync export could time out. Mitigation: hard cap at 1000 notes per export response in v1.
- **No row-level DB constraint on `user_id`** → Bug in controller could leak data. Mitigation: every repository method requires `$user_id` and adds it to the WHERE clause for both notes and labels.
- **CSV injection** → Cells starting with `=`, `+`, `-`, `@` can execute formulas in Excel. Mitigation: prefix such cells with a single quote on export.

## Migration Plan

- Activation hook in `WpErpAppHelper` runs `dbDelta()` for all four tables.
- No data migration (greenfield). Rollback = deactivate plugin; tables remain (matches existing plugin behavior for `erp_payment_requests`).
