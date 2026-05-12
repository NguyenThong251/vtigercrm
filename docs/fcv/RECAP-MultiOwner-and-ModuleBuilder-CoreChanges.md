# Recap: FCVMultiOwner and FcvModuleBuilder Core Changes

> Last updated: 2026-05-12
>
> Purpose: this document summarizes the two active/custom feature areas in this
> Vtiger CRM source tree. It focuses on what each feature does, which core files
> were patched, which module files were added/changed, which database tables are
> touched, and what a future agent should read before continuing.

---

## 1. Current Scope

There are two related feature tracks:

| Feature | Status | Goal |
|---|---|---|
| `FCVMultiOwner` | In progress | Add a custom `uitype=200` "Multi Owners" field. Each record can have extra users with `read` or `write` permission, beyond the normal `assigned_user_id`. |
| `FcvModuleBuilder` | Existing feature, recently fixed | Settings module for creating/deleting custom modules and relationships. Recent work fixed module creation, nav registration, sharing visibility, default list view, and cleanup bugs. |

These two tracks touch different parts of the system:

- `FCVMultiOwner` adds a new utility module plus core ACL/list-query integration.
- `FcvModuleBuilder` mostly lives inside its own Settings module, but it also writes Vtiger metadata tables when creating modules.

---

## 2. FCVMultiOwner Feature Recap

### What It Does

`FCVMultiOwner` introduces a custom field type:

```text
field type label: Multi Owners
uitype: 200
field data type: FCVMultiOwner
storage field: varchar JSON blob on the module table/custom field table
real permission storage: vtiger_fcv_multiowner
```

The UI lets a user:

1. Click "Add owner".
2. Search active Vtiger users.
3. Add one or more users as chips.
4. Toggle each user between `R` and `W`.
5. Save the record.

After save, the JSON value from the hidden input is synchronized into
`vtiger_fcv_multiowner`. That table is then used by ACL and list queries.

### Main Data Model

Created by [modules/FCVMultiOwner/setup.php](../../modules/FCVMultiOwner/setup.php):

```sql
vtiger_fcv_multiowner (
    id,
    crmid,
    userid,
    tabid,
    permission ENUM('read','write'),
    created_at,
    UNIQUE (crmid, userid)
)
```

Purpose:

- `crmid`: record receiving extra owners.
- `userid`: extra owner user.
- `tabid`: module id for that record.
- `permission`: `read` means view only; `write` means view and edit.

Also created:

```sql
vtiger_fcv_multiowner_grants (
    id,
    userid,
    tabid,
    UNIQUE (userid, tabid)
)
```

Purpose: track module tab access automatically granted when a user is added as a multi-owner.

### FCVMultiOwner Module Files Added

| File | Responsibility |
|---|---|
| [modules/FCVMultiOwner/FCVMultiOwner.php](../../modules/FCVMultiOwner/FCVMultiOwner.php) | Minimal module class so Vtiger can route `module=FCVMultiOwner`. This is a utility module, not a normal entity module. |
| [modules/FCVMultiOwner/setup.php](../../modules/FCVMultiOwner/setup.php) | One-time setup: registers `FCVMultiOwner` in `vtiger_tab`, creates DB tables, registers event handler, registers `uitype=200` as `FCVMultiOwner`. |
| [modules/FCVMultiOwner/models/MultiOwner.php](../../modules/FCVMultiOwner/models/MultiOwner.php) | Business logic: read owners for a record, sync owners after save, delete owners after delete, search active users, auto-grant module tab access. |
| [modules/FCVMultiOwner/FCVMultiOwnerHandler.php](../../modules/FCVMultiOwner/FCVMultiOwnerHandler.php) | `VTEventHandler` for `vtiger.entity.aftersave` and `vtiger.entity.afterdelete`. Finds all `uitype=200` fields for the saved module and syncs owner rows. |
| [modules/FCVMultiOwner/actions/SearchUsers.php](../../modules/FCVMultiOwner/actions/SearchUsers.php) | AJAX endpoint used by the picker: `index.php?module=FCVMultiOwner&action=SearchUsers&query=...`. |
| [modules/FCVMultiOwner/add_field_to_module.php](../../modules/FCVMultiOwner/add_field_to_module.php) | CLI helper to add a fixed `fcv_multiowner_data` field to a target module. Layout Editor support is now the preferred UI path. |

### FCVMultiOwner UI Files Added

| File | Responsibility |
|---|---|
| [modules/Vtiger/uitypes/FCVMultiOwner.php](../../modules/Vtiger/uitypes/FCVMultiOwner.php) | Vtiger UIType class. Chooses edit template, renders read-only chips in detail view, loads existing owners, stores raw JSON field value. |
| [layouts/v7/modules/Vtiger/uitypes/FCVMultiOwner.tpl](../../layouts/v7/modules/Vtiger/uitypes/FCVMultiOwner.tpl) | Edit/create template. Adds hidden input named as the actual field name and renders the chip wrapper. |
| [layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.js](../../layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.js) | Chip behavior: init, search popup, AJAX user search, add/remove user, toggle read/write, serialize to JSON. |
| [layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.css](../../layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.css) | Chip and popup styles for edit/detail rendering. |

### Core Files Patched for FCVMultiOwner

These are the important core modifications. Do not remove them unless the feature is being intentionally removed.

| Core file | Change | Reason |
|---|---|---|
| [data/CRMEntity.php](../../data/CRMEntity.php) | Added `fcvMultiOwnerActive()` and extended `getNonAdminAccessControlQuery()` to include records where current user appears in `vtiger_fcv_multiowner`. | Makes list views show records assigned through Multi Owners, not only records where user is primary `smownerid`. |
| [include/utils/UserInfoUtil.php](../../include/utils/UserInfoUtil.php) | Patched `isReadPermittedBySharing()` to allow access if `(crmid, userid)` exists in `vtiger_fcv_multiowner`. | Allows detail/read access for extra owners. |
| [include/utils/UserInfoUtil.php](../../include/utils/UserInfoUtil.php) | Patched `isReadWritePermittedBySharing()` to allow write access only if the row has `permission='write'`. | Separates read-only multi-owner from write-capable multi-owner. |
| [modules/Settings/LayoutEditor/models/Module.php](../../modules/Settings/LayoutEditor/models/Module.php) | Added `FCVMultiOwner` to supported field types and mapped it to `uitype=200`, `VARCHAR(255)`, `V~O`. | Allows admins to add "Multi Owners" from Layout Editor. |
| [languages/en_us/Settings/LayoutEditor.php](../../languages/en_us/Settings/LayoutEditor.php) | Added label `'FCVMultiOwner' => 'Multi Owners'`. | Human-readable label in Layout Editor. |

### How Save/Read Works

```text
Edit/Create view
  -> FCVMultiOwner.tpl renders hidden field with JSON
  -> fcv-multiowner.js writes owners JSON before submit
  -> normal Vtiger save stores JSON in the field column
  -> FCVMultiOwnerHandler receives vtiger.entity.aftersave
  -> handler finds all uitype=200 fields for this module
  -> handler decodes JSON
  -> MultiOwner::syncForRecord() replaces vtiger_fcv_multiowner rows
  -> ACL/list-query patches read vtiger_fcv_multiowner later
```

### Setup and Verification Commands

Run setup once from the Vtiger root:

```powershell
php modules/FCVMultiOwner/setup.php
```

Check tables:

```powershell
docker exec mysql8 mysql -u root -padmin vtigercrm -e "SHOW TABLES LIKE 'vtiger_fcv_multiowner%';"
```

Check field type registration:

```powershell
docker exec mysql8 mysql -u root -padmin vtigercrm -e "SELECT * FROM vtiger_ws_fieldtype WHERE fieldtypename='FCVMultiOwner' OR uitype=200;"
```

Important environment note from project brain: use `docker exec mysql8 ...`; do not use `docker exec -i mysql8 ...` in this environment.

### Known Caution Points

- `CRMEntity.php` currently uses a subquery inside the sharing join:
  `SELECT crmid FROM vtiger_fcv_multiowner WHERE userid={$user->id}`.
  It is cached behind `fcvMultiOwnerActive()` so installs without the table do not crash.
- `UserInfoUtil.php` fallback checks assume `vtiger_fcv_multiowner` exists. In normal flow, setup creates it first.
- `permission='read'` grants detail/list access but not write access.
- The field column stores JSON only as a transport/display value. The actual permission source of truth is `vtiger_fcv_multiowner`.
- Clear Smarty cache if the new UIType template does not appear:

```powershell
Remove-Item -Path "test\templates_c\v7\*.php"
```

---

## 3. FcvModuleBuilder Fix Recap

### What It Does

`FcvModuleBuilder` is a Settings module that can:

1. Create custom entity modules.
2. Delete custom entity modules.
3. Create/delete relationships: `1:1`, `1:M`, `M:M`.
4. Assign or change the Vtiger 8 nav app group: `MARKETING`, `SALES`, `INVENTORY`, `SUPPORT`, `PROJECT`, `TOOLS`.
5. Snapshot generated files before create/delete operations.

Primary module directory:

```text
modules/FcvModuleBuilder/
layouts/v7/modules/FcvModuleBuilder/
languages/en_us/FcvModuleBuilder.php
storage/fcvmodulebuilder/backups/
```

### FcvModuleBuilder Module Files

| File | Responsibility |
|---|---|
| [modules/FcvModuleBuilder/FcvModuleBuilder.php](../../modules/FcvModuleBuilder/FcvModuleBuilder.php) | Settings module base class. |
| [modules/FcvModuleBuilder/models/Builder.php](../../modules/FcvModuleBuilder/models/Builder.php) | Create/delete module logic, nav assignment, generated source templates, module listing. |
| [modules/FcvModuleBuilder/models/Relationship.php](../../modules/FcvModuleBuilder/models/Relationship.php) | Create/delete relationships and cascade cleanup. |
| [modules/FcvModuleBuilder/models/Backup.php](../../modules/FcvModuleBuilder/models/Backup.php) | File backup/snapshot support for generated modules. |
| [modules/FcvModuleBuilder/views/Index.php](../../modules/FcvModuleBuilder/views/Index.php) | Assigns data to Smarty and renders main Settings UI. |
| [modules/FcvModuleBuilder/actions/ModuleCreate.php](../../modules/FcvModuleBuilder/actions/ModuleCreate.php) | POST action for module creation. |
| [modules/FcvModuleBuilder/actions/ModuleDelete.php](../../modules/FcvModuleBuilder/actions/ModuleDelete.php) | POST action for module deletion. |
| [modules/FcvModuleBuilder/actions/ModuleUndo.php](../../modules/FcvModuleBuilder/actions/ModuleUndo.php) | POST action for restoring/deleting from backup flow. |
| [modules/FcvModuleBuilder/actions/ModuleSetNav.php](../../modules/FcvModuleBuilder/actions/ModuleSetNav.php) | POST action for changing nav group after creation. |
| [modules/FcvModuleBuilder/actions/RelationCreate.php](../../modules/FcvModuleBuilder/actions/RelationCreate.php) | POST action for relationship creation. |
| [modules/FcvModuleBuilder/actions/RelationDelete.php](../../modules/FcvModuleBuilder/actions/RelationDelete.php) | POST action for relationship deletion using string `delete_key`. |
| [layouts/v7/modules/FcvModuleBuilder/Index.tpl](../../layouts/v7/modules/FcvModuleBuilder/Index.tpl) | Main UI: tabs, module form/list, nav selector, JS handlers. |
| [layouts/v7/modules/FcvModuleBuilder/partials/RelForm.tpl](../../layouts/v7/modules/FcvModuleBuilder/partials/RelForm.tpl) | Relationship creation form partial. |
| [layouts/v7/modules/FcvModuleBuilder/partials/RelList.tpl](../../layouts/v7/modules/FcvModuleBuilder/partials/RelList.tpl) | Relationship list/delete partial. |

### Important Core/Metadata Tables Touched by Module Creation

`FcvModuleBuilder` mostly avoids patching PHP core, but it writes many Vtiger metadata tables through vtlib and direct SQL.

| Table | Why it is touched |
|---|---|
| `vtiger_tab` | Registers the module and its label. Also stores legacy `parent` value. |
| `vtiger_app2tab` | Vtiger 8 nav source of truth. Required for module to appear in sidebar under an app group. |
| `vtiger_blocks` | Creates the default module block. |
| `vtiger_field` | Creates module fields, including name, assigned user, created/modified time, description, etc. |
| `vtiger_profile2tab` | Grants profile visibility to the module. |
| `vtiger_def_org_share` | Adds module to Settings -> Sharing Access. Without this row, the module can be invisible in sharing settings. |
| `vtiger_customview` | Creates default `All` list view. Required because `cvid` is not auto-increment in this schema. |
| `vtiger_cvcolumnlist` | Adds columns for the default `All` custom view. |
| `vtiger_relatedlists` | Stores related list relationships. |
| `vtiger_fieldmodulerel` | Stores relate-field relationships. |
| `vtiger_profile2field` | Field permissions cleanup when deleting relationship fields. |
| `vtiger_def_org_field` | Field org default cleanup when deleting relationship fields. |
| `vtiger_fcv_custom_modules` | Custom tracking table for modules created by FcvModuleBuilder. |
| `vtiger_fcv_module_relations` | Custom tracking table for relationships created by FcvModuleBuilder. |

### Recent FcvModuleBuilder Fixes

| Area | File(s) | What changed |
|---|---|---|
| Vtiger 8 nav registration | [Builder.php](../../modules/FcvModuleBuilder/models/Builder.php), [ModuleSetNav.php](../../modules/FcvModuleBuilder/actions/ModuleSetNav.php), [Index.tpl](../../layouts/v7/modules/FcvModuleBuilder/Index.tpl) | Use `vtiger_app2tab` as the real nav table. `vtiger_tab.parent` is only synchronized as legacy metadata. |
| Nav group list | [Builder.php](../../modules/FcvModuleBuilder/models/Builder.php) | `getParentMenus()` uses canonical app keys instead of the old `vtiger_parenttab` table. |
| Generated module class | [Builder.php](../../modules/FcvModuleBuilder/models/Builder.php) | `generateSourceFiles()` now creates a proper `CRMEntity` subclass with `$db`, `$log`, `$IsCustomModule`, `getColumnFields(get_class($this))`, `save_module()`, and correct list/search mappings. |
| Blank page on save | [Builder.php](../../modules/FcvModuleBuilder/models/Builder.php) | Root cause was generated entity constructors missing `$this->db = PearDatabase::getInstance()`. |
| PHP 8 error handling | [Builder.php](../../modules/FcvModuleBuilder/models/Builder.php), [Relationship.php](../../modules/FcvModuleBuilder/models/Relationship.php) | Use `catch (\Throwable $e)` instead of `catch (Exception $e)` so PHP 8 `Error`/`TypeError` is caught. |
| Relationship delete cleanup | [Relationship.php](../../modules/FcvModuleBuilder/models/Relationship.php), [RelationDelete.php](../../modules/FcvModuleBuilder/actions/RelationDelete.php), [RelList.tpl](../../layouts/v7/modules/FcvModuleBuilder/partials/RelList.tpl) | Delete now uses string keys like `tracking-5`, `relatedlist-82`, `field-47` and cascades cleanup through Vtiger metadata tables. |
| Cache flush | [Builder.php](../../modules/FcvModuleBuilder/models/Builder.php), [Relationship.php](../../modules/FcvModuleBuilder/models/Relationship.php) | Use `Vtiger_Cache::flush()` instead of nonexistent `flushAllCache()`. |
| Sharing Access visibility | [Builder.php](../../modules/FcvModuleBuilder/models/Builder.php) | Module creation inserts `vtiger_def_org_share`. Module deletion removes it. |
| Default list view | [Builder.php](../../modules/FcvModuleBuilder/models/Builder.php) | Module creation inserts a default `All` row in `vtiger_customview` plus records in `vtiger_cvcolumnlist`. |

### Generated Module Contract

Every entity module generated by FcvModuleBuilder must have a valid
`modules/{Module}/{Module}.php` class:

```php
class MyModule extends CRMEntity {
    var $IsCustomModule = true;
    var $db;
    var $log;

    var $table_name  = 'vtiger_mymodule';
    var $table_index = 'mymoduleid';

    var $tab_name = [
        'vtiger_crmentity',
        'vtiger_mymodule',
        'vtiger_mymodulecf',
    ];

    var $tab_name_index = [
        'vtiger_crmentity' => 'crmid',
        'vtiger_mymodule' => 'mymoduleid',
        'vtiger_mymodulecf' => 'mymoduleid',
    ];

    var $customFieldTable = ['vtiger_mymodulecf', 'mymoduleid'];
    var $column_fields = [];

    function __construct() {
        global $log;
        $this->column_fields = getColumnFields(get_class($this));
        $this->db = PearDatabase::getInstance();
        $this->log = $log;
    }

    function save_module($module) {}
}
```

If `$this->db` is missing, `CRMEntity::saveentity()` crashes when it calls
`$this->db->startTransaction()`, causing a blank page after record save.

### Relationship Delete Key System

Relationship rows can come from different sources, so delete cannot use a plain integer id.

```text
tracking-{id}
  -> delete row from vtiger_fcv_module_relations
  -> cascade cleanup related fields/related lists

relatedlist-{relation_id}
  -> delete directly from vtiger_relatedlists

field-{fieldid}
  -> delete vtiger_field row
  -> cleanup vtiger_fieldmodulerel, vtiger_profile2field, vtiger_def_org_field
```

Because of this, [RelationDelete.php](../../modules/FcvModuleBuilder/actions/RelationDelete.php)
must pass the raw string delete key. Do not cast it to int.

### FcvModuleBuilder DB Verification Commands

Check custom modules:

```powershell
docker exec mysql8 mysql -u root -padmin vtigercrm -e "SELECT * FROM vtiger_fcv_custom_modules ORDER BY id DESC;"
```

Check module nav registration:

```powershell
docker exec mysql8 mysql -u root -padmin vtigercrm -e "SELECT t.tabid, t.name, t.label, t.parent, a.appname, a.visible FROM vtiger_tab t LEFT JOIN vtiger_app2tab a ON a.tabid=t.tabid WHERE t.customized=1 ORDER BY t.tabid DESC LIMIT 20;"
```

Check sharing access rows:

```powershell
docker exec mysql8 mysql -u root -padmin vtigercrm -e "SELECT t.name, s.permission, s.editstatus FROM vtiger_tab t LEFT JOIN vtiger_def_org_share s ON s.tabid=t.tabid WHERE t.customized=1 ORDER BY t.tabid DESC LIMIT 20;"
```

Check default custom views:

```powershell
docker exec mysql8 mysql -u root -padmin vtigercrm -e "SELECT cvid, viewname, entitytype, setdefault FROM vtiger_customview WHERE entitytype IN (SELECT module_name FROM vtiger_fcv_custom_modules);"
```

---

## 4. Git/Worktree Notes at Time of Recap

The local branch was `dev`, tracking `origin/dev`, and local `dev` was ahead by two commits:

```text
efeffc3 feat: add FCVMultiOwner module - DB tables + setup script
e06ef21 Add FCVMultiOwner MultiOwner model with business logic
```

There were also uncommitted/untracked files for the continuing FCVMultiOwner integration and FcvModuleBuilder fixes. Review `git status --short --branch` before editing further.

Generated/test artifacts seen in the worktree included:

- `modules/Demo4/`
- `modules/Demo5/`
- `languages/en_us/Demo4.php`
- `languages/en_us/Demo5.php`
- `storage/fcvmodulebuilder/backups/...`
- `user_privileges/user_privileges_*.php`
- `user_privileges/sharing_privileges_*.php`

Treat these as environment/test artifacts unless the current task explicitly asks to keep them.

---

## 5. Files to Read Before Continuing

For `FCVMultiOwner`:

1. [modules/FCVMultiOwner/setup.php](../../modules/FCVMultiOwner/setup.php)
2. [modules/FCVMultiOwner/models/MultiOwner.php](../../modules/FCVMultiOwner/models/MultiOwner.php)
3. [modules/FCVMultiOwner/FCVMultiOwnerHandler.php](../../modules/FCVMultiOwner/FCVMultiOwnerHandler.php)
4. [modules/Vtiger/uitypes/FCVMultiOwner.php](../../modules/Vtiger/uitypes/FCVMultiOwner.php)
5. [layouts/v7/modules/Vtiger/uitypes/FCVMultiOwner.tpl](../../layouts/v7/modules/Vtiger/uitypes/FCVMultiOwner.tpl)
6. [data/CRMEntity.php](../../data/CRMEntity.php)
7. [include/utils/UserInfoUtil.php](../../include/utils/UserInfoUtil.php)

For `FcvModuleBuilder`:

1. [docs/fcv/BRAIN-FcvModuleBuilder.md](BRAIN-FcvModuleBuilder.md)
2. [docs/fcv/CHANGELOG-FcvModuleBuilder.md](CHANGELOG-FcvModuleBuilder.md)
3. [modules/FcvModuleBuilder/models/Builder.php](../../modules/FcvModuleBuilder/models/Builder.php)
4. [modules/FcvModuleBuilder/models/Relationship.php](../../modules/FcvModuleBuilder/models/Relationship.php)
5. [layouts/v7/modules/FcvModuleBuilder/Index.tpl](../../layouts/v7/modules/FcvModuleBuilder/Index.tpl)
6. [layouts/v7/modules/FcvModuleBuilder/partials/RelList.tpl](../../layouts/v7/modules/FcvModuleBuilder/partials/RelList.tpl)

---

## 6. Practical Test Checklist

### FCVMultiOwner

1. Run setup:

```powershell
php modules/FCVMultiOwner/setup.php
```

2. Add a `Multi Owners` field from Layout Editor to a test module.
3. Open a record edit view and add two users, one `R`, one `W`.
4. Save record.
5. Verify DB rows:

```powershell
docker exec mysql8 mysql -u root -padmin vtigercrm -e "SELECT * FROM vtiger_fcv_multiowner ORDER BY id DESC LIMIT 20;"
```

6. Login as read-only multi-owner: detail/list should work, edit should not.
7. Login as write multi-owner: detail/list/edit should work.

### FcvModuleBuilder

1. Create a test module with a nav group.
2. Verify it appears in `vtiger_tab`, `vtiger_app2tab`, `vtiger_def_org_share`, and `vtiger_customview`.
3. Open the generated module list view; it should have an `All` view.
4. Create a relationship, then delete it.
5. Verify no orphan rows remain in:

```text
vtiger_relatedlists
vtiger_field
vtiger_fieldmodulerel
vtiger_profile2field
vtiger_def_org_field
vtiger_fcv_module_relations
```

6. Create and save a record in the generated module; there should be no blank page.

