<?php
/*+***********************************************************************************
 * Settings_FcvModuleBuilder_Index_View
 * Settings view — Custom Module Builder
 * Must be at modules/Settings/FcvModuleBuilder/views/Index.php so Vtiger_Loader
 * resolves it before falling back to Settings_Vtiger_Index_View.
 ************************************************************************************/

require_once 'modules/FcvModuleBuilder/models/Builder.php';
require_once 'modules/FcvModuleBuilder/models/Relationship.php';
require_once 'modules/FcvModuleBuilder/models/Backup.php';

class Settings_FcvModuleBuilder_Index_View extends Settings_Vtiger_Index_View {

    public function process(Vtiger_Request $request) {
        $viewer    = $this->getViewer($request);
        $activeTab = $request->get('tab') ?: 'custom_module';

        $viewer->assign('ACTIVE_TAB',    $activeTab);
        $viewer->assign('MODULE_LIST',   FcvModuleBuilder_Builder_Model::getModuleList());
        $viewer->assign('ALL_MODULES',   FcvModuleBuilder_Builder_Model::getAllEntityModules());
        $viewer->assign('ALL_RELATIONS', FcvModuleBuilder_Relationship_Model::getAllRelations());
        $viewer->assign('BACKUPS',       FcvModuleBuilder_Backup_Model::listAll());
        $viewer->assign('PARENT_MENUS',  FcvModuleBuilder_Builder_Model::getParentMenus());

        $viewer->view('Index.tpl', 'FcvModuleBuilder');
    }
}
