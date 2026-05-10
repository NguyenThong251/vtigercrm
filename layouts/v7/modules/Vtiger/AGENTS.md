<!-- Parent: ../../layouts/AGENTS.md -->
<!-- Generated: 2026-05-10 | Updated: 2026-05-10 -->

# layouts/v7/modules/Vtiger (Core Templates)

## Purpose
Core Smarty templates and resources for the Vtiger CRM UI. Contains base templates, field type templates (uitypes), and shared resources used across all modules.

## Key Files
| File | Description |
|------|-------------|
| `EditView.tpl` | Record edit form base |
| `DetailViewBlockView.tpl` | Record detail view |
| `ListViewContents.tpl` | List view with pagination |
| `Header.tpl` | Page header |
| `Footer.tpl` | Page footer |
| `ModuleHeader.tpl` | Module navigation header |

## Key Directories
| Directory | Purpose |
|-----------|---------|
| `uitypes/` | Field type templates |
| `resources/` | Module-specific JavaScript |
| `partials/` | Template partials/includes |
| `dashboards/` | Dashboard widgets |

## UIType Templates (uitypes/)
| File | Field Type |
|------|------------|
| `Date.tpl` | Date picker |
| `DateTime.tpl` | Date + time picker |
| `Picklist.tpl` | Dropdown select |
| `MultiSelect.tpl` | Multi-select |
| `Reference.tpl` | Related record picker |
| `Owner.tpl` | User/group assignment |
| `Currency.tpl` | Money with currency |
| `Image.tpl` | Image upload with preview |
| `File.tpl` | File upload |
| `Boolean.tpl` | Checkbox |
| `Phone.tpl` | Phone number |
| `Email.tpl` | Email address |
| `Url.tpl` | URL field |
| `Password.tpl` | Password (hidden) |
| `Text.tpl` | Text input |
| `TextArea.tpl` | Multi-line text |
| `Number.tpl` | Numeric input |
| `Percentage.tpl` | Percentage with % |
| `Recurrence.tpl` | Recurring event |
| `Reminder.tpl` | Reminder settings |
| `MultiOwner.tpl` | Multi-user assignment |
| `MultiPicklist.tpl` | Multi-select dropdown |
| `ProductTax.tpl` | Tax configuration |

## For AI Agents

### Template Structure
```
uitypes/{TypeName}.tpl  ← Field template
  ↓
layouts/v7/modules/{Module}/  ← Module override (if exists)
  ↓
layouts/v7/modules/Vtiger/  ← Base template
```

### Smarty Variables in Templates
```smarty
{$FIELD_MODEL}     - Vtiger_Field_Model for this field
{$FIELD_VALUE}     - Current field value
{$MODULE}           - Current module name
{$RECORD_ID}        - Current record ID
```

### JavaScript in Templates
```smarty
{include file="jsResources.tpl"}
{literal}
<script>
    // Custom JS here
</script>
{/literal}
```

### Adding Custom UIType
1. Create `uitypes/Custom.tpl`
2. Register in `vtiger_ws_fieldtype` table
3. Create PHP class in `modules/FieldTypes/` if needed

## Resources
| File | Purpose |
|------|---------|
| `resources/validation.js` | vtValidate jQuery plugin |
| `resources/Utils.js` | Client-side utilities |
| `resources/application.js` | Core app JS (in parent) |

<!-- MANUAL: -->
