<!-- Parent: ../AGENTS.md -->
<!-- Generated: 2026-05-10 | Updated: 2026-05-10 -->

# modules (CRM Business Modules)

## Purpose
Core CRM modules implementing business logic for entities, sales, service, and marketing. Each module represents a business concept with its own data model, views, and actions.

## Module Categories

### Business Entities
| Module | Purpose |
|--------|---------|
| `Accounts/` | Company/organization records |
| `Contacts/` | Individual person records linked to accounts |
| `Leads/` | Pre-qualified potential customers |

### Sales & Inventory
| Module | Purpose |
|--------|---------|
| `Potentials/` | Sales opportunities/pipeline |
| `Quotes/` | Customer price quotes |
| `SalesOrder/` | Customer orders |
| `PurchaseOrder/` | Vendor purchase orders |
| `Invoice/` | Customer invoices |
| `Products/` | Product catalog |
| `PriceBooks/` | Product pricing lists |
| `Inventory/` | Base inventory handling |

### Customer Service
| Module | Purpose |
|--------|---------|
| `HelpDesk/` | Support tickets/issues |
| `ServiceContracts/` | Customer service agreements |
| `Services/` | Service offerings |

### Activities
| Module | Purpose |
|--------|---------|
| `Calendar/` | Activities/events with date/time |
| `Events/` | Calendar events (similar to Calendar) |
| `Emails/` | Email management |

### Marketing
| Module | Purpose |
|--------|---------|
| `Campaigns/` | Marketing campaign tracking |
| `Reports/` | Custom report generation |

### Documents
| Module | Purpose |
|--------|---------|
| `Documents/` | File attachments and documents |
| `Faq/` | Frequently asked questions |
| `EmailTemplates/` | Email template management |

### Integration
| Module | Purpose |
|--------|---------|
| `Google/` | Google integration (calendar, contacts) |
| `PBXManager/` | Phone system integration |
| `Webforms/` | Lead capture web forms |
| `MailManager/` | Email server integration |
| `CustomerPortal/` | Customer self-service portal |

### System
| Module | Purpose |
|--------|---------|
| `Users/` | User account management |
| `Settings/` | Admin configuration panels |
| `Migration/` | Database schema migrations |
| `CustomView/` | Custom filter views |
| `Import/` | Data import functionality |
| `ModTracker/` | Change tracking |
| `com_vtiger_workflow/` | Workflow automation engine |

## Standard Module Structure
```
modules/{ModuleName}/
├── actions/           # Action handlers (EditView, DetailView, etc.)
├── models/           # Vtiger_Record_Model subclasses
├── views/            # Vtiger_View_Controller subclasses
├── handlers/         # Event handlers
├── templates/        # Smarty templates (if not in layouts/)
├── language/         # Module-specific translations
└── {ModuleName}.php  # Module class (optional)
```

## For AI Agents

### Adding a Module
1. Create module directory in `modules/`
2. Use `vtlib/Vtiger/Module.php` to initialize
3. Add fields using `vtlib/Vtiger/Field.php`
4. Create views in `views/` extending `Vtiger_View_Controller`
5. Create Smarty templates in `layouts/v7/modules/{ModuleName}/`

### Working with Records
```php
// Load a record
$recordModel = Vtiger_Record_Model::getInstanceById($recordId, $moduleName);

// Get field values
$value = $recordModel->get('fieldname');

// Save changes
$recordModel->save();

// Set field value
$recordModel->set('fieldname', $value);
```

### Module-Specific Templates
Module templates override base templates in `layouts/v7/modules/Vtiger/`. Place in `layouts/v7/modules/{ModuleName}/`.

<!-- MANUAL: -->
