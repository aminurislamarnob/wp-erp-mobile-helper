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

All standup tracker endpoints require the `erp_manage_standup` permission and a valid Bearer token.

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
