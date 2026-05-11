<?php
/*+***********************************************************************************
 * FcvModuleBuilder_ModuleDelete_Action
 * POST handler: xóa module hoàn toàn (vtlib + tables + files)
 ************************************************************************************/

require_once 'modules/FcvModuleBuilder/models/Builder.php';
require_once 'modules/FcvModuleBuilder/models/Backup.php';

class FcvModuleBuilder_ModuleDelete_Action extends Vtiger_Action_Controller {

    public function checkPermission(Vtiger_Request $request) {
        $currentUserModel = Users_Record_Model::getCurrentUserModel();
        if (!$currentUserModel->isAdminUser()) {
            throw new AppException('LBL_PERMISSION_DENIED');
        }
    }

    public function process(Vtiger_Request $request) {
        $moduleName = trim($request->get('module_name'));

        if (empty($moduleName)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'module_name không được để trống']);
            return;
        }

        // Snapshot TRƯỚC khi xóa — backup toàn bộ files để có thể undo
        $backupRef = FcvModuleBuilder_Backup_Model::snapshot($moduleName, 'delete_before');

        $result            = FcvModuleBuilder_Builder_Model::deleteModule($moduleName);
        $result['backup_ref'] = $backupRef; // Trả về ref để user biết cách undo

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
