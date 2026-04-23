## 1. Database Schema

- [x] 1.1 Add `create_payment_requests_table()` to `Installer.php` — create `erp_payment_requests` table with all columns (id, employee_id, title, amount, description, status, hr_note, reviewed_by, reviewed_at, created_at, updated_at)
- [x] 1.2 Add `create_payment_request_attachments_table()` to `Installer.php` — create `erp_payment_request_attachments` table (id, request_id, attachment_id, file_type)
- [x] 1.3 Call both new table methods from `Installer::create_tables()`
- [ ] 1.4 Run plugin activation (or call `Installer::run()` manually) to verify tables are created correctly

## 2. PHP Class — PaymentRequestListTable

- [x] 2.1 Create `includes/PaymentRequestListTable.php` extending `WP_List_Table`
- [x] 2.2 Implement `get_columns()` — Employee, Amount, Description, Attachments, Submitted, Status, Actions
- [x] 2.3 Implement `get_views()` — Pending / Approved / Rejected / Withdrawn tabs with counts
- [x] 2.4 Implement `prepare_items()` — query `erp_payment_requests` joined with `wp_users`; filter by status and `current_user_id` (HR sees all, employee sees own)
- [x] 2.5 Implement `column_default()` — render each column; Attachments column shows file links from `erp_payment_request_attachments`; Actions column shows Approve/Reject buttons for HR on pending rows

## 3. PHP Class — PaymentRequestManager

- [x] 3.1 Create `includes/PaymentRequestManager.php` — constructor wires up all hooks
- [x] 3.2 Implement `register_erp_menu()` — add "Payment Requests" submenu (HR only: `erp_manage_hr_settings` / `administrator`) and "My Bill Requests" submenu (employees)
- [x] 3.3 Implement `render_hr_list_page()` — instantiate `PaymentRequestListTable`, call `prepare_items()`, render with wrap div
- [x] 3.4 Implement `render_employee_page()` — show employee's own requests list with "New Request" button; toggle to form view on `?view=new`
- [x] 3.5 Implement `enqueue_scripts()` — enqueue `payment-request.js` and localize nonce + ajaxurl on relevant admin pages
- [x] 3.6 Implement AJAX handler `erp_app_helper_submit_payment_request` — validate fields, validate attachment ownership and MIME types, insert into `erp_payment_requests`, insert attachment links, return success
- [x] 3.7 Implement AJAX handler `erp_app_helper_withdraw_payment_request` — verify ownership and pending status, set status to `withdrawn`
- [x] 3.8 Implement AJAX handler `erp_app_helper_review_payment_request` — HR approve (no note required) or reject (note required); update `reviewed_by`, `reviewed_at`; trigger email notification
- [x] 3.9 Implement `send_notification()` — call `erp_send_email()` (with `wp_mail()` fallback) on approve/reject; pass employee email, request title, amount, status, hr_note
- [x] 3.10 Implement `render_modal_template()` — output the rejection note modal on relevant admin pages via `admin_footer` hook

## 4. Templates

- [x] 4.1 Create `templates/payment-request-form.php` — employee submission form with fields: title (text), amount (number), description (textarea), file attachment area (wp.media uploader with hidden attachment_ids inputs), Submit button
- [x] 4.2 Create `templates/payment-request-modal.php` — reject modal (textarea for hr_note, Submit/Cancel buttons); mirrors `leave-approval-modal.php` structure

## 5. Frontend JS & CSS

- [x] 5.1 Create `assets/admin/js/payment-request.js` — wire up wp.media uploader: open media frame on button click, collect attachment IDs, render file list with remove buttons
- [x] 5.2 Add AJAX submit handler for the new request form — POST to admin-ajax, show success/error message, reload list on success
- [x] 5.3 Add AJAX handler for Withdraw button — confirm dialog, POST to admin-ajax, remove row on success
- [x] 5.4 Add AJAX handler for Approve button — confirm dialog, POST to admin-ajax, update row status on success
- [x] 5.5 Add AJAX handler for Reject button — open modal, POST to admin-ajax with hr_note, update row status on success
- [x] 5.6 Add CSS for status badges (pending/approved/rejected/withdrawn) and attachment list to `assets/admin/css/admin.css` (or new `payment-request.css`)

## 6. PHP Class — PaymentRequestController (REST API)

- [x] 6.1 Create `includes/PaymentRequestController.php` — set namespace `erp-app/v1`, implement `register_routes()`
- [x] 6.2 Register `POST /payment-requests` — permission: `erp_leave_create_request`; validate title, amount, description, attachment_ids; insert request and attachments; return full response object
- [x] 6.3 Register `GET /payment-requests` — permission: `erp_leave_create_request`; return authenticated employee's own requests
- [x] 6.4 Register `GET /payment-requests/{id}` — permission check (own request only); return full request with expanded attachments array
- [x] 6.5 Register `DELETE /payment-requests/{id}` — permission: own request, status must be `pending`; set status to `withdrawn`
- [x] 6.6 Register `GET /hr/payment-requests` — permission: `erp_manage_hr_settings`; support `?status=` filter; include employee name in each row
- [x] 6.7 Register `POST /hr/payment-requests/{id}/review` — permission: `erp_manage_hr_settings`; validate `action` (approve|reject) and `hr_note` (required if reject); update record; trigger notification
- [x] 6.8 Implement `format_request_response()` helper — builds the standard response shape with expanded `attachments` array using `wp_get_attachment_url()` and `get_post_mime_type()`
- [x] 6.9 Implement `validate_attachments()` helper — verify each `attachment_id` exists, belongs to current user (`post_author`), and has allowed MIME type (application/pdf, image/jpeg, image/png)

## 7. Plugin Registration

- [x] 7.1 Add `$this->container['payment_requests'] = new PaymentRequestManager();` in `WpErpAppHelper::init_classes()`
- [x] 7.2 Add `$this->container['payment_request_api'] = new PaymentRequestController();` in `WpErpAppHelper::init_classes()`
- [x] 7.3 Add `$this->container['payment_request_api']->register_routes();` in `WpErpAppHelper::register_rest_route()`

## 8. Validation & Edge Cases

- [x] 8.1 Verify 10 MB file size limit is enforced — add pre-upload JS check in `payment-request.js` and server-side MIME/size check in AJAX and REST handlers
- [x] 8.2 Verify attachment ownership check works — test with an attachment uploaded by a different user; confirm 400/error response
- [x] 8.3 Verify status transitions — confirm only `pending` requests can be approved/rejected/withdrawn; others return errors
- [x] 8.4 Verify employee cannot access HR endpoints — confirm 403 is returned for `GET/POST /hr/payment-requests` by a non-HR user
