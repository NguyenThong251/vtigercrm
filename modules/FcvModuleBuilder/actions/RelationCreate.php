<?php
/*+***********************************************************************************
 * FcvModuleBuilder_RelationCreate_Action
 * POST handler: tạo relationship (1:1, 1:M, M:M) giữa 2 modules
 ************************************************************************************/

require_once 'modules/FcvModuleBuilder/models/Relationship.php';

class FcvModuleBuilder_RelationCreate_Action extends Vtiger_Action_Controller {

    public function checkPermission(Vtiger_Request $request) {
        if (!Users_Record_Model::getCurrentUserModel()->isAdminUser()) {
            throw new AppException('LBL_PERMISSION_DENIED');
        }
    }

    public function process(Vtiger_Request $request) {
        $type    = trim($request->get('relation_type')); // '1:1' | '1:M' | 'M:M'
        $module1 = trim($request->get('module1'));
        $module2 = trim($request->get('module2'));
        $label1  = trim($request->get('label1'));
        $label2  = trim($request->get('label2'));

        if (empty($module1) || empty($module2)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Please select both modules.']);
            return;
        }

        switch ($type) {
            case '1:1':
                $result = FcvModuleBuilder_Relationship_Model::create11($module1, $module2, $label1, $label2);
                break;
            case '1:M':
                $result = FcvModuleBuilder_Relationship_Model::create1M($module1, $module2, $label1, $label2);
                break;
            case 'M:M':
                $result = FcvModuleBuilder_Relationship_Model::createMM($module1, $module2, $label1, $label2);
                break;
            default:
                $result = ['success' => false, 'message' => "Invalid relationship type: '$type'."];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
