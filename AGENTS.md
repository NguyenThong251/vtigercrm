<!-- Generated: 2026-05-10 | Updated: 2026-05-10 -->

# vtigercrm

## Purpose
Vtiger CRM 8.4 - A PHP-based Customer Relationship Management system built on a custom MVC framework with Smarty templating. Manages business entities (Leads, Contacts, Accounts), sales processes (Potentials, Quotes, Invoices), customer service (HelpDesk, Tickets), and integrates with external services (Google, Email, PBX).

## Key Files
| File | Description |
|------|-------------|
| `index.php` | Application entry point, routes to Vtiger_WebUI |
| `config.inc.php` | Database and application configuration |
| `composer.json` | PHP dependencies (Smarty, TCPDF, Monolog, PHPMailer) |
| `CLAUDE.md` | AI agent guidance for this project |

## Subdirectories
| Directory | Purpose |
|-----------|---------|
| `modules/` | CRM modules (Accounts, Leads, Contacts, etc.) - see `modules/AGENTS.md` |
| `layouts/` | Smarty templates and UI resources - see `layouts/AGENTS.md` |
| `include/` | Core PHP library (legacy includes) - see `include/AGENTS.md` |
| `includes/` | Main framework (runtime, main, HTTP) - see `includes/AGENTS.md` |
| `vtlib/` | Module development SDK - see `vtlib/AGENTS.md` |
| `libraries/` | Third-party dependencies (jQuery, Bootstrap) - see `libraries/AGENTS.md` |
| `languages/` | Translation files for 17 locales |
| `cron/` | Scheduled task modules (workflows, reports) |
| `kcfinder/` | File browser/manager for uploads |
| `data/` | Application data storage |
| `vendor/` | Composer-managed dependencies |

## For AI Agents

### Entry Point
- `index.php` loads `Vtiger_WebUI` which routes all requests
- All modules accessible via `?module={ModuleName}&view={ViewName}`
- Smarty templates cached in `layouts/v7/smarty/templates_c/`

### Key Framework Files
- `includes/main/WebUI.php` - URL routing and module loading
- `includes/runtime/Controller.php` - Base controller with permission system
- `includes/runtime/Viewer.php` - Smarty template rendering
- `vtlib/Vtiger/Module.php` - Module creation and management

### Working Here
- Clear Smarty cache after template changes if needed
- Use `composer install` after modifying `composer.json`
- Check `config.inc.php` for database credentials and settings
- Template changes in `layouts/v7/` take effect immediately in dev mode

## Dependencies

### External
- PHP >= 8.1 with mysqli, imap, curl extensions
- Smarty 4.x - Template engine
- TCPDF 6.x - PDF generation
- PHPMailer 6.x - Email handling
- jQuery - Client-side JavaScript

### Key Libraries
- `libraries/jquery/` - jQuery and plugins (CKEditor, Select2, Validation)
- `libraries/bootstrap/` - CSS framework
- `libraries/adodb_vtigerfix/` - Database abstraction

<!-- MANUAL: Project-specific notes -->
