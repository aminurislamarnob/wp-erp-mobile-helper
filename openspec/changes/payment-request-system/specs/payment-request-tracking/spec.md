## ADDED Requirements

### Requirement: Employee can track their own bill requests
Any employee who has submitted requests SHALL see a "My Bill Requests" page under ERP HR menu showing only their own requests with current status.

#### Scenario: Employee views their requests list
- **WHEN** an employee navigates to ERP HR → My Bill Requests
- **THEN** they see a list of their own requests (all statuses) with columns: Title, Amount, Submitted date, Status badge, HR Note (if rejected), Actions

#### Scenario: Employee cannot see other employees' requests
- **WHEN** an employee views the My Bill Requests page
- **THEN** only requests where `employee_id` matches their user ID are shown

### Requirement: Employee can see HR rejection note
When a request is rejected, the employee SHALL be able to read the HR note explaining the reason.

#### Scenario: Rejection note visible on rejected request
- **WHEN** an employee views a request with status `rejected`
- **THEN** the HR note is displayed inline in the list row or detail view

#### Scenario: No note shown on approved request
- **WHEN** an employee views a request with status `approved`
- **THEN** no HR note field is displayed

### Requirement: Employee receives email notification on HR decision
When an HR manager approves or rejects a request, the employee SHALL receive an email notification via ERP's notification system.

#### Scenario: Email sent on approval
- **WHEN** an HR manager approves a request
- **THEN** the employee receives an email confirming the approval, including the request title and amount

#### Scenario: Email sent on rejection
- **WHEN** an HR manager rejects a request
- **THEN** the employee receives an email with the rejection reason (hr_note), request title, and amount

#### Scenario: No email sent on withdrawal
- **WHEN** an employee withdraws their own request
- **THEN** no email notification is sent

### Requirement: Employee can submit a new request from the tracking page
The "My Bill Requests" page SHALL include a prominent "New Request" button that opens the submission form.

#### Scenario: New request button visible
- **WHEN** an employee is on the My Bill Requests page
- **THEN** a "New Request" button is visible at the top of the page

#### Scenario: New request button leads to submission form
- **WHEN** an employee clicks "New Request"
- **THEN** they are taken to the submission form (same admin page, different view/tab)
