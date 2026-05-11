{*<!--
 * FcvModuleBuilder — Main UI Template
 * 4 tabs: Custom Module | 1:1 Relationship | 1:M Relationship | M:M Relationship
 * Tab switching is pure JS/CSS — no Smarty conditionals needed.
-->*}
<div class="vte-module-builder" style="padding: 10px 20px;">

    {* ===== Page Header ===== *}
    <div class="row" style="margin-bottom:16px;">
        <div class="col-sm-12">
            <h3 style="margin:0 0 4px 0;">
                <i class="fa fa-cubes" style="color:#e07b39;"></i>
                &nbsp;FCV Custom Module Builder
            </h3>
            <p class="text-muted" style="margin:0;">
                Create and manage custom modules using the Vtiger vtlib API — directly in source and database.
            </p>
        </div>
    </div>

    {* ===== Nav Tabs ===== *}
    <ul class="nav nav-tabs" id="fcvBuilderTabs" style="margin-bottom:0;">
        <li class="fcv-tab-li active" data-tab="custom_module">
            <a href="#" onclick="return false;">
                <i class="fa fa-puzzle-piece"></i> Custom Module
            </a>
        </li>
        <li class="fcv-tab-li" data-tab="rel_11">
            <a href="#" onclick="return false;">1:1 Relationship</a>
        </li>
        <li class="fcv-tab-li" data-tab="rel_1m">
            <a href="#" onclick="return false;">1:M Relationship</a>
        </li>
        <li class="fcv-tab-li" data-tab="rel_mm">
            <a href="#" onclick="return false;">M:M Relationship</a>
        </li>
    </ul>

    <div class="tab-content" style="border:1px solid #ddd; border-top:none; padding:24px; background:#fff;">

        {* ================================================================
           TAB 1: Custom Module
        ================================================================ *}
        <div class="fcv-tab-pane" id="fcv-tab-custom_module">

            {* --- Create Form --- *}
            <h5><i class="fa fa-plus-circle text-success"></i> Create New Module</h5>
            <div class="alert alert-info" style="font-size:13px;">
                <strong>Auto-created fields:</strong>
                <span class="label label-default">Record Number</span>
                <span class="label label-default">Name</span>
                <span class="label label-primary">Assigned To</span>
                <span class="label label-default">Description</span>
                &nbsp;— Standard Vtiger vtlib fields. Additional fields can be added via Layout Editor.
            </div>

            <form id="fcv-create-module-form" style="max-width:620px;">
                <div class="form-group">
                    <label>* Module Label <small class="text-muted">(display name)</small></label>
                    <input type="text" name="module_label" class="form-control"
                           placeholder="e.g. Employees, Parts, Servers" required />
                </div>
                <div class="form-group">
                    <label>* Module Name <small class="text-muted">(technical name)</small></label>
                    <input type="text" name="module_name" class="form-control"
                           placeholder="e.g. Employees, Parts, Servers"
                           id="fcv-module-name-input" required />
                    <p class="help-block">PascalCase, 3–50 characters, must start with a letter, no spaces.</p>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="2"
                              placeholder="Short description (optional)"></textarea>
                </div>
                <div class="form-group">
                    <label>Navigation Menu Group <small class="text-muted">(where it appears in the top nav)</small></label>
                    <select name="parent_menu" class="form-control" style="max-width:320px;">
                        <option value="">-- None / Hidden --</option>
                        {foreach from=$PARENT_MENUS item=pm}
                            <option value="{$pm.id|escape}">{$pm.label|escape}</option>
                        {/foreach}
                    </select>
                    <p class="help-block">Leave blank to add the module without placing it in any menu group.</p>
                </div>
                <button type="submit" class="btn btn-success" id="fcv-create-btn">
                    <i class="fa fa-plus"></i>&nbsp; Create Module
                </button>
                <span id="fcv-create-spinner" style="display:none; margin-left:10px;">
                    <i class="fa fa-spinner fa-spin"></i> Creating...
                </span>
            </form>

            <hr/>

            {* --- Module List --- *}
            <h5><i class="fa fa-list"></i> Created Modules</h5>
            {if $MODULE_LIST}
            <div class="table-responsive">
            <table class="table table-bordered table-hover" style="max-width:1100px;">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Module Label</th>
                        <th>Module Name</th>
                        <th>Description</th>
                        <th>Nav Group</th>
                        <th>Created At</th>
                        <th style="width:80px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                {foreach from=$MODULE_LIST item=m name=row}
                    <tr>
                        <td>{$smarty.foreach.row.iteration}</td>
                        <td><strong>{$m.module_label|escape}</strong></td>
                        <td><code>{$m.module_name|escape}</code></td>
                        <td>{$m.description|escape|default:'—'}</td>
                        <td style="min-width:160px;">
                            <div class="input-group input-group-sm" style="width:170px;">
                                <select class="form-control fcv-nav-select"
                                        data-module="{$m.module_name|escape}"
                                        style="font-size:12px;">
                                    <option value="">— Hidden —</option>
                                    {foreach from=$PARENT_MENUS item=pm}
                                        <option value="{$pm.id|escape}"
                                            {if $m.nav_group == $pm.id}selected{/if}>
                                            {$pm.label|escape}
                                        </option>
                                    {/foreach}
                                </select>
                                <span class="input-group-btn">
                                    <button class="btn btn-default btn-xs fcv-set-nav"
                                            data-module="{$m.module_name|escape}"
                                            title="Apply nav group">
                                        <i class="fa fa-check"></i>
                                    </button>
                                </span>
                            </div>
                        </td>
                        <td style="white-space:nowrap;">{$m.created_at}</td>
                        <td>
                            <button class="btn btn-danger btn-sm fcv-delete-module"
                                    data-module="{$m.module_name|escape}"
                                    data-label="{$m.module_label|escape}"
                                    title="Permanently delete this module">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
            </div>
            {else}
                <p class="text-muted"><em>No custom modules created yet.</em></p>
            {/if}

            {* --- Undo / Backups --- *}
            {if $BACKUPS}
            <hr/>
            <h5><i class="fa fa-history text-warning"></i> Undo / Backups</h5>
            <p class="text-muted" style="font-size:12px;">
                A snapshot is created before every create/delete operation. Click <strong>Undo</strong> to revert to the previous state.
            </p>
            <div class="table-responsive">
            <table class="table table-sm table-bordered" style="max-width:900px;">
                <thead>
                    <tr>
                        <th>Backup Ref</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Time</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                {foreach from=$BACKUPS item=b}
                    <tr>
                        <td><code style="font-size:11px;">{$b.ref|escape}</code></td>
                        <td>{$b.module|escape}</td>
                        <td>
                            {if $b.action=='create'}
                                <span class="label label-success">create</span>
                            {elseif $b.action=='delete_before'}
                                <span class="label label-danger">delete_before</span>
                            {else}
                                <span class="label label-default">{$b.action|escape}</span>
                            {/if}
                        </td>
                        <td style="white-space:nowrap;">{$b.time}</td>
                        <td>
                            <button class="btn btn-warning btn-xs fcv-undo"
                                    data-ref="{$b.ref|escape}"
                                    title="Undo this operation">
                                <i class="fa fa-undo"></i> Undo
                            </button>
                        </td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
            </div>
            {/if}

        </div>{* /#fcv-tab-custom_module *}

        {* ================================================================
           TAB 2: 1:1 Relationship
        ================================================================ *}
        <div class="fcv-tab-pane" id="fcv-tab-rel_11" style="display:none;">
            <h5><i class="fa fa-arrows-h"></i> Create 1:1 Relationship</h5>
            <div class="alert alert-info" style="font-size:13px;">
                Creates a <strong>bidirectional</strong> relate field between 2 modules.
                Module 2 will have a field pointing to Module 1 and vice versa.
            </div>
            {include file='modules/FcvModuleBuilder/partials/RelForm.tpl'
                     rel_type='1:1'
                     lbl1='Field Label on Module 2 (points to Module 1)'
                     lbl2='Field Label on Module 1 (points to Module 2)'}
            {include file='modules/FcvModuleBuilder/partials/RelList.tpl'}
        </div>

        {* ================================================================
           TAB 3: 1:M Relationship
        ================================================================ *}
        <div class="fcv-tab-pane" id="fcv-tab-rel_1m" style="display:none;">
            <h5><i class="fa fa-sitemap"></i> Create 1:M Relationship</h5>
            <div class="alert alert-info" style="font-size:13px;">
                <strong>Module 1 (Primary/Parent)</strong> &rarr; <strong>Module 2 (Child)</strong>.<br/>
                The child will have a relate field pointing to the parent.
                The parent will have a related list showing child records.
            </div>
            {include file='modules/FcvModuleBuilder/partials/RelForm.tpl'
                     rel_type='1:M'
                     lbl1='New Field Label (on Child / Module 2)'
                     lbl2='Related List Label (on Parent / Module 1)'}
            {include file='modules/FcvModuleBuilder/partials/RelList.tpl'}
        </div>

        {* ================================================================
           TAB 4: M:M Relationship
        ================================================================ *}
        <div class="fcv-tab-pane" id="fcv-tab-rel_mm" style="display:none;">
            <h5><i class="fa fa-random"></i> Create M:M Relationship</h5>
            <div class="alert alert-info" style="font-size:13px;">
                Both modules will have a <strong>related list</strong> of each other.
                No relate field is created — only related list panels on both sides.
            </div>
            {include file='modules/FcvModuleBuilder/partials/RelForm.tpl'
                     rel_type='M:M'
                     lbl1='Related List Label on Module 2 (shows Module 1 records)'
                     lbl2='Related List Label on Module 1 (shows Module 2 records)'}
            {include file='modules/FcvModuleBuilder/partials/RelList.tpl'}
        </div>

    </div>{* /.tab-content *}
</div>{* /.vte-module-builder *}

{* ================================================================
   JavaScript
================================================================ *}
<script type="text/javascript">
{literal}
jQuery(function($) {

    // ---- Tab switching ----
    function fcvShowTab(tabId) {
        $('.fcv-tab-pane').hide();
        $('#fcv-tab-' + tabId).show();
        $('.fcv-tab-li').removeClass('active');
        $('.fcv-tab-li[data-tab="' + tabId + '"]').addClass('active');
    }

    $('.fcv-tab-li').on('click', function() {
        fcvShowTab($(this).data('tab'));
    });

    // Show first tab on load
    fcvShowTab('custom_module');

    var BASE_POST = {
        parent: 'Settings',
        module: 'FcvModuleBuilder'
    };

    // ---- Helper: POST JSON ----
    function fcvPost(extraData, onSuccess, onError) {
        var data = $.extend({}, BASE_POST, extraData);
        $.ajax({
            url    : 'index.php',
            type   : 'POST',
            data   : data,
            dataType: 'json',
            success: function(res) {
                if (res && res.success) {
                    onSuccess(res);
                } else {
                    onError(res ? res.message : 'Unknown error');
                }
            },
            error: function(xhr) {
                onError('Server error: ' + xhr.status);
            }
        });
    }

    // ---- Create Module ----
    $('#fcv-create-module-form').on('submit', function(e) {
        e.preventDefault();
        var formData = {};
        $.each($(this).serializeArray(), function(_, kv) { formData[kv.name] = kv.value; });

        $('#fcv-create-btn').prop('disabled', true);
        $('#fcv-create-spinner').show();

        fcvPost($.extend(formData, { action: 'ModuleCreate' }),
            function(res) {
                alert('OK: ' + res.message);
                location.reload();
            },
            function(msg) {
                alert('Error: ' + msg);
                $('#fcv-create-btn').prop('disabled', false);
                $('#fcv-create-spinner').hide();
            }
        );
    });

    // ---- Delete Module ----
    $(document).on('click', '.fcv-delete-module', function() {
        var name  = $(this).data('module');
        var label = $(this).data('label');
        if (!confirm('Delete module "' + label + '" (' + name + ')?\n\nThis will:\n- Delete all source files\n- Drop DB tables (vtiger_' + name.toLowerCase() + '*)\n- Remove all metadata\n\nA backup will be created automatically before deletion.')) {
            return;
        }
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        fcvPost({ action: 'ModuleDelete', module_name: name },
            function(res) {
                alert('Deleted: ' + res.message + (res.backup_ref ? '\nBackup ref: ' + res.backup_ref : ''));
                location.reload();
            },
            function(msg) {
                alert('Error: ' + msg);
                $btn.prop('disabled', false).html('<i class="fa fa-trash"></i>');
            }
        );
    });

    // ---- Undo ----
    $(document).on('click', '.fcv-undo', function() {
        var ref = $(this).data('ref');
        if (!confirm('Undo backup: ' + ref + '?\n\nThis will reverse the previous operation.')) {
            return;
        }
        fcvPost({ action: 'ModuleUndo', backup_ref: ref },
            function(res) {
                var steps = res.steps ? '\n\nDetails:\n- ' + res.steps.join('\n- ') : '';
                alert('Undone: ' + res.message + steps);
                location.reload();
            },
            function(msg) { alert('Error: ' + msg); }
        );
    });

    // ---- Create Relationship ----
    $(document).on('submit', '.fcv-rel-form', function(e) {
        e.preventDefault();
        var formData = {};
        $.each($(this).serializeArray(), function(_, kv) { formData[kv.name] = kv.value; });

        var $btn = $(this).find('button[type=submit]').prop('disabled', true);

        fcvPost($.extend(formData, { action: 'RelationCreate' }),
            function(res) {
                alert('Created: ' + res.message);
                location.reload();
            },
            function(msg) {
                alert('Error: ' + msg);
                $btn.prop('disabled', false);
            }
        );
    });

    // ---- Change Nav Group ----
    $(document).on('click', '.fcv-set-nav', function() {
        var $btn    = $(this);
        var name    = $btn.data('module');
        var navVal  = $btn.closest('.input-group').find('.fcv-nav-select').val();
        var display = navVal || '(hidden)';
        if (!confirm('Move module "' + name + '" to nav group: ' + display + '?')) return;
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        fcvPost({ action: 'ModuleSetNav', module_name: name, parent_menu: navVal },
            function(res) {
                alert(res.message);
                location.reload();
            },
            function(msg) {
                alert('Error: ' + msg);
                $btn.prop('disabled', false).html('<i class="fa fa-check"></i>');
            }
        );
    });

    // ---- Delete Relationship ----
    $(document).on('click', '.fcv-delete-relation', function() {
        var id = $(this).data('id');
        if (!confirm('Delete relationship #' + id + '?')) return;

        fcvPost({ action: 'RelationDelete', relation_id: id },
            function(res) { alert('Deleted: ' + res.message); location.reload(); },
            function(msg) { alert('Error: ' + msg); }
        );
    });

    // ---- Validate module name ----
    $('input#fcv-module-name-input').on('input', function() {
        var val = $(this).val();
        var valid = /^[A-Za-z][A-Za-z0-9_]{2,49}$/.test(val);
        $(this).closest('.form-group').toggleClass('has-error', val.length > 0 && !valid);
        $(this).closest('.form-group').find('.fcv-name-error').remove();
        if (val.length > 0 && !valid) {
            $(this).closest('.form-group').append('<span class="help-block fcv-name-error text-danger">Invalid: PascalCase, 3-50 chars, must start with a letter.</span>');
        }
    });

    // ---- Auto-generate module name from label ----
    $('input[name="module_label"]').on('input', function() {
        var nameField = $('input[name="module_name"]');
        if (nameField.data('manual')) return;
        var name = $(this).val()
            .replace(/[^a-zA-Z0-9\s]/g, '')
            .replace(/(?:^|\s)(\w)/g, function(_, c) { return c.toUpperCase(); })
            .replace(/\s+/g, '');
        nameField.val(name);
    });

    $('input[name="module_name"]').on('input', function() {
        $(this).data('manual', $(this).val().length > 0);
    });

});
{/literal}
</script>
