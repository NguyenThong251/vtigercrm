{*<!--
 * FCVMultiOwner.tpl — Edit / Create view for uitype 200
 * Variables available from EditView.tpl scope:
 *   $FIELD_MODEL  — field model instance
 *   $MODULE       — module name
 *   $RECORD_ID    — crmid (0 on create, non-zero on edit)
-->*}
{strip}
{* $FIELD_MODEL->get('name') returns the actual DB field name (e.g. cf_932) *}
{assign var="FIELD_NAME"   value=$FIELD_MODEL->get('name')}
{assign var="UITYPE_MODEL" value=$FIELD_MODEL->getUITypeModel()}
{* EditView.tpl uses $RECORD_ID (not $RECORD). PHP fallback reads $_REQUEST['record']. *}
{assign var="CRMID"        value=$RECORD_ID|default:0}
{assign var="OWNERS_JSON"  value=$UITYPE_MODEL->getExistingOwnersJson($CRMID)}

{* ── Load CSS + JS once per page ───────────────────────────────────────── *}
{if !isset($fcv_mo_assets_loaded)}
    {assign var="fcv_mo_assets_loaded" value=true}
    <link rel="stylesheet" href="{vresource_url('layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.css')}">
    <script src="{vresource_url('layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.js')}"></script>
{/if}

{* ── Widget wrapper ─────────────────────────────────────────────────────── *}
<div class="fcv-mo-wrapper"
     data-uitype="200"
     data-fieldname="{$FIELD_NAME|escape}"
     data-owners="{$OWNERS_JSON|escape:'html'}">

    {* Hidden input — JS writes JSON here before form submit.
       name must match the actual vtiger field name so CRMEntity can read it. *}
    <input type="hidden"
           name="{$FIELD_NAME}"
           id="fcv_mo_{$FIELD_NAME}"
           class="fcv-mo-hidden inputElement"
           value="{$OWNERS_JSON|escape:'html'}">

    {* Chip strip — JS renders chips from data-owners on init *}
    <div class="fcv-mo-chips">
        <button type="button" class="fcv-mo-add-btn">
            <span aria-hidden="true">＋</span> Add owner
        </button>
    </div>
</div>
{/strip}
