# wp-erp-app-helper — CLAUDE.md

## Overview

A WeLabs scaffold/helper plugin for WP-ERP. Acts as a companion plugin that extends WP-ERP (`erp` is a required dependency) & ERP-Pro. Version: `0.0.1`. Text domain: `wp-erp-app-helper`.

**Deps:** WP ERP, ERP Pro.

## Architecture

```
wp-erp-app-helper/
├── wp-erp-app-helper.php          # Entry point — defines constants, loads autoloader, calls init()
├── includes/
│   ├── WpErpAppHelper.php         # Main singleton class (plugin lifecycle, hooks, template loader)
│   ├── Assets.php                 # Script/style registration & enqueueing
│   ├── PaymentRequestManager.php  # Bill request UI, menus, profile tab, AJAX handlers
│   ├── PaymentRequestController.php  # REST API endpoints for payment requests
│   └── PaymentRequestListTable.php   # WP_List_Table subclass for HR queue
├── templates/
│   ├── my-bill-requests.php           # Employee bill requests list (rendered in profile tab)
│   ├── payment-request-form-modal.php # New / edit bill request modal
│   └── payment-request-modal.php      # HR approve / reject modal
├── assets/
│   ├── admin/css/admin.css        # All admin styles (modals, badges, datepicker, success panel)
│   ├── admin/js/payment-request.js  # Bill request modal, submit, success flow
│   └── public/css|js/             # Frontend assets (style.css, script.js)
└── bin/build.sh                   # Release build script
```

## Key Patterns

### Singleton entry point

```php
welabs_wp_erp_app_helper()            // returns WpErpAppHelper::init()
welabs_wp_erp_app_helper()->version   // access public properties
welabs_wp_erp_app_helper()->scripts   // access container classes via __get()
```

### Constants (defined in `define_constants()`)

| Constant                                | Value                              |
| --------------------------------------- | ---------------------------------- |
| `WP_ERP_APP_HELPER_FILE`                | Main plugin file path              |
| `WP_ERP_APP_HELPER_DIR`                 | Plugin root directory              |
| `WP_ERP_APP_HELPER_INC_DIR`             | `includes/` directory              |
| `WP_ERP_APP_HELPER_TEMPLATE_DIR`        | `templates/` directory             |
| `WP_ERP_APP_HELPER_PLUGIN_ASSET`        | `assets/` URL                      |
| `WP_ERP_APP_HELPER_PLUGIN_ADMIN_ASSET`  | `assets/admin/` URL                |
| `WP_ERP_APP_HELPER_PLUGIN_PUBLIC_ASSET` | `assets/public/` URL               |
| `WP_ERP_APP_HELPER_LOAD_STYLE`          | `true` — toggle to disable styles  |
| `WP_ERP_APP_HELPER_LOAD_SCRIPTS`        | `true` — toggle to disable scripts |

### Template loading

```php
// Simple
welabs_wp_erp_app_helper()->get_template( 'admin/my-view.php' );

// With variables
welabs_wp_erp_app_helper()->get_template( 'admin/my-view.php', [
    'foo' => $foo,
    'bar' => $bar,
] );
```

Template path filter: `wp-erp-app-helper_template`

### Adding new classes

Register in `init_classes()` inside `WpErpAppHelper.php`:

```php
$this->container['my_feature'] = new MyFeature();
// Access: welabs_wp_erp_app_helper()->my_feature
```

### REST API

Add routes inside `register_rest_route()` — already hooked to `rest_api_init`.

### Action hooks provided

- `wp_erp_app_helper_loaded` — fires after plugin init
- `wp_erp_app_helper_before_template_part` — before template include
- `wp_erp_app_helper_after_template_part` — after template include

### JS global objects

- Admin: `Wp_Erp_App_Helper_Admin` (localized to `wp_erp_app_helper_admin_script`)
- Frontend: `Wp_Erp_App_Helper` (localized to `wp_erp_app_helper_script`)
- Payment requests: `erpPaymentRequest` (localized to `erp-app-helper-payment-request`)

## Features

### User Notes API

Per-user personal notes with GitHub-style colored labels. REST-only — no admin UI in v1.

**Namespace:** `erp-app/v1` (paths `/notes` and `/labels`)

**DB tables:** `erp_app_helper_notes`, `erp_app_helper_note_labels`, `erp_app_helper_note_label_relationships`, `erp_app_helper_note_attachments`

**Code:** `includes/Notes/` — `Schema`, `LabelsRepository`, `NotesRepository`, `LabelsController`, `NotesController`. Controllers wired in `WpErpAppHelper::init_classes()` as `notes_api` / `labels_api`.

**Highlights:**
- Labels are first-class per-user resources (`name`, `color #rrggbb`, optional `description`); cap 100 per user, 20 per note.
- Notes support pin / archive (boolean columns + intent-named POST endpoints), title `LIKE` search, date range, and label AND-filter (`?label=12&label=34`).
- Attachments are stock WP Media Library items — clients upload via `POST /wp/v2/media`, then pass IDs in `attachment_ids`. Ownership is verified server-side.
- Export endpoint streams JSON or CSV (capped at 1000 notes; CSV has formula-injection guard).
- Schema auto-installs on activation and on version-mismatch via `Schema::DB_VERSION_OPTION`.

Full reference: [`docs/notes-api.md`](docs/notes-api.md).

### Payment Request (Bill Submission) System

Employees submit expense reimbursement requests; HR reviews and approves or rejects them.

**DB tables:** `erp_payment_requests`, `erp_payment_request_attachments`

**Entry points:**

| Surface | URL | Who sees it |
|---|---|---|
| HR queue (admin menu) | `erp-hr` → Payment Requests | `erp_manage_hr_settings` |
| Employee profile tab | `erp-hr → People → Employee → view → tab=bill-requests` | Own profile + HR managers |
| Leave calendar dashboard button | `erp-hr` dashboard | `erp_list_employee` |

**How the profile tab works:**

`PaymentRequestManager` hooks into `erp_hr_employee_single_tabs` (filter, priority 10) to inject a `bill-requests` tab. The tab is shown only when `$employee->get_user_id() === get_current_user_id()` or the viewer has `erp_manage_hr_settings`. The tab callback `render_profile_tab( $employee )` renders `my-bill-requests.php` scoped to the viewed employee.

**How the dashboard button works:**

`PaymentRequestManager` hooks into `erp_hr_leave_calendar_actions` (action) and renders a "New Bill Request" `<a#erp-pr-open-form-modal>` button inside the leave calendar actions wrap. Scripts and modals are also enqueued on `page=erp-hr&section=dashboard`.

**Script / modal enqueue detection (`enqueue_scripts`, `render_modal_template`):**

```
$is_hr_queue    — page=erp-hr  &  section=payment-requests
$is_profile_tab — page=erp-hr  &  action=view  &  tab=bill-requests
$is_dashboard   — page=erp-hr  &  section=dashboard|''
```

**`listUrl` (post-submit redirect target):**

- Profile tab → employee profile `?tab=bill-requests`
- Dashboard → current user's own profile `?tab=bill-requests`
- HR queue → HR payment-requests page

**Submit success flow (JS):**

On AJAX success the form modal transitions to a success panel (`#erp-pr-form-success`): animated green checkmark, title, message, a 2 s CSS progress bar, then `window.location.href = erpPaymentRequest.listUrl`.

**AJAX actions:**

| Action | Handler | Cap |
|---|---|---|
| `erp_app_helper_submit_payment_request` | `ajax_submit_payment_request` | `erp_list_employee` |
| `erp_app_helper_update_payment_request` | `ajax_update_payment_request` | `erp_list_employee` (own pending only) |
| `erp_app_helper_review_payment_request` | `ajax_review_payment_request` | `erp_manage_hr_settings` |

**Attachments:** PDF / JPG / PNG, max 10 MB each, at least one required. Validated server-side (ownership, MIME, size).

## Namespace

`WeLabs\WpErpAppHelper` — PSR-4 autoloaded from `includes/`.

## Development Setup

```bash
composer install          # Install all dependencies (including dev)
composer run phpcs        # Lint PHP (WordPress Coding Standards)
composer run phpcbf       # Auto-fix linting issues
```

## Build / Release

```bash
chmod +x bin/build.sh
bin/build.sh              # Produces release zip, strips dev files
```

Production install (no dev deps):

```bash
composer install --optimize-autoloader --no-dev -q
```

## Coding Standards

- **Standard**: WordPress Coding Standards (WPCS) via `phpcs.xml`
- **PHP compatibility**: 7.4+, WP 5.4+
- **Text domain**: always `wp-erp-app-helper`
- Strict comparisons enforced (`===`) — violations are errors
- Short array syntax `[]` is allowed
- SQL queries: use `$wpdb->prepare()` — violations are warnings
- `var_export`, direct DB queries, and escape warnings are suppressed (severity 0) — use judgment anyway
