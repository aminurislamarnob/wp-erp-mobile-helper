## Context

The plugin already has a leave approval workflow (`LeaveApprovalManager`, `RequiredApprovalListTable`) that serves as the architectural blueprint. It uses `WP_List_Table` for admin lists, AJAX for actions, PHP templates for modals, and the `Installer` class for schema changes. The new payment request system follows this exact pattern to keep the codebase consistent.

File storage delegates entirely to WordPress Media Library (`wp_posts`). A dedicated linking table (`erp_payment_request_attachments`) holds the many-to-one relationship between requests and attachments.

The REST API follows the existing `erp-app/v1` namespace established in `HrmController` and `StandupTrackerController`.

## Goals / Non-Goals

**Goals:**
- Full CRUD for employee bill requests in WP Admin (submit, view, withdraw)
- HR review queue with approve/reject and mandatory rejection note
- WP Media Library attachment support (PDF, JPG, PNG ≤ 10 MB)
- REST API parity with admin UI for mobile app
- Email notification to employee on HR decision (hook into ERP notification system)
- Consistent code patterns with existing plugin features

**Non-Goals:**
- Multi-level approval chains (single-level HR approval only)
- ERP accounting/payroll integration (request tracking only)
- Frontend (non-admin) submission page
- Batch approve/reject
- Budget limits or spending categories

## Decisions

### Decision: Two dedicated DB tables over ERP table extension

**Choice**: New tables `erp_payment_requests` + `erp_payment_request_attachments` via `Installer::create_tables()`.

**Rationale**: Payment requests are a separate domain from leaves/HR records. Extending ERP's tables (as done for leave approval columns) is acceptable for small additions but inappropriate for a new entity with its own lifecycle and relations. New tables are cleaner, easier to query, and don't risk breaking ERP core upgrades.

**Alternative considered**: Single table with a polymorphic `type` column. Rejected — adds complexity without benefit at this scale.

---

### Decision: WP Media Library for file storage

**Choice**: Use native WordPress attachment API (`wp_insert_attachment`, `wp_get_attachment_url`). Link via `erp_payment_request_attachments.attachment_id → wp_posts.ID`.

**Rationale**: Zero infrastructure overhead — no custom upload handler, no separate directory permissions. WordPress handles MIME type validation, file naming, and URL generation. Thumbnails generated automatically for images.

**Trade-off**: Uploaded files appear in the site's Media Library visible to other admins. Accepted — these are internal documents, not sensitive enough to warrant custom storage.

---

### Decision: AJAX for admin actions (not page reload)

**Choice**: Approve/Reject triggered via AJAX (`wp_ajax_*`), same as `process_leave_action` in `LeaveApprovalManager`.

**Rationale**: Consistent with existing UX. Avoid page reload on action. Modal for rejection note already works this way.

---

### Decision: REST API uses `/wp/v2/media` for file uploads (mobile)

**Choice**: Mobile app uploads files via the standard WordPress REST media endpoint, gets attachment IDs, then passes `attachment_ids[]` in the `POST /payment-requests` body.

**Rationale**: Avoids building a custom multipart upload endpoint. WP core already handles authentication, MIME validation, and size limits on `/wp/v2/media`. The plugin only needs to validate that the given attachment IDs belong to the authenticated user and are of allowed types.

---

### Decision: Capability checks mirror leave approval

**Choice**:
- Submit/view own: `erp_leave_create_request` cap (all ERP employees, team leads, project leads)
- HR review: `erp_manage_hr_settings` OR role `administrator`

**Rationale**: Reuses existing capability taxonomy. No new capabilities need to be defined.

---

### Decision: Email via ERP notification system

**Choice**: Use `erp_send_email()` or equivalent ERP hook on approve/reject, same as leave notifications.

**Rationale**: Keeps email templating consistent with the rest of ERP HR. Fallback to `wp_mail()` if ERP function unavailable.

## Risks / Trade-offs

- **Media Library pollution** → Accepted trade-off. Mitigate with clear file naming (`payment-request-{id}-{filename}`).
- **Attachment ownership not enforced by WP core** → The REST API must verify `attachment_id` was uploaded by the authenticated employee before linking. Validate via `get_post()->post_author === current_user_id()`.
- **10 MB limit enforcement** → WP's `upload_size_limit` filter may be lower than 10 MB on some hosts. The API should return a clear error and the admin JS should pre-validate before upload.
- **ERP notification function availability** → `erp_send_email()` may not exist in all ERP versions. Wrap in `function_exists()` with `wp_mail()` fallback.

## Migration Plan

1. Plugin activation triggers `Installer::run()` — `dbDelta()` creates new tables safely (no-op if tables exist).
2. No data migration needed — new feature with no existing data.
3. Rollback: deactivate plugin; tables remain but are inert. Manual `DROP TABLE` if needed.

## Open Questions

- Should withdrawn requests be hard-deleted or soft-deleted (status = `withdrawn`)? Soft-delete recommended for audit trail — add `withdrawn` to status enum.
- Should HR be able to re-open a rejected request (e.g., ask employee to resubmit)? Out of scope for now; employee can submit a new request.
