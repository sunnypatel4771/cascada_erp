<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$statusLabels = [
    'red'    => 'label-danger',
    'yellow' => 'label-warning',
    'green'  => 'label-success',
];
$canEdit = $can_edit ?? (staff_can('edit', RAMOS_MODULE_NAME) || is_admin());
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-col md:tw-flex-row tw-justify-between tw-items-start md:tw-items-center tw-gap-4 tw-mb-4">
                    <div>
                        <h4 class="tw-text-2xl tw-font-semibold tw-text-slate-900 tw-mb-1"><?php echo html_escape($title); ?></h4>
                        <p class="tw-text-slate-500 tw-mb-0"><?php echo html_escape($subtitle); ?></p>
                    </div>
                    <div class="tw-flex tw-gap-2">
                        <form method="get" class="tw-flex tw-gap-2">
                            <input type="date" name="date" class="form-control" value="<?php echo html_escape($selected_date); ?>" onchange="this.form.submit()">
                        </form>
                        <a href="<?php echo admin_url('ramos/picking/console'); ?>" class="btn btn-default">
                            <i class="fa-regular fa-list-check tw-mr-1"></i><?php echo _l('ramos_facturacion_go_to_console'); ?>
                        </a>
                    </div>
                </div>

                <?php if (empty($routes_data)) : ?>
                    <div class="alert alert-info tw-text-sm"><?php echo _l('ramos_facturacion_no_routes'); ?></div>
                <?php endif; ?>

                <div id="ramos-facturacion-routes">
                    <?php foreach ($routes_data as $entry) : ?>
                        <?php
                        $this->load->view('ramos/facturacion/partials/route_card', [
                            'route'        => $entry['route'],
                            'customers'    => $entry['customers'],
                            'statusLabels' => $statusLabels,
                            'can_edit'     => $canEdit,
                        ]);
                        ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
(function($) {
    "use strict";

    var refreshInterval = 30000;
    var timerId = null;

    function scheduleNext() {
        timerId = setTimeout(fetchUpdates, refreshInterval);
    }

    function fetchUpdates() {
        var date = $('input[name="date"]').val();
        $.post(admin_url + 'ramos/facturacion/refresh', { date: date })
            .done(function(response) {
                if (response && response.success && response.html) {
                    $('#ramos-facturacion-routes').html(response.html);
                }
            })
            .always(function() {
                scheduleNext();
            });
    }

    scheduleNext();
})(jQuery);
</script>
