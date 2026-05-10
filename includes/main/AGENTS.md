<!-- Parent: ../../includes/AGENTS.md -->
<!-- Generated: 2026-05-10 | Updated: 2026-05-10 -->

# includes/main (Main Application Classes)

## Purpose
Core application classes handling the web request lifecycle, including URL routing, request parsing, and user session management.

## Key Files
| File | Description |
|------|-------------|
| `WebUI.php` | Main URL router and request dispatcher |
| `WebRequest.php` | Vtiger_Request wrapper for HTTP input |

## For AI Agents

### Request Flow
1. `index.php` → `Vtiger_WebUI->process()`
2. `WebUI` parses module/view from URL
3. Loads appropriate controller from `modules/{Module}/views/`
4. Controller renders view

### Vtiger_Request
```php
$request = new Vtiger_Request($_REQUEST, $_REQUEST);

$module = $request->getModule();      // Get module name
$view = $request->get('view');       // Get view name
$record = $request->get('record');   // Get record ID
$mode = $request->get('mode');       // Get action mode

// Check if parameter exists
if ($request->has('fieldname')) {
    $value = $request->get('fieldname');
}

// Validate read access
$request->validateReadAccess();
```

### WebUI URL Patterns
- `?module={Module}&view={View}` - Standard view
- `?module={Module}&action={Action}` - Direct action
- `?module={Module}&record={id}&view={View}` - Record view

<!-- MANUAL: -->
