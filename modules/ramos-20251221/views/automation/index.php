<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-col md:tw-flex-row tw-justify-between tw-items-start md:tw-items-center tw-gap-4 tw-mb-4">
                    <div>
                        <h4 class="tw-text-2xl tw-font-semibold tw-text-slate-900 tw-mb-1">
                            <?php echo html_escape($title); ?>
                        </h4>
                        <p class="tw-text-slate-500 tw-mb-0">
                            <?php echo html_escape($subtitle); ?>
                        </p>
                    </div>
                    <div class="tw-flex tw-gap-2">
                        <?php if (staff_can('create', RAMOS_MODULE_NAME)) : ?>
                            <button class="btn btn-info" id="ramos-analyze-button">
                                <i class="fa-regular fa-magnifying-glass-chart tw-mr-1"></i><?php echo _l('ramos_automation_analyze_button'); ?>
                            </button>
                            <button class="btn btn-primary" id="ramos-automation-trigger">
                                <i class="fa-regular fa-play tw-mr-1"></i><?php echo _l('ramos_automation_trigger_button'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Unprocessed Orders Counter -->
                <div class="panel_s tw-mb-4">
                    <div class="panel-body">
                        <div class="tw-flex tw-items-center tw-justify-between">
                            <div>
                                <h5 class="tw-text-lg tw-font-medium tw-mb-1">
                                    <?php echo _l('ramos_automation_unprocessed_label'); ?>
                                </h5>
                                <p class="tw-text-slate-600 tw-mb-0">
                                    <?php echo _l('ramos_automation_unprocessed_count', '<span id="unprocessed-count" class="tw-font-semibold">' . $unprocessed_count . '</span>'); ?>
                                </p>
                            </div>
                            <div class="tw-text-5xl tw-text-primary">
                                <i class="fa-regular fa-box-open"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analysis Preview Section -->
                <div class="panel_s tw-mb-4" id="analysis-panel" style="display: none;">
                    <div class="panel-body">
                        <div class="tw-flex tw-justify-between tw-items-center tw-mb-3">
                            <h5 class="tw-text-lg tw-font-medium tw-mb-0">
                                <?php echo _l('ramos_automation_analysis_heading'); ?>
                            </h5>
                            <button class="btn btn-default btn-sm" id="copy-analysis-button">
                                <i class="fa-regular fa-copy tw-mr-1"></i><?php echo _l('ramos_automation_copy_button'); ?>
                            </button>
                        </div>
                        <div id="analysis-content" class="table-responsive"></div>
                    </div>
                </div>

                <!-- Recent Automation Runs -->
                <div class="panel_s">
                    <div class="panel-body">
                        <h5 class="tw-text-lg tw-font-medium tw-mb-3">
                            <?php echo _l('ramos_automation_recent_runs'); ?>
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="automation-runs-table">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('ramos_automation_run_type'); ?></th>
                                        <th><?php echo _l('ramos_automation_run_by'); ?></th>
                                        <th><?php echo _l('ramos_automation_run_at'); ?></th>
                                        <th><?php echo _l('ramos_automation_orders_processed'); ?></th>
                                        <th><?php echo _l('ramos_automation_pos_created'); ?></th>
                                        <th><?php echo _l('ramos_automation_run_status'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_runs)) : ?>
                                        <tr>
                                            <td colspan="6" class="text-center tw-text-slate-500">
                                                <?php echo _l('ramos_automation_no_runs'); ?>
                                            </td>
                                        </tr>
                                    <?php else : ?>
                                        <?php foreach ($recent_runs as $run) : ?>
                                            <tr>
                                                <td><?php echo html_escape(ucfirst(str_replace('_', ' ', $run['run_type']))); ?></td>
                                                <td><?php echo html_escape($run['staff_name'] ?? 'System'); ?></td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($run['run_at'])); ?></td>
                                                <td><?php echo (int) $run['total_orders_processed']; ?></td>
                                                <td><?php echo (int) $run['total_purchase_orders_created']; ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = $run['status'] === 'completed' ? 'label-success' : ($run['status'] === 'failed' ? 'label-danger' : 'label-info');
                                                    ?>
                                                    <span class="label <?php echo $statusClass; ?>">
                                                        <?php echo _l('ramos_automation_' . $run['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
    (function() {
        "use strict";

        var baseUrl = <?php echo json_encode(admin_url()); ?>;
        var $triggerBtn = $('#ramos-automation-trigger');
        var $analyzeBtn = $('#ramos-analyze-button');
        var $analysisPanel = $('#analysis-panel');
        var $analysisContent = $('#analysis-content');
        var $copyBtn = $('#copy-analysis-button');
        var $unprocessedCount = $('#unprocessed-count');

        // Run automation
        $triggerBtn.on('click', function() {
            if (!confirm(<?php echo json_encode(_l('ramos_automation_confirm_run')); ?>)) {
                return;
            }

            $triggerBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin tw-mr-1"></i><?php echo _l('ramos_automation_running'); ?>');

            $.ajax({
                url: baseUrl + 'ramos/automation/run',
                type: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert_float('success', <?php echo json_encode(_l('ramos_automation_success_message')); ?>
                            .replace('%s', response.orders_processed)
                            .replace('%s', response.purchase_orders_created));

                        // Refresh the page to show updated data
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        alert_float('danger', response.message || <?php echo json_encode(_l('ramos_automation_error')); ?>);
                        $triggerBtn.prop('disabled', false).html('<i class="fa-regular fa-play tw-mr-1"></i><?php echo _l('ramos_automation_trigger_button'); ?>');
                    }
                },
                error: function() {
                    alert_float('danger', <?php echo json_encode(_l('ramos_automation_error')); ?>);
                    $triggerBtn.prop('disabled', false).html('<i class="fa-regular fa-play tw-mr-1"></i><?php echo _l('ramos_automation_trigger_button'); ?>');
                }
            });
        });

        // Preview analysis
        $analyzeBtn.on('click', function() {
            $analyzeBtn.prop('disabled', true);

            $.ajax({
                url: baseUrl + 'ramos/automation/analyze',
                type: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        renderAnalysis(response.analysis);
                        $analysisPanel.slideDown();
                    } else {
                        alert_float('warning', response.message || <?php echo json_encode(_l('ramos_automation_no_orders')); ?>);
                    }
                    $analyzeBtn.prop('disabled', false);
                },
                error: function() {
                    alert_float('danger', <?php echo json_encode(_l('ramos_automation_error')); ?>);
                    $analyzeBtn.prop('disabled', false);
                }
            });
        });

        // Render analysis table
        function renderAnalysis(analysis) {
            if (!analysis || analysis.length === 0) {
                $analysisContent.html('<p class="text-center tw-text-slate-500 tw-py-4"><?php echo _l('ramos_automation_no_orders'); ?></p>');
                return;
            }

            var html = '<table class="table table-bordered">';
            html += '<thead><tr>';
            html += '<th><?php echo _l('ramos_automation_analysis_table_supplier'); ?></th>';
            html += '<th><?php echo _l('ramos_automation_analysis_table_item'); ?></th>';
            html += '<th><?php echo _l('ramos_automation_analysis_table_required'); ?></th>';
            html += '<th><?php echo _l('ramos_automation_analysis_table_on_hand'); ?></th>';
            html += '<th><?php echo _l('ramos_automation_analysis_table_to_buy'); ?></th>';
            html += '</tr></thead><tbody>';

            analysis.forEach(function(group) {
                var firstRow = true;
                group.items.forEach(function(item) {
                    html += '<tr>';
                    if (firstRow) {
                        html += '<td rowspan="' + group.items.length + '"><strong>' + escapeHtml(group.supplier_name) + '</strong></td>';
                        firstRow = false;
                    }
                    html += '<td>' + escapeHtml(item.item_name) + '</td>';
                    html += '<td>' + parseFloat(item.required).toFixed(2) + '</td>';
                    html += '<td>' + parseFloat(item.on_hand).toFixed(2) + '</td>';
                    html += '<td><strong>' + parseFloat(item.to_buy).toFixed(2) + '</strong></td>';
                    html += '</tr>';
                });
            });

            html += '</tbody></table>';
            $analysisContent.html(html);
        }

        // Copy analysis to clipboard
        $copyBtn.on('click', function() {
            var text = buildCopyText();

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    alert_float('success', <?php echo json_encode(_l('ramos_automation_copied_success')); ?>);
                }).catch(function() {
                    fallbackCopy(text);
                });
            } else {
                fallbackCopy(text);
            }
        });

        function buildCopyText() {
            var text = '';
            var $table = $analysisContent.find('table');

            $table.find('tbody tr').each(function() {
                var $cells = $(this).find('td');
                var line = [];

                $cells.each(function() {
                    line.push($(this).text().trim());
                });

                text += line.join(' | ') + '\n';
            });

            return text;
        }

        function fallbackCopy(text) {
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();

            try {
                document.execCommand('copy');
                alert_float('success', <?php echo json_encode(_l('ramos_automation_copied_success')); ?>);
            } catch (err) {
                alert_float('danger', 'Unable to copy to clipboard.');
            }

            $temp.remove();
        }

        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    })();
</script>
