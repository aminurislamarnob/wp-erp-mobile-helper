## 1. Database & Activation

- [x] 1.1 Add a `Notes\Schema` class with `dbDelta()` SQL for `{$wpdb->prefix}erp_app_helper_notes` (id, user_id, title, content LONGTEXT, is_pinned TINYINT, is_archived TINYINT, created_at, updated_at; indexes on user_id, is_pinned, is_archived, created_at)
- [x] 1.2 Add `dbDelta()` SQL for `{$wpdb->prefix}erp_app_helper_note_labels` (id, user_id, name VARCHAR(50), color CHAR(7), description VARCHAR(200), created_at, updated_at; unique index on (user_id, name) — enforce case-insensitive uniqueness in the repository)
- [x] 1.3 Add `dbDelta()` SQL for `{$wpdb->prefix}erp_app_helper_note_label_relationships` (id, note_id, label_id; unique index on (note_id, label_id); FKs with ON DELETE CASCADE if engine supports, otherwise enforce in repository)
- [x] 1.4 Add `dbDelta()` SQL for `{$wpdb->prefix}erp_app_helper_note_attachments` (id, note_id, attachment_id, created_at; unique index on (note_id, attachment_id))
- [x] 1.5 Wire schema creation into the existing plugin activation hook in `WpErpAppHelper`
- [x] 1.6 Bump plugin version and add a `dbVersion` option for future migrations

## 2. Labels Repository & Controller

- [x] 2.1 Create `Notes\LabelsRepository` with `create($user_id, $data)`, `find($user_id, $id)`, `find_many($user_id, $ids)`, `query($user_id, $args)`, `update($user_id, $id, $data)`, `delete($user_id, $id)` — every method enforces `user_id` in WHERE
- [x] 2.2 Implement case-insensitive name uniqueness check (returns conflicting id) and 100-label cap in `LabelsRepository::create`
- [x] 2.3 Implement color normalization to lowercase 6-char hex; reject other formats
- [x] 2.4 Create `Notes\LabelsController` registering `GET/POST /labels`, `GET/PATCH/DELETE /labels/{id}` under `wp-erp-app-helper/v1`
- [x] 2.5 Implement label delete cascade — remove rows from `note_label_relationships` (DB cascade if available, otherwise explicit DELETE in repository)

## 3. Notes Repository

- [x] 3.1 Create `Notes\NotesRepository` with `create($user_id, $data)`, `find($user_id, $id)`, `update($user_id, $id, $data)`, `delete($user_id, $id)` — every method enforces `user_id` in WHERE
- [x] 3.2 Add `query($user_id, $args)` supporting `label` (single or array — AND semantics via `JOIN ... GROUP BY HAVING COUNT(DISTINCT label_id)=N`), date_from, date_to, pinned, archived, search, page, per_page; returns `[items, total]`
- [x] 3.3 Add label-link helpers: `set_labels($note_id, $user_id, $label_ids)` validates each label belongs to the user, caps at 20, replaces join rows in a transaction; `get_labels($note_id)` returns full label objects
- [x] 3.4 Add attachment helpers: `set_attachments($note_id, $user_id, $attachment_ids)` (validates ownership via `wp_get_attachment` author, replaces join rows in a transaction); `get_attachments($note_id)` returning resolved metadata, omitting deleted media
- [x] 3.5 Add `set_pinned($user_id, $id, bool)` and `set_archived($user_id, $id, bool)`

## 4. Notes REST Controller

- [x] 4.1 Create `Notes\NotesController` extending `WP_REST_Controller`, namespace `wp-erp-app-helper/v1`, base `notes`
- [x] 4.2 Register routes: GET/POST `/notes`, GET/PUT/PATCH/DELETE `/notes/(?P<id>\d+)`, POST `/notes/{id}/pin`, `/unpin`, `/archive`, `/unarchive`, GET `/notes/export`
- [x] 4.3 Implement `permission_callback` returning `is_user_logged_in()` for all routes; per-item handlers re-check ownership via repository
- [x] 4.4 Define `args` with sanitize/validate callbacks for title (1–200), content (`wp_kses_post`), `label_ids` (array of positive ints, ≤ 20), `attachment_ids` (array of positive ints), pagination, filters (note `label` arg accepts repeatable single-or-array)
- [x] 4.5 Implement create / read / update / delete handlers returning the schema-shaped item with embedded `labels` and `attachments`; map missing/foreign rows to `404 rest_note_not_found`
- [x] 4.6 Implement list handler with `X-WP-Total` / `X-WP-TotalPages` headers and pin-first sort (is_pinned DESC, created_at DESC)
- [x] 4.7 Implement pin/unpin/archive/unarchive handlers
- [x] 4.8 Implement export handler: JSON (full label objects) and CSV (`name (#color)` joined by `|`), honors filters, hard cap 1000 with `Warning` header, CSV formula-injection guard, `Content-Disposition` attachment header

## 5. Wiring

- [x] 5.1 Register `NotesController` and `LabelsController` in `WpErpAppHelper::init_classes()` container
- [x] 5.2 Hook `register_routes()` for both controllers from inside the existing `rest_api_init` flow
- [x] 5.3 Ensure PSR-4 autoload picks up `WeLabs\WpErpAppHelper\Notes\*`

## 6. Tests / Manual Verification

- [ ] 6.1 Manual: label CRUD as user A — create with valid hex, reject `red` and `#abc`, hit the duplicate-name 409, hit the 100-label cap; verify user B cannot read/update/delete A's labels
- [ ] 6.2 Manual: create, read, update, delete a note as user A; verify user B gets 404 on the same id
- [ ] 6.3 Manual: attach own labels to a note; attempt to attach a label owned by user B and verify 400; PATCH `label_ids` and confirm replace (not append) semantics
- [ ] 6.4 Manual: list with filters (`?label=` repeated for AND, date range, pinned, archived, search); confirm pin-first ordering + headers
- [ ] 6.5 Manual: upload a media item as user A, attach it to a note; attempt to attach a media item owned by user B and verify 400
- [ ] 6.6 Manual: pin/unpin and archive/unarchive flows
- [ ] 6.7 Manual: delete a label that is linked to several notes — confirm label is removed from all linked notes but notes themselves persist
- [ ] 6.8 Manual: export JSON and CSV; verify label serialization (`name (#color)|...` in CSV, full objects in JSON), formula-injection guard, and 1000-row cap warning

## 7. Documentation

- [x] 7.1 Add a `docs/notes-api.md` (or extend existing API docs) describing notes + labels endpoints, params, response shapes, and label color/format rules
- [x] 7.2 Update `CLAUDE.md` Features section with a "User Notes API" subsection covering notes + GitHub-style labels
