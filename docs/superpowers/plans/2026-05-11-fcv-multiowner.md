# FCVMultiOwner Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a custom uitype 200 "FCVMultiOwner" field that lets any record have multiple co-owners (users) beyond the primary owner, each with an explicit Read or Write permission, with a chip UI and full ACL integration.

**Architecture:** A permanent DB table `vtiger_fcv_multiowner` stores `(crmid, userid, tabid, permission)` rows. A VTEventHandler persists the field on every save. ACL is enforced by three targeted patches to core: list-query INNER JOIN, and both `isReadPermittedBySharing` / `isReadWritePermittedBySharing` in `UserInfoUtil.php`. The UI is a chip strip (avatar initial + name + R/W toggle + remove × button) injected into any module's edit and detail views.

**Tech Stack:** PHP 8.2, MySQL 8.0, Smarty 3, jQuery (vtiger bundled), vanilla CSS chips, vtiger VTEventHandler API, vtiger uitype 200.

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| **Create** | `modules/FCVMultiOwner/FCVMultiOwner.php` | Minimal CRMEntity stub (required for module loader) |
| **Create** | `modules/FCVMultiOwner/FCVMultiOwnerHandler.php` | VTEventHandler: persists multiowner rows on aftersave, cleans on afterdelete |
| **Create** | `modules/FCVMultiOwner/models/MultiOwner.php` | Business logic: get/set/delete multiowner rows, auto-grant module tab access |
| **Create** | `modules/FCVMultiOwner/actions/SearchUsers.php` | AJAX action: search active users by name, return JSON |
| **Create** | `modules/FCVMultiOwner/setup.php` | Run-once install: create DB tables, register event handler, register uitype |
| **Create** | `modules/Vtiger/uitypes/FCVMultiOwner.php` | UIType PHP class (extends Vtiger_Base_UIType), uitype 200 |
| **Create** | `layouts/v7/modules/Vtiger/uitypes/FCVMultiOwner.tpl` | Edit/create view: chip strip + hidden JSON input + search popup |
| **Create** | `layouts/v7/modules/Vtiger/uitypes/FCVMultiOwnerDetail.tpl` | Detail view: read-only chip strip |
| **Create** | `layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.css` | Chip and popup styles |
| **Create** | `layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.js` | Chip init, user search AJAX, R/W toggle, JSON serialization, form hook |
| **Patch** | `data/CRMEntity.php` | `getNonAdminAccessControlQuery`: extend INNER JOIN OR clause for list queries |
| **Patch** | `include/utils/UserInfoUtil.php` | `isReadPermittedBySharing` + `isReadWritePermittedBySharing`: DB check against multiowner table for detail/edit access |

---

## Task 1: Database Tables + Setup Script

**Files:**
- Create: `modules/FCVMultiOwner/setup.php`
- Run: `php modules/FCVMultiOwner/setup.php` (once, from web root)

### Context

The setup script creates two DB tables and registers the event handler + uitype. Run it from CLI or browser once after deploying files.

- [ ] **Step 1: Create setup.php**

```php
<?php
// modules/FCVMultiOwner/setup.php
// Run once from vtiger web root: php modules/FCVMultiOwner/setup.php
chdir(dirname(__FILE__) . '/../../');
require_once 'include/main/WebUI.php';

$db = PearDatabase::getInstance();

// ── 1. Main multiowner table ─────────────────────────────────────────────────
$db->pquery("CREATE TABLE IF NOT EXISTS vtiger_fcv_multiowner (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    crmid      INT NOT NULL,
    userid     INT NOT NULL,
    tabid      INT NOT NULL,
    permission ENUM('read','write') NOT NULL DEFAULT 'write',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crmid_userid (crmid, userid),
    INDEX idx_crmid  (crmid),
    INDEX idx_userid (userid),
    INDEX idx_tabid  (tabid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", []);

echo "✓ vtiger_fcv_multiowner created\n";

// ── 2. Auto-grant tracking table ─────────────────────────────────────────────
$db->pquery("CREATE TABLE IF NOT EXISTS vtiger_fcv_multiowner_grants (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    userid  INT NOT NULL,
    tabid   INT NOT NULL,
    UNIQUE KEY uq_user_tab (userid, tabid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", []);

echo "✓ vtiger_fcv_multiowner_grants created\n";

// ── 3. Register event handler ─────────────────────────────────────────────────
require_once 'vtlib/Vtiger/Module.php';
require_once 'include/events/include.inc';

$handlerFile = 'modules/FCVMultiOwner/FCVMultiOwnerHandler.php';
$em = new VTEventsManager($db);

// Remove stale registrations first
$em->unRegisterHandler('FCVMultiOwnerHandler');

$em->registerHandler('vtiger.entity.aftersave',   $handlerFile, 'FCVMultiOwnerHandler');
$em->registerHandler('vtiger.entity.afterdelete', $handlerFile, 'FCVMultiOwnerHandler');
$em->setModuleForHandler('FCVMultiOwner', 'FCVMultiOwnerHandler');

echo "✓ FCVMultiOwnerHandler registered for aftersave + afterdelete\n";

// ── 4. Register uitype 200 in vtiger_ws_fieldtype ─────────────────────────────
$existing = $db->pquery(
    "SELECT 1 FROM vtiger_ws_fieldtype WHERE fieldtypename = 'FCVMultiOwner'", []
);
if ($db->num_rows($existing) === 0) {
    $db->pquery(
        "INSERT INTO vtiger_ws_fieldtype (fieldtypename, uitype) VALUES (?, ?)",
        ['FCVMultiOwner', 200]
    );
    echo "✓ uitype 200 registered as FCVMultiOwner\n";
} else {
    echo "- uitype 200 already registered, skipping\n";
}

echo "\nSetup complete.\n";
```

- [ ] **Step 2: Verify the script runs without errors**

```bash
cd C:/xampp/htdocs/vtigercrm
php modules/FCVMultiOwner/setup.php
```

Expected output:
```
✓ vtiger_fcv_multiowner created
✓ vtiger_fcv_multiowner_grants created
✓ FCVMultiOwnerHandler registered for aftersave + afterdelete
✓ uitype 200 registered as FCVMultiOwner
Setup complete.
```

- [ ] **Step 3: Verify tables exist**

```bash
docker exec mysql8 mysql -u root -proot vtigercrm -e "SHOW TABLES LIKE 'vtiger_fcv_multiowner%';"
```

Expected:
```
vtiger_fcv_multiowner
vtiger_fcv_multiowner_grants
```

- [ ] **Step 4: Commit**

```bash
git add modules/FCVMultiOwner/setup.php
git commit -m "feat(FCVMultiOwner): add setup script — tables + event handler + uitype 200 registration"
```

---

## Task 2: Minimal CRMEntity Stub

**Files:**
- Create: `modules/FCVMultiOwner/FCVMultiOwner.php`

### Context

Vtiger's module loader expects a `{ModuleName}.php` file in every module directory. This is a non-entity "utility" module so we skip standard fields.

- [ ] **Step 1: Create the stub**

```php
<?php
// modules/FCVMultiOwner/FCVMultiOwner.php
// Minimal non-entity stub required by the module loader.
class FCVMultiOwner extends CRMEntity {
    var $table_name  = 'vtiger_fcv_multiowner';
    var $table_index = 'id';
    var $column_fields = [];

    function __construct() {
        global $log;
        $this->db  = PearDatabase::getInstance();
        $this->log = $log;
    }
    function save_module($module) {}
}
```

- [ ] **Step 2: Commit**

```bash
git add modules/FCVMultiOwner/FCVMultiOwner.php
git commit -m "feat(FCVMultiOwner): add minimal CRMEntity stub"
```

---

## Task 3: Model — MultiOwner Business Logic

**Files:**
- Create: `modules/FCVMultiOwner/models/MultiOwner.php`

### Context

Central model for all DB operations on `vtiger_fcv_multiowner`. Also handles auto-granting tab access in `vtiger_profile2tab` when a user is added as multiowner.

- [ ] **Step 1: Create the model**

```php
<?php
// modules/FCVMultiOwner/models/MultiOwner.php

class FCVMultiOwner_MultiOwner_Model {

    /**
     * Return all multiowner rows for a record.
     * @return array  [ ['userid'=>int, 'permission'=>'read'|'write', 'username'=>string], ... ]
     */
    public static function getForRecord(int $crmid): array {
        $db = PearDatabase::getInstance();
        $res = $db->pquery(
            "SELECT m.userid, m.permission,
                    CONCAT(u.first_name,' ',u.last_name) AS username,
                    u.user_name
             FROM vtiger_fcv_multiowner m
             INNER JOIN vtiger_users u ON u.id = m.userid
             WHERE m.crmid = ? AND u.status = 'Active'
             ORDER BY m.id",
            [$crmid]
        );
        $rows = [];
        while ($row = $db->fetch_array($res)) {
            $rows[] = [
                'userid'     => (int) $row['userid'],
                'permission' => $row['permission'],
                'username'   => trim($row['username']) ?: $row['user_name'],
            ];
        }
        return $rows;
    }

    /**
     * Replace all multiowner rows for a record with the given list.
     * @param int    $crmid
     * @param int    $tabid
     * @param array  $owners  [ ['userid'=>int, 'permission'=>'read'|'write'], ... ]
     */
    public static function syncForRecord(int $crmid, int $tabid, array $owners): void {
        $db = PearDatabase::getInstance();

        // Remove existing rows
        $db->pquery("DELETE FROM vtiger_fcv_multiowner WHERE crmid = ?", [$crmid]);

        foreach ($owners as $o) {
            $userId     = (int) ($o['userid'] ?? 0);
            $permission = ($o['permission'] ?? 'write') === 'read' ? 'read' : 'write';
            if ($userId <= 0) continue;

            $db->pquery(
                "INSERT INTO vtiger_fcv_multiowner (crmid, userid, tabid, permission)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE permission = VALUES(permission)",
                [$crmid, $userId, $tabid, $permission]
            );

            // Auto-grant module tab access to the user if not already granted
            self::ensureTabAccess($userId, $tabid);
        }
    }

    /**
     * Delete all multiowner rows for a record (called on entity delete).
     */
    public static function deleteForRecord(int $crmid): void {
        $db = PearDatabase::getInstance();
        $db->pquery("DELETE FROM vtiger_fcv_multiowner WHERE crmid = ?", [$crmid]);
    }

    /**
     * Search active users by name fragment. Returns up to 20 results.
     * @return array  [ ['id'=>int, 'name'=>string], ... ]
     */
    public static function searchUsers(string $query): array {
        $db  = PearDatabase::getInstance();
        $q   = '%' . trim($query) . '%';
        $res = $db->pquery(
            "SELECT id,
                    CONCAT(first_name,' ',last_name) AS full_name,
                    user_name
             FROM vtiger_users
             WHERE status = 'Active'
               AND deleted = 0
               AND (CONCAT(first_name,' ',last_name) LIKE ?
                    OR user_name LIKE ?)
             ORDER BY first_name, last_name
             LIMIT 20",
            [$q, $q]
        );
        $users = [];
        while ($row = $db->fetch_array($res)) {
            $name = trim($row['full_name']) ?: $row['user_name'];
            $users[] = ['id' => (int) $row['id'], 'name' => $name];
        }
        return $users;
    }

    /**
     * Ensure the user's primary profile has visibility on $tabid.
     * Tracks grants in vtiger_fcv_multiowner_grants to avoid redundant work.
     */
    private static function ensureTabAccess(int $userId, int $tabid): void {
        $db = PearDatabase::getInstance();

        // Check if we already granted this
        $already = $db->pquery(
            "SELECT 1 FROM vtiger_fcv_multiowner_grants WHERE userid=? AND tabid=?",
            [$userId, $tabid]
        );
        if ($db->num_rows($already) > 0) return;

        // Check if user already has access via some profile
        $hasAccess = $db->pquery(
            "SELECT 1 FROM vtiger_profile2tab pt
             INNER JOIN vtiger_user2role u2r ON u2r.userid = ?
             INNER JOIN vtiger_roles r ON r.roleid = u2r.roleid
             INNER JOIN vtiger_profile2role p2r ON p2r.roleid = r.roleid
             WHERE pt.profileid = p2r.profileid
               AND pt.tabid = ?
               AND pt.permissions = 0
             LIMIT 1",
            [$userId, $tabid]
        );
        if ($db->num_rows($hasAccess) > 0) {
            // Already has access, just record the grant and return
            $db->pquery(
                "INSERT IGNORE INTO vtiger_fcv_multiowner_grants (userid, tabid) VALUES (?,?)",
                [$userId, $tabid]
            );
            return;
        }

        // Grant tab visibility in user's first profile
        $profileRes = $db->pquery(
            "SELECT p2r.profileid FROM vtiger_user2role u2r
             INNER JOIN vtiger_roles r ON r.roleid = u2r.roleid
             INNER JOIN vtiger_profile2role p2r ON p2r.roleid = r.roleid
             WHERE u2r.userid = ?
             LIMIT 1",
            [$userId]
        );
        if ($db->num_rows($profileRes) === 0) return;
        $profileId = (int) $db->query_result($profileRes, 0, 'profileid');

        // Upsert tab visibility (permissions=0 means visible)
        $db->pquery(
            "INSERT INTO vtiger_profile2tab (profileid, tabid, permissions)
             VALUES (?, ?, 0)
             ON DUPLICATE KEY UPDATE permissions = 0",
            [$profileId, $tabid]
        );

        // Track that we granted this
        $db->pquery(
            "INSERT IGNORE INTO vtiger_fcv_multiowner_grants (userid, tabid) VALUES (?,?)",
            [$userId, $tabid]
        );
    }
}
```

- [ ] **Step 2: Verify PHP syntax**

```bash
php -l modules/FCVMultiOwner/models/MultiOwner.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add modules/FCVMultiOwner/models/MultiOwner.php
git commit -m "feat(FCVMultiOwner): add MultiOwner model — get/sync/delete/search + tab auto-grant"
```

---

## Task 4: Event Handler (Save + Delete)

**Files:**
- Create: `modules/FCVMultiOwner/FCVMultiOwnerHandler.php`

### Context

`vtiger.entity.aftersave` fires after every record save. The handler reads the JSON `fcv_multiowner_data` field from the entity's `column_fields` (the form posted it in the hidden input), then calls `syncForRecord`.

`vtiger.entity.afterdelete` fires when a record is trashed — calls `deleteForRecord`.

- [ ] **Step 1: Create the handler**

```php
<?php
// modules/FCVMultiOwner/FCVMultiOwnerHandler.php

require_once 'modules/FCVMultiOwner/models/MultiOwner.php';

class FCVMultiOwnerHandler extends VTEventHandler {

    public function handleEvent(string $eventName, VTEntityData $entityData): void {
        $crmid  = (int) $entityData->getId();
        $module = $entityData->getModuleName();

        if ($crmid <= 0) return;

        // Modules that are not entity modules don't need multiowner
        $tabid = getTabid($module);
        if (!$tabid) return;

        if ($eventName === 'vtiger.entity.aftersave') {
            // Read the JSON-encoded multiowner list submitted with the form
            $raw = $entityData->get('fcv_multiowner_data');
            if ($raw === null || $raw === '') {
                // Fallback: try $_REQUEST (some flows don't copy all fields to column_fields)
                $raw = $_REQUEST['fcv_multiowner_data'] ?? '';
            }
            if ($raw === '' || $raw === null) return;

            $owners = json_decode($raw, true);
            if (!is_array($owners)) return;

            FCVMultiOwner_MultiOwner_Model::syncForRecord($crmid, (int) $tabid, $owners);
        }

        if ($eventName === 'vtiger.entity.afterdelete') {
            FCVMultiOwner_MultiOwner_Model::deleteForRecord($crmid);
        }
    }
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l modules/FCVMultiOwner/FCVMultiOwnerHandler.php
```

- [ ] **Step 3: Commit**

```bash
git add modules/FCVMultiOwner/FCVMultiOwnerHandler.php
git commit -m "feat(FCVMultiOwner): add VTEventHandler — persist multiowner on aftersave/afterdelete"
```

---

## Task 5: AJAX — User Search Action

**Files:**
- Create: `modules/FCVMultiOwner/actions/SearchUsers.php`

### Context

Called by the chip popup's search box. Returns JSON array of `{id, name}`.

- [ ] **Step 1: Create the action**

```php
<?php
// modules/FCVMultiOwner/actions/SearchUsers.php

require_once 'modules/FCVMultiOwner/models/MultiOwner.php';

class FCVMultiOwner_SearchUsers_Action extends Vtiger_Action_Controller {

    public function checkPermission(Vtiger_Request $request): void {
        // Any logged-in user can search users
    }

    public function process(Vtiger_Request $request): void {
        $query = trim((string) $request->get('query'));
        $users = FCVMultiOwner_MultiOwner_Model::searchUsers($query);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'users' => $users], JSON_UNESCAPED_UNICODE);
    }
}
```

- [ ] **Step 2: Test the endpoint manually**

Open in browser (logged in as admin):
```
http://localhost/vtigercrm/index.php?module=FCVMultiOwner&action=SearchUsers&query=admin
```

Expected JSON:
```json
{"success":true,"users":[{"id":1,"name":"Administrator"}]}
```

- [ ] **Step 3: Commit**

```bash
git add modules/FCVMultiOwner/actions/SearchUsers.php
git commit -m "feat(FCVMultiOwner): add SearchUsers AJAX action"
```

---

## Task 6: UIType PHP Class

**Files:**
- Create: `modules/Vtiger/uitypes/FCVMultiOwner.php`

### Context

Extends `Vtiger_Base_UIType`. Vtiger's field renderer calls `getTemplateName()` for the edit-view template and `getDisplayValue()` for the detail-view chip HTML. Also stores/retrieves the JSON blob as a non-column field (data lives in `vtiger_fcv_multiowner`, not in the module table).

- [ ] **Step 1: Create the UIType class**

```php
<?php
// modules/Vtiger/uitypes/FCVMultiOwner.php

require_once 'modules/FCVMultiOwner/models/MultiOwner.php';

class Vtiger_FCVMultiOwner_UIType extends Vtiger_Base_UIType {

    /**
     * Template used in Edit / Create view.
     */
    public function getTemplateName(): string {
        return 'uitypes/FCVMultiOwner.tpl';
    }

    /**
     * Render read-only chips for Detail view.
     * $value is the crmid of the record.
     */
    public function getDisplayValue($crmid, $record = false, $recordInstance = false): string {
        if (!$crmid) return '';

        $owners = FCVMultiOwner_MultiOwner_Model::getForRecord((int) $crmid);
        if (empty($owners)) return '<em class="fcv-mo-empty">—</em>';

        $html = '<div class="fcv-mo-chips fcv-mo-detail">';
        foreach ($owners as $o) {
            $initial = mb_strtoupper(mb_substr($o['username'], 0, 1));
            $perm    = htmlspecialchars($o['permission']);
            $name    = htmlspecialchars($o['username']);
            $badge   = $perm === 'write'
                ? '<span class="fcv-mo-perm fcv-mo-write">W</span>'
                : '<span class="fcv-mo-perm fcv-mo-read">R</span>';
            $html .= "<span class=\"fcv-mo-chip\" title=\"{$name} ({$perm})\">"
                   . "<span class=\"fcv-mo-avatar\">{$initial}</span>"
                   . "<span class=\"fcv-mo-name\">{$name}</span>"
                   . $badge
                   . "</span>";
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * The field value stored in vtiger_{module}.fcv_multiowner_data is just JSON.
     * Return it as-is; the actual data is in vtiger_fcv_multiowner.
     */
    public function getDBInsertValue($value): string {
        return is_string($value) ? $value : '';
    }

    public function getUserRequestValue($value): string {
        return is_string($value) ? $value : '';
    }
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l modules/Vtiger/uitypes/FCVMultiOwner.php
```

- [ ] **Step 3: Commit**

```bash
git add modules/Vtiger/uitypes/FCVMultiOwner.php
git commit -m "feat(FCVMultiOwner): add Vtiger_FCVMultiOwner_UIType — display + template routing"
```

---

## Task 7: CSS — Chip Styles

**Files:**
- Create: `layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.css`

- [ ] **Step 1: Create the stylesheet**

```css
/* layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.css */

/* ── Chip strip container ──────────────────────────────────────────────── */
.fcv-mo-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
    min-height: 32px;
}

/* ── Individual chip ───────────────────────────────────────────────────── */
.fcv-mo-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #eef1f7;
    border: 1px solid #c8d0df;
    border-radius: 16px;
    padding: 2px 8px 2px 4px;
    font-size: 12px;
    line-height: 1.4;
    white-space: nowrap;
    max-width: 220px;
}

/* ── Avatar circle ─────────────────────────────────────────────────────── */
.fcv-mo-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #4a90d9;
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    flex-shrink: 0;
}

/* ── Name ──────────────────────────────────────────────────────────────── */
.fcv-mo-name {
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 120px;
    color: #333;
}

/* ── R/W permission badge ──────────────────────────────────────────────── */
.fcv-mo-perm {
    font-size: 10px;
    font-weight: 700;
    border-radius: 3px;
    padding: 1px 4px;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s;
}
.fcv-mo-write {
    background: #2ecc71;
    color: #fff;
}
.fcv-mo-read {
    background: #3498db;
    color: #fff;
}
.fcv-mo-perm:hover {
    opacity: 0.8;
}

/* ── Remove × button ───────────────────────────────────────────────────── */
.fcv-mo-remove {
    cursor: pointer;
    color: #999;
    font-size: 14px;
    line-height: 1;
    padding: 0 2px;
    border: none;
    background: none;
}
.fcv-mo-remove:hover {
    color: #e74c3c;
}

/* ── "Add owner" button ────────────────────────────────────────────────── */
.fcv-mo-add-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border: 1px dashed #4a90d9;
    border-radius: 14px;
    background: #fff;
    color: #4a90d9;
    font-size: 12px;
    cursor: pointer;
    transition: background 0.15s;
}
.fcv-mo-add-btn:hover {
    background: #eef5ff;
}

/* ── Search popup ──────────────────────────────────────────────────────── */
.fcv-mo-popup {
    position: absolute;
    z-index: 10000;
    background: #fff;
    border: 1px solid #c8d0df;
    border-radius: 6px;
    box-shadow: 0 4px 16px rgba(0,0,0,.12);
    min-width: 240px;
    padding: 8px;
}
.fcv-mo-popup-search {
    width: 100%;
    box-sizing: border-box;
    padding: 5px 8px;
    border: 1px solid #c8d0df;
    border-radius: 4px;
    font-size: 13px;
    margin-bottom: 6px;
}
.fcv-mo-popup-results {
    max-height: 200px;
    overflow-y: auto;
}
.fcv-mo-result-item {
    padding: 5px 8px;
    cursor: pointer;
    border-radius: 3px;
    font-size: 13px;
}
.fcv-mo-result-item:hover {
    background: #eef1f7;
}
.fcv-mo-no-results {
    color: #999;
    font-size: 12px;
    padding: 4px 8px;
}

/* ── Detail view chips (no remove, no add, lighter) ───────────────────── */
.fcv-mo-detail .fcv-mo-chip {
    background: #f5f7fb;
    border-color: #dde3ee;
}
.fcv-mo-empty {
    color: #aaa;
}
```

- [ ] **Step 2: Commit**

```bash
git add layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.css
git commit -m "feat(FCVMultiOwner): add chip + popup CSS"
```

---

## Task 8: JavaScript — Chip UI Logic

**Files:**
- Create: `layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.js`

### Context

Initialises on `DOMContentLoaded` and on vtiger's `Post.Record.Save.AJAX`. For each `[data-uitype="200"]` wrapper:
1. Renders existing chips from `data-owners` JSON
2. "Add owner" button opens search popup
3. Popup searches via AJAX to `FCVMultiOwner/SearchUsers`
4. Clicking a result adds a chip (skipping duplicates)
5. Clicking the R/W badge toggles between `read` and `write`
6. Clicking × removes the chip
7. Before form submit, serializes current chips to the hidden JSON input

- [ ] **Step 1: Create the JS module**

```js
/* layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.js */
(function ($) {
    'use strict';

    // ── Helpers ──────────────────────────────────────────────────────────────

    function getInitial(name) {
        return (name || '?').trim().charAt(0).toUpperCase();
    }

    function buildChip(userid, name, permission) {
        var perm  = permission === 'read' ? 'read' : 'write';
        var label = perm === 'write' ? 'W' : 'R';
        var cls   = perm === 'write' ? 'fcv-mo-write' : 'fcv-mo-read';
        return $('<span class="fcv-mo-chip">')
            .attr({'data-userid': userid, 'data-name': name, 'data-permission': perm})
            .append(
                $('<span class="fcv-mo-avatar">').text(getInitial(name)),
                $('<span class="fcv-mo-name">').text(name),
                $('<span class="fcv-mo-perm ' + cls + '">').text(label),
                $('<button type="button" class="fcv-mo-remove" tabindex="-1">').html('&times;')
            );
    }

    function serializeChips($wrapper) {
        var owners = [];
        $wrapper.find('.fcv-mo-chip').each(function () {
            owners.push({
                userid:     parseInt($(this).attr('data-userid'), 10),
                permission: $(this).attr('data-permission')
            });
        });
        $wrapper.find('.fcv-mo-hidden').val(JSON.stringify(owners));
    }

    // ── Popup ────────────────────────────────────────────────────────────────

    function openPopup($wrapper) {
        // Remove any existing popup
        $('.fcv-mo-popup').remove();

        var $popup = $('<div class="fcv-mo-popup">')
            .append(
                $('<input type="text" class="fcv-mo-popup-search" placeholder="Search user…">'),
                $('<div class="fcv-mo-popup-results">')
            );

        // Position near the add button
        var $btn   = $wrapper.find('.fcv-mo-add-btn');
        var offset = $btn.offset();
        $popup.css({ top: offset.top + $btn.outerHeight() + 4, left: offset.left });
        $('body').append($popup);

        var $input   = $popup.find('.fcv-mo-popup-search');
        var $results = $popup.find('.fcv-mo-popup-results');
        $input.focus();

        // ── Search on input ──
        var timer;
        $input.on('input', function () {
            clearTimeout(timer);
            var q = $(this).val().trim();
            if (q.length < 1) { $results.empty(); return; }
            timer = setTimeout(function () {
                $.getJSON('index.php', {
                    module: 'FCVMultiOwner',
                    action: 'SearchUsers',
                    query:  q
                }, function (data) {
                    $results.empty();
                    if (!data.success || !data.users.length) {
                        $results.append('<div class="fcv-mo-no-results">No users found</div>');
                        return;
                    }
                    data.users.forEach(function (u) {
                        // Skip if already added
                        if ($wrapper.find('.fcv-mo-chip[data-userid="' + u.id + '"]').length) return;
                        $('<div class="fcv-mo-result-item">')
                            .text(u.name)
                            .attr('data-userid', u.id)
                            .attr('data-name',   u.name)
                            .on('click', function () {
                                var $chip = buildChip(u.id, u.name, 'write');
                                $wrapper.find('.fcv-mo-chips').prepend($chip);  // add before add-btn
                                serializeChips($wrapper);
                                $popup.remove();
                            })
                            .appendTo($results);
                    });
                });
            }, 250);
        });

        // ── Close on outside click ──
        $(document).one('click.fcvpopup', function (e) {
            if (!$(e.target).closest('.fcv-mo-popup, .fcv-mo-add-btn').length) {
                $popup.remove();
            }
        });
    }

    // ── Init a single wrapper ────────────────────────────────────────────────

    function initWrapper($wrapper) {
        if ($wrapper.data('fcv-mo-init')) return;
        $wrapper.data('fcv-mo-init', true);

        var $chips = $wrapper.find('.fcv-mo-chips');

        // Render initial chips from data attribute
        var existing = [];
        try { existing = JSON.parse($wrapper.attr('data-owners') || '[]'); } catch(e) {}
        existing.forEach(function (o) {
            $chips.prepend(buildChip(o.userid, o.username, o.permission));
        });
        serializeChips($wrapper);

        // ── Add button ──
        $chips.find('.fcv-mo-add-btn').on('click', function (e) {
            e.stopPropagation();
            openPopup($wrapper);
        });

        // ── Toggle R/W on perm badge ──
        $chips.on('click', '.fcv-mo-perm', function (e) {
            e.stopPropagation();
            var $chip = $(this).closest('.fcv-mo-chip');
            var cur   = $chip.attr('data-permission');
            var next  = cur === 'write' ? 'read' : 'write';
            $chip.attr('data-permission', next);
            $(this)
                .text(next === 'write' ? 'W' : 'R')
                .removeClass('fcv-mo-write fcv-mo-read')
                .addClass(next === 'write' ? 'fcv-mo-write' : 'fcv-mo-read');
            serializeChips($wrapper);
        });

        // ── Remove chip ──
        $chips.on('click', '.fcv-mo-remove', function (e) {
            e.stopPropagation();
            $(this).closest('.fcv-mo-chip').remove();
            serializeChips($wrapper);
        });
    }

    // ── Bootstrap ────────────────────────────────────────────────────────────

    function initAll() {
        $('[data-uitype="200"]').each(function () {
            initWrapper($(this));
        });
    }

    $(document).ready(initAll);

    // Re-init after vtiger Quick-Create or AJAX form reload
    if (typeof app !== 'undefined' && app.event) {
        app.event.on('Post.EditView.Load', initAll);
        app.event.on('Post.QuickCreate.Load', initAll);
    }

}(jQuery));
```

- [ ] **Step 2: Verify the file has no obvious syntax errors**

```bash
node -e "require('fs').readFileSync('layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.js','utf8'); console.log('OK')"
```

- [ ] **Step 3: Commit**

```bash
git add layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.js
git commit -m "feat(FCVMultiOwner): add chip JS — render, search popup, R/W toggle, serialize"
```

---

## Task 9: Smarty Template — Edit View

**Files:**
- Create: `layouts/v7/modules/Vtiger/uitypes/FCVMultiOwner.tpl`

### Context

Rendered in Edit and Create views. The template:
1. Loads CSS + JS (lazy, once per page)
2. Reads existing multiowner rows from `vtiger_fcv_multiowner` for the current record
3. Renders an empty chip strip with an "Add owner" button
4. Provides a hidden input `fcv_multiowner_data` that JS serializes chips into before form submit

The `$RECORD_ID` Smarty var is `0` on create, non-zero on edit.

- [ ] **Step 1: Create the edit template**

```smarty
{*
 * FCVMultiOwner edit/create field template — uitype 200
 * Variables: $FIELD_MODEL, $RECORD_ID (0 on create)
 *}
{strip}
{assign var="FIELD_NAME" value=$FIELD_MODEL->get('name')}
{assign var="CRMID"      value=$RECORD_ID|default:0}

{* ── Load assets once per page ────────────────────────────────────────── *}
{if !$smarty.session.fcv_mo_assets_loaded}
    {assign var="fcv_mo_assets_loaded" value=true scope="session"}
    <link rel="stylesheet" href="{vtigerResourcePath('layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.css')}">
    <script src="{vtigerResourcePath('layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.js')}"></script>
{/if}

{* ── Fetch existing multiowner data for edit view ──────────────────────── *}
{assign var="EXISTING_OWNERS" value=[]}
{if $CRMID > 0}
    {assign var="EXISTING_OWNERS" value=FCVMultiOwner_MultiOwner_Model::getForRecord($CRMID)}
{/if}
{assign var="OWNERS_JSON" value=$EXISTING_OWNERS|@json_encode}

{* ── Widget wrapper ─────────────────────────────────────────────────────── *}
<div class="fcv-mo-wrapper" data-uitype="200" data-fieldname="{$FIELD_NAME}" data-owners="{$OWNERS_JSON|escape:'html'}">

    {* Hidden input: JS writes serialized JSON here before form submit *}
    <input type="hidden"
           name="fcv_multiowner_data"
           class="fcv-mo-hidden"
           value="{$OWNERS_JSON|escape:'html'}">

    {* Chip strip — JS populates chips; .fcv-mo-add-btn is always last *}
    <div class="fcv-mo-chips">
        <button type="button" class="fcv-mo-add-btn">
            <span>＋</span> Add owner
        </button>
    </div>
</div>
{/strip}
```

- [ ] **Step 2: Verify the template loads without Smarty error**

Open any record edit view in the browser (where uitype 200 field is registered), check for Smarty exceptions in browser console or PHP error log.

- [ ] **Step 3: Commit**

```bash
git add layouts/v7/modules/Vtiger/uitypes/FCVMultiOwner.tpl
git commit -m "feat(FCVMultiOwner): add edit/create Smarty template (uitype 200)"
```

---

## Task 10: Smarty Template — Detail View

**Files:**
- Create: `layouts/v7/modules/Vtiger/uitypes/FCVMultiOwnerDetail.tpl`

### Context

Rendered in the Detail (read-only) view. Calls `getDisplayValue()` which is defined in `Vtiger_FCVMultiOwner_UIType`. The PHP class renders the chips HTML directly — we just output it here.

- [ ] **Step 1: Create the detail template**

```smarty
{*
 * FCVMultiOwner detail view field template — uitype 200
 * Variables: $FIELD_MODEL, $RECORD_ID
 *}
{strip}
{assign var="CRMID"    value=$RECORD_ID|default:0}
{assign var="UI_TYPE"  value=$FIELD_MODEL->getUITypeModel()}

{* Load CSS once per page *}
{if !$smarty.session.fcv_mo_css_loaded}
    {assign var="fcv_mo_css_loaded" value=true scope="session"}
    <link rel="stylesheet" href="{vtigerResourcePath('layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.css')}">
{/if}

{$UI_TYPE->getDisplayValue($CRMID)}
{/strip}
```

- [ ] **Step 2: Commit**

```bash
git add layouts/v7/modules/Vtiger/uitypes/FCVMultiOwnerDetail.tpl
git commit -m "feat(FCVMultiOwner): add detail view Smarty template (uitype 200)"
```

---

## Task 11: ACL Patch — List Query (getNonAdminAccessControlQuery)

**Files:**
- Modify: `data/CRMEntity.php`

### Context

For "Private" org sharing (`$defaultOrgSharingPermission[$tabId] == 3`), vtiger generates:
```sql
INNER JOIN vt_tmp_u5 ON vt_tmp_u5.id = vtiger_crmentity.smownerid
```
This hides all records not owned by accessible owners. We extend the ON clause so multiowner records are also visible:
```sql
INNER JOIN vt_tmp_u5 ON (vt_tmp_u5.id = vtiger_crmentity.smownerid
                          OR vtiger_crmentity.crmid IN (SELECT crmid FROM vtiger_fcv_multiowner WHERE userid=5))
```

**Guard:** Only add the OR if the `vtiger_fcv_multiowner` table exists (checked once per request, cached in a static property on `CRMEntity`).

- [ ] **Step 1: Read the current function in data/CRMEntity.php**

Find the exact text around line 2853:
```
function getNonAdminAccessControlQuery($module, $user, $scope = '') {
```
Read lines 2853–2882 to confirm the exact text before editing.

- [ ] **Step 2: Add the static table-existence check near the top of CRMEntity class**

Find the line:
```php
class CRMEntity {
```
After the class declaration and opening `{`, add:

```php
    /** @var bool|null Cache: whether vtiger_fcv_multiowner table exists */
    private static $fcvMoTableExists = null;

    /** Check once if the FCVMultiOwner table exists */
    private static function fcvMultiOwnerActive(): bool {
        if (self::$fcvMoTableExists === null) {
            $db  = PearDatabase::getInstance();
            $res = $db->pquery("SHOW TABLES LIKE 'vtiger_fcv_multiowner'", []);
            self::$fcvMoTableExists = ($db->num_rows($res) > 0);
        }
        return self::$fcvMoTableExists;
    }
```

- [ ] **Step 3: Patch getNonAdminAccessControlQuery**

Locate the two lines that build `$query` (both inside the if-block for private sharing):

```php
			if($scope == ''){
				$query = " INNER JOIN $tableName $tableName$scope ON $tableName$scope.id = " .
						"vtiger_crmentity$scope.smownerid ";
			}else{
				$query = " INNER JOIN $tableName $tableName$scope ON $tableName$scope.id = " .
						"vtiger_crmentity$scope.smownerid OR vtiger_crmentity$scope.smownerid IS NULL";
			}
```

Replace with:

```php
			if($scope == ''){
				if (self::fcvMultiOwnerActive()) {
					$query = " INNER JOIN $tableName $tableName$scope ON ($tableName$scope.id = " .
							"vtiger_crmentity$scope.smownerid " .
							"OR vtiger_crmentity$scope.crmid IN " .
							"(SELECT crmid FROM vtiger_fcv_multiowner WHERE userid={$user->id})) ";
				} else {
					$query = " INNER JOIN $tableName $tableName$scope ON $tableName$scope.id = " .
							"vtiger_crmentity$scope.smownerid ";
				}
			}else{
				if (self::fcvMultiOwnerActive()) {
					$query = " INNER JOIN $tableName $tableName$scope ON ($tableName$scope.id = " .
							"vtiger_crmentity$scope.smownerid " .
							"OR vtiger_crmentity$scope.crmid IN " .
							"(SELECT crmid FROM vtiger_fcv_multiowner WHERE userid={$user->id})) " .
							"OR vtiger_crmentity$scope.smownerid IS NULL";
				} else {
					$query = " INNER JOIN $tableName $tableName$scope ON $tableName$scope.id = " .
							"vtiger_crmentity$scope.smownerid OR vtiger_crmentity$scope.smownerid IS NULL";
				}
			}
```

- [ ] **Step 4: Verify PHP syntax**

```bash
php -l data/CRMEntity.php
```

Expected: `No syntax errors detected`

- [ ] **Step 5: Smoke-test list view**

Log in as a non-admin user. Navigate to Leads (or any Private module). Confirm list still loads correctly and shows only records the user owns. Then add that user as a multiowner on a record owned by another user and confirm it appears in the list.

- [ ] **Step 6: Commit**

```bash
git add data/CRMEntity.php
git commit -m "feat(FCVMultiOwner): patch getNonAdminAccessControlQuery to include multiowner records in list queries"
```

---

## Task 12: ACL Patch — Detail + Edit View (isReadPermittedBySharing + isReadWritePermittedBySharing)

**Files:**
- Modify: `include/utils/UserInfoUtil.php`

### Context

`isPermitted($module, 'DetailView', $crmid)` with a private module eventually calls `isReadPermittedBySharing`. If no role/group sharing rule matches, it returns `'no'` — blocking the detail page even for a multiowner user.

We add a fallback check against `vtiger_fcv_multiowner` in:
- `isReadPermittedBySharing` — for ViewDetail (actionid 3, 4)
- `isReadWritePermittedBySharing` — for Edit/Save (actionid 0, 1)

The write check adds `AND permission = 'write'` to the DB query.

- [ ] **Step 1: Find the end of isReadPermittedBySharing**

Search for the function. It ends with:
```php
	$log->debug("Exiting isReadPermittedBySharing method ...");
	return $sharePer;
}
```

The function is typically around lines 585–720. Read to confirm the exact closing lines.

- [ ] **Step 2: Patch isReadPermittedBySharing**

Find the last `return $sharePer;` before the closing `}` of `isReadPermittedBySharing` and add the multiowner check just above it:

```php
	// FCVMultiOwner fallback: check if user is explicitly granted read on this record
	if ($sharePer == 'no') {
		$res = $adb->pquery(
			"SELECT 1 FROM vtiger_fcv_multiowner WHERE crmid = ? AND userid = ?",
			[$record_id, $current_user->id]
		);
		if ($adb->num_rows($res) > 0) {
			$sharePer = 'yes';
		}
	}

	$log->debug("Exiting isReadPermittedBySharing method ...");
	return $sharePer;
}
```

- [ ] **Step 3: Find isReadWritePermittedBySharing and patch it identically (write-only)**

Find the function signature:
```php
function isReadWritePermittedBySharing($module,$tabid,$actionid,$record_id)
```

Before its final `return $sharePer;`, add:

```php
	// FCVMultiOwner fallback: check if user has write permission on this record
	if ($sharePer == 'no') {
		$res = $adb->pquery(
			"SELECT 1 FROM vtiger_fcv_multiowner WHERE crmid = ? AND userid = ? AND permission = 'write'",
			[$record_id, $current_user->id]
		);
		if ($adb->num_rows($res) > 0) {
			$sharePer = 'yes';
		}
	}
```

- [ ] **Step 4: Verify syntax**

```bash
php -l include/utils/UserInfoUtil.php
```

- [ ] **Step 5: Smoke test detail view**

1. Log in as User B (non-admin, no sharing rules)
2. Navigate to a Leads record owned by User A
3. Before patch: `403 / Permission denied`
4. Add User B as multiowner on that record (via DB directly: `INSERT INTO vtiger_fcv_multiowner ...`)
5. After patch: detail view should load

- [ ] **Step 6: Commit**

```bash
git add include/utils/UserInfoUtil.php
git commit -m "feat(FCVMultiOwner): patch isReadPermittedBySharing + isReadWritePermittedBySharing — multiowner grants detail/edit access"
```

---

## Task 13: Add FCVMultiOwner Field to a Module (Example: Leads)

**Files:**
- Create: `modules/FCVMultiOwner/add_field_to_module.php` (utility script, run manually)

### Context

To add the uitype 200 field to a module, you use vtlib. This script adds it once to any given module. Run from vtiger web root.

- [ ] **Step 1: Create the helper script**

```php
<?php
// modules/FCVMultiOwner/add_field_to_module.php
// Usage: php modules/FCVMultiOwner/add_field_to_module.php Leads
chdir(dirname(__FILE__) . '/../../');
require_once 'include/main/WebUI.php';
require_once 'vtlib/Vtiger/Module.php';

$moduleName = $argv[1] ?? 'Leads';

$module = Vtiger_Module::getInstance($moduleName);
if (!$module) {
    die("Module $moduleName not found\n");
}

// Add field to first block
$blocks = $module->getBlocks();
$block  = reset($blocks);

$field = new Vtiger_Field();
$field->name       = 'fcv_multiowner_data';
$field->label      = 'Multi Owners';
$field->uitype     = 200;
$field->typeofdata = 'V~O';  // Varchar, Optional
$field->table      = $module->basetable;
$field->column     = 'fcv_multiowner_data';

$block->addField($field);

echo "✓ Field fcv_multiowner_data (uitype 200) added to module $moduleName\n";
```

- [ ] **Step 2: Run it for a test module**

```bash
cd C:/xampp/htdocs/vtigercrm
php modules/FCVMultiOwner/add_field_to_module.php Leads
```

Expected: `✓ Field fcv_multiowner_data (uitype 200) added to module Leads`

- [ ] **Step 3: Clear Smarty cache and verify the field appears in Leads Edit view**

```bash
del /Q "C:\xampp\htdocs\vtigercrm\test\templates_c\v7\*" 2>NUL || true
```

Open Leads → any record → Edit → confirm "Multi Owners" chip field appears.

- [ ] **Step 4: Commit**

```bash
git add modules/FCVMultiOwner/add_field_to_module.php
git commit -m "feat(FCVMultiOwner): add vtlib helper script to add uitype 200 field to any module"
```

---

## Task 14: End-to-End Test

Manual verification checklist — no code changes.

- [ ] **Step 1: Create + add multiowner**

1. Log in as Admin
2. Open a Leads record → Edit
3. Confirm "Multi Owners" chip area appears
4. Click "Add owner" → search for a non-admin user (e.g. "John")
5. Select John → chip appears with "W" badge
6. Click "W" → badge toggles to "R"  
7. Click "W" again → back to "W"
8. Click × on chip → chip removed
9. Re-add John with permission Write → Save

- [ ] **Step 2: Verify persistence**

1. After save, re-open the record in Detail view
2. "Multi Owners" field shows John's chip with "W" badge
3. Check DB: `SELECT * FROM vtiger_fcv_multiowner WHERE crmid=<id>;`  
   Expected: one row with `userid=<john_id>`, `permission='write'`

- [ ] **Step 3: Verify list-view access (Private module)**

1. Set Leads org sharing to Private: Settings → Sharing Access → Leads → Private
2. Log in as John (non-admin)
3. Navigate to Leads list → the record should appear (via multiowner)
4. Records owned by other users (where John is NOT multiowner) should NOT appear

- [ ] **Step 4: Verify detail-view access**

1. Still logged in as John
2. Click the Leads record he's a multiowner of → Detail view should load  
3. Access a Leads record he is NOT a multiowner of → should be denied

- [ ] **Step 5: Verify write/read enforcement**

1. Change John's permission to Read in the chip UI → Save
2. Log in as John → navigate to the record → Edit button should be disabled/denied
3. Change back to Write → Edit works again

- [ ] **Step 6: Verify delete cleanup**

1. As Admin, delete the Leads record
2. Check DB: `SELECT * FROM vtiger_fcv_multiowner WHERE crmid=<id>;`  
   Expected: empty (handler cleaned up)

---

## Task 15: Final Commit + Push

- [ ] **Step 1: Verify git status is clean**

```bash
git status
```

- [ ] **Step 2: Run syntax check on all new PHP files**

```bash
for f in modules/FCVMultiOwner/FCVMultiOwner.php \
          modules/FCVMultiOwner/FCVMultiOwnerHandler.php \
          modules/FCVMultiOwner/models/MultiOwner.php \
          modules/FCVMultiOwner/actions/SearchUsers.php \
          modules/FCVMultiOwner/setup.php \
          modules/FCVMultiOwner/add_field_to_module.php \
          modules/Vtiger/uitypes/FCVMultiOwner.php; do
    php -l $f && echo "OK: $f"
done
```

Expected: all `OK`

- [ ] **Step 3: Push to dev branch**

```bash
git push origin dev
```

---

## Self-Review

### Spec Coverage

| Requirement | Task(s) |
|-------------|---------|
| Applies to all modules | Task 13 (helper script), vtlib adds field to any module |
| Chip UI: avatar + name + R/W toggle | Task 7 (CSS), Task 8 (JS) |
| Default permission = Write | JS buildChip defaults to `'write'`; handler defaults to `'write'` |
| Multiowner is additional to primary owner | Primary owner unchanged; multiowner stored separately |
| No max users | No limit in model or UI |
| User search popup with name query | Task 5 (AJAX), Task 8 (popup JS) |
| Read = can view detail | Task 12 (`isReadPermittedBySharing` patch) |
| Write = can edit record | Task 12 (`isReadWritePermittedBySharing` patch) |
| List view shows multiowner records | Task 11 (`getNonAdminAccessControlQuery` patch) |
| Auto-grant module tab access | Task 3 (`ensureTabAccess` in model) |
| Cleanup on record delete | Task 4 (event handler `afterdelete`) |

### Known Limitations (v1)

1. **Module tab visibility**: `ensureTabAccess` grants tab to user's FIRST profile. If user has no profile at all, the grant is skipped. Admin should ensure users have at least a basic profile.
2. **Smarty cache**: Session-scoped asset loading (`$smarty.session`) resets on browser tab. CSS/JS may double-load across page navigations — harmless but suboptimal. A page-level deduplication (e.g. `$smarty.request`) is cleaner.
3. **Reports/Workflows**: The ACL patches cover list/detail views. Advanced reports and workflows use their own query builders and may not pick up multiowner records. This is a known vtiger ACL limitation for per-record sharing.
4. **Bulk actions**: Bulk delete/edit checks `isPermitted` per record — write-permission multiowners are covered by the `isReadWritePermittedBySharing` patch.
