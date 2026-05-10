<!-- Parent: ../AGENTS.md -->
<!-- Generated: 2026-05-10 | Updated: 2026-05-10 -->

# layouts/v7/resources (Global Application JS)

## Purpose
Global JavaScript resources loaded across the application. Contains the core application.js event bus and utilities.

## Key Files
| File | Description |
|------|-------------|
| `application.js` | Core app with event bus (`app.event`), utilities |

## application.js Features

### Event Bus
```javascript
// app.event is a jQuery-backed event bus
app.event.on('Pre.Record.Save', callback);      // Before save
app.event.on('Post.Record.Save', callback);     // After save
app.event.on('Record.Change', callback);        // Field change

// Trigger custom event
app.event.trigger('Custom.Event', data);
```

### Key Objects
| Object | Description |
|--------|-------------|
| `app` | Main application namespace |
| `app.event` | jQuery event bus |
| `app.cache` | Client-side caching |
| `app.validator` | Validation utilities |
| `app.websocket` | WebSocket connection |

### Utilities
```javascript
app.getDateFormat();              // Get user's date format
app.getCurrencySymbol();          // Get currency symbol
app.helper` | Random ID generation
app.convertToDatePickerFormat(); // Format conversion
```

## For AI Agents

### IMPORTANT: Event Binding
```javascript
// WRONG - doesn't work in Vtiger
$(document).on('Pre.Record.Save', handler);

// CORRECT - use app.event
app.event.on('Pre.Record.Save', handler);
```

### Date Formatting
```javascript
var format = app.getDateFormat(); // e.g., "mm-dd-yyyy"
```

<!-- MANUAL: -->
