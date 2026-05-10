<!-- Parent: ../AGENTS.md -->
<!-- Generated: 2026-05-10 | Updated: 2026-05-10 -->

# libraries (Third-Party Dependencies)

## Purpose
Third-party JavaScript libraries, CSS frameworks, and PHP utilities bundled with Vtiger CRM for UI components, data processing, and system integration.

## Key JavaScript Libraries

### jQuery Plugins (`libraries/jquery/`)
| Directory | Purpose |
|-----------|---------|
| `jquery-ui/` | UI widget framework |
| `ckeditor/` | Rich text editor |
| `select2/` | Enhanced dropdown selects |
| `posabsolute-jQuery-Validation-Engine/` | Form validation |
| `handsontable/` | Spreadsheet-like data grid |
| `gantt/` | Gantt chart visualization |
| `jqplot/` | Chart/graph plotting |
| `timepicker/` | Time selection widget |
| `datepicker/` | Date selection widget |
| `dangrossman-bootstrap-daterangepicker/` | Date range picker |
| `lazyYT/` | Lazy-loaded YouTube embeds |
| `pdfjs/` | PDF viewer |
| `pivot/` | Pivot table |
| `multiplefileupload/` | Multi-file upload |
| `pnotify/` | Notifications |
| `gridster/` | Dashboard grid layout |
| `boxslider/` | Carousel/slider |

### Bootstrap Ecosystem
| Directory | Purpose |
|-----------|---------|
| `bootstrap/` | CSS framework with JS |
| `jasny-bootstrap/` | Bootstrap extension |
| `bootstrap-legacy/` | Older Bootstrap version |

### Other JavaScript
| Directory | Purpose |
|-----------|---------|
| `fullcalendar/` | Calendar widget |
| `video-js/` | Video player |
| `DOMPurify/` | HTML sanitization |
| `twitter-text-js/` | Twitter text processing |

## Key CSS/UI
| Directory | Purpose |
|-----------|---------|
| `bootstrap/css/` | Bootstrap styles |
| `InStyle/` | Custom styling |
| `garand-sticky/` | Sticky elements |

## PHP Libraries

### Database
| Directory | Purpose |
|-----------|---------|
| `adodb_vtigerfix/` | ADOdb database abstraction |

### Email
| Directory | Purpose |
|-----------|---------|
| `PHPMailer/` | Email sending |
| `Oauth/` | OAuth authentication |

### Other PHP
| Directory | Purpose |
|-----------|---------|
| `antlr/` | Parser generator |
| `freetag/` | Tag processing |
| `guidersjs/` | Tour/guide overlays |
| `google-api-php-client/` | Google API access |
| `csrf-magic/` | CSRF protection |
| `PHPExcel/` | Excel file handling |
| `PHPMarkdown/` | Markdown parsing |
| `HTTP_Session2/` | Session handling |

## For AI Agents

### Using jQuery Plugins
Include via layouts:
```html
<script src="libraries/jquery/{plugin}/js/file.js"></script>
```

### Adding a jQuery Plugin
1. Place files in `libraries/jquery/{plugin}/`
2. Register in layout resources if needed
3. Initialize in JavaScript:
```javascript
$('.selector').pluginName({ options });
```

### Date Picker
Vtiger uses `eternicode-bootstrap-datepicker`:
```javascript
$('.dateField').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true
});
```

<!-- MANUAL: -->
