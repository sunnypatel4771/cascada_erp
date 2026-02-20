<?php defined('BASEPATH') || exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tw-flex tw-justify-between tw-items-center">
                            <h4 class="tw-my-0 tw-font-semibold">
                                <?php echo _l('bots'); ?>
                            </h4>
                            <div>
                                <?php if (staff_can('create', 'whatsapp_bot')) { ?>
                                    <a href="<?php echo admin_url('whatsapp/bots/form'); ?>" class="btn btn-primary">
                                        <?php echo _l('create_bot'); ?>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                        <hr class="hr-panel-separator">
                        <div class="panel-table-full">
                            <?php render_datatable([
                                _l('name'),
                                _l('bot_type'),
                                _l('trigger_on'),
                                _l('relation_type'),
                                _l('reply_type'),
                                _l('active'),
                                _l('sending_count'),
                                _l('actions'),
                            ], 'bots_table'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>

<script>
    "use strict";

    // Initialize the DataTable
    initDataTable('.table-bots_table', admin_url + 'whatsapp/bots/table/bots_table');

    // Handle the delete action
    $(document).on('click', '[id^=delete_]', function(event) {
        event.preventDefault();
        const botId = $(this).data('id');

        if (confirm("<?php echo _l('confirm_delete_bot'); ?>")) {
            $.ajax({
                url: admin_url + 'whatsapp/bots/delete/' + botId,
                type: 'POST',
                data: {
                    csrf_token_name: csrfData['csrf_token_name'] // CSRF token
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        alert("<?php echo _l('bot_deleted_successfully'); ?>");
                        $('.table-bots_table').DataTable().ajax.reload(); // Reload the table
                    } else {
                        alert(response.message || "<?php echo _l('bot_delete_failed'); ?>");
                    }
                },
                error: function(xhr, status, error) {
                    console.error(`Error: ${status} - ${error}`);
                    console.log('Response Text:', xhr.responseText);
                    alert("<?php echo _l('error_occurred'); ?>");
                }
            });
        }
    });
</script>
