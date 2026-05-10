<!-- Parent: ../AGENTS.md -->
<!-- Generated: 2026-05-10 | Updated: 2026-05-10 -->

# layouts/v7 (Modern UI)

## Purpose
Modern responsive layout for Vtiger CRM. This is the active UI layer containing Smarty templates, JavaScript resources, CSS, and theme definitions.

## Key Directories
| Directory | Purpose |
|-----------|---------|
| `modules/` | Module-specific templates |
| `resources/` | Global JavaScript and CSS |
| `lib/` | Frontend libraries |
| `skins/` | Theme definitions |

## Subdirectories
| Directory | Purpose |
|-----------|---------|
| `modules/Vtiger/` | Core templates (uitypes, detailviews, listviews) |
| `modules/{Module}/` | Module overrides |
| `resources/` | Global application JS |
| `lib/` | Third-party frontend libs |
| `skins/` | Theme CSS |

## Key Files
| File | Description |
|------|-------------|
| `modules/Vtiger/uitypes/*.tpl` | Field type templates (Date, Picklist, etc.) |
| `resources/application.js` | Core app JS with event bus |
| `resources/validation.js` | Form validation (vtValidate) |
| `modules/Vtiger/EditView.tpl` | Record edit form |
| `modules/Vtiger/DetailViewBlockView.tpl` | Record detail view |
| `modules/Vtiger/ListViewContents.tpl` | List view |

## For AI Agents

### Core JavaScript
- `layouts/v7/resources/application.js` - Event bus (`app.event`), utilities
- `layouts/v7/modules/Vtiger/resources/validation.js` - vtValidate plugin

### Event System
```javascript
// Bind to Vtiger events (use app.event, NOT jQuery(document))
app.event.on('Pre.Record.Save', function(event, data) {
    // Access form data via data.data
    return true; // or false to prevent save
});
```

### Validation
```javascript
// vtValidate is jQuery-based
$('#edit_form').vtValidate({
    validationUrl: 'validate.php'
});
```

### Adding Custom JS
1. Add to `layouts/v7/resources/` or module resources
2. Include via controller's `getHeaderScripts()`

## Dependencies

### Internal
- `includes/runtime/Viewer.php` - Template rendering
- `modules/` - View controllers
- `libraries/jquery/` - jQuery and plugins

### External
- jQuery
- Bootstrap CSS/JS
- Smarty templating

<!-- MANUAL: -->
