<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?php echo _l('contacts'); ?>
                        </h4>
                        <hr class="hr-panel-heading" />

                        <?php if (!empty($contacts)) { ?>
                            <div class="table-responsive">
                                <table class="table table-bordered dt-table table-auto w-full">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Type ID</th>
                                            <th>WhatsApp Number</th>
                                            <th>Unread</th>
                                            <th>Lead Status Name</th>
                                            <th>Assigned Staff ID</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($contacts as $contact) { ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($contact['id'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($contact['name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($contact['type'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($contact['type_id'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($contact['receiver_id'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($contact['unread'] ?? '0'); ?></td>
                                                <td><?php echo htmlspecialchars($contact['lead_status_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($contact['assigned_staff_id'] ?? 'N/A'); ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <p><?php echo _l('No verification data available'); ?></p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
