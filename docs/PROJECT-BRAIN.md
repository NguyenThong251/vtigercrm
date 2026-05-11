# PROJECT BRAIN — Vtiger CRM 8.4 Custom Development

> **Đây là bộ não của dự án.** Agent mới đọc file này sẽ hiểu toàn bộ context,  
> kiến trúc, quyết định thiết kế, lịch sử task, và các rule để tiếp tục đúng hướng.  
>
> **Cập nhật lần cuối:** 2026-05-11  
> **Stack:** Vtiger CRM 8.4 · PHP 8.2 · MySQL 8.0 (Docker) · Smarty 3

---

## PHẦN 1 — TỔNG QUAN DỰ ÁN

### Môi trường

| Item | Giá trị |
|---|---|
| URL | `http://localhost/vtigercrm/` |
| Root | `C:/xampp/htdocs/vtigercrm/` |
| PHP | 8.2 (XAMPP) |
| MySQL | 8.0 (Docker container: `mysql8`) |
| DB name | `vtigercrm` |
| DB user/pass | `root` / `admin` |
| phpMyAdmin | `http://localhost:8080` |
| Smarty cache | `test/templates_c/v7/` ← **KHÔNG phải** `layouts/v7/smarty/templates_c/` |

### Kết nối DB

```bash
# Query trực tiếp
docker exec mysql8 mysql -u root -padmin vtigercrm -e "SQL..."

# Chú ý: không dùng flag -i (gây lỗi trong môi trường này)
# Chú ý: 2>/dev/null hoặc grep -v Warning để bỏ password warning
```

### Hai custom modules của dự án

| Module | Thư mục | Mục đích |
|---|---|---|
| **FCVAdvancedFields** | `modules/FCVAdvancedFields/` | Thêm field types nâng cao (uitype 250 = file upload, enhanced datetime) |
| **FcvModuleBuilder** | `modules/FcvModuleBuilder/` | Tạo/xóa custom module và relationship qua UI (Settings) |

---

## PHẦN 2 — KIẾN TRÚC VTIGER CRM 8.4

### Request flow

```
Browser → index.php
  → includes/main/WebUI.php (Vtiger_WebUI — router)
    → modules/{Module}/actions/{Action}.php  (controller)
      → modules/{Module}/models/*.php        (business logic)
        → layouts/v7/modules/{Module}/*.tpl  (Smarty view)
```

### Các thư mục cốt lõi

| Thư mục | Vai trò |
|---|---|
| `modules/{Name}/` | Module PHP source |
| `modules/{Name}/{Name}.php` | CRMEntity subclass — **BẮT BUỘC** với entity module |
| `layouts/v7/modules/{Name}/` | Smarty templates |
| `layouts/v7/modules/Vtiger/uitypes/` | UIType templates (Date.tpl, Owner.tpl…) |
| `languages/en_us/{Name}.php` | Language strings |
| `vtlib/Vtiger/` | vtlib SDK (Module.php, Field.php, Block.php) |
| `data/CRMEntity.php` | Base class của mọi entity module |
| `include/database/PearDatabase.php` | DB wrapper (ADOdb/MySQLi) |
| `includes/runtime/` | Controller, Viewer, Cache base classes |
| `test/templates_c/v7/` | Smarty compiled cache |

### Core classes quan trọng

| Class | File | Vai trò |
|---|---|---|
| `CRMEntity` | `data/CRMEntity.php` | Base class cho entity modules — save/retrieve records |
| `Vtiger_Record_Model` | `modules/Vtiger/models/Record.php` | MVC record layer — `save()`, `getInstanceById()` |
| `Vtiger_Module_Model` | `modules/Vtiger/models/Module.php` | Module metadata, `saveRecord()` |
| `Vtiger_Action_Controller` | `includes/runtime/Controller.php` | Base action controller |
| `PearDatabase` | `include/database/PearDatabase.php` | `getInstance()`, `pquery()`, `fetch_array()` |
| `Vtiger_Module` (vtlib) | `vtlib/Vtiger/Module.php` | Module creation SDK |
| `Vtiger_Field` (vtlib) | `vtlib/Vtiger/Field.php` | Field creation SDK |

---

## PHẦN 3 — CRMENTY CONTRACT (BẮT BUỘC KHI TẠO ENTITY MODULE)

Mọi entity module (có records) phải có file `modules/{Name}/{Name}.php` đúng chuẩn:

```php
class MyModule extends CRMEntity {

    var $IsCustomModule = true;   // Đánh dấu custom module

    // PHẢI khai báo — CRMEntity::saveentity() gọi $this->db->startTransaction()
    var $db;
    var $log;

    var $table_name  = 'vtiger_mymodule';
    var $table_index = 'mymoduleid';

    var $tab_name = [
        'vtiger_crmentity',    // PHẢI có
        'vtiger_mymodule',     // PHẢI có
        'vtiger_mymodulecf',   // PHẢI có (custom fields)
    ];

    var $tab_name_index = [
        'vtiger_crmentity'   => 'crmid',
        'vtiger_mymodule'    => 'mymoduleid',
        'vtiger_mymodulecf'  => 'mymoduleid',
    ];

    var $entity_table     = 'vtiger_crmentity';
    var $customFieldTable = ['vtiger_mymodulecf', 'mymoduleid'];
    var $column_fields    = [];

    var $additional_column_fields = ['smcreatorid', 'smownerid', 'crmid'];
    var $mandatory_fields = ['assigned_user_id', 'mymodulename', 'createdtime', 'modifiedtime'];
    var $sortby_fields    = [];

    var $list_fields = [
        'Name'        => ['mymodule' => 'mymodulename'],
        'Assigned To' => ['crmentity' => 'smownerid'],
    ];
    var $list_fields_name = [
        'Name'        => 'mymodulename',
        'Assigned To' => 'assigned_user_id',   // ← 'assigned_user_id', KHÔNG phải 'smownerid'
    ];
    var $list_link_field     = 'mymodulename';
    var $search_fields       = ['Name' => ['mymodule' => 'mymodulename'], 'Assigned To' => ['crmentity' => 'smownerid']];
    var $search_fields_name  = ['Name' => 'mymodulename', 'Assigned To' => 'smownerid'];
    var $def_basicsearch_col = 'mymodulename';
    var $default_order_by    = 'mymodulename';
    var $default_sort_order  = 'ASC';
    var $module_name         = 'MyModule';

    // CONSTRUCTOR — 3 dòng này BẮT BUỘC
    function __construct() {
        global $log;
        $this->column_fields = getColumnFields(get_class($this));  // get_class() không hardcode
        $this->db  = PearDatabase::getInstance();                  // BẮT BUỘC
        $this->log = $log;                                         // BẮT BUỘC
    }

    function save_module($module) {}  // Hook bắt buộc (có thể rỗng)
}
```

**Tại sao `$this->db` bắt buộc:**  
`CRMEntity::saveentity()` gọi `$this->db->startTransaction()` và `$this->db->completeTransaction()`.  
Nếu `$this->db = null` → PHP Fatal Error → blank page, không có redirect.

**Tại sao `get_class($this)` thay vì hardcode:**  
`CRMEntity::getInstance()` override `column_fields` sau constructor, nhưng constructor  
vẫn cần `getColumnFields()` cho các trường hợp class được instantiate trực tiếp.

---

## PHẦN 4 — NAV / MENU SYSTEM VTIGER 8

### Vtiger 8 dùng `vtiger_app2tab` (KHÔNG phải `vtiger_parenttab`)

| Bảng | Vai trò |
|---|---|
| `vtiger_app2tab` | **Chính** — quyết định module ở nav group nào và có visible không |
| `vtiger_tab.parent` | Legacy text (`'Sales'`, `'Marketing'`…) — sync kèm nhưng không đủ một mình |
| `vtiger_parenttab` | Vtiger 6/7 cũ — KHÔNG dùng trong Vtiger 8 |

### Schema `vtiger_app2tab`

```sql
tabid    INT          -- FK → vtiger_tab.tabid
appname  VARCHAR(100) -- UPPERCASE: 'MARKETING','SALES','INVENTORY','SUPPORT','PROJECT','TOOLS'
visible  TINYINT      -- 1=hiện, 0=ẩn
```

### Tạo module + gán nav

```php
// Sau khi vtlib tạo module và trả về $tabId:
$db->pquery('INSERT INTO vtiger_app2tab (tabid, appname, visible) VALUES (?,?,1)', [$tabId, 'SALES']);
$db->pquery('UPDATE vtiger_tab SET parent=? WHERE tabid=?', ['Sales', $tabId]);
```

### Mapping appname ↔ label

```
MARKETING → 'Marketing'  |  SALES → 'Sales'  |  INVENTORY → 'Inventory'
SUPPORT   → 'Support'    |  PROJECT → 'Project'  |  TOOLS → 'Tools'
```

---

## PHẦN 5 — PHP 8 RULES (KHÔNG ĐƯỢC VI PHẠM)

### Rule 1: Luôn dùng `catch(\Throwable)` thay vì `catch(Exception)`

```php
// SAI — PHP 8 Error không extend Exception
try { ... } catch (Exception $e) { return ['success'=>false, 'message'=>$e->getMessage()]; }

// ĐÚNG
try { ... } catch (\Throwable $e) { return ['success'=>false, 'message'=>$e->getMessage()]; }
```

### Rule 2: Khởi tạo biến trước khi dùng với `in_array()`

```php
// SAI — $colNames có thể null → TypeError
if ($result) { while ($row = ...) { $colNames[] = $row[0]; } }
return in_array($col, $colNames);

// ĐÚNG
$colNames = [];  // Init trước
if ($result) { while ($row = ...) { $colNames[] = $row[0]; } }
return in_array($col, $colNames);
```

### Rule 3: `Vtiger_Cache::flush()` (không phải `flushAllCache()`)

```php
Vtiger_Cache::flush();          // ✅ tồn tại
Vtiger_Cache::flushAllCache();  // ❌ không tồn tại → Fatal Error
```

### Rule 4: Không dùng `Vtiger_Menu::addModule()` để assign nav

`Vtiger_Menu::addModule()` gọi `create_parenttab_data_file()` — function không tồn tại trong Vtiger 8.  
→ Dùng SQL trực tiếp vào `vtiger_app2tab`.

### Rule 5: PearDatabase query syntax

```php
$db = PearDatabase::getInstance();
$result = $db->pquery('SELECT * FROM table WHERE col=?', [$value]);
$row    = $db->fetch_array($result);
$count  = $db->num_rows($result);
```

---

## PHẦN 6 — MODULE FCVAdvancedFields

### Mục đích
Thêm field types không có trong Vtiger gốc:
- **uitype 250** — File upload field với preview thumbnail
- **DateTime enhanced** — Fix lỗi datetime lưu `0000-00-00 00:00:00`

### File quan trọng

| File | Mục đích |
|---|---|
| `modules/FCVAdvancedFields/FCVAdvancedFields.php` | Module class |
| `layouts/v7/modules/Vtiger/uitypes/Fcvfile.tpl` | Template render uitype 250 |
| `layouts/v7/modules/Vtiger/uitypes/DateTime.tpl` | Fixed datetime template |
| `vtiger_fcvadvancedfield_uploads` | Upload records table |

### uitype 250 — File upload flow

```
1. User upload file → form submit
2. EventHandler.php intercept beforesave event
3. File saved to storage/
4. Record created in vtiger_fcvadvancedfield_uploads
5. Record created in vtiger_attachments (linked)
6. Display: DownloadAttachment endpoint → blob URL
```

### JS patterns (FCVAdvancedFields)

```javascript
// Load thumbnail preview
fcvLoadThumb(fieldId, attachmentId);
// Dùng fetch() với credentials:'same-origin' → blob URL → objectURL

// DateTime fix
app.event.on('Pre.Record.Save', function(data, event) {
    window.fcvDtUpdate(); // update hidden combined field
});
```

### Các bugs đã fix trong FCVAdvancedFields

| Bug | Fix |
|---|---|
| DateTime lưu `0000-00-00 00:00:00` | DateTime.tpl dùng `app.event.on('Pre.Record.Save')` thay vì onblur đơn giản |
| File upload preview broken | `fcvLoadThumb()` dùng fetch blob thay vì `<img src="...">` trực tiếp |
| `vtiger_attachments` record thiếu | EventHandler.php tạo record sau khi save |

---

## PHẦN 7 — MODULE FcvModuleBuilder

### Mục đích
Settings module cho phép tạo/xóa custom entity module và quản lý relationships qua UI.

### File structure

```
modules/FcvModuleBuilder/
├── FcvModuleBuilder.php           -- Settings CRMEntity class
├── actions/
│   ├── Index.php                  -- Router → view
│   ├── ModuleCreate.php           -- POST: tạo module
│   ├── ModuleDelete.php           -- POST: xóa module
│   ├── ModuleSetNav.php           -- POST: đổi nav group
│   ├── RelationCreate.php         -- POST: tạo relationship
│   └── RelationDelete.php         -- POST: xóa relationship
├── models/
│   ├── Builder.php                -- Logic tạo/xóa module
│   └── Relationship.php          -- Logic tạo/xóa relationship
└── views/
    └── Index.php                  -- Smarty viewer

layouts/v7/modules/FcvModuleBuilder/
├── Index.tpl                      -- Main UI (form, tabs, JS)
└── partials/
    ├── RelForm.tpl                -- Form tạo relationship
    └── RelList.tpl                -- Danh sách relationships
```

### API (tất cả POST JSON response)

| Action | Params chính | Kết quả |
|---|---|---|
| `ModuleCreate` | `module_name`, `module_label`, `parent_menu` | Tạo module + DB tables + source files |
| `ModuleDelete` | `module_name` | Xóa module hoàn toàn (cascade) |
| `ModuleSetNav` | `module_name`, `parent_menu` | Đổi nav group |
| `RelationCreate` | `primary_module`, `child_module`, `rel_type` (1M/MM/11) | Tạo relationship |
| `RelationDelete` | `relation_id` (string delete_key) | Xóa relationship + cascade cleanup |

### delete_key system

```
tracking-{id}     → xóa vtiger_fcv_module_relations + cascade cleanup fields/relatedlists
relatedlist-{id}  → xóa trực tiếp vtiger_relatedlists
field-{id}        → xóa vtiger_field + cascade (fieldmodulerel, profile2field, def_org_field)
```

### Tracking tables (custom)

```sql
-- Danh sách module được tạo
vtiger_fcv_custom_modules (id, module_name UNIQUE, label, created_at)

-- Danh sách relationship được tạo
vtiger_fcv_module_relations (relation_id, module1, module2, rel_type, label, created_at)
```

### Module được tạo — chuẩn bắt buộc

Khi `generateSourceFiles()` tạo file PHP cho module mới, class phải có:
- `var $IsCustomModule = true`
- `var $db` và `var $log` (khai báo)
- Constructor với `$this->db = PearDatabase::getInstance()` và `$this->log = $log`
- `function save_module($module) {}`

Template chuẩn: xem `modules/FcvModuleBuilder/models/Builder.php::generateSourceFiles()`

---

## PHẦN 8 — DB CLEANUP MODULE (thứ tự đúng tránh FK error)

```sql
-- Bước 1: Records
DELETE FROM vtiger_{lcname}cf WHERE {lcname}id IN (SELECT crmid FROM vtiger_crmentity WHERE setype=?);
DELETE FROM vtiger_{lcname}   WHERE {lcname}id IN (SELECT crmid FROM vtiger_crmentity WHERE setype=?);
DELETE FROM vtiger_crmentity_user_field WHERE recordid IN (SELECT crmid FROM vtiger_crmentity WHERE setype=?);
DELETE FROM vtiger_seactivityrel WHERE crmid IN (SELECT crmid FROM vtiger_crmentity WHERE setype=?);
DELETE FROM vtiger_crmentityrel  WHERE crmid IN (...) OR relcrmid IN (...);
DELETE FROM vtiger_crmentity WHERE setype=?;

-- Bước 2: Fields (cascade)
DELETE fmr FROM vtiger_fieldmodulerel fmr JOIN vtiger_field f ON f.fieldid=fmr.fieldid WHERE f.tabid=?;
DELETE p2f FROM vtiger_profile2field  p2f JOIN vtiger_field f ON f.fieldid=p2f.fieldid WHERE f.tabid=?;
DELETE dof FROM vtiger_def_org_field  dof JOIN vtiger_field f ON f.fieldid=dof.fieldid WHERE f.tabid=?;
DELETE FROM vtiger_field WHERE tabid=?;
DELETE FROM vtiger_fieldmodulerel WHERE relmodule=?;  -- relate fields pointing AT this module

-- Bước 3: Structure
DELETE FROM vtiger_blocks WHERE tabid=?;
DELETE FROM vtiger_relatedlists WHERE tabid=? OR related_tabid=?;

-- Bước 4: Tracking
DELETE FROM vtiger_fcv_module_relations WHERE module1=? OR module2=?;
DELETE FROM vtiger_fcv_custom_modules WHERE module_name=?;

-- Bước 5: Tab registration
DELETE FROM vtiger_app2tab     WHERE tabid=?;
DELETE FROM vtiger_profile2tab WHERE tabid=?;
DELETE FROM vtiger_def_org_share WHERE tabid=?;
DELETE FROM vtiger_parenttabrel WHERE tabid=?;
DELETE FROM vtiger_ws_entity    WHERE name=?;
DELETE FROM vtiger_tab          WHERE tabid=?;

-- Bước 6: Drop tables
DROP TABLE IF EXISTS vtiger_{lcname}cf;
DROP TABLE IF EXISTS vtiger_{lcname};

-- Bước 7: Files
-- rm -rf modules/{Name}/
-- rm -f  languages/en_us/{Name}.php
-- rm -rf layouts/v7/modules/{Name}/
```

---

## PHẦN 9 — SESSION TASK HISTORY

### Session tasks đã hoàn thành (tháng 5/2026)

#### Task 1 — Dịch UI sang tiếng Anh + Fix features không hiển thị
- Dịch toàn bộ label trong FcvModuleBuilder từ tiếng Việt sang tiếng Anh
- Fix feature tabs không render đúng

#### Task 2 — Fix module creation bị broken hoàn toàn
- Missing core CRM fields, wrong table mappings, no source files
- Rebuild toàn bộ `Builder.php::createModule()` từ đầu

#### Task 3 — Fix duplicate creation bug
- MySQL 8 incompatibility bugs (GROUP BY, subquery)
- Cleaned up test artifacts

#### Task 4 — Fix "Created Modules" list không update sau creation
- UI hiển thị "No custom modules created yet" dù đã tạo
- Fix refresh/reload sau create action

#### Task 5 — Fix nav group feature + add nav group selector
- Thêm `parent_menu` selector khi tạo module
- Fix `getParentMenus()` query sai bảng

#### Task 6 — Fix "Existing Relationships" section trống
- `getAllRelations()` viết lại với 3-source merge:
  1. `vtiger_fcv_module_relations` (tracking)
  2. `vtiger_relatedlists`
  3. `vtiger_fieldmodulerel` (relate fields)

#### Task 7 — Fix "Error: Unknown error" khi tạo relation + nav không hiện module label
- Root cause 1: `catch(Exception)` miss `TypeError` → đổi sang `catch(\Throwable)`
- Root cause 2: `PearDatabase::getColumnNames()` return null → `in_array()` TypeError → fix init `$colNames = []`
- Root cause 3: `getParentMenus()` query `vtiger_parenttab` (sai) → hardcode từ `vtiger_app2tab`

#### Task 8 — Fix module không hiện trong nav sidebar
- Root cause: Vtiger 8 dùng `vtiger_app2tab`, không phải `vtiger_tab.parent`
- Fix: `createModule()` INSERT vào `vtiger_app2tab` sau khi vtlib tạo module
- Fix: `setModuleNav()` DELETE + INSERT `vtiger_app2tab`
- Thêm inline nav group selector trong module list (với Apply button)
- Tạo `ModuleSetNav` action controller mới

#### Task 9 — Fix blank page khi save record trong module mới tạo
- Root cause: Constructor thiếu `$this->db = PearDatabase::getInstance()`
- `CRMEntity::saveentity()` gọi `$this->db->startTransaction()` → Fatal Error
- Fix: Update template trong `generateSourceFiles()` + fix `Demo.php`, `Demo2.php` trực tiếp
- Reference: `modules/Assets/Assets.php` — chuẩn vtlib cho custom module

#### Task 10 — Fix delete relationship không clean up
- Root cause: `deleteRelation()` chỉ xóa tracking row
- Rewrite với `delete_key` system: `tracking-{id}`, `relatedlist-{id}`, `field-{id}`
- Cascade cleanup: `vtiger_field` → `vtiger_fieldmodulerel` → `vtiger_profile2field` → `vtiger_def_org_field`
- Update `RelList.tpl`: `data-id="{$r.delete_key}"` (thay vì `{$r.relation_id}`)
- Update `RelationDelete.php`: pass raw string, không cast sang int

#### Task 11 — Clean up Demo, Demo2, Demo3 modules
- Xóa toàn bộ: records, fields, relationships, tab registration, source files
- DB verified: 0 remaining tabs, 0 remaining tables

---

## PHẦN 10 — ANTI-PATTERNS ĐÃ GẶP (KHÔNG LẶP LẠI)

| Anti-pattern | Hệ quả | Đúng cách |
|---|---|---|
| Constructor không set `$this->db` | Fatal Error → blank page khi save | Luôn `$this->db = PearDatabase::getInstance()` |
| Constructor không set `$this->log` | Fatal Error trong list/query methods | Luôn `global $log; $this->log = $log` |
| `catch(Exception)` | PHP 8 Error/TypeError bị nuốt | `catch(\Throwable)` |
| `in_array($x, null)` | TypeError PHP 8 | Init array trước |
| Query `vtiger_parenttab` để lấy nav groups | Empty dropdown | Hardcode từ `vtiger_app2tab.appname` |
| Chỉ update `vtiger_tab.parent` khi gán nav | Module không hiện trong sidebar | Phải INSERT `vtiger_app2tab` |
| `Vtiger_Menu::addModule()` | Fatal Error (`create_parenttab_data_file` không tồn tại) | SQL trực tiếp `vtiger_app2tab` |
| `Vtiger_Cache::flushAllCache()` | Fatal Error | `Vtiger_Cache::flush()` |
| Hardcode module name trong `getColumnFields('Demo')` | Bug khi copy class | `getColumnFields(get_class($this))` |
| Delete relationship không cascade | Orphan data trong DB | Cascade delete đúng thứ tự |
| `docker exec -i mysql8` | Lỗi trong môi trường này | `docker exec mysql8` (không có `-i`) |
| Heredoc `<< 'SQL'` trong docker exec | SQL không execute | Dùng `-e "SQL"` hoặc pipe từ file |

---

## PHẦN 11 — RULES CHO AGENT MỚI

### Khi nhận task liên quan đến FcvModuleBuilder

1. **Module create issue** → Check `modules/FcvModuleBuilder/models/Builder.php::createModule()` và `generateSourceFiles()`
2. **Record save blank page** → Check CRMEntity class của module: có `$this->db` và `$this->log` không?
3. **Nav không hiện module** → Check `vtiger_app2tab` table, không phải `vtiger_tab.parent`
4. **Relationship error** → Check `catch(\Throwable)` trong `Relationship.php`
5. **Delete không clean** → Check `deleteRelation(string $deleteKey)` và cascade

### Khi nhận task liên quan đến FCVAdvancedFields

1. **File upload issue** → Check `Fcvfile.tpl`, `EventHandler.php`, `vtiger_fcvadvancedfield_uploads`
2. **DateTime issue** → Check `DateTime.tpl` và `app.event.on('Pre.Record.Save')`

### Khi tạo entity module mới (thủ công)

Tham khảo `modules/Assets/Assets.php` làm reference — đây là vtlib chuẩn nhất.

### Khi debug

```bash
# Check PHP error (XAMPP)
tail -50 C:/xampp/php/logs/php_error_log

# Check vtiger log (FATAL only by default)
tail -50 C:/xampp/htdocs/vtigercrm/logs/vtigercrm.log

# Clear Smarty cache
find C:/xampp/htdocs/vtigercrm/test/templates_c -name "*.php" -delete

# Check module DB registration
docker exec mysql8 mysql -u root -padmin vtigercrm -e "
SELECT t.tabid, t.name, t.label, t.parent, a.appname, a.visible
FROM vtiger_tab t LEFT JOIN vtiger_app2tab a ON a.tabid=t.tabid
WHERE t.customized=1 ORDER BY t.tabid DESC LIMIT 20;"
```

### Khi viết SQL cho vtiger

- Dùng `PearDatabase::pquery($sql, $params)` — prepared statements
- Params là array: `['value1', 'value2']`
- Fetch: `$db->fetch_array($result)` hoặc `$db->query_result($result, $row, $col)`

---

## PHẦN 12 — FILES CẦN ĐỌC KHI TIẾP TỤC DỰ ÁN

```
# Core custom modules
modules/FcvModuleBuilder/models/Builder.php         -- Module creation logic
modules/FcvModuleBuilder/models/Relationship.php    -- Relationship logic
layouts/v7/modules/FcvModuleBuilder/Index.tpl       -- Main UI + JS
layouts/v7/modules/FcvModuleBuilder/partials/RelList.tpl

# Vtiger base (đọc khi cần hiểu framework)
data/CRMEntity.php                                  -- saveentity(), getInstance()
modules/Vtiger/models/Record.php                    -- save(), getCleanInstance()
modules/Vtiger/models/Module.php                    -- saveRecord()
modules/Assets/Assets.php                           -- Reference custom module chuẩn

# Fixed files (đừng revert)
include/database/PearDatabase.php                   -- $colNames = [] fix (line trước if block)

# Docs
docs/fcv/CHANGELOG-FcvModuleBuilder.md              -- Chi tiết từng thay đổi session này
docs/fcv/BRAIN-FcvModuleBuilder.md                  -- Brain chi tiết về FcvModuleBuilder
docs/PROJECT-BRAIN.md                               -- File này — bộ não toàn dự án
```
