# WP ERP App Helper

The WP ERP App Helper plugin provides extended functionality for the WP ERP ecosystem, specifically focused on enhancing HRM capabilities and reporting.

## Key Features

### Human Resource Management (HRM)
- **Daily Standup Tracker**: Integrated tool to record and monitor daily standup meetings with aggregate monthly reporting.
- **Attendance & Shift Integration**: Leverages WP-ERP's internal shift logic to ensure tracking is context-aware.
- **Intermediate Leave Approval**: Adds a modern "Team Lead" approval workflow. Leave requests can be assigned to a specific approver before final HR processing.
- **Team Lead Role**: New dedicated local role for managing department-level approvals without granting full HR Manager permissions.
- **Custom Approver Dashboard**: A dedicated workspace for Team Leads to manage pending, approved, and rejected requests with reason-based feedback.
- **Employee Transparency**: Live status tracking for employees, showing exactly where their request is in the approval chain (Waiting for, Approved by, or Rejected by).

### REST API (App Integration)
- **Mobile-Ready Endpoints**: Fully documented API (`/erp-app/v1/hrm/...`) for fetching pending and rejected leaves, complete with intermediate approval status and messages.
- **Enhanced Data Injection**: Seamless integration with core WP-ERP endpoints to include "Required Approval" metadata in standard JSON responses.

---

### 🚧 For Development Environment
Run the following command for development environment.
```
composer install

```

To update dependency versions according to composer.json (Modifies your composer.lock)
```
composer update
```

### 🚀 For Production Environment
Run the following command for production environment to ignore the dev dependencies.
```
composer install --optimize-autoloader --no-dev -q

```

### 📦 For Build Release
Set execution permission to the script file by `chmod +x bin/build.sh` command. Now, Run the following bash script.
```
bin/build.sh
```