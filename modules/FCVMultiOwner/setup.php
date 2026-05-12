<?php
// modules/FCVMultiOwner/setup.php
// Run once from vtiger web root: php modules/FCVMultiOwner/setup.php
chdir(dirname(__FILE__) . '/../../');
require_once 'includes/main/WebUI.php';
require_once 'vtlib/Vtiger/Module.php';

$db = PearDatabase::getInstance();

// ── 0. Register FCVMultiOwner as a utility module in vtiger_tab ──────────────
// presence=1 = hidden from nav but still routable (needed for AJAX actions)
// isentitytype=0 = utility module, no CRUD views
$existing = Vtiger_Module::getInstance('FCVMultiOwner');
if (!$existing) {
    $mod               = new Vtiger_Module();
    $mod->name         = 'FCVMultiOwner';
    $mod->label        = 'FCVMultiOwner';
    $mod->isentitytype = 0;
    $mod->presence     = 1;
    $mod->save();
    echo "✓ FCVMultiOwner registered in vtiger_tab\n";
} else {
    echo "- FCVMultiOwner already in vtiger_tab (tabid=" . $existing->id . ")\n";
}

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
require_once 'include/events/include.inc';

$handlerFile = 'modules/FCVMultiOwner/FCVMultiOwnerHandler.php';
$em = new VTEventsManager($db);

// Remove stale registrations first
$em->unregisterHandler('FCVMultiOwnerHandler');

$em->registerHandler('vtiger.entity.aftersave',   $handlerFile, 'FCVMultiOwnerHandler');
$em->registerHandler('vtiger.entity.afterdelete', $handlerFile, 'FCVMultiOwnerHandler');
$em->setModuleForHandler('FCVMultiOwner', 'FCVMultiOwnerHandler');

echo "✓ FCVMultiOwnerHandler registered for aftersave + afterdelete\n";

// ── 4. Register uitype 200 in vtiger_ws_fieldtype ─────────────────────────────
$existing = $db->pquery(
    "SELECT 1 FROM vtiger_ws_fieldtype WHERE uitype = '200'", []
);
if ($db->num_rows($existing) === 0) {
    $db->pquery(
        "INSERT INTO vtiger_ws_fieldtype (uitype, fieldtype) VALUES (?, ?)",
        ['200', 'FCVMultiOwner']
    );
    echo "✓ uitype 200 registered as FCVMultiOwner\n";
} else {
    echo "- uitype 200 already registered, skipping\n";
}

echo "\nSetup complete.\n";
