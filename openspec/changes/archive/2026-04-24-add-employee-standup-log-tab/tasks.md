## 1. StandupLogManager Class

- [x] 1.1 Create `includes/StandupLogManager.php` with namespace `WeLabs\WpErpAppHelper` and class `StandupLogManager`
- [x] 1.2 Add constructor that hooks into `erp_hr_employee_single_tabs` (filter, priority 10) and `admin_enqueue_scripts`
- [x] 1.3 Implement `add_profile_tab($tabs, $employee)` — inject `standup-log` tab only for own profile or `erp_manage_hr_settings`
- [x] 1.4 Implement `render_standup_log_tab($employee)` — query current month from `erp_standup_tracker` scoped to `employee_id` and pass data to template
- [x] 1.5 Implement `enqueue_scripts()` — enqueue `standup-log.js` and localize `erpStandupLog` (nonce, ajaxurl, employeeId) only when `page=erp-hr`, `action=view`, `tab=standup-log`
- [x] 1.6 Register AJAX action `wp_ajax_erp_app_helper_standup_log` mapped to `ajax_standup_log()` in the constructor
- [x] 1.7 Implement `ajax_standup_log()` — verify nonce, authorize (own or HR), sanitize `employee_id` and `month` (max = current month), query DB, return HTML partial via `get_template('standup-log-partial.php')`

## 2. Register in Plugin Container

- [x] 2.1 In `includes/WpErpAppHelper.php` `init_classes()`, add `$this->container['standup_log'] = new StandupLogManager();`
- [x] 2.2 Add `require_once` for `StandupLogManager.php` or confirm PSR-4 autoloading picks it up automatically (the autoloader already covers `includes/`)

## 3. Templates

- [x] 3.1 Create `templates/standup-log.php` — renders the full tab shell (month picker `<input type="month">`, summary row, table wrapper) with initial server-side populated data
- [x] 3.2 Create `templates/standup-log-partial.php` — renders only the summary row and `<tbody>` rows (date + status badge) for AJAX replacement
- [x] 3.3 Add empty-state markup in both templates when `$requests` is empty

## 4. JavaScript

- [x] 4.1 Create `assets/admin/js/standup-log.js` — listen for `change` on `#erp-sl-month-picker`, send AJAX request with nonce + employee_id + month, replace `#erp-sl-table-body` and `#erp-sl-summary` with response HTML
- [x] 4.2 Add loading indicator during AJAX (disable picker, show spinner, re-enable on response)
- [x] 4.3 Register and enqueue the handle in `Assets.php` or inside `StandupLogManager::enqueue_scripts()`

## 5. Styles

- [x] 5.1 Add CSS for `.erp-sl-badge-present` (green), `.erp-sl-badge-absent` (red), `.erp-sl-badge-leave` (yellow/amber) to `assets/admin/css/admin.css`
- [x] 5.2 Add CSS for the summary counts row above the table (flex row, icon + count pairs)
- [x] 5.3 Add CSS for the empty-state block inside the standup log tab

## 6. Verification

- [x] 6.1 Confirm tab appears on own profile and on any employee profile when logged in as HR manager
- [x] 6.2 Confirm tab is absent when a non-HR user views another employee's profile
- [x] 6.3 Confirm current month loads on first tab open, summary counts are correct
- [x] 6.4 Confirm month picker AJAX returns correct data for a prior month without page reload
- [x] 6.5 Confirm future months are blocked by the `max` attribute and rejected by the AJAX handler
- [x] 6.6 Confirm AJAX returns error on invalid nonce and on unauthorized `employee_id`
- [x] 6.7 Run `composer run phpcs` — zero new PHPCS errors
