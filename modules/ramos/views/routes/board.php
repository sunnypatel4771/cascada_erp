<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .ramos-route-column {
        min-width: 280px;
    }
    .ramos-stop-list {
        min-height: 120px;
    }
    .ramos-stop-card {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 12px;
        cursor: move;
        transition: box-shadow 0.2s ease;
    }
    .ramos-stop-card:hover {
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.12);
    }
    .ramos-stop-card--readonly {
        cursor: default;
    }
    .ramos-stop-placeholder {
        border: 2px dashed #2563eb;
        border-radius: 6px;
        margin-bottom: 12px;
        min-height: 70px;
        background-color: rgba(37, 99, 235, 0.08);
    }
    .ramos-stop-empty {
        border: 1px dashed #cbd5f5;
        padding: 16px;
        border-radius: 6px;
        text-align: center;
        color: #6b7280;
        font-size: 12px;
        background-color: #f8fafc;
    }
</style>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-col md:tw-flex-row tw-justify-between tw-items-start md:tw-items-center tw-gap-4 tw-mb-4">
                    <div>
                        <h4 class="tw-text-2xl tw-font-semibold tw-text-slate-900 tw-mb-1"><?php echo _l('ramos_routes_board_title'); ?></h4>
                        <p class="tw-text-slate-500 tw-mb-1"><?php echo _l('ramos_routes_board_subtitle'); ?></p>
                        <p class="tw-text-xs tw-text-slate-400 tw-mb-0"><?php echo _l('ramos_routes_board_hint'); ?></p>
                    </div>
                    <div class="tw-flex tw-gap-2">
                        <a href="<?php echo admin_url('ramos/routes?date=' . $date); ?>" class="btn btn-default">
                            <i class="fa-regular fa-arrow-left-long tw-mr-1"></i><?php echo _l('ramos_routes_board_back_button'); ?>
                        </a>
                    </div>
                </div>

                <div class="panel_s tw-mb-4">
                    <div class="panel-body tw-flex tw-flex-col sm:tw-flex-row tw-gap-3 tw-items-start sm:tw-items-center">
                        <?php echo form_open(admin_url('ramos/routes/board'), ['method' => 'get', 'class' => 'tw-flex tw-gap-2 tw-items-center tw-flex-wrap']); ?>
                            <label for="board-date" class="control-label tw-mb-0"><?php echo _l('ramos_routes_board_date_label'); ?></label>
                            <input type="date" id="board-date" name="date" value="<?php echo html_escape($date); ?>" class="form-control" style="max-width: 200px;">
                            <button type="submit" class="btn btn-default btn-sm"><?php echo _l('submit'); ?></button>
                        <?php echo form_close(); ?>
                    </div>
                </div>

                <div class="panel_s tw-mb-4">
                    <div class="panel-body tw-flex tw-flex-wrap tw-gap-4 tw-items-center tw-text-xs tw-text-slate-600">
                        <span class="tw-font-semibold tw-text-slate-500"><?php echo _l('ramos_routes_board_legend_label'); ?>:</span>
                        <span><span class="label label-success">&nbsp;</span>&nbsp;<?php echo _l('ramos_routes_board_legend_green'); ?></span>
                        <span><span class="label label-warning">&nbsp;</span>&nbsp;<?php echo _l('ramos_routes_board_legend_yellow'); ?></span>
                        <span><span class="label label-danger">&nbsp;</span>&nbsp;<?php echo _l('ramos_routes_board_legend_red'); ?></span>
                    </div>
                </div>

                <div class="panel_s <?php echo empty($routes) ? '' : 'tw-hidden'; ?>" id="ramos-board-empty">
                    <div class="panel-body">
                        <p class="tw-text-sm tw-text-slate-500 tw-mb-0"><?php echo _l('ramos_routes_board_no_routes'); ?></p>
                    </div>
                </div>

                <div class="tw-overflow-x-auto <?php echo empty($routes) ? 'tw-hidden' : ''; ?>" id="ramos-board-container">
                    <div class="row tw-flex-nowrap tw-gap-4" id="ramos-board">
                        <?php
                        $progressTemplate = _l('ramos_routes_board_progress_label');
                        $pendingTemplate  = _l('ramos_routes_board_pending_label');
                        foreach ($routes as $route) :
                            $this->load->view('ramos/routes/partials/board_column', [
                                'route'             => $route,
                                'progress_template' => $progressTemplate,
                                'pending_template'  => $pendingTemplate,
                            ]);
                        endforeach;
                        ?>
                    </div>
                </div>
                <input type="hidden" id="ramos-board-csrf" data-name="<?php echo html_escape($csrf['name']); ?>" data-value="<?php echo html_escape($csrf['hash']); ?>">
                <div id="ramos-board-templates" data-progress-template="<?php echo html_escape(_l('ramos_routes_board_progress_label')); ?>" data-pending-template="<?php echo html_escape(_l('ramos_routes_board_pending_label')); ?>"></div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<link rel="stylesheet" href="<?php echo base_url('assets/plugins/jquery-ui/jquery-ui.min.css'); ?>">
<script src="<?php echo base_url('assets/plugins/jquery-ui/jquery-ui.min.js'); ?>"></script>
<script>
(function ($) {
    "use strict";

    var canEdit = <?php echo json_encode((bool) $can_edit); ?>;
    var csrfHolder = $('#ramos-board-csrf');
    var csrfName = csrfHolder.data('name');
    var csrfHash = csrfHolder.data('value');
    var templatesHolder = $('#ramos-board-templates');
    var currentDate = <?php echo json_encode($date); ?>;
    var refreshInterval = 20000;
    var pollTimer = null;
    var isSorting = false;

    function formatTemplate(template, values) {
        var output = template;
        values.forEach(function (value) {
            output = output.replace('%s', value);
        });
        return output;
    }

    function updateMetrics(data) {
        if (!data || typeof data !== 'object') {
            return;
        }

        var routeId = data.id;
        var $column = $('.ramos-route-column[data-route-id="' + routeId + '"]');

        if (!$column.length) {
            return;
        }

        var $state = $column.find('[data-route-state]');
        if ($state.length && data.board_state) {
            $state.removeClass('label-success label-warning label-danger label-default')
                .addClass(data.board_state.badge_class)
                .text(data.board_state.label);
        }

        var $progress = $column.find('[data-route-progress]');
        if ($progress.length) {
            var template = $progress.data('template') || templatesHolder.data('progressTemplate') || '%s / %s';
            $progress.text(formatTemplate(template, [data.completed_stops, data.total_stops]));
        }

        var $pending = $column.find('[data-route-pending]');
        if ($pending.length) {
            var pendingTemplate = $pending.data('template') || templatesHolder.data('pendingTemplate') || 'Pending: %s';
            $pending.text(formatTemplate(pendingTemplate, [data.pending_stops]));
        }
    }

    function refreshEmpty($list) {
        if (!$list || !$list.length) {
            return;
        }

        if ($list.children('.ramos-stop-card').length === 0) {
            if ($list.children('.ramos-stop-empty').length === 0) {
                $list.append('<li class="ramos-stop-empty">' + $list.data('emptyText') + '</li>');
            }
        } else {
            $list.children('.ramos-stop-empty').remove();
        }
    }

    function updateCsrf(csrf) {
        if (!csrf || !csrf.hash) {
            return;
        }
        csrfHash = csrf.hash;
        csrfHolder.attr('data-value', csrfHash);
    }

    function refreshAllLists() {
        $('.ramos-stop-list').each(function () {
            refreshEmpty($(this));
        });
    }

    function applyReadonlyState() {
        if (!canEdit) {
            $('.ramos-stop-card').addClass('ramos-stop-card--readonly');
        } else {
            $('.ramos-stop-card').removeClass('ramos-stop-card--readonly');
        }
    }

    function destroySortable() {
        if (!canEdit) {
            return;
        }
        $('.ramos-stop-list').each(function () {
            var $this = $(this);
            if ($this.data('ui-sortable')) {
                $this.sortable('destroy');
            }
        });
    }

    function initSortable() {
        if (!canEdit) {
            return;
        }

        // Check if jQuery UI sortable is available
        if (typeof $.fn.sortable === 'undefined') {
            console.error('Ramos Routes Board: jQuery UI Sortable is not loaded!');
            alert('Drag & drop functionality requires jQuery UI. Please contact your administrator.');
            return;
        }

        $('.ramos-stop-list').sortable({
            connectWith: '.ramos-stop-list',
            items: '.ramos-stop-card',
            placeholder: 'ramos-stop-placeholder',
            cursor: 'move',
            forcePlaceholderSize: true,
            tolerance: 'pointer',
            start: function (event, ui) {
                console.log('Drag started for stop ID:', ui.item.data('stopId'));
                ui.item.data('origin-route', ui.item.closest('.ramos-stop-list').data('routeId'));
                isSorting = true;
            },
            receive: function (event, ui) {
                refreshEmpty($(this));
                refreshEmpty(ui.sender);
            },
            update: function (event, ui) {
                var $list = $(this);

                if (ui.item.parent()[0] !== $list[0]) {
                    console.log('Update triggered on sender list, skipping');
                    refreshEmpty($list);
                    return;
                }

                var stopIds = [];
                $list.children('.ramos-stop-card').each(function () {
                    var id = parseInt($(this).data('stopId'), 10);
                    if (!isNaN(id)) {
                        stopIds.push(id);
                    }
                });

                var payload = {
                    stop_id: parseInt(ui.item.data('stopId'), 10) || 0,
                    origin_route_id: parseInt(ui.item.data('origin-route'), 10) || 0,
                    destination_route_id: parseInt($list.data('routeId'), 10) || 0,
                    order: stopIds
                };

                console.log('Sending update:', payload);

                payload[csrfName] = csrfHash;

                $.post(admin_url + 'ramos/routes/move_stop', payload)
                    .done(function (response) {
                        console.log('Server response:', response);

                        if (response && response.csrf) {
                            updateCsrf(response.csrf);
                        }

                        if (response && response.success) {
                            console.log('Update successful');
                            updateMetrics(response.destination);
                            refreshEmpty($list);

                            if (response.origin) {
                                updateMetrics(response.origin);
                                refreshEmpty($('.ramos-stop-list[data-route-id="' + response.origin.id + '"]'));
                            }

                            ui.item.data('origin-route', $list.data('routeId'));
                        } else {
                            console.error('Update failed:', response);
                            var message = response && response.message ? response.message : '<?php echo _l('ramos_routes_board_move_error'); ?>';
                            alert(message);
                            window.location.reload();
                        }
                    })
                    .fail(function (xhr) {
                        console.error('AJAX error:', xhr);
                        if (xhr.responseJSON && xhr.responseJSON.csrf) {
                            updateCsrf(xhr.responseJSON.csrf);
                        }
                        var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : '<?php echo _l('ramos_routes_board_move_error'); ?>';
                        alert(message);
                        window.location.reload();
                    });
            },
            stop: function () {
                isSorting = false;
            }
        }).disableSelection();
    }

    function toggleEmptyState(hasRoutes) {
        $('#ramos-board-empty').toggleClass('tw-hidden', hasRoutes);
        $('#ramos-board-container').toggleClass('tw-hidden', !hasRoutes);
    }

    function replaceBoard(html, hasRoutes) {
        var $board = $('#ramos-board');
        if (!$board.length) {
            return;
        }

        destroySortable();
        $board.html(html || '');
        applyReadonlyState();
        refreshAllLists();

        toggleEmptyState(hasRoutes);

        if (canEdit) {
            initSortable();
        }
    }

    function scheduleNext() {
        pollTimer = setTimeout(fetchBoard, refreshInterval);
    }

    function fetchBoard() {
        if (isSorting) {
            scheduleNext();
            return;
        }

        $.get(admin_url + 'ramos/routes/board_refresh', { date: currentDate })
            .done(function (response) {
                if (response && response.success) {
                    if (typeof response.html === 'string') {
                        var hasRoutes = Array.isArray(response.routes) ? response.routes.length > 0 : response.html.trim().length > 0;
                        replaceBoard(response.html, hasRoutes);
                    }
                }
            })
            .always(function () {
                scheduleNext();
            });
    }

    refreshAllLists();
    applyReadonlyState();

    // Initialize sortable if user has edit permissions
    if (canEdit) {
        // Wait for DOM to be fully ready
        $(document).ready(function() {
            initSortable();
            console.log('Ramos Routes Board: Sortable initialized, canEdit =', canEdit);
        });
    } else {
        console.log('Ramos Routes Board: Sortable disabled, canEdit =', canEdit);
    }

    scheduleNext();
})(jQuery);
</script>
