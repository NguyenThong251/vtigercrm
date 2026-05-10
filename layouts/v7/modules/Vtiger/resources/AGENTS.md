<!-- Parent: ../AGENTS.md -->
<!-- Generated: 2026-05-10 | Updated: 2026-05-10 -->

# layouts/v7/modules/Vtiger/resources

## Purpose
Module-specific JavaScript resources including the vtValidate form validation plugin, client-side utilities, and module-level JavaScript.

## Key Files
| File | Description |
|------|-------------|
| `validation.js` | vtValidate jQuery plugin for form validation |
| `Edit.js` | Edit view JavaScript |
| `Detail.js` | Detail view JavaScript |
| `List.js` | List view JavaScript |
| `AdvanceFilter.js` | Advanced filter logic |

## vtValidate Plugin
```javascript
// Basic usage
$('#edit_form').vtValidate({
    validationUrl: 'validate.php'
});

// Custom validation
$.validator.addMethod('customRule', function(value, element) {
    return this.optional(element) || /pattern/.test(value);
}, 'Error message');

// vtValidate ignores: :hidden, .ignore-validation, .select2-input
```

### Error Event (note typo)
```javascript
app.event.on('Vtiger.Validation.Show.Messsage', function(event, data) {
    // Shows validation errors
});
```

## For AI Agents

### Validation in Edit View
- Errors shown via qTip tooltips
- Submit handler clears old validator before re-validation
- Hidden fields are IGNORED by validation

### Adding Custom Validation
```javascript
$form.vtValidate({
    customRemoteValidators: {
        myValidator: function(value, field) {
            return $.ajax({
                url: 'validate.php',
                data: { value: value }
            });
        }
    }
});
```

<!-- MANUAL: -->
