<?php
/*+***********************************************************************************
 * FcvModuleBuilder_Builder_Model
 * Wrapper for vtlib API — creates/deletes custom modules following Vtiger standards.
 * Properly wires vtiger_crmentity fields and generates source files.
 ************************************************************************************/

require_once 'vtlib/Vtiger/Module.php';
require_once 'vtlib/Vtiger/Block.php';
require_once 'vtlib/Vtiger/Field.php';

class FcvModuleBuilder_Builder_Model {

    /**
     * Create a new module following Vtiger vtlib standards.
     * Generates: DB tables, source PHP class, language file, layouts dir,
     * all standard fields (RecordNo, Name, AssignedTo, Description,
     * CreatedTime, ModifiedTime, ModifiedBy, Starred, Tags).
     */
    /**
     * Return available navigation menu groups from vtiger_parenttab.
     */
    /**
     * Nav app name → display label mapping.
     * These are the UPPERCASE keys used in vtiger_app2tab.appname.
     */
    public static function getParentMenus(): array {
        // Canonical list from Vtiger_MenuStructure_Model::getAppMenuList()
        return [
            ['id' => 'MARKETING', 'label' => 'Marketing'],
            ['id' => 'SALES',     'label' => 'Sales'],
            ['id' => 'INVENTORY', 'label' => 'Inventory'],
            ['id' => 'SUPPORT',   'label' => 'Support'],
            ['id' => 'PROJECT',   'label' => 'Projects'],
            ['id' => 'TOOLS',     'label' => 'Tools'],
        ];
    }

    public static function createModule(
        string $moduleName,
        string $moduleLabel,
        string $description  = '',
        string $parentMenu   = ''
    ): array {
        // --- Validate ---
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{2,49}$/', $moduleName)) {
            return ['success' => false, 'message' => 'Invalid module name. Use PascalCase, 3-50 chars, starting with a letter (e.g. Employees, MyModule).'];
        }
        if (Vtiger_Module::getInstance($moduleName)) {
            return ['success' => false, 'message' => "Module '$moduleName' already exists in the system."];
        }
        if (empty(trim($moduleLabel))) {
            return ['success' => false, 'message' => 'Module Label cannot be empty.'];
        }

        try {
            $lcName = strtolower($moduleName);

            // ---- 1. Create source files BEFORE vtlib (vtlib save() does NOT create files) ----
            self::generateSourceFiles($moduleName, $moduleLabel, $lcName);

            // ---- 2. Register module in vtiger_tab via vtlib ----
            $module               = new Vtiger_Module();
            $module->name         = $moduleName;
            $module->label        = $moduleLabel;
            $module->isentitytype = 1;
            $module->save();

            // ---- 3. Create DB tables: vtiger_{module}, vtiger_{module}cf ----
            $module->initTables();

            // ---- 4. Add tags column to module base table (not in vtiger_crmentity) ----
            $db = PearDatabase::getInstance();
            // Vtiger_Utils::AddColumn checks if column exists before ALTER TABLE
            Vtiger_Utils::AddColumn("vtiger_{$lcName}", 'tags', 'TEXT DEFAULT NULL');

            // ---- 5. Register webservice ----
            $module->initWebservice();

            // ---- 6. Primary block ----
            $block        = new Vtiger_Block();
            $block->label = 'LBL_' . strtoupper($moduleName) . '_INFORMATION';
            $module->addBlock($block);

            // ---- 7. Field: Record Number (uitype 4, auto-increment sequence) ----
            $fNo               = new Vtiger_Field();
            $fNo->name         = $lcName . 'no';
            $fNo->label        = $moduleName . ' No';
            $fNo->uitype       = 4;
            $fNo->typeofdata   = 'V~O';
            $fNo->readonly     = 1;
            $fNo->masseditable = 0;
            $fNo->displaytype  = 1;
            $fNo->presence     = 0;
            $block->addField($fNo);

            // ---- 8. Field: Name — entity identifier (uitype 2, stored in module table) ----
            $fName              = new Vtiger_Field();
            $fName->name        = $lcName . 'name';
            $fName->label       = 'Name';
            $fName->uitype      = 2;
            $fName->typeofdata  = 'V~M';
            $fName->column      = $lcName . 'name';
            $fName->columntype  = 'VARCHAR(255)';
            $fName->masseditable = 1;
            $fName->presence    = 0;
            $fName->displaytype = 1;
            $block->addField($fName);
            $module->setEntityIdentifier($fName);

            // ---- 9. Field: Assigned To (uitype 53 → vtiger_crmentity.smownerid) ----
            $fOwner              = new Vtiger_Field();
            $fOwner->name        = 'assigned_user_id';
            $fOwner->label       = 'Assigned To';
            $fOwner->uitype      = 53;
            $fOwner->typeofdata  = 'V~M';
            $fOwner->table       = 'vtiger_crmentity';
            $fOwner->column      = 'smownerid';
            $fOwner->masseditable = 1;
            $fOwner->readonly    = 1;
            $fOwner->presence    = 0;
            $fOwner->displaytype = 1;
            $block->addField($fOwner);

            // ---- 10. Field: Description (uitype 19 → vtiger_crmentity.description) ----
            $fDesc              = new Vtiger_Field();
            $fDesc->name        = 'description';
            $fDesc->label       = 'Description';
            $fDesc->uitype      = 19;
            $fDesc->typeofdata  = 'V~O';
            $fDesc->table       = 'vtiger_crmentity';
            $fDesc->column      = 'description';
            $fDesc->masseditable = 1;
            $fDesc->readonly    = 1;
            $fDesc->presence    = 2;
            $fDesc->displaytype = 1;
            $block->addField($fDesc);

            // ---- 11. Second block: Other Information (timestamps, audit fields) ----
            $block2        = new Vtiger_Block();
            $block2->label = 'LBL_OTHER_INFORMATION';
            $module->addBlock($block2);

            // ---- 12. Field: Created Time (uitype 70 → vtiger_crmentity.createdtime) ----
            $fCreated              = new Vtiger_Field();
            $fCreated->name        = 'createdtime';
            $fCreated->label       = 'Created Time';
            $fCreated->uitype      = 70;
            $fCreated->typeofdata  = 'DT~O';
            $fCreated->table       = 'vtiger_crmentity';
            $fCreated->column      = 'createdtime';
            $fCreated->masseditable = 0;
            $fCreated->readonly    = 1;
            $fCreated->presence    = 0;
            $fCreated->displaytype = 2;
            $block2->addField($fCreated);

            // ---- 13. Field: Modified Time (uitype 70 → vtiger_crmentity.modifiedtime) ----
            $fModified              = new Vtiger_Field();
            $fModified->name        = 'modifiedtime';
            $fModified->label       = 'Modified Time';
            $fModified->uitype      = 70;
            $fModified->typeofdata  = 'DT~O';
            $fModified->table       = 'vtiger_crmentity';
            $fModified->column      = 'modifiedtime';
            $fModified->masseditable = 0;
            $fModified->readonly    = 1;
            $fModified->presence    = 0;
            $fModified->displaytype = 2;
            $block2->addField($fModified);

            // ---- 14. Field: Modified By (uitype 52 → vtiger_crmentity.modifiedby) ----
            $fModBy              = new Vtiger_Field();
            $fModBy->name        = 'modifiedby';
            $fModBy->label       = 'Last Modified By';
            $fModBy->uitype      = 52;
            $fModBy->typeofdata  = 'V~O';
            $fModBy->table       = 'vtiger_crmentity';
            $fModBy->column      = 'modifiedby';
            $fModBy->masseditable = 0;
            $fModBy->readonly    = 1;
            $fModBy->presence    = 0;
            $fModBy->displaytype = 3;
            $block2->addField($fModBy);

            // ---- 15. Field: Starred (uitype 56 → vtiger_crmentity_user_field.starred) ----
            $fStarred              = new Vtiger_Field();
            $fStarred->name        = 'starred';
            $fStarred->label       = 'Starred';
            $fStarred->uitype      = 56;
            $fStarred->typeofdata  = 'C~O';
            $fStarred->table       = 'vtiger_crmentity_user_field';
            $fStarred->column      = 'starred';
            $fStarred->masseditable = 0;
            $fStarred->readonly    = 1;
            $fStarred->presence    = 2;
            $fStarred->displaytype = 6;
            $block2->addField($fStarred);

            // ---- 16. Field: Tags (uitype 1 → vtiger_{module}.tags TEXT) ----
            $fTags              = new Vtiger_Field();
            $fTags->name        = 'tags';
            $fTags->label       = 'Tags';
            $fTags->uitype      = 1;
            $fTags->typeofdata  = 'V~O';
            $fTags->table       = "vtiger_{$lcName}";
            $fTags->column      = 'tags';
            $fTags->columntype  = 'TEXT';
            $fTags->masseditable = 0;
            $fTags->readonly    = 1;
            $fTags->presence    = 2;
            $fTags->displaytype = 6;
            $block2->addField($fTags);

            // ---- 17. Default related lists ----
            $docMod = Vtiger_Module::getInstance('Documents');
            $actMod = Vtiger_Module::getInstance('Activities');
            $comMod = Vtiger_Module::getInstance('ModComments');

            if ($docMod) $module->setRelatedList($docMod, 'Documents', ['ADD', 'SELECT']);
            if ($actMod) $module->setRelatedList($actMod, 'Activities', ['ADD']);
            if ($comMod) $module->setRelatedList($comMod, 'Comments', ['ADD']);

            // ---- 18. Record in tracking table ----
            $db->pquery(
                'INSERT INTO vtiger_fcv_custom_modules (module_name, module_label, description) VALUES (?,?,?)',
                [$moduleName, $moduleLabel, $description]
            );

            // ---- 18b. Register in org sharing so module appears in Settings → Sharing Access ----
            // vtiger_def_org_share uses INNER JOIN — modules without a row are invisible.
            // permission=2 = Public Read Write (matches all standard entity modules)
            // editstatus=0 = admin can change the rule
            $db->pquery(
                'INSERT IGNORE INTO vtiger_def_org_share (tabid, permission, editstatus) VALUES (?,?,?)',
                [$module->id, 2, 0]
            );

            // ---- 18c. Create default "All" list view (vtiger_customview) ----
            // vtiger_customview.cvid is NOT auto-increment — must be assigned manually.
            // Without this, the module list page is blank and JS navigation breaks.
            $cvRes  = $db->pquery('SELECT COALESCE(MAX(cvid),0)+1 AS next_cv FROM vtiger_customview', []);
            $cvRow  = $db->fetch_array($cvRes);
            $nextCv = (int)($cvRow['next_cv'] ?? $cvRow[0] ?? 1);

            $db->pquery(
                "INSERT INTO vtiger_customview (cvid, viewname, setdefault, setmetrics, entitytype, status, userid)
                 VALUES (?, 'All', 1, 0, ?, 0, 1)",
                [$nextCv, $moduleName]
            );

            // Column list format: {table}:{column}:{fieldname}:{Module_Label}:{typeofdata}
            $lcMod = strtolower($moduleName);
            $cvColumns = [
                1 => "vtiger_{$lcMod}:{$lcMod}name:{$lcMod}name:{$moduleName}_Name:V",
                2 => "vtiger_crmentity:smownerid:assigned_user_id:{$moduleName}_Assigned_To:V",
                3 => "vtiger_crmentity:createdtime:createdtime:{$moduleName}_Created_Time:DT",
            ];
            foreach ($cvColumns as $idx => $col) {
                $db->pquery(
                    'INSERT INTO vtiger_cvcolumnlist (cvid, columnindex, columnname) VALUES (?,?,?)',
                    [$nextCv, $idx, $col]
                );
            }

            // ---- 19. Assign to navigation menu group via vtiger_app2tab (Vtiger 8 nav system) ----
            if (!empty($parentMenu)) {
                $appName = strtoupper($parentMenu);
                // Also keep vtiger_tab.parent for legacy compatibility
                $db->pquery('UPDATE vtiger_tab SET parent=? WHERE name=?', [$appName, $moduleName]);
                // vtiger_app2tab drives the actual left-nav sidebar
                $seqRes  = $db->pquery(
                    'SELECT COALESCE(MAX(sequence),0)+1 AS next_seq FROM vtiger_app2tab WHERE appname=?',
                    [$appName]
                );
                $seqRow  = $db->fetch_array($seqRes);
                $nextSeq = $seqRow['next_seq'] ?? $seqRow[0] ?? 1;
                $db->pquery(
                    'INSERT INTO vtiger_app2tab (tabid, appname, sequence, visible) VALUES (?,?,?,1)',
                    [$module->id, $appName, $nextSeq]
                );
            }

            // ---- 20. Flush caches ----
            Vtiger_Cache::flush();

            return ['success' => true, 'message' => "Module '$moduleName' created successfully." . (!empty($parentMenu) ? " Added to '$parentMenu' menu." : '')];

        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error creating module: ' . $e->getMessage()];
        }
    }

    /**
     * Update the navigation menu group for an existing module.
     * Pass empty string to hide the module from the nav.
     */
    public static function setModuleNav(string $moduleName, string $parentMenu): array {
        $db     = PearDatabase::getInstance();
        $tabRes = $db->pquery('SELECT tabid FROM vtiger_tab WHERE name=?', [$moduleName]);
        $tabRow = $db->fetch_array($tabRes);
        if (!$tabRow) {
            return ['success' => false, 'message' => "Module '$moduleName' not found."];
        }
        $tabId = $tabRow['tabid'] ?? $tabRow[0];

        if (empty($parentMenu)) {
            // Hide from nav: set visible=0 in vtiger_app2tab
            $db->pquery('UPDATE vtiger_app2tab SET visible=0 WHERE tabid=?', [$tabId]);
            $db->pquery("UPDATE vtiger_tab SET parent='' WHERE tabid=?", [$tabId]);
            $msg = "Module '$moduleName' hidden from navigation menu.";
        } else {
            $appName = strtoupper($parentMenu);
            // Remove from any existing app entry
            $db->pquery('DELETE FROM vtiger_app2tab WHERE tabid=?', [$tabId]);
            // Insert into new app
            $seqRes  = $db->pquery(
                'SELECT COALESCE(MAX(sequence),0)+1 AS next_seq FROM vtiger_app2tab WHERE appname=?',
                [$appName]
            );
            $seqRow  = $db->fetch_array($seqRes);
            $nextSeq = $seqRow['next_seq'] ?? $seqRow[0] ?? 1;
            $db->pquery(
                'INSERT INTO vtiger_app2tab (tabid, appname, sequence, visible) VALUES (?,?,?,1)',
                [$tabId, $appName, $nextSeq]
            );
            // Keep vtiger_tab.parent in sync for legacy compatibility
            $db->pquery('UPDATE vtiger_tab SET parent=? WHERE tabid=?', [$appName, $tabId]);
            $msg = "Module '$moduleName' moved to '$parentMenu' menu.";
        }
        Vtiger_Cache::flush();
        return ['success' => true, 'message' => $msg];
    }

    /**
     * Generate the required source files for a new module:
     *   modules/{Name}/{Name}.php      — CRMEntity subclass
     *   languages/en_us/{Name}.php     — English language strings
     *   layouts/v7/modules/{Name}/     — Template directory (empty, LayoutEditor uses defaults)
     */
    private static function generateSourceFiles(string $moduleName, string $moduleLabel, string $lcName): void {
        // ---- modules/{Name}/ ----
        $modDir = "modules/{$moduleName}";
        if (!is_dir($modDir)) {
            mkdir($modDir, 0755, true);
        }

        // ---- modules/{Name}/{Name}.php ----
        $classFile = "{$modDir}/{$moduleName}.php";
        $blockLbl  = 'LBL_' . strtoupper($moduleName) . '_INFORMATION';
        $php = <<<PHP
<?php
/*+***********************************************************************************
 * {$moduleName} — Custom module generated by FcvModuleBuilder
 ************************************************************************************/
class {$moduleName} extends CRMEntity {

    /** Required by vtlib — marks this as a user-created module */
    var \$IsCustomModule = true;

    /** Used by CRMEntity::saveentity() and list/query methods */
    var \$db;
    var \$log;

    var \$table_name  = 'vtiger_{$lcName}';
    var \$table_index = '{$lcName}id';

    var \$tab_name = [
        'vtiger_crmentity',
        'vtiger_{$lcName}',
        'vtiger_{$lcName}cf',
    ];

    var \$tab_name_index = [
        'vtiger_crmentity'   => 'crmid',
        'vtiger_{$lcName}'   => '{$lcName}id',
        'vtiger_{$lcName}cf' => '{$lcName}id',
    ];

    var \$entity_table = 'vtiger_crmentity';

    /** Required for custom fields support */
    var \$customFieldTable = ['vtiger_{$lcName}cf', '{$lcName}id'];

    var \$column_fields = [];

    var \$additional_column_fields = ['smcreatorid', 'smownerid', 'crmid'];

    var \$mandatory_fields = ['assigned_user_id', '{$lcName}name', 'createdtime', 'modifiedtime'];

    var \$sortby_fields = [];

    var \$list_fields = [
        'Name'        => ['{$lcName}' => '{$lcName}name'],
        'Assigned To' => ['crmentity' => 'smownerid'],
    ];

    var \$list_fields_name = [
        'Name'        => '{$lcName}name',
        'Assigned To' => 'assigned_user_id',
    ];

    var \$list_link_field = '{$lcName}name';

    var \$search_fields = [
        'Name'        => ['{$lcName}' => '{$lcName}name'],
        'Assigned To' => ['crmentity' => 'smownerid'],
    ];

    var \$search_fields_name = [
        'Name'        => '{$lcName}name',
        'Assigned To' => 'smownerid',
    ];

    var \$def_basicsearch_col = '{$lcName}name';

    var \$default_order_by   = '{$lcName}name';
    var \$default_sort_order = 'ASC';

    var \$module_name = '{$moduleName}';

    /**
     * Constructor — must initialise \$this->db and \$this->log so that
     * CRMEntity::saveentity() (\$this->db->startTransaction()) does not
     * throw a fatal error (blank page on record save).
     */
    function __construct() {
        global \$log;
        \$this->column_fields = getColumnFields(get_class(\$this));
        \$this->db  = PearDatabase::getInstance();
        \$this->log = \$log;
    }

    /** Required by CRMEntity — module-specific post-save hook */
    function save_module(\$module) {}
}
PHP;
        file_put_contents($classFile, $php);

        // ---- languages/en_us/{Name}.php ----
        $langFile = "languages/en_us/{$moduleName}.php";
        $blockKey = 'LBL_' . strtoupper($moduleName) . '_INFORMATION';
        $lang = <<<PHP
<?php
/*+***********************************************************************************
 * {$moduleName} — English language file (auto-generated by FcvModuleBuilder)
 ************************************************************************************/
\$languageStrings = [
    '{$moduleName}'          => '{$moduleLabel}',
    'SINGLE_{$moduleName}'   => '{$moduleLabel}',
    'LBL_ADD_RECORD'         => 'Add {$moduleLabel}',
    'LBL_RECORDS_LIST'       => '{$moduleLabel} List',

    // Blocks
    '{$blockKey}'            => '{$moduleLabel} Information',
    'LBL_OTHER_INFORMATION'  => 'Other Information',

    // Field labels
    '{$moduleName} No'       => '{$moduleName} No',
    'Name'                   => 'Name',
    'Assigned To'            => 'Assigned To',
    'Description'            => 'Description',
    'Created Time'           => 'Created Time',
    'Modified Time'          => 'Modified Time',
    'Last Modified By'       => 'Last Modified By',
    'Starred'                => 'Starred',
    'Tags'                   => 'Tags',
];

\$jsLanguageStrings = [];
PHP;
        file_put_contents($langFile, $lang);

        // ---- layouts/v7/modules/{Name}/ ----
        $layoutDir = "layouts/v7/modules/{$moduleName}";
        if (!is_dir($layoutDir)) {
            mkdir($layoutDir, 0755, true);
        }
    }

    /**
     * Delete a module completely:
     *   1. vtlib->delete()  — removes vtiger_tab, vtiger_field, vtiger_block, profiles, links
     *   2. Drop DB tables   — vtiger_{module}, vtiger_{module}cf
     *   3. Delete source    — modules/{Name}/, layouts/v7/modules/{Name}/, languages/en_us/{Name}.php
     *   4. Clear tracking   — vtiger_fcv_custom_modules
     */
    public static function deleteModule(string $moduleName): array {
        $module = Vtiger_Module::getInstance($moduleName);
        if (!$module) {
            return ['success' => false, 'message' => "Module '$moduleName' not found in the system."];
        }

        try {
            $db     = PearDatabase::getInstance();
            $lcName = strtolower($moduleName);

            // 1. vtlib delete — cleans all vtiger_* metadata
            $module->delete();

            // 2. Drop DB tables
            foreach (["vtiger_$lcName", "vtiger_{$lcName}cf", "vtiger_{$lcName}grouprel"] as $tbl) {
                $db->pquery("DROP TABLE IF EXISTS `$tbl`", []);
            }

            // 3. Delete source files
            $dirsToRemove = ["modules/$moduleName", "layouts/v7/modules/$moduleName"];
            foreach ($dirsToRemove as $dir) {
                if (is_dir($dir)) {
                    self::rmdirRecursive($dir);
                }
            }
            $langFile = "languages/en_us/{$moduleName}.php";
            if (file_exists($langFile)) {
                unlink($langFile);
            }

            // 4. Remove from tracking + sharing rules
            $db->pquery('DELETE FROM vtiger_fcv_custom_modules WHERE module_name=?', [$moduleName]);
            $db->pquery('DELETE FROM vtiger_def_org_share WHERE tabid=?', [$module->id]);

            // 5. Flush caches
            Vtiger_Cache::flush();

            return ['success' => true, 'message' => "Module '$moduleName' deleted completely (source + DB)."];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error deleting module: ' . $e->getMessage()];
        }
    }

    /**
     * List custom modules created via FcvModuleBuilder
     */
    public static function getModuleList(): array {
        $db  = PearDatabase::getInstance();
        $res = $db->pquery(
            'SELECT fcv.*,
                    COALESCE(a.appname, \'\') AS nav_group
             FROM   vtiger_fcv_custom_modules fcv
             LEFT   JOIN vtiger_tab t ON t.name = fcv.module_name
             LEFT   JOIN vtiger_app2tab a ON a.tabid = t.tabid AND a.visible = 1
             ORDER  BY fcv.created_at DESC',
            []
        );
        $list = [];
        while ($row = $db->fetch_array($res)) {
            $list[] = $row;
        }
        return $list;
    }

    /**
     * All entity modules (for relationship dropdowns)
     */
    public static function getAllEntityModules(): array {
        $db  = PearDatabase::getInstance();
        $res = $db->pquery(
            'SELECT tabid, name, tablabel FROM vtiger_tab WHERE isentitytype=1 AND presence=0 ORDER BY tablabel ASC',
            []
        );
        $list = [];
        while ($row = $db->fetch_array($res)) {
            $list[] = $row;
        }
        return $list;
    }

    // ---- private helpers ----

    private static function rmdirRecursive(string $dir): void {
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $path = "$dir/$f";
            is_dir($path) ? self::rmdirRecursive($path) : unlink($path);
        }
        rmdir($dir);
    }
}
