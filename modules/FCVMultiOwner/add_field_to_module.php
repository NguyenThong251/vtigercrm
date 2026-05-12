<?php
/*+***********************************************************************************
 * add_field_to_module.php
 * Adds the fcv_multiowner_data (uitype 200) field to any entity module.
 * Usage: php modules/FCVMultiOwner/add_field_to_module.php <ModuleName>
 * Run from vtiger web root.
 ************************************************************************************/
chdir(dirname(__FILE__) . '/../../');
require_once 'includes/main/WebUI.php';
require_once 'vtlib/Vtiger/Module.php';

$moduleName = $argv[1] ?? null;
if (!$moduleName) {
    die("Usage: php modules/FCVMultiOwner/add_field_to_module.php <ModuleName>\n");
}

$module = Vtiger_Module::getInstance($moduleName);
if (!$module) {
    die("Module '$moduleName' not found. Check the module name and try again.\n");
}

// Check if field already exists
$db = PearDatabase::getInstance();
$existing = $db->pquery(
    "SELECT fieldid FROM vtiger_field WHERE tabid = ? AND fieldname = 'fcv_multiowner_data'",
    [getTabid($moduleName)]
);
if ($db->num_rows($existing) > 0) {
    die("Field 'fcv_multiowner_data' already exists in module '$moduleName'.\n");
}

// Get first block
$blocks = $module->getBlocks();
if (empty($blocks)) {
    die("No blocks found in module '$moduleName'.\n");
}
$block = reset($blocks);

// Create the field
$field            = new Vtiger_Field();
$field->name      = 'fcv_multiowner_data';
$field->label     = 'Multi Owners';
$field->uitype    = 200;
$field->typeofdata = 'V~O';   // Varchar, Optional
$field->masseditable = 0;    // Exclude from mass-edit

$block->addField($field);

echo "✓ Field 'fcv_multiowner_data' (uitype 200, label: Multi Owners) added to module '$moduleName'.\n";
echo "  Clear Smarty cache if needed: del /Q \"test\\templates_c\\v7\\*\"\n";
