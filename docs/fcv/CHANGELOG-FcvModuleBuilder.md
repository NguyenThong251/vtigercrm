# FcvModuleBuilder — Session Changelog

> **Phạm vi:** Các thay đổi được thực hiện trong session phát triển/fix bug tháng 5/2026  
> **Vtiger version:** 8.4 · PHP 8.2 · MySQL 8.0 (Docker)

---

## Tổng quan tính năng

`FcvModuleBuilder` là một Settings module tùy chỉnh cho phép:
1. **Tạo / xóa custom entity module** trực tiếp từ giao diện Settings
2. **Tạo / xóa relationship** (1:1, 1:M, M:M) giữa các module
3. **Gán / đổi nhóm nav** (MARKETING, SALES, INVENTORY, SUPPORT, PROJECT, TOOLS) cho từng module

---

## Các file thay đổi

### `modules/FcvModuleBuilder/models/Builder.php`

| Thay đổi | Lý do |
|---|---|
| `getParentMenus()` — hardcode canonical app list từ `vtiger_app2tab.appname` (MARKETING, SALES…) thay vì query `vtiger_parenttab` | `vtiger_parenttab` là bảng legacy, không phải bảng quyết định nav trong Vtiger 8 |
| `createModule()` — thêm INSERT vào `vtiger_app2tab` + UPDATE `vtiger_tab.parent` | Vtiger 8 dùng `vtiger_app2tab` để quyết định module hiển thị ở sidebar nào; chỉ cập nhật `vtiger_tab.parent` là không đủ |
| `setModuleNav()` — viết lại: DELETE cũ + INSERT mới vào `vtiger_app2tab`, sync `vtiger_tab.parent` | Cơ chế đổi nav group sau khi tạo module |
| `getModuleList()` — thêm JOIN `vtiger_app2tab` để lấy `nav_group` | Hiển thị nav group hiện tại trong danh sách module |
| **Template `generateSourceFiles()`** — constructor được sửa hoàn toàn | Xem mục "Root cause - blank page" bên dưới |
| Tất cả `catch(Exception $e)` → `catch(\Throwable $e)` | PHP 8: `Error` không extend `Exception`; phải catch `\Throwable` |

**Template constructor trước (SAI — gây blank page khi save record):**
```php
public function __construct() {
    $this->column_fields = getColumnFields('ModuleName');
}
```

**Template constructor sau (ĐÚNG — theo chuẩn Assets.php của vtiger):**
```php
function __construct() {
    global $log;
    $this->column_fields = getColumnFields(get_class($this));
    $this->db  = PearDatabase::getInstance();
    $this->log = $log;
}
```

**Tại sao cần `$this->db` và `$this->log`:**
- `CRMEntity::saveentity()` gọi `$this->db->startTransaction()` (line 122) và `$this->db->completeTransaction()` (line 134)
- Nếu `$this->db` là null → PHP Fatal Error → blank page (không có output, không có redirect)
- `$this->log` cần cho các method như `process_list_query()`, `process_full_list_query()`

**Properties cần thêm vào class:**
```php
var $IsCustomModule = true;  // Đánh dấu đây là custom module
var $db;                      // PearDatabase instance
var $log;                     // Logger instance
var $sortby_fields = [];      // Cần cho sort trong list view
```

---

### `modules/FcvModuleBuilder/models/Relationship.php`

| Thay đổi | Lý do |
|---|---|
| `deleteRelation()` — đổi signature từ `(int $relationId)` sang `(string $deleteKey)` | Cần biết nguồn dữ liệu để xóa đúng bảng |
| Thêm `delete_key` system: `tracking-{id}`, `relatedlist-{id}`, `field-{id}` | Mỗi nguồn relationship cần xóa theo cách khác nhau |
| `deleteRelation()` — thêm cascade cleanup: `vtiger_field`, `vtiger_fieldmodulerel`, `vtiger_profile2field`, `vtiger_def_org_field`, `vtiger_relatedlists` | Trước đây chỉ xóa tracking row, không xóa data thật |
| Thêm private helpers: `getTabId()`, `deleteRelateField()`, `deleteFieldById()` | Tái sử dụng logic xóa field |
| `getAllRelations()` — mỗi row thêm field `delete_key` | Template cần `delete_key` để gửi đúng identifier khi delete |
| `Vtiger_Cache::flushAllCache()` → `Vtiger_Cache::flush()` | `flushAllCache()` không tồn tại trong Vtiger 8 |
| Tất cả `catch(Exception)` → `catch(\Throwable)` | Xem Builder.php |

**Logic `delete_key`:**
```
tracking-5     → xóa trong vtiger_fcv_module_relations (id=5) + cascade cleanup fields/relatedlists
relatedlist-82 → xóa trực tiếp vtiger_relatedlists (relation_id=82)
field-47       → xóa vtiger_field (fieldid=47) + cascade fieldmodulerel/profile2field/def_org_field
```

---

### `modules/FcvModuleBuilder/actions/RelationDelete.php`

| Thay đổi | Lý do |
|---|---|
| `$relationId = (int) $request->get(...)` → `$deleteKey = trim((string) $request->get(...))` | Không cast sang int vì `delete_key` là string như "tracking-5" |
| Validation: `$relationId <= 0` → `$deleteKey === ''` | Điều kiện phù hợp với string |

---

### `modules/FcvModuleBuilder/actions/ModuleSetNav.php` *(file mới)*

POST handler để đổi nav group cho module đã tồn tại:
```
POST index.php?module=FcvModuleBuilder&action=ModuleSetNav
  module_name=Demo
  parent_menu=SALES
```
Gọi `FcvModuleBuilder_Builder_Model::setModuleNav($moduleName, $parentMenu)`.

---

### `layouts/v7/modules/FcvModuleBuilder/partials/RelList.tpl`

| Thay đổi |
|---|
| `data-id="{$r.relation_id}"` → `data-id="{$r.delete_key}"` trên nút Delete |
| Thêm badge màu cho từng `rel_type` (1:1, 1:M, M:M, relate) |

---

### `layouts/v7/modules/FcvModuleBuilder/Index.tpl`

| Thay đổi |
|---|
| Nav group dropdown trong form tạo module dùng `value="{$pm.id}"` (UPPERCASE key: MARKETING, SALES…) |
| Thêm cột "Nav Group" trong bảng module list với inline select + Apply button |
| JS handler `.fcv-set-nav` gọi action `ModuleSetNav` |
| JS handler `.fcv-delete-relation` đọc `data-id` (bây giờ là string delete_key) |

---

### `include/database/PearDatabase.php`

| Thay đổi | Lý do |
|---|---|
| `getColumnNames()`: thêm `$colNames = [];` trước if block | PHP 8: `in_array()` không chấp nhận null làm `$haystack`; khi `MetaColumns()` trả về false, `$colNames` bị undefined → `in_array(): Argument #2 must be of type array, null given` |

---

## Thay đổi cấu trúc DB (custom tracking tables)

### Bảng `vtiger_fcv_custom_modules` *(tạo bởi Builder.php)*

```sql
CREATE TABLE IF NOT EXISTS vtiger_fcv_custom_modules (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    module_name VARCHAR(100) NOT NULL UNIQUE,
    label       VARCHAR(200) DEFAULT '',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
)
```
Lưu danh sách module được tạo bởi FcvModuleBuilder. Được dùng trong `getAllRelations()` để filter relationships của custom module.

### Bảng `vtiger_fcv_module_relations` *(tạo bởi Relationship.php)*

```sql
CREATE TABLE IF NOT EXISTS vtiger_fcv_module_relations (
    relation_id INT AUTO_INCREMENT PRIMARY KEY,
    module1     VARCHAR(100) NOT NULL,
    module2     VARCHAR(100) NOT NULL,
    rel_type    VARCHAR(10)  NOT NULL,   -- '1:1', '1:M', 'M:M'
    label       VARCHAR(200) DEFAULT '',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
)
```
Tracking table cho tất cả relationship được tạo qua FcvModuleBuilder.

### Bảng vtiger core bị ảnh hưởng khi tạo module mới

| Bảng | Hành động | Nội dung |
|---|---|---|
| `vtiger_tab` | INSERT | tabid, name, label, tabsequence, modifiedby, customized=1 |
| `vtiger_app2tab` | INSERT | tabid, appname (UPPERCASE), visible=1 |
| `vtiger_tab` | UPDATE | `parent = 'Sales'` (hoặc label tương ứng với appname) |
| `vtiger_field` | INSERT (nhiều rows) | Tất cả fields: name, recordno, assignedto, description, createdtime, modifiedtime… |
| `vtiger_blocks` | INSERT | Block đầu tiên của module |
| `vtiger_profile2tab` | INSERT | Cấp quyền cho admin profile |
| `vtiger_def_org_share` | INSERT | Default sharing |
| `vtiger_{modulename}` | CREATE TABLE | Bảng data chính |
| `vtiger_{modulename}cf` | CREATE TABLE | Bảng custom fields |

---

## Bug fixes quan trọng theo thứ tự phát hiện

### 1. `Vtiger_Cache::flushAllCache()` không tồn tại
**Triệu chứng:** Fatal Error sau khi tạo relationship  
**Fix:** Đổi thành `Vtiger_Cache::flush()`

### 2. `catch(Exception)` không bắt được PHP 8 Errors
**Triệu chứng:** "Unknown error" thay vì message lỗi thực tế  
**Fix:** `catch(\Throwable $e)` trên tất cả try/catch blocks

### 3. `in_array(): Argument #2 must be of type array, null given`
**File:** `include/database/PearDatabase.php::getColumnNames()`  
**Nguyên nhân:** `$colNames` chưa được khởi tạo trước khi check  
**Fix:** Thêm `$colNames = [];` trước if block

### 4. Nav group dropdown chỉ hiện "-- None --"
**Nguyên nhân:** Query sai bảng (`vtiger_parenttab` thay vì hardcode từ `vtiger_app2tab`)  
**Fix:** `getParentMenus()` trả về hardcoded list từ `Vtiger_MenuStructure_Model::getAppMenuList()`

### 5. Module tạo xong không hiện trong nav sidebar
**Nguyên nhân:** Vtiger 8 dùng `vtiger_app2tab` để quyết định nav, không phải `vtiger_tab.parent`  
**Fix:** `createModule()` INSERT vào `vtiger_app2tab` với `appname` UPPERCASE

### 6. Blank page khi save record trong module mới tạo
**Nguyên nhân:** `CRMEntity::saveentity()` gọi `$this->db->startTransaction()` nhưng `$this->db` không được khởi tạo trong constructor  
**Fix:** Thêm `$this->db = PearDatabase::getInstance()` và `$this->log = $log` vào constructor (theo chuẩn Assets.php)

### 7. Delete relationship không clean up data
**Nguyên nhân:** `deleteRelation()` chỉ xóa tracking row, không xóa `vtiger_field`/`vtiger_relatedlists`  
**Fix:** Viết lại với cascade cleanup + `delete_key` system phân biệt nguồn

---

## Các file không cần sửa (hoạt động đúng)

- `modules/FcvModuleBuilder/FcvModuleBuilder.php` — CRMEntity class
- `modules/FcvModuleBuilder/actions/Index.php` — điều hướng view
- `modules/FcvModuleBuilder/views/Index.php` — render Smarty
- `modules/FcvModuleBuilder/actions/ModuleCreate.php` — POST handler tạo module
- `modules/FcvModuleBuilder/actions/ModuleDelete.php` — POST handler xóa module
- `modules/FcvModuleBuilder/actions/RelationCreate.php` — POST handler tạo relationship
