<!-- Parent: ../../includes/AGENTS.md -->
<!-- Generated: 2026-05-10 | Updated: 2026-05-10 -->

# includes/runtime (MVC Runtime)

## Purpose
Core MVC runtime components including base controller, viewer, models, and caching. These classes form the foundation of Vtiger's request handling and template rendering.

## Key Files
| File | Description |
|------|-------------|
| `Controller.php` | Base controllers (Vtiger_Controller, Vtiger_Action_Controller, Vtiger_View_Controller) |
| `Viewer.php` | Smarty template renderer (Vtiger_Viewer) |
| `BaseModel.php` | Base model class (Vtiger_Base_Model) |
| `Cache.php` | Application caching (Vtiger_Cache) |
| `Configs.php` | Application configuration |
| `Theme.php` | Theme handling |
| `LanguageHandler.php` | Language/translation |
| `EntryPoint.php` | Base entry point class |
| `JavaScript.php` | JavaScript resource handling |
| `Globals.php` | Global variable helpers |

## Subdirectories
| Directory | Purpose |
|-----------|---------|
| `cache/` | Cache storage directory |

## For AI Agents

### Controller Hierarchy
```
Vtiger_Controller (abstract)
  └── Vtiger_Action_Controller (abstract)
        └── Vtiger_View_Controller (abstract)
              └── {Module}IndexView
              └── {Module}EditView
              └── {Module}DetailView
```

### Controller Methods
```php
class MyModuleIndexView extends Vtiger_View_Controller {
    function getViewer(Vtiger_Request $request) { }
    function preProcess(Vtiger_Request $request) { }
    function process(Vtiger_Request $request) { }
    function postProcess(Vtiger_Request $request) { }
}
```

### Viewer Usage
```php
$viewer = $this->getViewer($request);
$viewer->assign('VAR_NAME', $value);
$viewer->view('TemplateName.tpl', $moduleName);
```

### Caching
```php
Vtiger_Cache::set('key', $value);      // Set cache
Vtiger_Cache::get('key');               // Get cache
Vtiger_Cache::delete('key');            // Clear entry
```

## Key Patterns

### Permission Checking
```php
function requiresPermission(Vtiger_Request $request) {
    return [
        ['module' => 'ModuleName', 'action' => 'EditView']
    ];
}

function checkPermission(Vtiger_Request $request) {
    // Built-in via requiresPermission
}
```

### Exposing Methods to Client
```php
class MyModuleAjax extends Vtiger_Action_Controller {
    protected $exposedMethods = ['getData'];

    public function getData(Vtiger_Request $request) {
        // Called via AJAX
    }
}
```

<!-- MANUAL: -->
