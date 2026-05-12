<?php
/*+***********************************************************************************
 * FCVMultiOwnerHandler — VTEventHandler for uitype 200 multi-owner fields
 * Fires on vtiger.entity.aftersave and vtiger.entity.afterdelete.
 *
 * Field discovery: instead of hardcoding 'fcv_multiowner_data', we query
 * vtiger_field for all uitype=200 fields on the saved module.
 * This works regardless of the CF name vtiger assigns (e.g. cf_932).
 ************************************************************************************/

require_once 'modules/FCVMultiOwner/models/MultiOwner.php';

class FCVMultiOwnerHandler extends VTEventHandler {

    public function handleEvent($eventName, $entityData): void {
        $crmid  = (int) $entityData->getId();
        $module = $entityData->getModuleName();
        $tabid  = (int) getTabid($module);

        if ($crmid <= 0 || $tabid <= 0) return;

        if ($eventName === 'vtiger.entity.aftersave') {
            $this->syncMultiOwnerFields($crmid, $tabid, $entityData);
        }

        if ($eventName === 'vtiger.entity.afterdelete') {
            FCVMultiOwner_MultiOwner_Model::deleteForRecord($crmid);
        }
    }

    /**
     * Find every uitype=200 field registered for this module and sync its owners.
     * Using dynamic field lookup so this works for any CF name vtiger assigns.
     */
    private function syncMultiOwnerFields(int $crmid, int $tabid, $entityData): void {
        $db  = PearDatabase::getInstance();
        $res = $db->pquery(
            "SELECT fieldname FROM vtiger_field WHERE tabid = ? AND uitype = '200'",
            [$tabid]
        );

        $num = $db->num_rows($res);
        if ($num === 0) return;

        for ($i = 0; $i < $num; $i++) {
            $fieldname = $db->query_result($res, $i, 'fieldname');

            // entityData->get() reads from column_fields (populated during save)
            $raw = $entityData->get($fieldname);
            if (empty($raw)) {
                // Fallback: read directly from request (always available)
                $raw = $_REQUEST[$fieldname] ?? '';
            }

            if ($raw === '' || $raw === null) continue;

            $owners = json_decode($raw, true);
            if (!is_array($owners)) continue;

            FCVMultiOwner_MultiOwner_Model::syncForRecord($crmid, $tabid, $owners);
        }
    }
}
