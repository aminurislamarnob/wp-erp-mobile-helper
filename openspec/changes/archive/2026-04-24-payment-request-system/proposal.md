## Why

Employees in WP-ERP have no built-in way to submit expense reimbursement requests with supporting documents — they rely on email or manual processes outside the system. This change adds a first-class bill/payment request workflow directly in ERP HR, with both admin UI and a REST API so the mobile app can participate.

## What Changes

- New DB tables: `erp_payment_requests` and `erp_payment_request_attachments`
- New admin menu page "Payment Requests" for HR managers to review, approve, and reject requests
- New admin menu page "My Bill Requests" for employees to submit and track their requests
- File attachments (PDF, JPG, PNG ≤ 10 MB) stored in WP Media Library and linked to requests
- AJAX-driven approve/reject with mandatory rejection note (mirrors leave approval UX)
- REST API under `erp-app/v1` for mobile app consumption
- Email notification to employee when HR acts on their request

## Capabilities

### New Capabilities

- `payment-request-submission`: Employee submits a bill request with title, amount, description, and file attachments; can withdraw a pending request
- `payment-request-review`: HR reviews the full request queue with status tabs; approves or rejects (rejection requires a note)
- `payment-request-tracking`: Employee tracks status of their own requests and reads HR rejection notes
- `payment-request-api`: REST endpoints for mobile — employee CRUD on own requests, HR review endpoint, response shape includes expanded attachment URLs

### Modified Capabilities

## Impact

- **New PHP classes**: `PaymentRequestManager`, `PaymentRequestListTable`, `PaymentRequestController`
- **Modified**: `Installer.php` — two new tables; `WpErpAppHelper.php` — register new classes
- **New templates**: `payment-request-form.php`, `payment-request-modal.php`
- **New assets**: `assets/admin/js/payment-request.js`, reuses `assets/admin/css/admin.css`
- **REST API**: 6 new endpoints under `erp-app/v1`
- **Dependencies**: WP Media Library (native), ERP notification system for emails
- **No breaking changes** to existing leave approval or standup features
