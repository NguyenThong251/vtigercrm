<!-- Parent: ../AGENTS.md -->
<!-- Generated: 2026-05-10 | Updated: 2026-05-10 -->

# vtlib (Module Development SDK)

## Purpose
Vtiger Module Development SDK providing classes and tools for creating, modifying, and packaging CRM modules. This is the standard way to develop custom modules that integrate properly with Vtiger.

## Key Files
| File | Description |
|------|-------------|
| `Vtiger/Module.php` | Main module class - create modules, add fields, blocks |
| `Vtiger/ModuleBasic.php` | Basic module operations |
| `Vtiger/Field.php` | Field creation and configuration |
| `Vtiger/FieldBasic.php` | Field properties (uitype, label, presence) |
| `Vtiger/Block.php` | Block/section management |
| `Vtiger/Filter.php` | Custom view filter creation |
| `Vtiger/Language.php` | Language pack management |
| `Vtiger/Layout.php` | Layout handling |
| `Vtiger/Link.php` | Quick links and buttons |
| `Vtiger/PackageImport.php` | Module import/export |
| `Vtiger/PackageExport.php` | Module packaging |
| `Vtiger/Cron.php` | Scheduled task registration |
| `ModuleDir/` | Module template directory |

## Subdirectories
| Directory | Purpose |
|-----------|---------|
| `Vtiger/` | SDK classes |
| `ModuleDir/6.0.0/` | Empty module template |
| `thirdparty/` | Third-party parsing utilities |
| `tools/` | Command-line development tools |

## For AI Agents

### Creating a Module
```php
include_once 'vtlib/Vtiger/Module.php';
$module = new Vtiger_Module();
$module->name = 'MyModule';
$module->save();
$module->initWebservice(); // Creates REST endpoints

// Add fields
$block = new Vtiger_Block();
$block->label = 'My Block';
$module->addBlock($block);

$field = new Vtiger_Field();
$field->name = 'myfield';
$field->label = 'My Field';
$field->uitype = 1; // Text
$field->typeofdata = 'V~M'; // Varchar, Mandatory
$block->addField($field);
```

### Module Structure
```
modules/MyModule/
├── MyModule.php           # Module class (optional)
├── actions/               # Action handlers
│   └── Index.php
├── models/                # Business logic
│   └── RecordModel.php
├── views/                 # View controllers
│   └── Index.php
└── templates/             # Smarty templates
    └── Index.tpl
```

### Adding Custom Fields
```php
$field = new Vtiger_Field();
$field->name = 'cf_custom';           // Column name
$field->label = 'Custom Field';       // Display label
$field->uitype = 1;                   // Field type
$field->typeofdata = 'V~O';           // Type of data (Varchar, Optional)
$field->displaytype = 1;              // Editable
$field->masseditable = 1;            // Mass edit allowed
$block->addField($field);
```

## UIType Reference
| uitype | Description |
|--------|-------------|
| 1 | Text |
| 2 | Phone |
| 5 | Date |
| 6 | Time |
| 7 | Number |
| 10 | Reference (Related Entity) |
| 13 | Email |
| 14 | File Upload |
| 15 | Picklist |
| 16 | Multi-Select Picklist |
| 19 | Currency |
| 21 | Text Area |
| 56 | Checkbox |
| 57 | Contact Reference |
| 58 | Picklist Multi-Select |
| 71 | Currency with Symbol |
| 117 | Assigned To (Owner) |

<!-- MANUAL: -->
