## ADDED Requirements

### Requirement: Employee REST endpoints for own requests
The system SHALL expose authenticated REST endpoints under `erp-app/v1` for employees to manage their own payment requests from a mobile app.

#### Scenario: Submit a new request via API
- **WHEN** an authenticated employee sends `POST /erp-app/v1/payment-requests` with `title`, `amount`, `description`, and `attachment_ids[]`
- **THEN** a new request is created with status `pending` and the response returns the full request object including expanded attachment URLs

#### Scenario: List own requests via API
- **WHEN** an authenticated employee sends `GET /erp-app/v1/payment-requests`
- **THEN** the response returns an array of the employee's own requests (all statuses) with status, amount, and attachment count

#### Scenario: Get single request detail via API
- **WHEN** an authenticated employee sends `GET /erp-app/v1/payment-requests/{id}`
- **THEN** the response returns the full request object with expanded attachments array (id, url, type, filename)

#### Scenario: Withdraw a pending request via API
- **WHEN** an authenticated employee sends `DELETE /erp-app/v1/payment-requests/{id}` for their own pending request
- **THEN** the request status changes to `withdrawn` and a 200 response is returned

#### Scenario: Cannot access another employee's request via API
- **WHEN** an authenticated employee sends `GET /erp-app/v1/payment-requests/{id}` for a request they did not create
- **THEN** the system SHALL return a 403 Forbidden error

### Requirement: HR REST endpoints for reviewing all requests
The system SHALL expose HR-only REST endpoints for viewing and acting on all payment requests.

#### Scenario: HR lists all requests with status filter
- **WHEN** an authenticated HR manager sends `GET /erp-app/v1/hr/payment-requests?status=pending`
- **THEN** the response returns all requests matching the status filter, each including employee name, amount, and attachment count

#### Scenario: HR approves a request via API
- **WHEN** an authenticated HR manager sends `POST /erp-app/v1/hr/payment-requests/{id}/review` with body `{"action": "approve"}`
- **THEN** the request status changes to `approved`, timestamps set, and response returns updated request object

#### Scenario: HR rejects a request via API with note
- **WHEN** an authenticated HR manager sends `POST /erp-app/v1/hr/payment-requests/{id}/review` with body `{"action": "reject", "hr_note": "Missing receipt"}`
- **THEN** the request status changes to `rejected`, `hr_note` saved, and response returns updated request object

#### Scenario: HR rejection blocked without note via API
- **WHEN** an HR manager sends `POST .../review` with `action: reject` and empty or missing `hr_note`
- **THEN** the system SHALL return a 400 Bad Request error with a descriptive message

### Requirement: API response shape for a single request
The system SHALL return a consistent JSON response shape for individual request objects.

#### Scenario: Full response shape
- **WHEN** any endpoint returns a single payment request object
- **THEN** the response SHALL include: `id`, `title`, `amount` (string, 2 decimal places), `description`, `status`, `hr_note` (null if none), `reviewed_by` (object with `id` and `name`, or null), `reviewed_at`, `created_at`, `attachments` (array of objects with `id`, `url`, `type`, `filename`)

### Requirement: Attachment ownership validation on submission
The system SHALL verify that attachment IDs submitted via the API belong to the authenticated employee and are of allowed MIME types (PDF, JPG, PNG).

#### Scenario: Valid attachments accepted
- **WHEN** an employee submits `attachment_ids` that they uploaded and are PDF/JPG/PNG
- **THEN** the request is saved with those attachments linked

#### Scenario: Unowned attachment rejected
- **WHEN** an employee submits an `attachment_id` that was uploaded by a different user
- **THEN** the system SHALL return a 400 error and not save the request

#### Scenario: Invalid MIME type attachment rejected
- **WHEN** an employee submits an `attachment_id` whose MIME type is not `application/pdf`, `image/jpeg`, or `image/png`
- **THEN** the system SHALL return a 400 error listing the invalid attachment

### Requirement: Mobile file upload uses native WP media endpoint
The system SHALL rely on the standard WordPress REST API `/wp/v2/media` endpoint for file uploads from mobile. No custom upload endpoint is provided.

#### Scenario: Mobile upload flow
- **WHEN** a mobile user selects files to attach to a payment request
- **THEN** the app uploads each file to `POST /wp/v2/media` (standard WP auth), collects the returned attachment IDs, and includes them in the `POST /erp-app/v1/payment-requests` body
