<?php
/*+***********************************************************************************
 * FcvModuleBuilder_ModuleSetNav_Action
 * POST handler: update nav menu group for an existing custom module
 ************************************************************************************/

require_once 'modules/FcvModuleBuilder/models/Builder.php';

class FcvModuleBuilder_ModuleSetNav_Action extends Vtiger_Action_Controller {

    public function checkPermission(Vtiger_Request $request) {
        if (!Users_Record_Model::getCurrentUserModel()->isAdminUser()) {
            throw new AppException('LBL_PERMISSION_DENIED');
        }
    }

    public function process(Vtiger_Request $request) {
        $moduleName = trim($request->get('module_name'));
        $parentMenu = trim($request->get('parent_menu'));

        if (empty($moduleName)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Module name is required.']);
            return;
        }

        $result = FcvModuleBuilder_Builder_Model::setModuleNav($moduleName, $parentMenu);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result);
    }
}
