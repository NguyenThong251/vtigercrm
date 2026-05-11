<?php
/*+***********************************************************************************
 * FcvModuleBuilder_Relationship_Model
 * Wrapper for vtlib relationship API — create 1:1, 1:M, M:M between modules.
 * Custom tracking table: vtiger_fcv_module_relations
 ************************************************************************************/

require_once 'vtlib/Vtiger/Module.php';
require_once 'vtlib/Vtiger/Block.php';
require_once 'vtlib/Vtiger/Field.php';

class FcvModuleBuilder_Relationship_Model {

    /**
     * Ensure tracking table exists (called lazily)
     */
    private static function initTable(): void {
        $db = PearDatabase::getInstance();
        $db->pquery(
            "CREATE TABLE IF NOT EXISTS vtiger_fcv_module_relations (
                relation_id  INT AUTO_INCREMENT PRIMARY KEY,
                module1      VARCHAR(100) NOT NULL,
                module2      VARCHAR(100) NOT NULL,
                rel_type     VARCHAR(10)  NOT NULL,
                label        VARCHAR(200) DEFAULT '',
                created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP
            )",
            []
        );
    }

    /**
     * Create 1:M relationship.
     * Module 1 = Primary/Parent  →  Module 2 = Child.
     * Child gets a relate field pointing to Parent.
     * Parent gets a related list showing Child records.
     *
     * @param string $primaryModule  Parent module
     * @param string $childModule    Child module
     * @param string $fieldLabel     Label of relate field on Child (pointing to Parent)
     * @param string $relListLabel   Label of related list on Parent (showing Child records)
     */
    public static function create1M(
        string $primaryModule,
        string $childModule,
        string $fieldLabel   = '',
        string $relListLabel = ''
    ): array {
        $primary = Vtiger_Module::getInstance($primaryModule);
        $child   = Vtiger_Module::getInstance($childModule);

        if (!$primary) {
            return ['success' => false, 'message' => "Module '$primaryModule' not found."];
        }
        if (!$child) {
            return ['success' => false, 'message' => "Module '$childModule' not found."];
        }

        try {
            // Relate field on Child pointing to Primary
            $childBlocks = Vtiger_Block::getAllForModule($child);
            $firstBlock  = reset($childBlocks);

            $fieldName        = strtolower($primaryModule) . '_id';
            $field            = new Vtiger_Field();
            $field->name      = $fieldName;
            $field->label     = $fieldLabel ?: $primaryModule;
            $field->uitype    = 10;  // relate field
            $field->typeofdata = 'I~O';
            $firstBlock->addField($field);
            $field->setRelatedModules([$primaryModule]);

            // Related list on Primary showing Child records
            $primary->setRelatedList(
                $child,
                $relListLabel ?: $childModule,
                ['ADD', 'SELECT', 'DELETE']
            );

            // Track in custom table
            self::initTable();
            $db = PearDatabase::getInstance();
            $lbl = $relListLabel ?: $fieldLabel ?: "$primaryModule → $childModule";
            $db->pquery(
                'INSERT INTO vtiger_fcv_module_relations (module1, module2, rel_type, label) VALUES (?,?,?,?)',
                [$primaryModule, $childModule, '1:M', $lbl]
            );

            Vtiger_Cache::flush();

            return ['success' => true, 'message' => "1:M relationship created: $primaryModule → $childModule."];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create M:M relationship.
     * Both modules get a related list of each other.
     *
     * @param string $module1  Module 1
     * @param string $module2  Module 2
     * @param string $label1   Related list label on Module 2 (showing Module 1 records)
     * @param string $label2   Related list label on Module 1 (showing Module 2 records)
     */
    public static function createMM(
        string $module1,
        string $module2,
        string $label1 = '',
        string $label2 = ''
    ): array {
        $mod1 = Vtiger_Module::getInstance($module1);
        $mod2 = Vtiger_Module::getInstance($module2);

        if (!$mod1) {
            return ['success' => false, 'message' => "Module '$module1' not found."];
        }
        if (!$mod2) {
            return ['success' => false, 'message' => "Module '$module2' not found."];
        }

        try {
            $mod1->setRelatedList($mod2, $label2 ?: $module2, ['ADD', 'SELECT', 'DELETE']);
            $mod2->setRelatedList($mod1, $label1 ?: $module1, ['ADD', 'SELECT', 'DELETE']);

            // Track in custom table
            self::initTable();
            $db  = PearDatabase::getInstance();
            $lbl = $label2 ?: $label1 ?: "$module1 ↔ $module2";
            $db->pquery(
                'INSERT INTO vtiger_fcv_module_relations (module1, module2, rel_type, label) VALUES (?,?,?,?)',
                [$module1, $module2, 'M:M', $lbl]
            );

            Vtiger_Cache::flush();

            return ['success' => true, 'message' => "M:M relationship created: $module1 ↔ $module2."];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create 1:1 relationship (bidirectional relate fields).
     * Module 2 gets a relate field pointing to Module 1.
     * Module 1 gets a relate field pointing to Module 2.
     *
     * @param string $module1   Module 1
     * @param string $module2   Module 2
     * @param string $labelOn2  Label of field on Module 2 (pointing to Module 1)
     * @param string $labelOn1  Label of field on Module 1 (pointing to Module 2)
     */
    public static function create11(
        string $module1,
        string $module2,
        string $labelOn2 = '',
        string $labelOn1 = ''
    ): array {
        $mod1 = Vtiger_Module::getInstance($module1);
        $mod2 = Vtiger_Module::getInstance($module2);

        if (!$mod1) {
            return ['success' => false, 'message' => "Module '$module1' not found."];
        }
        if (!$mod2) {
            return ['success' => false, 'message' => "Module '$module2' not found."];
        }

        try {
            // Field on Module 2 pointing to Module 1
            $blocks2     = Vtiger_Block::getAllForModule($mod2);
            $firstBlock2 = reset($blocks2);
            $f1           = new Vtiger_Field();
            $f1->name     = strtolower($module1) . '_id';
            $f1->label    = $labelOn2 ?: $module1;
            $f1->uitype   = 10;
            $f1->typeofdata = 'I~O';
            $firstBlock2->addField($f1);
            $f1->setRelatedModules([$module1]);

            // Field on Module 1 pointing to Module 2
            $blocks1     = Vtiger_Block::getAllForModule($mod1);
            $firstBlock1 = reset($blocks1);
            $f2           = new Vtiger_Field();
            $f2->name     = strtolower($module2) . '_id';
            $f2->label    = $labelOn1 ?: $module2;
            $f2->uitype   = 10;
            $f2->typeofdata = 'I~O';
            $firstBlock1->addField($f2);
            $f2->setRelatedModules([$module2]);

            // Track in custom table
            self::initTable();
            $db  = PearDatabase::getInstance();
            $lbl = $labelOn1 ?: $labelOn2 ?: "$module1 ↔ $module2";
            $db->pquery(
                'INSERT INTO vtiger_fcv_module_relations (module1, module2, rel_type, label) VALUES (?,?,?,?)',
                [$module1, $module2, '1:1', $lbl]
            );

            Vtiger_Cache::flush();

            return ['success' => true, 'message' => "1:1 relationship created: $module1 ↔ $module2."];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Fully delete a relationship by its delete_key (format: "source-id").
     * Sources: tracking | relatedlist | field
     * Cleans up vtiger_field, vtiger_fieldmodulerel, vtiger_relatedlists, and our tracking table.
     */
    public static function deleteRelation(string $deleteKey): array {
        self::initTable();
        $db = PearDatabase::getInstance();

        [$source, $rawId] = explode('-', $deleteKey, 2) + ['', '0'];
        $id = (int) $rawId;

        switch ($source) {

            // ── Tracking table entry (covers 1:1, 1:M, M:M we created) ────────
            case 'tracking':
                $row = $db->fetch_array(
                    $db->pquery('SELECT * FROM vtiger_fcv_module_relations WHERE relation_id=?', [$id])
                );
                if (!$row) {
                    return ['success' => false, 'message' => "Tracking record #$id not found."];
                }
                $mod1    = $row['module1'];
                $mod2    = $row['module2'];
                $relType = $row['rel_type'];

                // Get tabids
                $t1 = self::getTabId($db, $mod1);
                $t2 = self::getTabId($db, $mod2);

                if (in_array($relType, ['1:1'])) {
                    // Delete relate fields on both sides
                    self::deleteRelateField($db, $t2, strtolower($mod1) . '_id');
                    self::deleteRelateField($db, $t1, strtolower($mod2) . '_id');

                } elseif (in_array($relType, ['1:M', '1:N'])) {
                    // Delete related list on primary + relate field on child
                    $db->pquery('DELETE FROM vtiger_relatedlists WHERE tabid=? AND related_tabid=?', [$t1, $t2]);
                    self::deleteRelateField($db, $t2, strtolower($mod1) . '_id');

                } elseif (in_array($relType, ['M:M', 'N:N'])) {
                    // Delete related lists on both sides
                    $db->pquery('DELETE FROM vtiger_relatedlists WHERE (tabid=? AND related_tabid=?) OR (tabid=? AND related_tabid=?)', [$t1, $t2, $t2, $t1]);
                }

                $db->pquery('DELETE FROM vtiger_fcv_module_relations WHERE relation_id=?', [$id]);
                Vtiger_Cache::flush();
                return ['success' => true, 'message' => "Relationship deleted ($mod1 ↔ $mod2)."];

            // ── Raw vtiger_relatedlists entry ─────────────────────────────────
            case 'relatedlist':
                $db->pquery('DELETE FROM vtiger_relatedlists WHERE relation_id=?', [$id]);
                Vtiger_Cache::flush();
                return ['success' => true, 'message' => "Related list #$id deleted."];

            // ── Raw vtiger_field relate entry (uitype 10) ─────────────────────
            case 'field':
                self::deleteFieldById($db, $id);
                Vtiger_Cache::flush();
                return ['success' => true, 'message' => "Relate field #$id deleted."];

            default:
                return ['success' => false, 'message' => "Unknown delete key format: $deleteKey"];
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private static function getTabId($db, string $moduleName): int {
        $res = $db->pquery('SELECT tabid FROM vtiger_tab WHERE name=?', [$moduleName]);
        $row = $db->fetch_array($res);
        return (int) ($row['tabid'] ?? $row[0] ?? 0);
    }

    /** Delete a relate field by module tabid + fieldname, with full cascade */
    private static function deleteRelateField($db, int $tabId, string $fieldName): void {
        $res = $db->pquery(
            'SELECT fieldid FROM vtiger_field WHERE tabid=? AND fieldname=? AND uitype=10',
            [$tabId, $fieldName]
        );
        while ($row = $db->fetch_array($res)) {
            self::deleteFieldById($db, (int)($row['fieldid'] ?? $row[0]));
        }
    }

    /** Delete a vtiger_field row plus all dependent rows */
    private static function deleteFieldById($db, int $fieldId): void {
        if (!$fieldId) return;
        $db->pquery('DELETE FROM vtiger_fieldmodulerel  WHERE fieldid=?', [$fieldId]);
        $db->pquery('DELETE FROM vtiger_profile2field   WHERE fieldid=?', [$fieldId]);
        $db->pquery('DELETE FROM vtiger_def_org_field   WHERE fieldid=?', [$fieldId]);
        $db->pquery('DELETE FROM vtiger_field           WHERE fieldid=?', [$fieldId]);
    }

    /**
     * List all relationships involving our custom modules.
     * Each row includes a `delete_key` field used by the delete action.
     * Sources:
     *   1. vtiger_fcv_module_relations  — tracking table (all types we created)
     *   2. vtiger_relatedlists          — 1:M / M:M already in DB before tracking
     *   3. vtiger_fieldmodulerel        — relate fields (uitype 10) already in DB
     */
    public static function getAllRelations(): array {
        self::initTable();
        $db   = PearDatabase::getInstance();
        $seen = [];
        $list = [];

        // ── 1. Tracking table ─────────────────────────────────────────────────
        $res = $db->pquery(
            'SELECT relation_id, module1, module2, rel_type, label, created_at
             FROM   vtiger_fcv_module_relations
             ORDER  BY relation_id DESC LIMIT 200',
            []
        );
        while ($row = $db->fetch_array($res)) {
            $row['delete_key'] = 'tracking-' . $row['relation_id'];
            $k = strtolower($row['module1']) . '|' . strtolower($row['module2']);
            $seen[$k] = $seen[strtolower($row['module2']).'|'.strtolower($row['module1'])] = true;
            $list[] = $row;
        }

        // ── 2. vtiger_relatedlists for our custom modules ─────────────────────
        try {
            $res2 = $db->pquery(
                'SELECT rl.relation_id, t1.name AS module1, t2.name AS module2,
                        rl.label, rl.relationtype AS rel_type, NULL AS created_at
                 FROM   vtiger_relatedlists rl
                 JOIN   vtiger_tab t1 ON t1.tabid = rl.tabid
                 JOIN   vtiger_tab t2 ON t2.tabid = rl.related_tabid
                 WHERE  EXISTS (SELECT 1 FROM vtiger_fcv_custom_modules c WHERE c.module_name=t1.name)
                    OR  EXISTS (SELECT 1 FROM vtiger_fcv_custom_modules c WHERE c.module_name=t2.name)
                 ORDER  BY rl.relation_id DESC LIMIT 200',
                []
            );
            while ($row = $db->fetch_array($res2)) {
                $k = strtolower($row['module1']) . '|' . strtolower($row['module2']);
                if (!isset($seen[$k])) {
                    $seen[$k] = $seen[strtolower($row['module2']).'|'.strtolower($row['module1'])] = true;
                    $row['delete_key'] = 'relatedlist-' . $row['relation_id'];
                    $list[] = $row;
                }
            }
        } catch (\Throwable $e) {}

        // ── 3. vtiger_fieldmodulerel — relate fields on our custom modules ─────
        try {
            $res3 = $db->pquery(
                'SELECT f.fieldid AS relation_id, t1.name AS module1,
                        fmr.relmodule AS module2, f.fieldlabel AS label,
                        \'relate\' AS rel_type, NULL AS created_at
                 FROM   vtiger_field f
                 JOIN   vtiger_fieldmodulerel fmr ON fmr.fieldid = f.fieldid
                 JOIN   vtiger_tab t1 ON t1.tabid = f.tabid
                 WHERE  f.uitype = 10
                   AND (EXISTS (SELECT 1 FROM vtiger_fcv_custom_modules c WHERE c.module_name=t1.name)
                    OR  EXISTS (SELECT 1 FROM vtiger_fcv_custom_modules c WHERE c.module_name=fmr.relmodule))
                 ORDER  BY f.fieldid DESC LIMIT 200',
                []
            );
            while ($row = $db->fetch_array($res3)) {
                $k = strtolower($row['module1']) . '|' . strtolower($row['module2']);
                if (!isset($seen[$k])) {
                    $seen[$k] = true;
                    $row['delete_key'] = 'field-' . $row['relation_id'];
                    $list[] = $row;
                }
            }
        } catch (\Throwable $e) {}

        return $list;
    }
}
