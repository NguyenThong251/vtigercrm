<?php
/*+***********************************************************************************
 * Vtiger_FCVMultiOwner_UIType — uitype 200
 * Custom multi-owner field with chip UI and R/W permission per user
 ************************************************************************************/

require_once 'modules/FCVMultiOwner/models/MultiOwner.php';

class Vtiger_FCVMultiOwner_UIType extends Vtiger_Base_UIType {

    /** Ensure CSS is only emitted once per request in detail view */
    private static bool $cssInjected = false;

    /**
     * Template used in Edit / Create view.
     */
    public function getTemplateName(): string {
        return 'uitypes/FCVMultiOwner.tpl';
    }

    /**
     * Render read-only chips for Detail view.
     * vtiger calls this with ($fieldValue, $crmid, $recordModel).
     */
    public function getDisplayValue($value, $record = false, $recordInstance = false): string {
        // $record is the crmid when called from detail view
        $crmid = (int) ($record ?: $value);
        if ($crmid <= 0) return '';

        $owners = FCVMultiOwner_MultiOwner_Model::getForRecord($crmid);

        // Inject CSS link once per request in detail view
        $css = '';
        if (!self::$cssInjected) {
            self::$cssInjected = true;
            $css = '<link rel="stylesheet" href="layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.css">';
        }

        if (empty($owners)) return $css . '<em class="fcv-mo-empty">—</em>';

        $html = $css . '<div class="fcv-mo-chips fcv-mo-detail">';
        foreach ($owners as $o) {
            $initial = mb_strtoupper(mb_substr($o['username'], 0, 1));
            $perm    = htmlspecialchars($o['permission'], ENT_QUOTES);
            $name    = htmlspecialchars($o['username'], ENT_QUOTES);
            $badge   = $perm === 'write'
                ? '<span class="fcv-mo-perm fcv-mo-write">W</span>'
                : '<span class="fcv-mo-perm fcv-mo-read">R</span>';
            $html .= "<span class=\"fcv-mo-chip\" title=\"{$name} ({$perm})\">"
                   . "<span class=\"fcv-mo-avatar\">{$initial}</span>"
                   . "<span class=\"fcv-mo-name\">{$name}</span>"
                   . $badge
                   . "</span>";
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Called from FCVMultiOwner.tpl to get existing owners JSON for a record.
     * Smarty cannot call static methods, so we expose this as an instance method.
     *
     * $crmid from Smarty's $RECORD may be 0 in some rendering contexts (quick-create,
     * inline-edit bootstrap, etc.). Always fall back to $_REQUEST['record'] so edit view
     * reliably loads the existing chip list.
     */
    public function getExistingOwnersJson($crmid = 0): string {
        $crmid = (int) $crmid;
        if ($crmid <= 0) {
            $crmid = (int) ($_REQUEST['record'] ?? 0);
        }
        if ($crmid <= 0) return '[]';
        $owners = FCVMultiOwner_MultiOwner_Model::getForRecord($crmid);
        return json_encode($owners, JSON_UNESCAPED_UNICODE);
    }

    /**
     * The field column stores a JSON blob (or empty string).
     * Actual multiowner rows live in vtiger_fcv_multiowner.
     */
    public function getDBInsertValue($value): string {
        return is_string($value) ? $value : '';
    }

    public function getUserRequestValue($value): string {
        return is_string($value) ? $value : '';
    }
}
