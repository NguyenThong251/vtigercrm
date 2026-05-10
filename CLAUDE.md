# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Vtiger CRM 8.4 - A PHP-based Customer Relationship Management system built on a custom MVC framework with Smarty templating.

## Common Commands

```bash
# Install dependencies (requires Composer)
composer install

# Run via XAMPP
# Access via: http://localhost/vtigercrm/

# Smarty template cache location (for clearing after template edits)
layouts/v7/smarty/templates_c/
```

## Architecture

### Request Flow
1. `index.php` - Entry point, loads `Vtiger_WebUI`
2. `includes/main/WebUI.php` - Routes requests to appropriate module/controller
3. Module controllers in `modules/{ModuleName}/` directory
4. Views render via Smarty templates in `layouts/v7/modules/{ModuleName}/`

### Key Directories

| Directory | Purpose |
|-----------|---------|
| `modules/` | Core modules (Leads, Accounts, Contacts, etc.) |
| `layouts/v7/modules/` | Module-specific Smarty templates and resources |
| `layouts/v7/modules/Vtiger/uitypes/` | Field type templates (Date.tpl, DateTime.tpl, etc.) |
| `includes/` | Core framework (Loader, Utils, fields, Webservices) |
| `includes/runtime/` | Runtime classes (Viewer, Controller, Cache) |
| `vtlib/Vtiger/` | Module development SDK (Module.php, Field.php, etc.) |
| `libraries/` | Third-party libraries (jQuery, CKEditor, Smarty) |
| `languages/` | Language packs for translations |
| `vendor/` | Composer dependencies |

### Core Classes

- **Vtiger_Controller** (`includes/runtime/Controller.php`) - Base controller with permission checking
- **Vtiger_Viewer** (`includes/runtime/Viewer.php`) - Smarty template renderer
- **Vtiger_Base_Model** (`includes/runtime/BaseModel.php`) - Key-value model base
- **Vtiger_Request** (`includes/main/WebRequest.php`) - Request parameter wrapper
- **Vtiger_WebUI** (`includes/main/WebUI.php`) - Main URL router

### UIType System

Custom field types are defined by:
- **UIType Template**: `layouts/v7/modules/Vtiger/uitypes/{TypeName}.tpl`
- **UIType Class**: `modules/FieldTypes/` or looked up via `getInstanceFromField()`
- **Field Type Registry**: `vtiger_ws_fieldtype` table

### Module Development (vtlib)

Use `vtlib/Vtiger/Module.php` for module creation:
```php
include_once 'vtlib/Vtiger/Module.php';
$module = new Vtiger_Module();
$module->initWebservice();
```

### JavaScript Framework

- `layouts/v7/resources/application.js` - Core app JS with event bus (`app.event`)
- Form validation via `layouts/v7/modules/Vtiger/resources/validation.js` (vtValidate jQuery plugin)
- Date fields use `eternicode-bootstrap-datepicker` library

### Database

- Uses ADOdb/MySQLi via `PearDatabase` class
- Config: `config.inc.php`
- Custom fields stored in `vtiger_fieldmodulerel` and module-specific tables

## Important Patterns

- **Event Binding**: Use `app.event.on()` (not `jQuery(document)`) for Vtiger CRM events like `Pre.Record.Save`
- **Form Validation**: vtValidate ignores `:hidden` fields - ensure visible proxy elements participate
- **Template Changes**: Smarty cache at `layouts/v7/smarty/templates_c/` - changes take effect immediately (no manual clearing needed on this setup)
- **File Downloads**: `DownloadAttachment` endpoint returns `Content-Disposition: attachment` - use `fetch()` with blob handling for inline display
- **JS Error Events**: Some event names contain typos (e.g., `Vtiger.Validation.Show.Messsage` with triple 's')

## FCVAdvancedFields Module

Custom module for advanced field types (uitype 250 for file uploads, custom datetime):
- Module: `modules/FCVAdvancedFields/`
- Templates: `layouts/v7/modules/FCVAdvancedFields/`
- Upload table: `vtiger_fcvadvancedfield_uploads`
