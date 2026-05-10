<!-- Parent: ../AGENTS.md -->
<!-- Generated: 2026-05-10 | Updated: 2026-05-10 -->

# layouts (Smarty Templates)

## Purpose
Smarty template layouts for the Vtiger CRM UI. Contains module-specific templates, shared resources, and skin/theme definitions.

## Key Directories
| Directory | Purpose |
|-----------|---------|
| `v7/` | Modern responsive layout (current) |
| `vlayout/` | Legacy layout (deprecated) |
| `v7/modules/` | Module-specific Smarty templates |
| `v7/resources/` | Shared JS, CSS, images |
| `v7/skins/` | Theme definitions |

## Subdirectories
| Directory | Purpose |
|-----------|---------|
| `v7/modules/Vtiger/` | Core templates (uitypes, listviews, detailviews) |
| `v7/modules/{Module}/` | Module-specific templates |
| `v7/resources/` | Global JavaScript and CSS |
| `v7/skins/` | Theme CSS files |
| `v7/lib/` | Frontend libraries (animate, datepicker) |
| `vlayout/` | Legacy template files |

## For AI Agents

### Template Resolution
- Templates found in `layouts/v7/modules/{ModuleName}/`
- Base templates in `layouts/v7/modules/Vtiger/`
- UIType templates in `layouts/v7/modules/Vtiger/uitypes/`

### Smarty Variables
- `{$MODULE}` - Current module name
- `{$VIEW}` - Current view name
- `{$RECORD_ID}` - Current record ID
- `{$CURRENT_USER}` - Logged-in user

### JavaScript Events
- Use `app.event.on()` (not `jQuery(document)`) for Vtiger events
- Common events: `Pre.Record.Save`, `Post.Record.Save`, `Record.Change`
- Validation via `layouts/v7/modules/Vtiger/resources/validation.js`

### UIType Templates
Custom field types in `layouts/v7/modules/Vtiger/uitypes/`:
- `Date.tpl` - Date picker
- `DateTime.tpl` - Date/time with time picker
- `Picklist.tpl` - Dropdown select
- `Reference.tpl` - Related record picker
- `Owner.tpl` - User/Group assignment
- `Currency.tpl` - Money with currency symbol
- `Image.tpl` - Image upload with preview

<!-- MANUAL: Template changes take effect immediately in dev mode -->
