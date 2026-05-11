# FcvModuleBuilder — Project Brain Document

> **Mục đích:** Tài liệu này là "bộ não" của FcvModuleBuilder và toàn bộ codebase liên quan.  
> Bất kỳ agent nào đọc file này đều có thể hiểu đúng kiến trúc, pain points, quyết định thiết kế,  
> và tiếp tục phát triển / fix bug mà không cần đọc lại toàn bộ conversation history.  
>  
> **Stack:** Vtiger CRM 8.4 · PHP 8.2 · MySQL 8.0 (Docker) · Smarty 3 · ADOdb/PearDatabase

---

## 1. Tổng quan hệ thống Vtiger CRM 8.4

### Request flow
```
Browser → index.php
  → includes/main/WebUI.php (Vtiger_WebUI)
    → module controller (modules/{Module}/actions/{Action}.php)
      → model (modules/{Module}/models/*.php)
        → Smarty view (layouts/v7/modules/{Module}/*.tpl)
```

### Các thư mục quan trọng

| Thư mục | Mục đích |
|---|---|
| `modules/{Name}/` | Module controller, action, model |
| `modules/{Name}/{Name}.php` | CRMEntity subclass — **BẮT BUỘC** cho mọi entity module |
| `layouts/v7/modules/{Name}/` | Smarty templates cho module |
| `languages/en_us/{Name}.php` | Language strings |
| `vtlib/Vtiger/` | vtlib SDK: Module.php, Field.php, Block.php |
| `data/CRMEntity.php` | Base class cho tất cả entity modules |
| `include/database/PearDatabase.php` | Database wrapper (ADOdb/MySQLi) |
| `modules/Vtiger/models/Record.php` | Vtiger_Record_Model — MVC record layer |
| `modules/Vtiger/models/Module.php` | Vtiger_Module_Model — module metadata |
| `test/templates_c/v7/` | **Smarty compiled cache** (không phải `layouts/v7/smarty/templates_c/`) |

### Smarty cache location (quan trọng!)
```
C:/xampp/htdocs/vtigercrm/test/templates_c/v7/
```
Khi template không cập nhật sau khi sửa `.tpl`, xóa các file `*.php` trong thư mục này.

---

## 2. CRMEntity — Hợp đồng bắt buộc

Mỗi entity module (module có record, list view, detail view) PHẢI có file `modules/{Name}/{Name}.php` với cấu trúc sau:

```php
class MyModule extends CRMEntity {

    // ── Required markers ──────────────────────────────────────
    var $IsCustomModule = true;   // Đánh dấu custom module

    // ── PHẢI khai báo — CRMEntity::saveentity() dùng trực tiếp ──
    var $db;   // PearDatabase — saveentity() gọi $this->db->startTransaction()
    var $log;  // Logger — process_list_query() gọi $this->log->debug()

    // ── DB mapping ────────────────────────────────────────────
    var $table_name  = 'vtiger_mymodule';
    var $table_index = 'mymoduleid';

    var $tab_name = [
        'vtiger_crmentity',   // PHẢI có — base entity table
        'vtiger_mymodule',    // PHẢI có — module table
        'vtiger_mymodulecf',  // PHẢI có — custom fields table
    ];

    var $tab_name_index = [
        'vtiger_crmentity'   => 'crmid',
        'vtiger_mymodule'    => 'mymoduleid',
        'vtiger_mymodulecf'  => 'mymoduleid',
    ];

    var $entity_table    = 'vtiger_crmentity';
    var $customFieldTable = ['vtiger_mymodulecf', 'mymoduleid'];

    // ── Column fields — được override bởi CRMEntity::getInstance() ──
    var $column_fields = [];

    // ── Các fields phụ cần cho saving ─────────────────────────
    var $additional_column_fields = ['smcreatorid', 'smownerid', 'crmid'];

    // ── Mandatory fields (validation) ─────────────────────────
    var $mandatory_fields = ['assigned_user_id', 'mymodulename', 'createdtime', 'modifiedtime'];

    // ── List view ─────────────────────────────────────────────
    var $sortby_fields  = [];
    var $list_fields = [
        'Name'        => ['mymodule' => 'mymodulename'],
        'Assigned To' => ['crmentity' => 'smownerid'],
    ];
    var $list_fields_name = [
        'Name'        => 'mymodulename',
        'Assigned To' => 'assigned_user_id',  // Phải là 'assigned_user_id' không phải 'smownerid'
    ];
    var $list_link_field = 'mymodulename';

    // ── Search / popup ────────────────────────────────────────
    var $search_fields = [
        'Name'        => ['mymodule' => 'mymodulename'],
        'Assigned To' => ['crmentity' => 'smownerid'],
    ];
    var $search_fields_name = [
        'Name'        => 'mymodulename',
        'Assigned To' => 'smownerid',  // Đây dùng 'smownerid' (khác list_fields_name)
    ];
    var $def_basicsearch_col = 'mymodulename';

    // ── Sorting ───────────────────────────────────────────────
    var $default_order_by   = 'mymodulename';
    var $default_sort_order = 'ASC';

    var $module_name = 'MyModule';

    // ── CONSTRUCTOR — phải có đủ 3 dòng này ──────────────────
    function __construct() {
        global $log;
        $this->column_fields = getColumnFields(get_class($this));  // get_class() thay vì hardcode
        $this->db  = PearDatabase::getInstance();                  // PHẢI CÓ
        $this->log = $log;                                         // PHẢI CÓ
    }

    // ── Hook bắt buộc của CRMEntity ───────────────────────────
    function save_module($module) {}
}
```

### Tại sao `$this->db` và `$this->log` là bắt buộc

```php
// data/CRMEntity.php — saveentity() lines 122-134
function saveentity($module, $fileid = '') {
    // ...
    $this->db->startTransaction();    // ← Fatal Error nếu $this->db null
    foreach ($this->tab_name as $table_name) {
        // ...insertIntoCrmEntity() / insertIntoEntityTable() — dùng global $adb
    }
    $this->db->completeTransaction(); // ← Fatal Error nếu $this->db null
}
```

### Flow save record (đầy đủ)

```
POST index.php?module=Demo&action=Save
  → Vtiger_Save_Action::process()
    → getRecordModelFromRequest()
      → Vtiger_Record_Model::getCleanInstance('Demo')
        → CRMEntity::getInstance('Demo')
          → new Demo()           // constructor chạy: set column_fields, db, log
          → $focus->column_fields = getColumnFields('Demo')   // OVERRIDE column_fields
          → $focus->initialize() // thêm vtiger_crmentity_user_field vào tab_name
      → set field values từ $_POST vào recordModel
    → $recordModel->save()
      → Vtiger_Module_Model::saveRecord($recordModel)
        → $focus = $recordModel->getEntity()
        → populate $focus->column_fields từ recordModel
        → $focus->save('Demo')
          → CRMEntity::save()
            → saveentity()
              → $this->db->startTransaction()  ← cần $this->db
              → insertIntoCrmEntity()          ← dùng global $adb
              → insertIntoEntityTable(...)     ← dùng global $adb
              → save_module($module)           ← empty hook
              → $this->db->completeTransaction()
          → header("Location: DetailViewUrl") ← redirect sau khi save
```

**Lưu ý quan trọng:** `CRMEntity::getInstance()` OVERRIDE `column_fields` sau khi constructor chạy.  
Constructor vẫn cần gọi `getColumnFields()` để tránh lỗi khi class được instantiate theo cách khác.

---

## 3. Nav / Menu System trong Vtiger 8

### Hai hệ thống nav (dễ nhầm lẫn)

| Bảng | Vai trò trong Vtiger 8 |
|---|---|
| `vtiger_tab.parent` | Legacy text field (`'Sales'`, `'Marketing'`…) — dùng bởi MenuStructure_Model để group nhưng KHÔNG quyết định visibility |
| `vtiger_app2tab` | **Bảng chính** — quyết định module xuất hiện ở nav group nào và có visible không |
| `vtiger_parenttab` | Bảng cũ (Vtiger 6/7) — KHÔNG được dùng trong Vtiger 8 |

### Schema `vtiger_app2tab`

```sql
tabid    INT          -- FK → vtiger_tab.tabid
appname  VARCHAR(100) -- UPPERCASE: 'MARKETING', 'SALES', 'INVENTORY', 'SUPPORT', 'PROJECT', 'TOOLS'
visible  TINYINT      -- 1 = hiện, 0 = ẩn
```

### Canonical app names (UPPERCASE — phải dùng đúng)

```php
// Vtiger_MenuStructure_Model::getAppMenuList()
['MARKETING', 'SALES', 'INVENTORY', 'SUPPORT', 'PROJECT', 'TOOLS']
```

### Mapping appname → vtiger_tab.parent (lowercase label)

```
MARKETING → 'Marketing'
SALES     → 'Sales'
INVENTORY → 'Inventory'
SUPPORT   → 'Support'
PROJECT   → 'Project'
TOOLS     → 'Tools'
```

### Tạo module + gán nav đúng cách

```php
// 1. Insert vào vtiger_app2tab
$db->pquery(
    'INSERT INTO vtiger_app2tab (tabid, appname, visible) VALUES (?,?,1)',
    [$tabId, 'SALES']
);

// 2. Sync vtiger_tab.parent (legacy — không thể bỏ qua)
$db->pquery(
    'UPDATE vtiger_tab SET parent=? WHERE tabid=?',
    ['Sales', $tabId]
);
```

### Đổi nav group sau khi tạo module

```php
// Xóa app cũ, thêm app mới
$db->pquery('DELETE FROM vtiger_app2tab WHERE tabid=?', [$tabId]);
$db->pquery('INSERT INTO vtiger_app2tab (tabid, appname, visible) VALUES (?,?,1)', [$tabId, $newAppName]);
$db->pquery('UPDATE vtiger_tab SET parent=? WHERE tabid=?', [$parentLabel, $tabId]);
```

---

## 4. Relationship System

### Ba loại relationship và cách vtlib tạo

| Type | Vtiger mechanism | Bảng bị ảnh hưởng |
|---|---|---|
| **1:M** | Relate field (uitype 10) trên child + related list trên parent | `vtiger_field`, `vtiger_fieldmodulerel`, `vtiger_relatedlists` |
| **M:M** | Related lists cả hai phía | `vtiger_relatedlists` |
| **1:1** | Relate field (uitype 10) cả hai phía | `vtiger_field`, `vtiger_fieldmodulerel` |

### Relate field (uitype 10)

```php
$field = new Vtiger_Field();
$field->name      = 'parentmodule_id';
$field->label     = 'Parent Module';
$field->uitype    = 10;
$field->typeofdata = 'I~O';
$block->addField($field);
$field->setRelatedModules(['ParentModule']);
```

### Related list

```php
$module->setRelatedList(
    $relatedModule,
    'Related Module Label',
    ['ADD', 'SELECT', 'DELETE']
);
```

### delete_key system (FcvModuleBuilder)

Khi hiển thị relationship list, mỗi row có một `delete_key` string encode nguồn dữ liệu:

```
tracking-{id}     → Xóa vtiger_fcv_module_relations + cascade cleanup
relatedlist-{id}  → Xóa trực tiếp vtiger_relatedlists
field-{id}        → Xóa vtiger_field + vtiger_fieldmodulerel + vtiger_profile2field + vtiger_def_org_field
```

**Cascade khi xóa field (fieldid):**
```sql
DELETE FROM vtiger_fieldmodulerel WHERE fieldid=?;
DELETE FROM vtiger_profile2field  WHERE fieldid=?;
DELETE FROM vtiger_def_org_field  WHERE fieldid=?;
DELETE FROM vtiger_field          WHERE fieldid=?;
```

---

## 5. FcvModuleBuilder — Kiến trúc chi tiết

### File structure

```
modules/FcvModuleBuilder/
├── FcvModuleBuilder.php           -- CRMEntity class (Settings module)
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
├── Index.tpl                      -- Main UI (tabs, forms, JS)
└── partials/
    ├── RelForm.tpl                -- Form tạo relationship
    └── RelList.tpl                -- Danh sách relationship hiện có
```

### API Actions

| Action | Method | Params | Response |
|---|---|---|---|
| `ModuleCreate` | POST | `module_name`, `module_label`, `description`, `parent_menu` | JSON `{success, message}` |
| `ModuleDelete` | POST | `module_name` | JSON `{success, message}` |
| `ModuleSetNav` | POST | `module_name`, `parent_menu` | JSON `{success, message}` |
| `RelationCreate` | POST | `primary_module`, `child_module`, `rel_type` (1M/MM/11), `field_label`, `rel_list_label` | JSON `{success, message}` |
| `RelationDelete` | POST | `relation_id` (string delete_key) | JSON `{success, message}` |

### Tracking tables

**`vtiger_fcv_custom_modules`** — Danh sách module được tạo bởi FcvModuleBuilder:
```sql
id INT PK, module_name VARCHAR(100) UNIQUE, label VARCHAR(200), created_at DATETIME
```

**`vtiger_fcv_module_relations`** — Danh sách relationship được tạo bởi FcvModuleBuilder:
```sql
relation_id INT PK, module1 VARCHAR(100), module2 VARCHAR(100),
rel_type VARCHAR(10), label VARCHAR(200), created_at DATETIME
```

### `generateSourceFiles()` — file được tạo khi tạo module

1. `modules/{Name}/{Name}.php` — CRMEntity class (xem chuẩn ở mục 2)
2. `languages/en_us/{Name}.php` — Language strings
3. `layouts/v7/modules/{Name}/` — Thư mục layout (tạo trống, vtlib render từ Vtiger base)

---

## 6. PHP 8 Gotchas trong Vtiger 8.4

### `catch(\Throwable)` thay vì `catch(Exception)`

PHP 8 strict: `Error` class (Fatal Error, TypeError…) **không** extend `Exception`.  
Mọi try/catch trong FcvModuleBuilder phải dùng `catch(\Throwable $e)`.

```php
// SAI — sẽ miss TypeError, Error
try { ... } catch (Exception $e) { ... }

// ĐÚNG
try { ... } catch (\Throwable $e) { ... }
```

### `in_array()` với null haystack

PHP 8: `in_array(needle, null)` throw `TypeError`.  
Bất kỳ code nào gọi `in_array()` cần đảm bảo argument thứ 2 là array:

```php
// PearDatabase::getColumnNames() — đã fix
$colNames = [];  // Phải init trước
if ($result) {
    while ($row = ...) {
        $colNames[] = $row[0];
    }
}
```

### `Vtiger_Cache::flush()` (không phải `flushAllCache()`)

```php
Vtiger_Cache::flush();         // ĐÚNG — tồn tại trong Vtiger 8
Vtiger_Cache::flushAllCache(); // SAI — không tồn tại
```

### `Vtiger_Menu::addModule()` gọi hàm không tồn tại

`vtlib/Vtiger/Menu.php::addModule()` gọi `create_parenttab_data_file()` — function này không tồn tại trong Vtiger 8.  
**Không dùng** `Vtiger_Menu::addModule()` để thêm module vào nav. Thay vào đó dùng SQL trực tiếp vào `vtiger_app2tab`.

---

## 7. Database — Cấu trúc quan trọng

### vtiger_tab

```sql
tabid        INT PK AUTO_INCREMENT
name         VARCHAR(25) UNIQUE   -- Module name (PascalCase)
presence     INT(1)               -- 0=enabled, 1=disabled
tabsequence  INT                  -- -1 = ẩn khỏi top menu
label        VARCHAR(100)         -- Display label
customized   TINYINT(1)           -- 1 = custom module
parent       VARCHAR(100)         -- Legacy: 'Sales', 'Marketing'… (dùng kèm vtiger_app2tab)
modifiedby   INT
ownedby      INT
isentitytype TINYINT(1)           -- 1 = entity module (có records), 0 = settings module
```

### vtiger_field (quan trọng nhất)

```sql
tabid        INT   -- FK → vtiger_tab
fieldid      INT PK AUTO_INCREMENT
fieldname    VARCHAR(200)  -- field identifier (lowercase)
fieldlabel   VARCHAR(200)  -- display label
columnname   VARCHAR(200)  -- DB column name
tablename    VARCHAR(200)  -- DB table name
uitype       INT           -- 10=relate, 19=text, 52=owner, 70=datetime…
typeofdata   VARCHAR(20)   -- 'V~M'=required string, 'I~O'=optional int
presence     INT           -- 0=visible, 2=hidden
```

### vtiger_relatedlists

```sql
relation_id     INT PK AUTO_INCREMENT
tabid           INT   -- Primary module tabid
related_tabid   INT   -- Related module tabid
name            VARCHAR(100)  -- Method name trên CRMEntity (hoặc vtlib generic)
sequence        INT
label           VARCHAR(100)  -- Display label
relationtype    VARCHAR(50)   -- '1:M', 'M:M', etc.
```

### vtiger_crmentity

```sql
crmid        INT PK AUTO_INCREMENT   -- Universal record ID
smcreatorid  INT
smownerid    INT                     -- Assigned user/group ID
setype       VARCHAR(30)             -- Module name ('Demo', 'Leads'…)
deleted      TINYINT(1)              -- Soft delete
label        VARCHAR(255)            -- Record title (auto-populated)
createdtime  DATETIME
modifiedtime DATETIME
modifiedby   INT
```

### vtiger_crmentity_user_field

```sql
recordid     INT   -- FK → vtiger_crmentity.crmid
starred      TINYINT(1)
```

Bảng này được `CRMEntity::initialize()` tự động thêm vào `$this->tab_name` khi save.  
Bảng tồn tại globally (không cần tạo per module).

---

## 8. Module xóa — cleanup đầy đủ

Khi xóa một custom module, phải xóa theo thứ tự sau (vì có FK constraints):

```sql
-- 1. Records
DELETE FROM vtiger_{lcname}cf WHERE {lcname}id IN (SELECT crmid FROM vtiger_crmentity WHERE setype=?);
DELETE FROM vtiger_{lcname}   WHERE {lcname}id IN (SELECT crmid FROM vtiger_crmentity WHERE setype=?);
DELETE FROM vtiger_crmentity_user_field WHERE recordid IN (...);
DELETE FROM vtiger_seactivityrel WHERE crmid IN (...);
DELETE FROM vtiger_crmentityrel WHERE crmid IN (...) OR relcrmid IN (...);
DELETE FROM vtiger_crmentity WHERE setype=?;

-- 2. Fields (cascade)
DELETE fmr FROM vtiger_fieldmodulerel fmr JOIN vtiger_field f ON f.fieldid=fmr.fieldid WHERE f.tabid=?;
DELETE p2f FROM vtiger_profile2field p2f JOIN vtiger_field f ON f.fieldid=p2f.fieldid WHERE f.tabid=?;
DELETE dof FROM vtiger_def_org_field dof JOIN vtiger_field f ON f.fieldid=dof.fieldid WHERE f.tabid=?;
DELETE FROM vtiger_field WHERE tabid=?;
DELETE FROM vtiger_fieldmodulerel WHERE relmodule=?;  -- relate fields pointing AT this module

-- 3. Structure
DELETE FROM vtiger_blocks WHERE tabid=?;
DELETE FROM vtiger_relatedlists WHERE tabid=? OR related_tabid=?;

-- 4. Tracking
DELETE FROM vtiger_fcv_module_relations WHERE module1=? OR module2=?;
DELETE FROM vtiger_fcv_custom_modules WHERE module_name=?;

-- 5. Tab registration
DELETE FROM vtiger_app2tab WHERE tabid=?;
DELETE FROM vtiger_profile2tab WHERE tabid=?;
DELETE FROM vtiger_def_org_share WHERE tabid=?;
DELETE FROM vtiger_parenttabrel WHERE tabid=?;
DELETE FROM vtiger_ws_entity WHERE name=?;
DELETE FROM vtiger_tab WHERE tabid=?;

-- 6. Drop tables
DROP TABLE IF EXISTS vtiger_{lcname}cf;
DROP TABLE IF EXISTS vtiger_{lcname};

-- 7. Files
rm -rf modules/{Name}/
rm -f  languages/en_us/{Name}.php
rm -rf layouts/v7/modules/{Name}/
```

---

## 9. Smarty & Cache

### Template cache

```
test/templates_c/v7/   ← Compiled Smarty templates
```

Khi sửa `.tpl` mà UI không cập nhật → xóa file `*FcvModuleBuilder*` trong thư mục trên.

### Vtigercrm log

```
logs/vtigercrm.log     ← FATAL level only (mặc định)
```

Để debug, tạm thời đổi `log4php.properties`:
```
log4php.rootLogger=DEBUG,A1
```

### PHP error log

XAMPP PHP error log: `C:/xampp/php/logs/php_error_log` (hoặc theo `php.ini`)

---

## 10. Kết nối MySQL

```bash
# Docker (production-like)
docker exec mysql8 mysql -u root -padmin vtigercrm -e "SQL..."

# Kiểm tra tables
docker exec mysql8 mysql -u root -padmin vtigercrm -e "SHOW TABLES LIKE 'vtiger_demo%';"

# Check tab registration
docker exec mysql8 mysql -u root -padmin vtigercrm -e "
  SELECT t.tabid, t.name, t.label, t.parent, a.appname, a.visible
  FROM vtiger_tab t
  LEFT JOIN vtiger_app2tab a ON a.tabid=t.tabid
  WHERE t.name='Demo';
"
```

---

## 11. Checklist khi tạo custom module mới (không dùng FcvModuleBuilder)

- [ ] File `modules/{Name}/{Name}.php` với constructor đầy đủ (`$this->db`, `$this->log`)
- [ ] Declare `var $IsCustomModule = true`
- [ ] `tab_name` gồm đúng 3 bảng: crmentity, module, modulecf
- [ ] `tab_name_index` đủ cho cả 3 bảng
- [ ] `list_fields_name` dùng `'assigned_user_id'` (không phải `'smownerid'`)
- [ ] Language file `languages/en_us/{Name}.php` với `$languageStrings`
- [ ] INSERT vào `vtiger_tab` với `isentitytype=1`, `customized=1`
- [ ] INSERT vào `vtiger_app2tab` với đúng UPPERCASE appname
- [ ] Tạo bảng `vtiger_{lcname}` và `vtiger_{lcname}cf`
- [ ] Tạo fields qua vtlib `Vtiger_Field` hoặc SQL trực tiếp
- [ ] INSERT vào `vtiger_profile2tab` (cấp quyền admin)

---

## 12. Pain Points & Anti-patterns đã gặp

| Anti-pattern | Hệ quả | Solution |
|---|---|---|
| Không set `$this->db` trong constructor | Fatal Error khi save → blank page | Luôn set trong constructor |
| `catch(Exception)` thay vì `catch(\Throwable)` | Lỗi PHP Error bị nuốt, hiện "Unknown error" | Dùng `\Throwable` |
| Dùng `vtiger_parenttab` để lấy nav groups | Dropdown empty | Hardcode từ `Vtiger_MenuStructure_Model::getAppMenuList()` |
| Chỉ update `vtiger_tab.parent` khi gán nav | Module không hiện trong nav | Phải INSERT/UPDATE `vtiger_app2tab` |
| Gọi `Vtiger_Menu::addModule()` | Fatal Error — hàm `create_parenttab_data_file()` không tồn tại | SQL trực tiếp vào `vtiger_app2tab` |
| Gọi `Vtiger_Cache::flushAllCache()` | Fatal Error — method không tồn tại | Dùng `Vtiger_Cache::flush()` |
| `in_array()` với `$colNames` chưa init | TypeError trong PHP 8 | Init `$colNames = []` trước |
| Delete relationship không cascade | Orphan fields/relatedlists trong DB | Cascade delete theo thứ tự đúng |
| Hardcode module name trong constructor (`getColumnFields('Demo')`) | Khi copy class, sẽ query sai module | Dùng `getColumnFields(get_class($this))` |
