## Context

The plugin already owns the `erp_standup_tracker` table (columns: `id`, `employee_id`, `standup_date`, `status` ∈ {present, absent, leave}, `created_by`, `created_at`, `updated_at`). HR records standup attendance daily via the Standup Tracker admin page. Employees have no way to see their own records. The employee profile tab mechanism is already established via the `erp_hr_employee_single_tabs` filter — the Bill Requests tab uses exactly this pattern inside `PaymentRequestManager`.

## Goals / Non-Goals

**Goals:**
- Surface standup log entries (date + status badge) for the viewed employee inside a new "Standup Log" profile tab.
- Default to the current calendar month; allow navigating to any prior month via a month picker (AJAX fetch, no full-page reload).
- Visible to the employee on their own profile and to HR managers on any profile — same visibility rule as the Bill Requests tab.
- Show a monthly summary row (present / absent / leave counts) at the top of the table.

**Non-Goals:**
- Employees cannot create, edit, or delete standup records — the tab is read-only.
- No new database tables or REST API endpoints are introduced.
- No pagination — monthly data volumes are small (≤ 31 rows per month).
- No export or CSV download in this change.

## Decisions

### 1. New class: `StandupLogManager`

**Decision:** Introduce a dedicated `StandupLogManager` class registered in `WpErpAppHelper::init_classes()` rather than extending `Standup.php`.

**Rationale:** `Standup.php` is the HR-facing tracker (requires `erp_manage_standup`); the log tab is employee-facing read-only data. Keeping them separate preserves single-responsibility and avoids capability leakage. Alternatives considered: adding the tab hook directly to `Standup.php` (rejected — mixes HR-write and employee-read concerns).

### 2. Month navigation via AJAX

**Decision:** Use a `<input type="month">` picker that triggers an AJAX request (`wp_ajax_erp_app_helper_standup_log`) returning an HTML fragment that replaces the table body and summary row.

**Rationale:** Matches the UX of the HR-facing standup tracker and avoids full page reloads that would reset the active profile tab. Alternatives considered: query-string reload (simpler but poor UX — resets the active tab context) and a full REST endpoint (over-engineered for a read-only HTML fragment).

### 3. Server-side rendered template

**Decision:** Initial render is server-side inside `render_standup_log_tab($employee)`; the AJAX handler returns the same partial via `get_template('standup-log-partial.php')`.

**Rationale:** No JavaScript dependency for the initial load; gracefully degrades if JS is disabled. Splitting template into a full view (`standup-log.php`) and a partial (`standup-log-partial.php`) lets AJAX reuse the table rows without re-rendering the chrome.

### 4. Capability check

**Decision:** Tab is shown when `$employee->get_user_id() === get_current_user_id()` OR `current_user_can('erp_manage_hr_settings')`. Data query is always scoped to `employee_id = $employee->get_user_id()`.

**Rationale:** Mirrors the exact rule used by the Bill Requests tab. HR managers can review standup compliance for any employee; employees can only see their own. The AJAX handler additionally verifies the requester can see the requested `employee_id` using the same rule before returning data.

### 5. Script/style enqueue scope

**Decision:** Enqueue a dedicated `standup-log.js` (inline or a new registered handle) only when `page=erp-hr`, `action=view`, `tab=standup-log` is detected in `admin_enqueue_scripts`. Reuse existing `admin.css` for badge styles (present/absent/leave badge classes already used in HR tracker context).

**Rationale:** Avoids loading JS on unrelated pages. Reusing CSS classes from the existing tracker reduces duplication.

## Risks / Trade-offs

- **Large employee count / missing standup data:** If an employee was never recorded in standup tracker (e.g., joined after the tracker was set up), the tab simply shows an empty state for those months — no risk of broken queries.
- **Month picker `max` attribute:** Set to the current month to prevent querying future months; enforced server-side in the AJAX handler as well.
- **AJAX nonce scope:** Nonce is localized per-page-load and tied to `erp_standup_log_nonce` action string, validated in the AJAX handler before any DB access.
