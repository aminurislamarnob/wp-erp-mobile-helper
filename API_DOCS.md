# WP-ERP App Helper API Documentation

This documentation outlines the REST API endpoints provided by the WP-ERP App Helper plugin for mobile app development.

## Base URL

The API base URL is: `https://your-site.com/wp-json/erp-app/v1`

## Authentication

The API uses **Bearer Token** authentication.

### 1. Login

Authenticate a user and obtain a persistent auth token.

- **Endpoint:** `POST /login`
- **Body Parameters:**
  | Parameter | Type | Required | Description |
  | :--- | :--- | :--- | :--- |
  | `username` | `string` | Yes | WordPress username or email. |
  | `password` | `string` | Yes | WordPress password. |

- **Response (Success - 200 OK):**
  ```json
  {
    "success": true,
    "token": "a-very-long-random-token-string",
    "user": {
      "id": 1,
      "name": "Admin",
      "slug": "admin",
      "roles": ["administrator"],
      "avatar_urls": {
        "24": "...",
        "48": "...",
        "96": "..."
      }
    }
  }
  ```

### 2. Logout

Revoke the current app auth token. Requires valid Bearer token.

- **Endpoint:** `POST /logout`
- **Headers:** `Authorization: Bearer <token>`
- **Response (Success - 200 OK):**
  ```json
  {
    "success": true
  }
  ```

---

## Standup Tracker

Endpoints 1–5 require the `erp_manage_standup` permission and a valid Bearer token. Endpoint 6 (`/standup/my-log`) requires only a valid Bearer token (any authenticated user).

### 1. Get History

List historical standup records grouped by date for a specific month.

- **Endpoint:** `GET /standup/history`
- **Headers:** `Authorization: Bearer <token>`
- **Query Parameters:**
  | Parameter | Type | Required | Description |
  | :--- | :--- | :--- | :--- |
  | `month` | `string` | No | Format `Y-m` (e.g., `2026-04`). Defaults to current month. |

- **Response:**
  ```json
  [
    {
      "standup_date": "2026-04-20",
      "present_count": "15",
      "absent_count": "2",
      "leave_count": "1"
    }
  ]
  ```

### 2. Get Employees for Date

Get employees with their shift/status for a specific date.

- **Endpoint:** `GET /standup/employees`
- **Headers:** `Authorization: Bearer <token>`
- **Query Parameters:**
  | Parameter | Type | Required | Description |
  | :--- | :--- | :--- | :--- |
  | `date` | `string` | Yes | Format `Y-m-d` (e.g., `2026-04-20`). |

- **Response:**
  ```json
  [
    {
      "employee_id": 5,
      "first_name": "John",
      "last_name": "Doe",
      "standup_status": "present",
      "name": "John Doe"
    }
  ]
  ```

### 3. Save Standup Records

Save or update standup records for a specific date.

- **Endpoint:** `POST /standup/save`
- **Headers:** `Authorization: Bearer <token>`
- **Body Parameters:**
  | Parameter | Type | Required | Description |
  | :--- | :--- | :--- | :--- |
  | `date` | `string` | Yes | Format `Y-m-d`. |
  | `records` | `array` | Yes | Array of objects: `{ "employee_id": int, "status": "present"|"absent"|"leave" }`. |

- **Response:**
  ```json
  {
    "success": true,
    "message": "Standup records saved successfully."
  }
  ```

### 4. Delete Standup Records

Delete all standup records for a specific date.

- **Endpoint:** `DELETE /standup/delete`
- **Headers:** `Authorization: Bearer <token>`
- **Query Parameters:**
  | Parameter | Type | Required | Description |
  | :--- | :--- | :--- | :--- |
  | `date` | `string` | Yes | Format `Y-m-d`. |

- **Response:**
  ```json
  {
    "success": true,
    "message": "Standup records deleted successfully."
  }
  ```

### 5. Get Monthly Report

Get aggregate report for a month.

- **Endpoint:** `GET /standup/report`
- **Headers:** `Authorization: Bearer <token>`
- **Query Parameters:**
  | Parameter | Type | Required | Description |
  | :--- | :--- | :--- | :--- |
  | `month` | `string` | Yes | Format `Y-m`. |

- **Response:**
  ```json
  {
    "total_working_days": 20,
    "stats": [
      {
        "employee_id": "5",
        "name": "John Doe",
        "attend": "18",
        "absent": "1",
        "leave": "1"
      }
    ]
  }
  ```

### 6. Get My Standup Log

Fetch the current authenticated user's own standup records. Supports filtering by calendar month or an arbitrary date range. Future dates are silently capped at today server-side.

- **Endpoint:** `GET /standup/my-log`
- **Headers:** `Authorization: Bearer <token>`
- **Permission:** Any authenticated user (Bearer token required).
- **Query Parameters:**

  `month` takes precedence over `from`/`to` when both are present.

  | Parameter | Type | Required | Description |
  | :--- | :--- | :--- | :--- |
  | `month` | `string` | No | Format `Y-m` (e.g., `2026-04`). Returns all records for that calendar month. Defaults to current month when no parameters are supplied. |
  | `from` | `string` | No | Format `Y-m-d`. Range start (inclusive). Defaults to first day of current month. |
  | `to` | `string` | No | Format `Y-m-d`. Range end (inclusive). Defaults to last day of current month. |

- **Response (200 OK):**
  ```json
  {
    "employee_id": 5,
    "filter": {
      "type": "month",
      "value": "2026-04"
    },
    "summary": {
      "present": 12,
      "absent": 3,
      "leave": 2,
      "total": 17
    },
    "logs": [
      { "date": "2026-04-24", "status": "present" },
      { "date": "2026-04-23", "status": "leave" },
      { "date": "2026-04-22", "status": "absent" }
    ]
  }
  ```

  When filtered by a date range, `filter` reflects the resolved bounds:
  ```json
  {
    "filter": {
      "type": "range",
      "from": "2026-01-01",
      "to": "2026-04-30"
    }
  }
  ```

- **Status values:** `present` | `absent` | `leave`

- **Example requests:**
  ```
  GET /standup/my-log
  GET /standup/my-log?month=2026-03
  GET /standup/my-log?from=2026-01-01&to=2026-04-30
  GET /standup/my-log?from=2026-04-01
  ```

---

## HRM (Leaves)

These endpoints provide leave request data with status filtering. Requires valid Bearer token and relevant permissions.

### 1. Get Pending Leaves

Get all pending leave requests for a specific employee.

- **Endpoint:** `GET /hrm/employees/{user_id}/pending-leaves`
- **Headers:** `Authorization: Bearer <token>`
- **Response:** Array of leave request objects.

### 2. Get Rejected Leaves

Get all rejected leave requests for a specific employee.

- **Endpoint:** `GET /hrm/employees/{user_id}/rejected-leaves`
- **Headers:** `Authorization: Bearer <token>`
- **Response:** Array of leave request objects.

---

## Intermediate Leave Approval Management

This feature adds required-approver workflow to leave requests.

### A) REST Response Enrichment (Read)

The plugin augments leave API responses with intermediate approval metadata.

- **Applies to routes:**
  - `GET /wp-json/erp/v1/hrm/leaves/requests...` (WP ERP native routes)
  - `GET /hrm/employees/{user_id}/pending-leaves`
  - `GET /hrm/employees/{user_id}/rejected-leaves`

- **Injected fields per leave item (when available):**
  - `required_approval`: object or `null`
    - `approver_id` (`int`)
    - `approver_name` (`string`)
    - `status` (`Pending|Approved|Rejected`)
  - `required_approval_message` (`string`)
  - `message` (`string`)

- **Example (item excerpt):**
  `json
    {
        "id": 123,
        "message": "Waiting for additional approval from John Doe",
        "required_approval": {
            "approver_id": 45,
            "approver_name": "John Doe",
            "status": "Pending"
        },
        "required_approval_message": "Waiting for additional approval from John Doe"
    }
    `

### B) Admin AJAX Endpoints (Write)

These endpoints are used by the ERP admin UI (`/wp-admin/admin-ajax.php`) for required approval actions.

- **Auth model:** Authenticated WordPress admin session + valid nonce.
- **Nonce key:** `nonce`
- **Nonce action:** `erp-app-helper-nonce`

#### 1) Get Team Leads

- **Endpoint:** `POST /wp-admin/admin-ajax.php`
- **Body Parameters:**
  - `action=erp_app_helper_get_team_leads`
  - `nonce` (required)
- **Response (success):**
  `json
    {
        "success": true,
        "data": [
            { "id": 12, "name": "Team Lead Name" }
        ]
    }
    `

#### 2) Save Required Approver

- **Endpoint:** `POST /wp-admin/admin-ajax.php`
- **Body Parameters:**
  - `action=erp_app_helper_save_required_approval`
  - `nonce` (required)
  - `request_id` (`int`, required)
  - `approver_id` (`int`, required)
- **Behavior:**
  - Updates leave request with:
    - `required_approver = approver_id`
    - `approval_status = Pending`
  - Adds a leave history row in `erp_hr_leave_approval_status`.
- **Response:**
  - Success: `{"success": true}`
  - Error: `{"success": false, "data": "Invalid request or approver."}`

#### 3) Process Leave Action (Intermediate Approver)

- **Endpoint:** `POST /wp-admin/admin-ajax.php`
- **Body Parameters:**
  - `action=erp_app_helper_process_leave_action`
  - `nonce` (required)
  - `request_id` (`int`, required)
  - `action_type` (`approve|reject`, required)
  - `message` (`string`, required for reject)
- **Behavior:**
  - Updates leave request with `approval_status` and `approval_message`.
  - Writes status history in `erp_hr_leave_approval_status`.
- **Response:**
  - Success: `{"success": true, "data": "Action processed successfully."}`
  - Error examples:
    - `{"success": false, "data": "Invalid request."}`
    - `{"success": false, "data": "Please provide a reason for rejection."}`

---

## User Account

### 1. Change Password

Change the authenticated user's password.

- **Endpoint:** `POST /user/change-password`
- **Headers:** `Authorization: Bearer <token>`
- **Permission:** Any authenticated user (Bearer token required).
- **Body Parameters:**
  | Parameter | Type | Required | Description |
  | :--- | :--- | :--- | :--- |
  | `current_password` | `string` | Yes | The user's current password. |
  | `new_password` | `string` | Yes | The new password (minimum 8 characters, must differ from current). |
  | `confirm_new_password` | `string` | Yes | Must match `new_password` exactly. |

- **Response (200 OK):**
  ```json
  {
    "success": true,
    "message": "Password updated successfully."
  }
  ```

- **Error responses:**
  | Code | Status | Reason |
  | :--- | :--- | :--- |
  | `missing_fields` | 400 | Any of the three fields is empty. |
  | `password_too_short` | 400 | New password is fewer than 8 characters. |
  | `password_mismatch` | 400 | `new_password` and `confirm_new_password` do not match. |
  | `same_password` | 400 | New password is identical to the current password. |
  | `wrong_password` | 403 | `current_password` is incorrect. |
  | `invalid_user` | 401 | Authenticated user record not found. |
  | `update_failed` | 500 | Database update failed. |

---

## Payment Request Management

Payment requests are available via REST endpoints for app clients, and AJAX endpoints for ERP admin screens.

### A) REST Endpoints (App)

All routes below are under: `https://your-site.com/wp-json/erp-app/v1`

#### 1) Create Payment Request

- **Endpoint:** `POST /payment-requests`
- **Headers:** `Authorization: Bearer <token>`
- **Permission:** `erp_list_employee` or `erp_manage_hr_settings` or `manage_options`
- **Body Parameters:**
  | Parameter | Type | Required | Description |
  | :--- | :--- | :--- | :--- |
  | `title` | `string` | Yes | Request title. |
  | `amount` | `number` | Yes | Must be greater than 0. |
  | `description` | `string` | Yes | Request details. |
  | `purchase_date` | `string` | No | Date in `Y-m-d` format. |
  | `expect_payment_by` | `string` | No | Date in `Y-m-d` format. |
  | `attachment_ids` | `array<int>` | Yes | WordPress media attachment IDs (PDF/JPG/PNG, max 10 MB each). |
  | `employee_id` | `int` | Conditional | Required for HR/admin submit; target employee user ID. |
- **HR Rules:**
  - HR/admin can submit requests on behalf of an employee by passing `employee_id`.
  - For HR/admin submit, attachment ownership is not restricted to current user (attachment must still exist and pass type/size validation).
  - Submitted rows store `created_by` as the acting user for downstream creator-based edit rules.
- **Response (201):** Standard payment request object.

#### 2) List Own Payment Requests

- **Endpoint:** `GET /payment-requests`
- **Headers:** `Authorization: Bearer <token>`
- **Permission:** `erp_list_employee`
- **Response:**
  ```json
  {
    "currency": "USD",
    "data": [ /* standard payment request objects */ ]
  }
  ```

#### 3) Get Single Own Payment Request

- **Endpoint:** `GET /payment-requests/{id}`
- **Headers:** `Authorization: Bearer <token>`
- **Permission:** `erp_list_employee`
- **Response:** Standard payment request object.

#### 4) HR List Payment Requests

- **Endpoint:** `GET /hr/payment-requests`
- **Headers:** `Authorization: Bearer <token>`
- **Permission:** `erp_manage_hr_settings` or `manage_options`
- **Query Parameters:**
  | Parameter | Type | Required | Description |
  | :--- | :--- | :--- | :--- |
  | `status` | `string` | No | Filter by status (`pending`, `approved`, `rejected`). |
- **Response:**
  ```json
  {
    "currency": "USD",
    "data": [ /* standard payment request objects plus employee_name */ ]
  }
  ```

#### 5) Get Currency

- **Endpoint:** `GET /currency`
- **Headers:** `Authorization: Bearer <token>`
- **Permission:** `erp_list_employee`
- **Response:** Currency code string (e.g., `"USD"`).

#### 6) HR Review Payment Request

- **Endpoint:** `POST /hr/payment-requests/{id}/review`
- **Headers:** `Authorization: Bearer <token>`
- **Permission:** `erp_manage_hr_settings` or `manage_options`
- **Body Parameters:**
  | Parameter | Type | Required | Description |
  | :--- | :--- | :--- | :--- |
  | `action` | `string` | Yes | `approve` or `reject`. |
  | `payment_type` | `string` | Conditionally | Required when `action=approve`; allowed: `cash`, `bank_transfer`. |
  | `hr_note` | `string` | Conditionally | Required when `action=reject`. |
- **Response:** Updated standard payment request object.

#### Standard Payment Request Object (REST)

```json
{
  "id": 41,
  "title": "Internet Bill - April",
  "amount": "1200.00",
  "description": "Monthly internet bill",
  "purchase_date": "2026-04-20",
  "expect_payment_by": "2026-04-30",
  "status": "pending",
  "payment_type": null,
  "hr_note": null,
  "reviewed_by": null,
  "reviewed_at": null,
  "created_at": "2026-04-23 10:20:30",
  "attachments": [
    {
      "id": 501,
      "url": "https://your-site.com/wp-content/uploads/.../invoice.pdf",
      "type": "pdf",
      "filename": "invoice.pdf"
    }
  ]
}
```

### B) Admin AJAX Endpoints (ERP Admin UI)

- **Endpoint (all):** `POST /wp-admin/admin-ajax.php`
- **Auth model:** Authenticated WordPress admin session + valid nonce.
- **Nonce key:** `nonce`
- **Nonce action:** `erp-payment-request-nonce`

#### 1) Submit Payment Request

- **Body Parameters:**
  - `action=erp_app_helper_submit_payment_request`
  - `nonce` (required)
  - `title` (required)
  - `amount` (required)
  - `description` (required)
  - `purchase_date` (optional)
  - `expect_payment_by` (optional)
  - `attachment_ids[]` (required)
- **Permission:** `erp_list_employee` or `erp_manage_hr_settings` or `manage_options`
- **HR Rule:** When submitted by HR/admin, `employee_id` is required and must be a valid user.
- **Response:**
  - Success: `{"success": true, "data": {"id": 41}}`

#### 2) Update Pending Payment Request

- **Body Parameters:**
  - `action=erp_app_helper_update_payment_request`
  - `nonce` (required)
  - `request_id` (required)
  - `title` (required)
  - `amount` (required)
  - `description` (required)
  - `purchase_date` (optional)
  - `expect_payment_by` (optional)
  - `attachment_ids[]` (required)
- **Permission:** `erp_list_employee` or `erp_manage_hr_settings` or `manage_options`
- **Rule:** Only `pending` requests can be edited.
- **HR Edit Rule:** HR/admin can edit a pending request only if they are the original creator of that request (`created_by` matches current user). Employees can edit only their own pending requests.
- **Response:**
  - Success: `{"success": true, "data": {"id": 41}}`

#### 3) Review Payment Request (HR)

- **Body Parameters:**
  - `action=erp_app_helper_review_payment_request`
  - `nonce` (required)
  - `request_id` (required)
  - `action_type` (`approve|reject`, required)
  - `payment_type` (`cash|bank_transfer`, required when approve)
  - `hr_note` (required when reject)
- **Permission:** `erp_manage_hr_settings` or `manage_options`
- **Rule:** Only `pending` requests can be reviewed.
- **Response:**
  - Success: `{"success": true, "data": "Request updated successfully."}`
