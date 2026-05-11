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
