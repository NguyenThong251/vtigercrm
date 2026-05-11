<?php
/*+***********************************************************************************
 * FcvModuleBuilder_RelationDelete_Action
 * POST handler: xóa relationship theo relation_id
 ************************************************************************************/

require_once 'modules/FcvModuleBuilder/models/Relationship.php';

class FcvModuleBuilder_RelationDelete_Action extends Vtiger_Action_Controller {

    public function checkPermission(Vtiger_Request $request) {
        if (!Users_Record_Model::getCurrentUserModel()->isAdminUser()) {
            throw new AppException('LBL_PERMISSION_DENIED');
        }
    }

    public function process(Vtiger_Request $request) {
        $deleteKey = trim((string) $request->get('relation_id'));

        if ($deleteKey === '') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'relation_id không hợp lệ']);
            return;
        }

        $result = FcvModuleBuilder_Relationship_Model::deleteRelation($deleteKey);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
