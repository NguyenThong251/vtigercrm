<!-- Parent: ../AGENTS.md -->
<!-- Generated: 2026-05-10 | Updated: 2026-05-10 -->

# modules/FCVAdvancedFields (Custom Advanced Fields Module)

## Purpose
Custom Vtiger module extending field types with advanced functionality including custom file uploads (uitype 250) and enhanced datetime fields.

## Key Components
| Component | Location | Description |
|-----------|----------|-------------|
| Module | `modules/FCVAdvancedFields/` | Module class |
| Templates | `layouts/v7/modules/FCVAdvancedFields/` | Smarty templates |
| Upload Table | `vtiger_fcvadvancedfield_uploads` | Upload file records |

## Upload Field (uitype 250)
- Stored in `vtiger_fcvadvancedfield_uploads`
- Linked via `vtiger_attachments`
- Files stored on disk in `storage/` directory

### Template
`layouts/v7/modules/Vtiger/uitypes/Fcvfile.tpl`

### JavaScript Features
- `fcvLoadThumb()` - Loads thumbnail preview for existing files
- Uses Fetch API with `credentials: 'same-origin'`
- Creates objectURL blob for image preview
- Thumbnail click opens full-size in new tab

### Known Issues Fixed
- DateTime fields saving as 0000-00-00 00:00:00 (fixed in DateTime.tpl)
- File upload preview broken (fixed with fcvLoadThumb)
- vtiger_attachments record creation missing (fixed in EventHandler.php)

## For AI Agents

### Working with File Uploads
1. Upload creates record in `vtiger_fcvadvancedfield_uploads`
2. Creates linked `vtiger_attachments` record
3. File stored on disk at `storage/` path
4. Display via `DownloadAttachment` endpoint

### DateTime Fix Details
- DateTime.tpl JS now uses `app.event.on('Pre.Record.Save')`
- Hidden combined field updated on blur
- `fcvDtUpdate` promoted to `window.fcvDtUpdate`

<!-- MANUAL: FCVAdvancedFields module customizations -->
