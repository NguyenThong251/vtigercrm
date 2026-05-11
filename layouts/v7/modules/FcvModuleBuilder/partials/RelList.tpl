{*<!--
 * FcvModuleBuilder — Relationship list partial
 * Variables expected: $ALL_RELATIONS
-->*}
<hr/>
<h5>Existing Relationships</h5>
{if $ALL_RELATIONS}
<div class="table-responsive">
<table class="table table-sm table-bordered table-hover">
    <thead class="thead-light">
        <tr>
            <th>#</th>
            <th>Module 1</th>
            <th>Module 2</th>
            <th>Label</th>
            <th>Type</th>
            <th style="width:60px;"></th>
        </tr>
    </thead>
    <tbody>
    {foreach from=$ALL_RELATIONS item=r name=row}
        <tr>
            <td>{$smarty.foreach.row.iteration}</td>
            <td><code>{$r.module1|escape}</code></td>
            <td><code>{$r.module2|escape}</code></td>
            <td>{$r.label|escape}</td>
            <td>
                {if $r.rel_type == '1:1'}
                    <span class="label label-info">1:1</span>
                {elseif $r.rel_type == '1:M' || $r.rel_type == '1:N'}
                    <span class="label label-primary">1:M</span>
                {elseif $r.rel_type == 'M:M' || $r.rel_type == 'N:N'}
                    <span class="label label-warning">M:M</span>
                {elseif $r.rel_type == 'relate'}
                    <span class="label label-default">relate</span>
                {else}
                    <span class="label label-default">{$r.rel_type|escape}</span>
                {/if}
            </td>
            <td>
                <button class="btn btn-danger btn-xs fcv-delete-relation"
                        data-id="{$r.delete_key|escape}"
                        title="Delete this relationship">
                    <i class="fa fa-trash"></i>
                </button>
            </td>
        </tr>
    {/foreach}
    </tbody>
</table>
</div>
{else}
    <p class="text-muted"><em>No relationships defined yet.</em></p>
{/if}
