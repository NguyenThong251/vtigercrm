/* layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.js
 * Modal manager for FCVMultiOwner (uitype 200).
 */
(function ($) {
    'use strict';

    function normalizePermission(permission) {
        return permission === 'read' ? 'read' : 'write';
    }

    function normalizeOwner(owner) {
        var userid = parseInt(owner.userid || owner.id, 10);
        if (!userid) {
            return null;
        }

        return {
            userid: userid,
            username: owner.username || owner.name || ('User ' + userid),
            permission: normalizePermission(owner.permission)
        };
    }

    function parseOwnersValue(raw) {
        var value = raw || [];

        if (typeof value === 'string') {
            try {
                value = value ? JSON.parse(value) : [];
            } catch (e) {
                value = [];
            }
        }

        if (!$.isArray(value)) {
            value = [];
        }

        return $.map(value, normalizeOwner);
    }

    function readOwners($wrapper) {
        var hiddenValue = $wrapper.find('.fcv-mo-hidden').val();
        if (hiddenValue) {
            return parseOwnersValue(hiddenValue);
        }
        return parseOwnersValue($wrapper.attr('data-owners') || $wrapper.data('owners'));
    }

    function serializeOwners(owners) {
        return JSON.stringify($.map(owners, function (owner) {
            return {
                userid: owner.userid,
                username: owner.username,
                permission: normalizePermission(owner.permission)
            };
        }));
    }

    function updateSummary($wrapper, owners) {
        var count = owners.length;
        var summary = count ? count + ' owner' + (count > 1 ? 's' : '') : 'No owners';
        var names = $.map(owners.slice(0, 2), function (owner) {
            return owner.username;
        }).join(', ');

        if (count > 2) {
            names += ' +' + (count - 2);
        }

        $wrapper.find('.fcv-mo-inline-summary')
            .text(names || summary)
            .attr('title', count ? $.map(owners, function (owner) { return owner.username; }).join(', ') : summary);

        $wrapper.find('.fcv-mo-count').text(summary);
    }

    function writeOwners($wrapper, owners) {
        var serialized = serializeOwners(owners);
        $wrapper.attr('data-owners', JSON.stringify(owners));
        $wrapper.find('.fcv-mo-hidden').val(serialized).trigger('change');
        updateSummary($wrapper, owners);
    }

    function readOwnersFromDetail($container) {
        var owners = [];
        $container.find('.value .fcv-mo-detail .fcv-mo-chip').each(function () {
            var $chip = $(this);
            var owner = normalizeOwner({
                userid: $chip.attr('data-userid'),
                name: $.trim($chip.find('.fcv-mo-name').text()),
                permission: $chip.attr('data-permission') || ($chip.find('.fcv-mo-read').length ? 'read' : 'write')
            });
            if (owner) {
                owners.push(owner);
            }
        });
        return owners;
    }

    function getInitial(name) {
        return ((name || '?').trim().charAt(0) || '?').toUpperCase();
    }

    function makeSelectedRow(owner) {
        return $('<div class="fcv-mo-selected-row">')
            .attr('data-userid', owner.userid)
            .append(
                $('<span class="fcv-mo-avatar">').text(getInitial(owner.username)),
                $('<span class="fcv-mo-selected-name">').text(owner.username),
                $('<select class="fcv-mo-permission-select inputElement">')
                    .append(
                        $('<option value="write">').text('Write'),
                        $('<option value="read">').text('Read')
                    )
                    .val(normalizePermission(owner.permission)),
                $('<button type="button" class="btn btn-default btn-sm fcv-mo-remove-owner" title="Remove">')
                    .append($('<i class="fa fa-trash" aria-hidden="true"></i>'))
            );
    }

    function makeAvailableRow(user) {
        return $('<div class="fcv-mo-available-row">')
            .attr({
                'data-userid': user.id,
                'data-username': user.name
            })
            .append(
                $('<span class="fcv-mo-avatar">').text(getInitial(user.name)),
                $('<span class="fcv-mo-available-name">').text(user.name),
                $('<button type="button" class="btn btn-default btn-sm fcv-mo-add-owner">')
                    .append($('<i class="fa fa-plus" aria-hidden="true"></i>'), document.createTextNode(' Add'))
            );
    }

    function openManager($wrapper) {
        var owners = readOwners($wrapper).slice();
        var usersCache = [];
        var searchTimer = null;

        var $modal = $(
            '<div class="modal fade fcv-mo-manager" tabindex="-1" role="dialog">' +
                '<div class="modal-dialog modal-lg fcv-mo-manager-dialog" role="document">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header">' +
                            '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>' +
                            '<h4 class="modal-title">Manage owners</h4>' +
                        '</div>' +
                        '<div class="modal-body">' +
                            '<div class="fcv-mo-manager-grid">' +
                                '<div class="fcv-mo-panel fcv-mo-selected-panel">' +
                                    '<div class="fcv-mo-panel-heading"><span>Selected users</span><span class="fcv-mo-count"></span></div>' +
                                    '<div class="fcv-mo-selected-list"></div>' +
                                '</div>' +
                                '<div class="fcv-mo-panel fcv-mo-available-panel">' +
                                    '<div class="fcv-mo-panel-heading"><span>Available users</span></div>' +
                                    '<input type="text" class="inputElement fcv-mo-user-search" placeholder="Search users">' +
                                    '<div class="fcv-mo-available-list"></div>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="modal-footer">' +
                            '<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>' +
                            '<button type="button" class="btn btn-success fcv-mo-apply">Apply</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );

        function selectedIds() {
            var ids = {};
            $.each(owners, function (_, owner) {
                ids[owner.userid] = true;
            });
            return ids;
        }

        function renderSelected() {
            var $list = $modal.find('.fcv-mo-selected-list').empty();
            $modal.find('.fcv-mo-count').text(owners.length + ' selected');

            if (!owners.length) {
                $list.append('<div class="fcv-mo-empty-state">No users selected</div>');
                return;
            }

            $.each(owners, function (_, owner) {
                $list.append(makeSelectedRow(owner));
            });
        }

        function renderAvailable() {
            var ids = selectedIds();
            var $list = $modal.find('.fcv-mo-available-list').empty();
            var added = 0;

            $.each(usersCache, function (_, user) {
                if (ids[parseInt(user.id, 10)]) {
                    return;
                }
                added++;
                $list.append(makeAvailableRow(user));
            });

            if (!added) {
                $list.append('<div class="fcv-mo-empty-state">No users available</div>');
            }
        }

        function renderAll() {
            renderSelected();
            renderAvailable();
        }

        function searchUsers(query) {
            var $list = $modal.find('.fcv-mo-available-list');
            $list.html('<div class="fcv-mo-empty-state">Loading...</div>');

            $.getJSON('index.php', {
                module: 'FCVMultiOwner',
                action: 'SearchUsers',
                query: query || ''
            }).done(function (data) {
                usersCache = (data && data.success && $.isArray(data.result)) ? data.result : [];
                renderAvailable();
            }).fail(function (xhr) {
                $list.html('<div class="fcv-mo-empty-state">Search failed (' + xhr.status + ')</div>');
            });
        }

        $modal.on('change', '.fcv-mo-permission-select', function () {
            var userid = parseInt($(this).closest('.fcv-mo-selected-row').attr('data-userid'), 10);
            var permission = normalizePermission($(this).val());
            $.each(owners, function (_, owner) {
                if (owner.userid === userid) {
                    owner.permission = permission;
                    return false;
                }
            });
        });

        $modal.on('click', '.fcv-mo-remove-owner', function () {
            var userid = parseInt($(this).closest('.fcv-mo-selected-row').attr('data-userid'), 10);
            owners = $.grep(owners, function (owner) {
                return owner.userid !== userid;
            });
            renderAll();
        });

        $modal.on('click', '.fcv-mo-add-owner', function () {
            var $row = $(this).closest('.fcv-mo-available-row');
            var owner = normalizeOwner({
                id: $row.attr('data-userid'),
                name: $row.attr('data-username'),
                permission: 'write'
            });
            if (owner) {
                owners.push(owner);
                renderAll();
            }
        });

        $modal.on('input', '.fcv-mo-user-search', function () {
            clearTimeout(searchTimer);
            var query = $(this).val();
            searchTimer = setTimeout(function () {
                searchUsers(query);
            }, 250);
        });

        $modal.on('click', '.fcv-mo-apply', function () {
            writeOwners($wrapper, owners);
            $modal.modal('hide');
        });

        $modal.on('hidden.bs.modal', function () {
            $modal.remove();
        });

        $('body').append($modal);
        renderSelected();
        searchUsers('');
        $modal.modal('show');
        $modal.find('.fcv-mo-user-search').trigger('focus');
    }

    function initWrapper($wrapper) {
        if ($wrapper.data('fcv-mo-init')) {
            updateSummary($wrapper, readOwners($wrapper));
            return;
        }

        $wrapper.data('fcv-mo-init', true);

        if (!$wrapper.find('.fcv-mo-inline-control').length) {
            $wrapper.find('.fcv-mo-chips').empty().append(
                $('<button type="button" class="btn btn-default btn-sm fcv-mo-manage-btn"></button>').append(
                    $('<i class="fa fa-users" aria-hidden="true"></i>'),
                    document.createTextNode(' Manage owners')
                ),
                $('<span class="fcv-mo-inline-summary"></span>')
            );
        }

        writeOwners($wrapper, readOwners($wrapper));

        $wrapper.on('click', '.fcv-mo-manage-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openManager($wrapper);
        });
    }

    function convertLegacyInlineEditor($editElement) {
        if ($editElement.data('fcv-mo-legacy-converted')) {
            return;
        }

        var $fieldValue = $editElement.closest('.fieldValue');
        if (!$fieldValue.find('.value .fcv-mo-detail').length) {
            return;
        }

        var $fieldBasicData = $fieldValue.find('.fieldBasicData').first();
        var $textInput = $editElement.find('input[type="text"].inputElement').first();
        if (!$textInput.length) {
            return;
        }

        var fieldName = $fieldBasicData.attr('data-name') || $textInput.attr('name');
        if (!fieldName) {
            return;
        }

        var owners = parseOwnersValue($fieldBasicData.attr('data-value'));
        if (!owners.length) {
            owners = readOwnersFromDetail($fieldValue);
        }

        var $wrapper = $('<div class="fcv-mo-wrapper fcv-mo-inline-editor"></div>').attr({
            'data-uitype': '200',
            'data-fieldname': fieldName,
            'data-owners': JSON.stringify(owners)
        });
        var $hidden = $('<input type="hidden" class="fcv-mo-hidden inputElement" />').attr('name', fieldName);
        var $control = $('<div class="fcv-mo-inline-control"></div>').append(
            $('<button type="button" class="btn btn-default btn-sm fcv-mo-manage-btn"></button>').append(
                $('<i class="fa fa-users" aria-hidden="true"></i>'),
                document.createTextNode(' Manage')
            ),
            $('<span class="fcv-mo-inline-summary"></span>')
        );

        $wrapper.append($hidden, $control);
        $textInput.replaceWith($wrapper);
        $editElement.data('fcv-mo-legacy-converted', true);
        initWrapper($wrapper);
    }

    function convertLegacyInlineEditors() {
        $('.editElement').each(function () {
            convertLegacyInlineEditor($(this));
        });
    }

    function initAll() {
        $('[data-uitype="200"]').each(function () {
            initWrapper($(this));
        });
        convertLegacyInlineEditors();
    }

    $(document).ready(initAll);

    $(document).on('click', '.editAction', function () {
        setTimeout(initAll, 0);
        setTimeout(initAll, 100);
        setTimeout(initAll, 300);
    });

    if (typeof app !== 'undefined' && app.event) {
        app.event.on('Post.EditView.Load', initAll);
        app.event.on('Post.QuickCreate.Load', initAll);
    }

    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function (mutations) {
            var found = false;
            $.each(mutations, function (_, mutation) {
                $.each(mutation.addedNodes, function (_, node) {
                    if (node.nodeType !== 1) {
                        return;
                    }
                    if (
                        $(node).is('[data-uitype="200"], .editElement, input[data-rule-fcvmultiowner="true"]') ||
                        $(node).find('[data-uitype="200"], .editElement, input[data-rule-fcvmultiowner="true"]').length
                    ) {
                        found = true;
                    }
                });
            });
            if (found) {
                initAll();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }
}(jQuery));
