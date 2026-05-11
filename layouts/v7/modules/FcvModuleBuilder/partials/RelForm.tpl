{*<!--
 * FcvModuleBuilder — Relationship creation form partial
 * Variables expected: $rel_type, $lbl1, $lbl2, $ALL_MODULES
-->*}
<form class="fcv-rel-form" style="max-width:640px; margin-bottom:0;">
    <input type="hidden" name="relation_type" value="{$rel_type}" />
    <table class="table table-bordered">
        <tr>
            <td style="width:220px;"><label>Module 1 <span class="redColor">*</span></label></td>
            <td>
                <select name="module1" class="form-control" required>
                    <option value="">-- Select --</option>
                    {foreach from=$ALL_MODULES item=m}
                        <option value="{$m.name|escape}">{$m.tablabel|escape} ({$m.name|escape})</option>
                    {/foreach}
                </select>
            </td>
        </tr>
        <tr>
            <td><label>{$lbl1|escape}</label></td>
            <td><input type="text" name="label1" class="form-control" placeholder="Leave blank for auto-generated label" /></td>
        </tr>
        <tr>
            <td><label>Module 2 <span class="redColor">*</span></label></td>
            <td>
                <select name="module2" class="form-control" required>
                    <option value="">-- Select --</option>
                    {foreach from=$ALL_MODULES item=m}
                        <option value="{$m.name|escape}">{$m.tablabel|escape} ({$m.name|escape})</option>
                    {/foreach}
                </select>
            </td>
        </tr>
        <tr>
            <td><label>{$lbl2|escape}</label></td>
            <td><input type="text" name="label2" class="form-control" placeholder="Leave blank for auto-generated label" /></td>
        </tr>
    </table>
    <button type="submit" class="btn btn-success">
        <i class="fa fa-link"></i>&nbsp; Create Relationship
    </button>
</form>
