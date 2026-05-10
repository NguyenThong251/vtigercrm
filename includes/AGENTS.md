<!-- Parent: ../AGENTS.md -->
<!-- Generated: 2026-05-10 | Updated: 2026-05-10 -->

# includes (Modern Framework)

## Purpose
Modern framework core directory containing the MVC architecture, HTTP handling, runtime components, and exception handling. This is the primary source for Vtiger's application framework.

## Key Files
| File | Description |
|------|-------------|
| `Loader.php` | PSR-4 compatible autoloader for framework classes |
| `main/WebUI.php` | Main URL router and request dispatcher |
| `main/WebRequest.php` | Vtiger_Request wrapper for HTTP requests |
| `runtime/Controller.php` | Base controller with permission checking |
| `runtime/Viewer.php` | Smarty template renderer |
| `runtime/BaseModel.php` | Key-value model base class |
| `runtime/Cache.php` | Application caching system |
| `http/HttpImport.php` | HTTP-related imports |
| `exceptions/` | Custom exception classes |

## Subdirectories
| Directory | Purpose |
|-----------|---------|
| `main/` | Main application classes (WebUI, Request) |
| `runtime/` | MVC runtime (Controller, Viewer, BaseModel, Cache) |
| `http/` | HTTP-related components |
| `exceptions/` | Exception handlers |
| `runtime/cache/` | Cache storage directory |

## For AI Agents

### MVC Architecture
- Controllers extend `Vtiger_Controller` → `Vtiger_Action_Controller` → `Vtiger_View_Controller`
- Views render via `Vtiger_Viewer` using Smarty templates
- Models extend `Vtiger_Base_Model` or `Vtiger_Record_Model`

### Request Flow
1. `index.php` → `Vtiger_WebUI`
2. `WebUI` → determines module and view
3. Module controller (`modules/{Module}/views/{View}.php`) processes request
4. `Vtiger_Viewer` renders Smarty template

### Key Patterns
- Use `Vtiger_Request` for all input (not `$_GET`/`$_POST`)
- Use `Users_Privileges_Model::isPermitted()` for permissions
- Use `vtranslate()` for language strings

## Dependencies

### Internal
- `include/` - Legacy utilities still in use
- `vtlib/` - Module development SDK
- `layouts/v7/` - Template files

### External
- Smarty templating engine
- PearDatabase for database access

<!-- MANUAL: -->
