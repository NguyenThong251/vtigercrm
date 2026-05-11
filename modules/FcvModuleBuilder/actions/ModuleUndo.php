<?php
/*+***********************************************************************************
 * FcvModuleBuilder_ModuleUndo_Action
 * POST handler: undo thao tác create hoặc delete từ backup snapshot
 ************************************************************************************/

require_once 'modules/FcvModuleBuilder/models/Builder.php';
require_once 'modules/FcvModuleBuilder/models/Backup.php';

class FcvModuleBuilder_ModuleUndo_Action extends Vtiger_Action_Controller {

    public function checkPermission(Vtiger_Request $request) {
        $currentUserModel = Users_Record_Model::getCurrentUserModel();
        if (!$currentUserModel->isAdminUser()) {
            throw new AppException('LBL_PERMISSION_DENIED');
        }
    }

    public function process(Vtiger_Request $request) {
        $ref      = trim($request->get('backup_ref'));
        $manifest = FcvModuleBuilder_Backup_Model::getManifest($ref);

        if (!$manifest) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => "Backup '$ref' không tồn tại"]);
            return;
        }

        $steps  = [];
        $result = ['success' => true, 'steps' => $steps];

        if ($manifest['action'] === 'create') {
            // Undo tạo module → xóa module vừa tạo
            $del = FcvModuleBuilder_Builder_Model::deleteModule($manifest['module']);
            $steps[] = 'delete_module: ' . $del['message'];
            if (!$del['success']) {
                $result = ['success' => false, 'message' => $del['message'], 'steps' => $steps];
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                return;
            }
            $result['message'] = "Undo tạo module '{$manifest['module']}' thành công";

        } elseif ($manifest['action'] === 'delete_before') {
            // Undo xóa module → restore files từ backup
            $restored = FcvModuleBuilder_Backup_Model::restoreFiles($ref);
            $steps[]  = 'files_restored: ' . count($restored) . ' files';

            // Ghi lại vào tracking table nếu chưa có
            $db  = PearDatabase::getInstance();
            $chk = $db->pquery(
                'SELECT id FROM vtiger_fcv_custom_modules WHERE module_name=?',
                [$manifest['module']]
            );
            if ($db->num_rows($chk) === 0) {
                $db->pquery(
                    'INSERT INTO vtiger_fcv_custom_modules (module_name, module_label, description) VALUES (?,?,?)',
                    [$manifest['module'], $manifest['module'], '(restored from backup)']
                );
                $steps[] = 'tracking_table: restored';
            }

            $result['message'] = "Undo xóa module '{$manifest['module']}': đã restore " . count($restored) . " files. "
                . "Lưu ý: DB tables (vtiger_{$manifest['module']}*) cần restore từ DB backup riêng nếu đã mất data.";
        } else {
            $result = ['success' => false, 'message' => "Action '{$manifest['action']}' không hỗ trợ undo tự động"];
        }

        // Xóa backup đã dùng
        FcvModuleBuilder_Backup_Model::remove($ref);

        $result['steps'] = $steps;
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
