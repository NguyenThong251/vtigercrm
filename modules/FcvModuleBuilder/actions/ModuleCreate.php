<?php
/*+***********************************************************************************
 * FcvModuleBuilder_ModuleCreate_Action
 * POST handler: tạo custom module mới via vtlib
 ************************************************************************************/

require_once 'modules/FcvModuleBuilder/models/Builder.php';
require_once 'modules/FcvModuleBuilder/models/Backup.php';

class FcvModuleBuilder_ModuleCreate_Action extends Vtiger_Action_Controller {

    public function checkPermission(Vtiger_Request $request) {
        $currentUserModel = Users_Record_Model::getCurrentUserModel();
        if (!$currentUserModel->isAdminUser()) {
            throw new AppException('LBL_PERMISSION_DENIED');
        }
    }

    public function process(Vtiger_Request $request) {
        $moduleName  = trim($request->get('module_name'));
        $moduleLabel = trim($request->get('module_label'));
        $description = trim($request->get('description'));
        $parentMenu  = trim($request->get('parent_menu'));

        // Snapshot before creation so the operation can be undone
        $backupRef = FcvModuleBuilder_Backup_Model::snapshot($moduleName, 'create');

        $result = FcvModuleBuilder_Builder_Model::createModule($moduleName, $moduleLabel, $description, $parentMenu);

        if ($result['success']) {
            // Gắn backup_ref vào record tracking
            $db = PearDatabase::getInstance();
            $db->pquery(
                'UPDATE vtiger_fcv_custom_modules SET backup_ref=? WHERE module_name=?',
                [$backupRef, $moduleName]
            );
        } else {
            // Tạo thất bại — xóa backup rỗng
            FcvModuleBuilder_Backup_Model::remove($backupRef);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
