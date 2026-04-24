## ADDED Requirements

### Requirement: HR can view the payment request queue
Users with the `erp_manage_hr_settings` capability or the `administrator` role SHALL have access to a "Payment Requests" page under ERP HR menu. The page displays all employee requests in a `WP_List_Table` with status tabs: Pending, Approved, Rejected, Withdrawn.

#### Scenario: HR views pending requests
- **WHEN** an HR manager navigates to ERP HR → Payment Requests
- **THEN** the Pending tab is shown by default, listing all requests with status `pending`, sorted by submission date descending

#### Scenario: HR filters by status tab
- **WHEN** an HR manager clicks the Approved, Rejected, or Withdrawn tab
- **THEN** only requests with that status are shown

#### Scenario: List table columns
- **WHEN** the list is displayed
- **THEN** each row shows: Employee name, Amount, Description (truncated), Attachment count with view links, Submitted date, Status badge, and Actions column

### Requirement: HR can approve a payment request
An HR manager SHALL be able to approve any `pending` request. No note is required for approval.

#### Scenario: Successful approval
- **WHEN** an HR manager clicks "Approve" on a pending request and confirms
- **THEN** the request status changes to `approved`, `reviewed_by` is set to the HR manager's user ID, `reviewed_at` is set to the current datetime, and the employee receives an email notification

#### Scenario: Cannot approve a non-pending request
- **WHEN** an HR manager attempts to approve a request that is not `pending`
- **THEN** the system SHALL deny the action

### Requirement: HR can reject a payment request with a mandatory note
An HR manager SHALL be able to reject any `pending` request. A rejection note (reason) is MANDATORY.

#### Scenario: Successful rejection with note
- **WHEN** an HR manager clicks "Reject", enters a rejection note, and confirms
- **THEN** the request status changes to `rejected`, `hr_note` is saved, `reviewed_by` and `reviewed_at` are set, and the employee receives an email notification with the rejection note

#### Scenario: Rejection blocked without note
- **WHEN** an HR manager attempts to reject without entering a note
- **THEN** the system SHALL display a validation error and not save the rejection

#### Scenario: Cannot reject a non-pending request
- **WHEN** an HR manager attempts to reject a request that is not `pending`
- **THEN** the system SHALL deny the action

### Requirement: HR can view attached files
An HR manager SHALL be able to view or download all files attached to a request directly from the list table.

#### Scenario: View attachments from list
- **WHEN** an HR manager clicks an attachment link in the list table
- **THEN** the file opens or downloads in the browser
