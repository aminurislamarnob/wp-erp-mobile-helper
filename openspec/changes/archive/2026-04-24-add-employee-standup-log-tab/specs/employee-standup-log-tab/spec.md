## ADDED Requirements

### Requirement: Standup Log tab appears in employee profile navigation
The system SHALL inject a "Standup Log" tab into the employee profile tab list, positioned beside the Attendance tab. The tab SHALL be visible only when the viewer is the employee themselves (own profile) or has the `erp_manage_hr_settings` capability. All other roles SHALL NOT see the tab.

#### Scenario: Employee views their own profile
- **WHEN** a logged-in employee navigates to their own WP-ERP profile page
- **THEN** a "Standup Log" tab appears in the profile navigation alongside the Attendance tab

#### Scenario: HR manager views any employee profile
- **WHEN** a user with `erp_manage_hr_settings` navigates to any employee profile
- **THEN** a "Standup Log" tab appears in that employee's profile navigation

#### Scenario: Non-HR user views another employee's profile
- **WHEN** a logged-in user who is neither the profile owner nor an HR manager views an employee profile
- **THEN** no "Standup Log" tab is visible

### Requirement: Standup log defaults to current month
The system SHALL display the logged-in employee's standup entries for the current calendar month when the Standup Log tab is first loaded. A monthly summary (present count, absent count, leave count) SHALL appear above the table.

#### Scenario: Tab loaded with no month parameter
- **WHEN** the Standup Log tab is opened without a month query parameter
- **THEN** entries for the current calendar month are shown
- **THEN** a summary row above the table shows the count of present, absent, and leave days for that month

#### Scenario: No entries for the current month
- **WHEN** no standup records exist for the employee in the current month
- **THEN** an empty state message is displayed instead of an empty table

### Requirement: Employee can browse previous months
The system SHALL provide a `<input type="month">` picker that allows the viewer to load standup log entries for any prior month without a full page reload. Future months SHALL be disabled (max = current month).

#### Scenario: Viewer selects a prior month
- **WHEN** the viewer changes the month picker to a previous month
- **THEN** an AJAX request is sent and the table body and summary row update to show entries for the selected month
- **THEN** the page does not fully reload and the active profile tab remains visible

#### Scenario: Viewer attempts to select a future month
- **WHEN** the viewer attempts to select a month beyond the current calendar month
- **THEN** the picker prevents selection (max attribute enforced in HTML)
- **THEN** the AJAX handler rejects requests for future months with an error response

### Requirement: Each standup entry row shows date and status badge
The system SHALL render one table row per standup record with the date and a colour-coded status badge: present (green), absent (red), leave (yellow).

#### Scenario: Employee has mixed statuses in the selected month
- **WHEN** the Standup Log table is rendered for a month containing present, absent, and leave records
- **THEN** each row shows the formatted date and a badge matching the record's status with the appropriate colour

### Requirement: AJAX handler enforces authorization
The AJAX handler for standup log data SHALL verify that the requesting user is either the employee whose data is being fetched or has `erp_manage_hr_settings`, and SHALL validate the nonce before querying the database. Unauthorized requests SHALL return an error.

#### Scenario: Employee requests their own standup log via AJAX
- **WHEN** an employee sends a valid nonce and their own `employee_id`
- **THEN** the server returns the standup log HTML partial for the requested month

#### Scenario: Unauthorized user requests another employee's log via AJAX
- **WHEN** a user sends a request for an `employee_id` that is not their own without `erp_manage_hr_settings`
- **THEN** the server returns a `wp_die` or JSON error with a permissions message and does not return any data

#### Scenario: Request with invalid nonce
- **WHEN** a request is sent with an invalid or missing nonce
- **THEN** the server rejects the request via `check_ajax_referer` and no data is returned
