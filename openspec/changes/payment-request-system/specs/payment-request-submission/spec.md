## ADDED Requirements

### Requirement: Employee can submit a bill request
Any WP-ERP user with the `erp_leave_create_request` capability (employees, team leads, project leads) SHALL be able to submit a payment/reimbursement request containing a title, amount, short description, and one or more file attachments (PDF, JPG, or PNG, max 10 MB each).

#### Scenario: Successful submission via admin form
- **WHEN** an employee fills in title, amount, description, attaches at least one valid file, and submits the form
- **THEN** a new record is created in `erp_payment_requests` with status `pending`, attachments linked in `erp_payment_request_attachments`, and the employee sees their new request in "My Bill Requests"

#### Scenario: Submission with invalid file type
- **WHEN** an employee attaches a file that is not PDF, JPG, or PNG
- **THEN** the system SHALL reject the upload and display an error before the request is saved

#### Scenario: Submission with file exceeding 10 MB
- **WHEN** an employee attaches a file larger than 10 MB
- **THEN** the system SHALL reject the upload and display a size error before the request is saved

#### Scenario: Submission with missing required fields
- **WHEN** an employee submits the form without title, amount, or at least one attachment
- **THEN** the system SHALL display a validation error and not save the request

#### Scenario: Amount must be positive
- **WHEN** an employee enters zero or a negative amount
- **THEN** the system SHALL display a validation error and not save the request

### Requirement: Employee can withdraw a pending request
An employee SHALL be able to withdraw their own request if and only if its status is `pending`. Withdrawal sets status to `withdrawn` (soft delete — record is retained for audit).

#### Scenario: Withdraw a pending request
- **WHEN** an employee clicks "Withdraw" on a request with status `pending`
- **THEN** the request status is updated to `withdrawn` and it no longer appears in the active queue

#### Scenario: Cannot withdraw an approved or rejected request
- **WHEN** an employee attempts to withdraw a request with status `approved` or `rejected`
- **THEN** the system SHALL deny the action and display an appropriate message

#### Scenario: Cannot withdraw another employee's request
- **WHEN** an employee attempts to withdraw a request they did not create
- **THEN** the system SHALL return a permission error

### Requirement: File attachment via WP Media Library uploader (admin)
In the WP Admin submission form, file uploads SHALL use the `wp.media` JS uploader. Files are uploaded to the WordPress Media Library first; the resulting attachment IDs are stored with the request.

#### Scenario: Attach multiple files
- **WHEN** an employee opens the media uploader and selects multiple valid files
- **THEN** all selected files are listed as attachments on the form and submitted with the request

#### Scenario: Remove a file before submission
- **WHEN** an employee removes a file from the attachment list before submitting
- **THEN** that file's attachment ID is not included in the submitted request
