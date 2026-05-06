# Notes & Labels REST API

Namespace: `erp-app/v1`

All endpoints require an authenticated WordPress user. Resources are strictly scoped to the current user — accessing another user's note or label returns `404`.

## Labels (GitHub-style colored tags)

A label has:

| Field         | Type   | Notes                                                |
| ------------- | ------ | ---------------------------------------------------- |
| `id`          | int    | Server-assigned                                       |
| `name`        | string | 1–50 chars; case-insensitively unique per user        |
| `color`       | string | 6-char hex `#rrggbb`; server lowercases               |
| `description` | string | Optional, ≤ 200 chars                                 |
| `created_at`  | RFC3339 |                                                     |
| `updated_at`  | RFC3339 |                                                     |

Per-user cap: **100 labels**.

| Method | Path              | Purpose            |
| ------ | ----------------- | ------------------ |
| GET    | `/labels`         | List (page/per_page/search) |
| POST   | `/labels`         | Create              |
| GET    | `/labels/{id}`    | Read                |
| PATCH  | `/labels/{id}`    | Update              |
| DELETE | `/labels/{id}`    | Delete + detach from notes |

Errors:
- `400 rest_invalid_param` — bad name/color/description
- `400 rest_label_limit_reached` — user already has 100 labels
- `409 rest_label_name_conflict` — duplicate name (response includes `conflicting_id`)
- `404 rest_label_not_found`

## Notes

A note has:

| Field         | Type      | Notes                                                          |
| ------------- | --------- | -------------------------------------------------------------- |
| `id`          | int       |                                                                |
| `title`       | string    | 1–200 chars; required                                          |
| `content`     | string    | HTML, sanitized via `wp_kses_post`                             |
| `is_pinned`   | bool      |                                                                |
| `is_archived` | bool      |                                                                |
| `labels`      | object[]  | Resolved label objects (`id`, `name`, `color`, `description`)  |
| `attachments` | object[]  | `id`, `url`, `mime_type`, `filename` (deleted media omitted)    |
| `created_at`  | RFC3339   |                                                                |
| `updated_at`  | RFC3339   |                                                                |

Max 20 labels per note.

### Endpoints

| Method | Path                            | Purpose                          |
| ------ | ------------------------------- | -------------------------------- |
| GET    | `/notes`                        | List with filters                |
| POST   | `/notes`                        | Create                           |
| GET    | `/notes/{id}`                   | Read                             |
| PUT/PATCH | `/notes/{id}`                | Update                           |
| DELETE | `/notes/{id}`                   | Delete                           |
| POST   | `/notes/{id}/pin`               | Set `is_pinned=true`             |
| POST   | `/notes/{id}/unpin`             | Set `is_pinned=false`            |
| POST   | `/notes/{id}/archive`           | Set `is_archived=true`           |
| POST   | `/notes/{id}/unarchive`         | Set `is_archived=false`          |
| GET    | `/notes/export?format=json\|csv` | Download export (current filters apply) |

### Create / Update payload

```json
{
  "title": "Quarterly review notes",
  "content": "<p>Action items…</p>",
  "label_ids": [12, 34],
  "attachment_ids": [987]
}
```

`label_ids` and `attachment_ids` REPLACE the existing set on PATCH (not append).

### List filters (`GET /notes`)

| Param       | Notes                                                                  |
| ----------- | ---------------------------------------------------------------------- |
| `label`     | Label ID. **Repeatable** — multiple values mean AND (note must have all). |
| `date_from` | ISO date (`YYYY-MM-DD`); applied to `created_at`                       |
| `date_to`   | ISO date (`YYYY-MM-DD`); applied to `created_at`                       |
| `pinned`    | bool                                                                    |
| `archived`  | bool; defaults to `false` (archived hidden by default)                 |
| `search`    | Case-insensitive substring match on title                              |
| `page`      | Default 1                                                               |
| `per_page`  | Default 20, max 100                                                    |

Sort: `is_pinned DESC, created_at DESC`.

Response headers: `X-WP-Total`, `X-WP-TotalPages`.

### Attachments

Upload to `POST /wp/v2/media` first, then pass the returned attachment IDs in `attachment_ids`. The notes API rejects attachment IDs not authored by the current user (`400 rest_invalid_attachment`).

### Export

`GET /notes/export?format=json` or `?format=csv`. All list filters apply.

- Hard cap **1000** notes per request. If matched > cap, response includes `Warning` header.
- CSV labels column: `Bug (#d73a4a)|Urgent (#ff0000)`.
- CSV cells starting with `=`, `+`, `-`, `@` are prefixed with `'` (formula-injection guard).
- JSON labels are full label objects.

### Errors (notes)

- `400 rest_invalid_param` — title length, label count, etc.
- `400 rest_invalid_label` — label ID does not belong to the user
- `400 rest_invalid_attachment` — attachment does not exist or not owned by the user
- `404 rest_note_not_found`
- `401 rest_not_logged_in`
