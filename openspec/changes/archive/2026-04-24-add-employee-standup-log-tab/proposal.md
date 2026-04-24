## Why

Employees have no self-service view of their own daily standup logs within WP-ERP; they must ask HR or check external tools. Adding a "Standup Log" tab to the employee profile makes these logs visible directly inside the existing HR portal, reducing back-and-forth and keeping team activity data in one place.

## What Changes

- A new **Standup Log** tab is injected into the employee profile navigation, placed beside the existing Attendance tab, visible only to the employee (own profile) and HR managers.
- A `StandupLogManager` class is introduced to handle tab registration, data retrieval, and any AJAX interactions.
- A template `standup-log.php` renders a filterable table of standup entries scoped to the viewed employee.
- The logged-in employee sees their **current month** logs by default, with a month/year picker to browse historical months.
- No new REST endpoints are added in this change; data is fetched server-side on page load (and optionally via AJAX for month switching).

## Capabilities

### New Capabilities

- `employee-standup-log-tab`: Profile tab that surfaces the employee's standup log entries month-by-month, with a default of the current month and the ability to navigate to prior months.

### Modified Capabilities

<!-- None — no existing spec-level requirements are changing. -->

## Impact

- **Files added:** `includes/StandupLogManager.php`, `templates/standup-log.php`, CSS/JS additions to existing `assets/admin/`.
- **Files modified:** `includes/WpErpAppHelper.php` (register `StandupLogManager` in `init_classes()`), `assets/admin/css/admin.css` (tab styles), `assets/admin/js/payment-request.js` or a new `standup-log.js` (month-picker AJAX).
- **DB dependency:** Reads from the existing WP-ERP standup log table (table name TBD in design phase; assumed `{prefix}erp_hr_employee_standup` or similar ERP core table).
- **No breaking changes.**
