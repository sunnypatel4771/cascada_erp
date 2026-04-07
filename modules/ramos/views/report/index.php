<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                <div class="tw-flex tw-flex-col md:tw-flex-row tw-justify-between tw-items-start md:tw-items-center tw-gap-4 tw-mb-4">
                    <div>
                        <h4 class="tw-text-2xl tw-font-semibold tw-text-slate-900 tw-mb-1"><?php echo _l('ramos_report_title'); ?></h4>
                        <p class="tw-text-slate-500 tw-mb-0"><?php echo _l('ramos_report_subtitle'); ?></p>
                    </div>
                    <div>
                        <?php
                        $exportParams = http_build_query([
                            'date_from' => $date_from,
                            'date_to'   => $date_to,
                            'module_id' => $module_id,
                            'staff_id'  => $staff_id,
                        ]);
                        ?>
                        <a href="<?php echo admin_url('ramos/report/export?' . $exportParams); ?>" class="btn btn-default">
                            <i class="fa-regular fa-file-csv tw-mr-1"></i><?php echo _l('ramos_report_export_csv'); ?>
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="panel_s tw-mb-4">
                    <div class="panel-body">
                        <?php echo form_open(admin_url('ramos/report'), ['method' => 'get', 'class' => 'tw-flex tw-flex-wrap tw-gap-3 tw-items-end']); ?>
                            <div>
                                <label class="control-label"><?php echo _l('ramos_report_filter_date_from'); ?></label>
                                <input type="date" name="date_from" value="<?php echo html_escape($date_from); ?>" class="form-control" style="min-width:150px;">
                            </div>
                            <div>
                                <label class="control-label"><?php echo _l('ramos_report_filter_date_to'); ?></label>
                                <input type="date" name="date_to" value="<?php echo html_escape($date_to); ?>" class="form-control" style="min-width:150px;">
                            </div>
                            <div>
                                <label class="control-label"><?php echo _l('ramos_report_filter_module'); ?></label>
                                <select name="module_id" class="form-control selectpicker" data-live-search="true" style="min-width:170px;">
                                    <option value="0"><?php echo _l('ramos_report_filter_all'); ?></option>
                                    <?php foreach ($modules as $mod) : ?>
                                        <option value="<?php echo (int) $mod['id']; ?>" <?php echo (int) $module_id === (int) $mod['id'] ? 'selected' : ''; ?>>
                                            <?php echo html_escape($mod['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="control-label"><?php echo _l('ramos_report_filter_staff'); ?></label>
                                <select name="staff_id" class="form-control selectpicker" data-live-search="true" style="min-width:170px;">
                                    <option value="0"><?php echo _l('ramos_report_filter_all'); ?></option>
                                    <?php foreach ($staff as $member) : ?>
                                        <option value="<?php echo (int) $member['staffid']; ?>" <?php echo (int) $staff_id === (int) $member['staffid'] ? 'selected' : ''; ?>>
                                            <?php echo html_escape($member['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary"><?php echo _l('search'); ?></button>
                                <a href="<?php echo admin_url('ramos/report'); ?>" class="btn btn-default"><?php echo _l('reset'); ?></a>
                            </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>

                <!-- Results -->
                <div class="panel_s">
                    <div class="panel-body">
                        <?php if (empty($records)) : ?>
                            <p class="tw-text-sm tw-text-slate-500 tw-mb-0"><?php echo _l('ramos_report_no_records'); ?></p>
                        <?php else : ?>
                            <p class="tw-text-xs tw-text-slate-400 tw-mb-3">
                                <?php echo _l('ramos_report_results_count', count($records)); ?>
                                &nbsp;&mdash;&nbsp;<?php echo _l('ramos_report_duration_note'); ?>
                            </p>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-striped" id="ramos-report-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo _l('ramos_report_col_date'); ?></th>
                                            <th><?php echo _l('ramos_report_col_module'); ?></th>
                                            <th><?php echo _l('ramos_report_col_staff'); ?></th>
                                            <th><?php echo _l('ramos_report_col_role'); ?></th>
                                            <th><?php echo _l('ramos_report_col_shift_start'); ?></th>
                                            <th><?php echo _l('ramos_report_col_shift_end'); ?></th>
                                            <th><?php echo _l('ramos_report_col_duration'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($records as $row) : ?>
                                            <tr>
                                                <td><?php echo html_escape(_d($row['shift_date'])); ?></td>
                                                <td><?php echo html_escape($row['module_name'] ?? '—'); ?></td>
                                                <td><?php echo html_escape($row['staff_name'] ?? '—'); ?></td>
                                                <td>
                                                    <span class="label <?php echo $row['role'] === 'supervisor' ? 'label-info' : 'label-default'; ?>">
                                                        <?php echo html_escape(ucfirst($row['role'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo html_escape($row['shift_started_at'] ? _dt($row['shift_started_at']) : '—'); ?></td>
                                                <td><?php echo html_escape($row['shift_ended_at'] ? _dt($row['shift_ended_at']) : '—'); ?></td>
                                                <td class="tw-font-mono tw-text-sm"><?php echo html_escape($row['duration']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
$(function() {
    // Pure client-side DataTables — rows are already in the HTML (server-rendered).
    // IMPORTANT: Do NOT use initDataTable() here — that helper is server-side AJAX only.
    // It POSTs to the given URL expecting DataTables JSON; getting HTML back leaves the table blank.
    //
    // Perfex sets the global wrapper class to "… table-loading" via:
    //   $.fn.dataTableExt.oStdClasses.sWrapper = "… table-loading"
    // This hides content with a skeleton CSS until the "table-loading" class is removed.
    // The initDataTable() helper removes it in its own initComplete callback.
    // We must do the same here for our client-side table.
    if ($.fn.DataTable && $('#ramos-report-table tbody tr').length > 0) {
        $('#ramos-report-table').DataTable({
            serverSide:  false,
            processing:  false,
            order:       [[0, 'desc']],
            pageLength:  25,
            responsive:  false,
            autoWidth:   false,
            initComplete: function() {
                // Mirror what Perfex's initDataTable does: remove skeleton-loader class
                $(this.api().table().container())
                    .closest('.table-loading')
                    .removeClass('table-loading');
                $(this.api().table().node())
                    .removeClass('dt-table-loading');
                mainWrapperHeightFix();
            },
        });
    }
});
</script>
