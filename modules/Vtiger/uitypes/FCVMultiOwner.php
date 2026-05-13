<?php
/*+***********************************************************************************
 * Vtiger_FCVMultiOwner_UIType — uitype 200
 * Custom multi-owner field with chip UI and R/W permission per user
 ************************************************************************************/

require_once 'modules/FCVMultiOwner/models/MultiOwner.php';

class Vtiger_FCVMultiOwner_UIType extends Vtiger_Base_UIType {

    /** Ensure CSS/JS assets are only emitted once per request in detail view */
    private static bool $assetsInjected = false;

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

        // Inline edit on Detail/Summary views is rendered by Field.js, not the
        // Smarty edit template, so load the widget assets from display render.
        $assets = '';
        if (!self::$assetsInjected) {
            self::$assetsInjected = true;
            $cssPath = 'layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.css';
            $jsPath = 'layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.js';
            $cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();
            $jsVersion = file_exists($jsPath) ? filemtime($jsPath) : time();
            $assets = '<link rel="stylesheet" href="' . $cssPath . '?v=' . $cssVersion . '">'
                    . '<script src="' . $jsPath . '?v=' . $jsVersion . '"></script>';
        }

        if (empty($owners)) return $assets . '<em class="fcv-mo-empty">&mdash;</em>';

        $html = $assets . '<div class="fcv-mo-chips fcv-mo-detail">';
        foreach ($owners as $o) {
            $initial = mb_strtoupper(mb_substr($o['username'], 0, 1));
            $perm    = htmlspecialchars($o['permission'], ENT_QUOTES);
            $name    = htmlspecialchars($o['username'], ENT_QUOTES);
            $badge   = $perm === 'write'
                ? '<span class="fcv-mo-perm fcv-mo-write">W</span>'
                : '<span class="fcv-mo-perm fcv-mo-read">R</span>';
            $userid = (int) $o['userid'];
            $html .= "<span class=\"fcv-mo-chip\" title=\"{$name} ({$perm})\" data-userid=\"{$userid}\" data-permission=\"{$perm}\">"
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
