# WP ERP App Helper

The WP ERP App Helper plugin provides extended functionality for the WP ERP ecosystem, specifically focused on enhancing HRM capabilities and reporting.

## Key Features

### Human Resource Management (HRM)
- **Daily Standup Tracker**: Integrated tool to record and monitor daily standup meetings with aggregate monthly reporting.
- **Attendance & Shift Integration**: leverages WP-ERP's internal shift logic to ensure tracking is context-aware.

### Reporting & Analytics
- **Monthly Standup Reports**: Generate and download detailed CSV reports of employee attendance, absences, and leaves.
- **Historical Analysis**: Full month-by-month history navigation for standup records.

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