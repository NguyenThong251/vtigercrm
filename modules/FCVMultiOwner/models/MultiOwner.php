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
