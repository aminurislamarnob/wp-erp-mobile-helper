# wp-erp-app-helper — CLAUDE.md

## Overview
A WeLabs scaffold/helper plugin for WP-ERP. Acts as a companion plugin that extends WP-ERP (`erp` is a required dependency). Version: `0.0.1`. Text domain: `wp-erp-app-helper`.

## Architecture

```
wp-erp-app-helper/
├── wp-erp-app-helper.php   # Entry point — defines constants, loads autoloader, calls init()
├── includes/
│   ├── WpErpAppHelper.php  # Main singleton class (plugin lifecycle, hooks, template loader)
│   └── Assets.php          # Script/style registration & enqueueing
├── templates/              # PHP templates — loaded via get_template()
├── assets/
│   ├── admin/css|js/       # Admin assets (style.css, script.js)
│   └── public/css|js/      # Frontend assets (style.css, script.js)
└── bin/build.sh            # Release build script
```

## Key Patterns

### Singleton entry point
```php
welabs_wp_erp_app_helper()            // returns WpErpAppHelper::init()
welabs_wp_erp_app_helper()->version   // access public properties
welabs_wp_erp_app_helper()->scripts   // access container classes via __get()
```

### Constants (defined in `define_constants()`)
| Constant | Value |
|---|---|
| `WP_ERP_APP_HELPER_FILE` | Main plugin file path |
| `WP_ERP_APP_HELPER_DIR` | Plugin root directory |
| `WP_ERP_APP_HELPER_INC_DIR` | `includes/` directory |
| `WP_ERP_APP_HELPER_TEMPLATE_DIR` | `templates/` directory |
| `WP_ERP_APP_HELPER_PLUGIN_ASSET` | `assets/` URL |
| `WP_ERP_APP_HELPER_PLUGIN_ADMIN_ASSET` | `assets/admin/` URL |
| `WP_ERP_APP_HELPER_PLUGIN_PUBLIC_ASSET` | `assets/public/` URL |
| `WP_ERP_APP_HELPER_LOAD_STYLE` | `true` — toggle to disable styles |
| `WP_ERP_APP_HELPER_LOAD_SCRIPTS` | `true` — toggle to disable scripts |

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
