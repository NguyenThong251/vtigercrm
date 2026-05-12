<?php
/*+***********************************************************************************
 * FCVMultiOwner_SearchUsers_Action
 * AJAX: search active users by name fragment, return JSON via Vtiger_Response
 ************************************************************************************/

require_once 'modules/FCVMultiOwner/models/MultiOwner.php';

class FCVMultiOwner_SearchUsers_Action extends Vtiger_Action_Controller {

    /**
     * No permission checks — any logged-in user can search users for the picker.
     * Override requiresPermission (not checkPermission) to return empty array.
     */
    public function requiresPermission(Vtiger_Request $request) {
        return [];
    }

    public function process(Vtiger_Request $request): void {
        $query = trim((string) $request->get('query'));
        $users = FCVMultiOwner_MultiOwner_Model::searchUsers($query);

        // Vtiger_Response wraps as {success:true, result:...}
        $response = new Vtiger_Response();
        $response->setResult($users);
        $response->emit();
    }
}
