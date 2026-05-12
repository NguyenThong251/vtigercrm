/* layouts/v7/modules/FCVMultiOwner/resources/fcv-multiowner.js
 * FCVMultiOwner — Chip UI for multi-owner field (uitype 200)
 * Handles: chip render, search popup, R/W toggle, remove, JSON serialization
 */
(function ($) {
    'use strict';

    // ── Helpers ──────────────────────────────────────────────────────────────

    function getInitial(name) {
        return ((name || '?').trim().charAt(0) || '?').toUpperCase();
    }

    function buildChip(userid, name, permission) {
        var perm  = (permission === 'read') ? 'read' : 'write';
        var label = (perm === 'write') ? 'W' : 'R';
        var cls   = (perm === 'write') ? 'fcv-mo-write' : 'fcv-mo-read';
        return $('<span class="fcv-mo-chip">')
            .attr({
                'data-userid':     userid,
                'data-name':       name,
                'data-permission': perm
            })
            .append(
                $('<span class="fcv-mo-avatar">').text(getInitial(name)),
                $('<span class="fcv-mo-name">').text(name),
                $('<span class="fcv-mo-perm ' + cls + '" title="Click to toggle Read/Write">').text(label),
                $('<button type="button" class="fcv-mo-remove" tabindex="-1" title="Remove">').html('&times;')
            );
    }

    function serializeChips($wrapper) {
        var owners = [];
        $wrapper.find('.fcv-mo-chips .fcv-mo-chip').each(function () {
            owners.push({
                userid:     parseInt($(this).attr('data-userid'), 10),
                permission: $(this).attr('data-permission')
            });
        });
        $wrapper.find('.fcv-mo-hidden').val(JSON.stringify(owners));
    }

    // ── Search Popup ─────────────────────────────────────────────────────────

    function openPopup($wrapper) {
        // Close any existing popup
        $('.fcv-mo-popup').remove();

        var $popup = $('<div class="fcv-mo-popup">')
            .append(
                $('<input type="text" class="fcv-mo-popup-search" placeholder="Search user by name...">'),
                $('<div class="fcv-mo-popup-results">')
            );

        // Position below the Add button
        var $btn    = $wrapper.find('.fcv-mo-add-btn');
        var offset  = $btn.offset();
        $popup.css({
            top:  offset.top + $btn.outerHeight() + 4,
            left: offset.left
        });
        $('body').append($popup);

        var $input   = $popup.find('.fcv-mo-popup-search');
        var $results = $popup.find('.fcv-mo-popup-results');
        $input.trigger('focus');

        // ── Shared search function ──
        // Vtiger_Response wraps results as {success:true, result:[...]}
        function doSearch(q) {
            $.getJSON('index.php', {
                module: 'FCVMultiOwner',
                action: 'SearchUsers',
                query:  q
            })
            .done(function (data) {
                $results.empty();
                var users = (data && data.success && Array.isArray(data.result)) ? data.result : [];
                if (!users.length) {
                    $results.append('<div class="fcv-mo-no-results">No users found</div>');
                    return;
                }
                var added = 0;
                users.forEach(function (u) {
                    // Skip already-added users
                    if ($wrapper.find('.fcv-mo-chip[data-userid="' + u.id + '"]').length) {
                        return;
                    }
                    added++;
                    $('<div class="fcv-mo-result-item">')
                        .text(u.name)
                        .attr({'data-userid': u.id, 'data-name': u.name})
                        .on('click', function () {
                            var $chip = buildChip(u.id, u.name, 'write');
                            $wrapper.find('.fcv-mo-add-btn').before($chip);
                            serializeChips($wrapper);
                            $popup.remove();
                        })
                        .appendTo($results);
                });
                if (added === 0) {
                    $results.append('<div class="fcv-mo-no-results">No users found</div>');
                }
            })
            .fail(function (xhr) {
                $results.html('<div class="fcv-mo-no-results">Search failed (' + xhr.status + ')</div>');
            });
        }

        // ── Load all users immediately on open (empty query = all users) ──
        doSearch('');

        // ── Live filter as user types ──
        var timer;
        $input.on('input', function () {
            clearTimeout(timer);
            var q = $(this).val().trim();
            timer = setTimeout(function () { doSearch(q); }, 250);
        });

        // ── Close on outside click ──
        setTimeout(function () {
            $(document).one('click.fcvpopup', function (e) {
                if (!$(e.target).closest('.fcv-mo-popup, .fcv-mo-add-btn').length) {
                    $popup.remove();
                }
            });
        }, 0);
    }

    // ── Init a single wrapper ────────────────────────────────────────────────

    function initWrapper($wrapper) {
        if ($wrapper.data('fcv-mo-init')) return;
        $wrapper.data('fcv-mo-init', true);

        // Load initial chips from data-owners attribute
        var existing = [];
        try {
            var raw = $wrapper.attr('data-owners');
            if (raw) existing = JSON.parse(raw);
        } catch (e) {
            existing = [];
        }

        existing.forEach(function (o) {
            $wrapper.find('.fcv-mo-add-btn').before(
                buildChip(o.userid, o.username || o.name || ('User ' + o.userid), o.permission)
            );
        });
        serializeChips($wrapper);

        // ── Add button click ──
        $wrapper.on('click', '.fcv-mo-add-btn', function (e) {
            e.stopPropagation();
            openPopup($wrapper);
        });

        // ── Toggle R/W badge ──
        $wrapper.on('click', '.fcv-mo-perm', function (e) {
            e.stopPropagation();
            var $chip = $(this).closest('.fcv-mo-chip');
            var next  = ($chip.attr('data-permission') === 'write') ? 'read' : 'write';
            $chip.attr('data-permission', next);
            $(this)
                .text(next === 'write' ? 'W' : 'R')
                .removeClass('fcv-mo-write fcv-mo-read')
                .addClass(next === 'write' ? 'fcv-mo-write' : 'fcv-mo-read');
            serializeChips($wrapper);
        });

        // ── Remove chip ──
        $wrapper.on('click', '.fcv-mo-remove', function (e) {
            e.stopPropagation();
            $(this).closest('.fcv-mo-chip').remove();
            serializeChips($wrapper);
        });
    }

    // ── Bootstrap ────────────────────────────────────────────────────────────

    function initAll() {
        $('[data-uitype="200"]').each(function () {
            initWrapper($(this));
        });
    }

    $(document).ready(initAll);

    // Re-init after vtiger Quick-Create, AJAX sub-panel, or full edit view load
    if (typeof app !== 'undefined' && app.event) {
        app.event.on('Post.EditView.Load',    initAll);
        app.event.on('Post.QuickCreate.Load', initAll);
    }

    // MutationObserver: catches inline-edit renders (detail view field click)
    // and any dynamically injected edit forms that don't fire vtiger events
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function (mutations) {
            var found = false;
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    // direct match
                    if ($(node).is('[data-uitype="200"]')) { found = true; }
                    // descendant match
                    if ($(node).find('[data-uitype="200"]').length) { found = true; }
                });
            });
            if (found) { initAll(); }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

}(jQuery));
