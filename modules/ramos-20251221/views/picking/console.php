<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$statusLabels = [
    'red'    => 'label-danger',
    'yellow' => 'label-warning',
    'green'  => 'label-success',
];
$canEditConsole = $can_edit ?? (staff_can('edit', RAMOS_MODULE_NAME) || is_admin());
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
                    <div>
                        <a href="<?php echo admin_url('ramos/picking'); ?>" class="btn btn-default">
                            <i class="fa-regular fa-gear tw-mr-1"></i><?php echo _l('settings'); ?>
                        </a>
                    </div>
                </div>

                <?php if (!$has_modules) : ?>
                    <div class="alert alert-info tw-text-sm"><?php echo _l('ramos_picking_console_no_access'); ?></div>
                <?php endif; ?>

                <div class="row" id="ramos-console-modules">
                    <?php foreach ($module_data as $entry) : ?>
                        <?php
                        $this->load->view('ramos/picking/partials/module_card', [
                            'module'       => $entry['module'],
                            'orders'       => $entry['orders'],
                            'statusLabels' => $statusLabels,
                            'can_edit'     => $canEditConsole,
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

    var refreshInterval = 20000;
    var timerId = null;

    function renderModules(html) {
        var $container = $('#ramos-console-modules');
        if (!$container.length) {
            return;
        }
        $container.html(html);
    }

    function scheduleNext() {
        timerId = setTimeout(fetchUpdates, refreshInterval);
    }

    function fetchUpdates() {
        $.get(admin_url + 'ramos/picking/console_refresh')
            .done(function(response) {
                if (response && response.success && response.html) {
                    renderModules(response.html);
                }
            })
            .always(function() {
                scheduleNext();
            });
    }

    scheduleNext();
})(jQuery);
</script>
