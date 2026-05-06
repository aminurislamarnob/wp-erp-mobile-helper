## ADDED Requirements

### Requirement: Authenticated note ownership
The system SHALL require an authenticated WordPress user for every notes and labels endpoint and SHALL restrict every read, update, and delete operation to resources whose `user_id` matches the current user's ID.

#### Scenario: Anonymous request is rejected
- **WHEN** an unauthenticated client calls any `wp-erp-app-helper/v1/notes` or `/labels` endpoint
- **THEN** the API responds `401 rest_not_logged_in`

#### Scenario: Cross-user access is rejected
- **WHEN** user A requests `GET /notes/{id}` (or `GET /labels/{id}`) for a resource owned by user B
- **THEN** the API responds `404 rest_note_not_found` (or `rest_label_not_found`) — not 403, to avoid existence leaks

### Requirement: Per-user label library
The system SHALL maintain a per-user label library and provide CRUD endpoints `GET /labels`, `POST /labels`, `GET /labels/{id}`, `PATCH /labels/{id}`, `DELETE /labels/{id}`.

A label SHALL have:
- `name`: required, 1–50 chars, trimmed; case-insensitively unique within a single user's library.
- `color`: required, 6-character hex string matching `/^#[0-9a-fA-F]{6}$/`; server normalizes to lowercase.
- `description`: optional, ≤ 200 chars.

A user SHALL be capped at 100 labels.

#### Scenario: Create a label
- **WHEN** the user POSTs `/labels` with `{ "name": "Bug", "color": "#D73A4A", "description": "Something is broken" }`
- **THEN** the API responds `201` with the created label, `color` lowercased to `#d73a4a` and a server-assigned `id`

#### Scenario: Duplicate label name rejected
- **WHEN** the user POSTs `/labels` with a name that case-insensitively matches an existing label they own
- **THEN** the API responds `409 rest_label_name_conflict` with the conflicting label's `id` in the response

#### Scenario: Invalid color rejected
- **WHEN** the user POSTs `/labels` with `color: "red"` or `color: "#abc"`
- **THEN** the API responds `400 rest_invalid_param`

#### Scenario: Label library cap
- **WHEN** the user already has 100 labels and POSTs `/labels`
- **THEN** the API responds `400 rest_label_limit_reached`

#### Scenario: Delete label detaches from notes
- **WHEN** the user DELETEs a label that is currently linked to several notes
- **THEN** the API responds `200` with `{ deleted: true, previous: <label> }`
- **AND** the label is removed from every linked note without deleting any note

### Requirement: Create note
The system SHALL allow an authenticated user to create a note with a title (required, 1–200 chars), optional content (HTML sanitized via `wp_kses_post`), optional `label_ids` (array of label IDs owned by the user, ≤ 20 entries), and optional `attachment_ids`.

#### Scenario: Successful creation
- **WHEN** the user POSTs `/notes` with a valid title
- **THEN** the API responds `201` with the created note including server-assigned `id`, `created_at`, `updated_at`, `is_pinned=false`, `is_archived=false`, and resolved `labels` array (full label objects, not just IDs)

#### Scenario: Missing title rejected
- **WHEN** the user POSTs `/notes` without a `title` (or with an empty title)
- **THEN** the API responds `400 rest_invalid_param`

#### Scenario: Foreign label rejected
- **WHEN** the user POSTs `/notes` with a `label_ids` entry pointing to a label owned by a different user
- **THEN** the API responds `400 rest_invalid_label` and does not persist the note

#### Scenario: Foreign attachment rejected
- **WHEN** the user POSTs `/notes` with an `attachment_ids` entry whose underlying media item was uploaded by a different user
- **THEN** the API responds `400 rest_invalid_attachment` and does not persist the note

#### Scenario: Too many labels rejected
- **WHEN** the user POSTs `/notes` with more than 20 `label_ids`
- **THEN** the API responds `400 rest_invalid_param`

### Requirement: Read note details
The system SHALL return a single note by ID with title, content, `labels` (resolved label objects: `id`, `name`, `color`, `description`), pin/archive state, timestamps, and resolved attachment objects (`id`, `url`, `mime_type`, `filename`).

#### Scenario: Owner reads own note
- **WHEN** the owner calls `GET /notes/{id}`
- **THEN** the API responds `200` with the full note payload including resolved labels and attachments

#### Scenario: Deleted attachment is omitted
- **WHEN** an attached media item has been deleted from WP since the note was saved
- **THEN** the response omits that attachment from the `attachments` array without erroring

### Requirement: Update note
The system SHALL allow the owner to update title, content, `label_ids`, and `attachment_ids` of a note via `PUT/PATCH /notes/{id}`. Sending `label_ids` SHALL replace the full label set on the note (not append).

#### Scenario: Successful update
- **WHEN** the owner PATCHes a note with a new title
- **THEN** the API responds `200` with the updated note and `updated_at` advanced

#### Scenario: Replacing labels
- **WHEN** the owner PATCHes a note with `label_ids: [5, 8]` (note previously had labels `[1, 5]`)
- **THEN** the resulting note has exactly labels `[5, 8]`

### Requirement: Delete note
The system SHALL allow the owner to delete a note via `DELETE /notes/{id}`. Deletion removes the row and the rows in `note_label_relationships` and `note_attachments` join tables but does NOT delete labels or media items.

#### Scenario: Successful delete
- **WHEN** the owner DELETEs a note
- **THEN** the API responds `200` with `{ deleted: true, previous: <note> }`
- **AND** the note row and its rows in both join tables are removed
- **AND** the labels themselves remain in the user's library

### Requirement: List notes with filters and search
The system SHALL provide `GET /notes` returning the current user's notes with pagination (`page`, `per_page` default 20, max 100), `X-WP-Total` and `X-WP-TotalPages` headers, and the following optional filters: `label` (label ID; repeatable — multiple values mean AND, the note must have ALL specified labels), `date_from` and `date_to` (ISO 8601, applied to `created_at`), `pinned` (bool), `archived` (bool, defaults to false), and `search` (case-insensitive substring match on title).

#### Scenario: Default list excludes archived
- **WHEN** the user calls `GET /notes` without `archived`
- **THEN** the response contains only notes where `is_archived=false`

#### Scenario: Filter by single label
- **WHEN** the user calls `GET /notes?label=12`
- **THEN** every returned note has label id `12` in its `labels` array

#### Scenario: Filter by multiple labels (AND)
- **WHEN** the user calls `GET /notes?label=12&label=34`
- **THEN** every returned note has BOTH label id `12` and label id `34` in its `labels` array

#### Scenario: Filter by date range
- **WHEN** the user calls `GET /notes?date_from=2026-01-01&date_to=2026-04-30`
- **THEN** every returned note's `created_at` falls within the inclusive range

#### Scenario: Search by title substring
- **WHEN** the user calls `GET /notes?search=quarterly`
- **THEN** every returned note's title contains `quarterly` (case-insensitive)

#### Scenario: Pinned notes appear first
- **WHEN** the user calls `GET /notes`
- **THEN** notes with `is_pinned=true` are returned before unpinned notes; secondary sort is `created_at DESC`

### Requirement: Pin and unpin
The system SHALL provide `POST /notes/{id}/pin` and `POST /notes/{id}/unpin` to toggle the `is_pinned` flag.

#### Scenario: Pin a note
- **WHEN** the owner POSTs `/notes/{id}/pin`
- **THEN** the API responds `200` and subsequent reads show `is_pinned=true`

### Requirement: Archive and unarchive
The system SHALL provide `POST /notes/{id}/archive` and `POST /notes/{id}/unarchive` to toggle the `is_archived` flag. Archived notes are excluded from default listings but retained.

#### Scenario: Archive hides from default list
- **WHEN** the owner archives a note
- **AND** then calls `GET /notes`
- **THEN** the archived note does not appear in the response

#### Scenario: Archived notes visible via filter
- **WHEN** the owner calls `GET /notes?archived=true`
- **THEN** only archived notes are returned

### Requirement: Attachments via WordPress media
The system SHALL accept attachment references as WP media attachment IDs and SHALL verify that each ID corresponds to a media item uploaded by the current user before persisting.

#### Scenario: Attach owned media
- **WHEN** the owner uploads a file via `POST /wp/v2/media` and then references its ID in `attachment_ids` on a note create or update
- **THEN** the note is saved with the attachment linked

### Requirement: Export notes
The system SHALL provide `GET /notes/export?format=json|csv` that returns the current user's notes as a downloadable file. The export SHALL honor the same filter parameters as the list endpoint and SHALL be capped at 1000 notes per request. Labels SHALL be serialized as full objects in JSON and as `name (#color)` joined by `|` in CSV.

#### Scenario: JSON export
- **WHEN** the owner calls `GET /notes/export?format=json`
- **THEN** the response has `Content-Type: application/json`, `Content-Disposition: attachment; filename="notes-<date>.json"`, and a JSON array of note objects (labels as full objects, attachments rendered as URLs)

#### Scenario: CSV export label serialization
- **WHEN** the owner calls `GET /notes/export?format=csv` for a note labeled with `Bug (#d73a4a)` and `Urgent (#ff0000)`
- **THEN** the labels column for that row contains `Bug (#d73a4a)|Urgent (#ff0000)`

#### Scenario: CSV export with formula-injection guard
- **WHEN** the owner calls `GET /notes/export?format=csv` and a note title begins with `=`, `+`, `-`, or `@`
- **THEN** that cell is prefixed with a single quote in the CSV output

#### Scenario: Cap enforced
- **WHEN** the filter would match more than 1000 notes
- **THEN** the API responds `200` with the first 1000 notes and a `X-WP-Total` header reflecting the true total, plus a `Warning` header noting the cap
