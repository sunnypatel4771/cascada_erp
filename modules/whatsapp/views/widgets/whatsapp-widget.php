<?php

// Fetch statistics from the database
$total_templates = total_rows(db_prefix() . 'whatsapp_templates');
$total_approved_template = total_rows(db_prefix() . 'whatsapp_templates', ['status' => 'APPROVED']);
$total_leads = total_rows(db_prefix() . 'leads');
$current_month_leads = total_rows(db_prefix() . 'leads', ['MONTH(dateadded)' => date('m')]);
$message_bots = total_rows(db_prefix() . 'whatsapp_bot');
$template_bots = total_rows(db_prefix() . 'whatsapp_campaigns');
$total_bots = $message_bots + $template_bots;
$total_contacts = total_rows(db_prefix() . 'contacts');
$current_month_contacts = total_rows(db_prefix() . 'contacts', ['MONTH(datecreated)' => date('m')]);
$total_message_bot_send = sum_from_table(db_prefix() . 'whatsapp_bot', ['field' => 'sending_count']);
$total_template_bot_send = sum_from_table(db_prefix() . 'whatsapp_campaigns', ['field' => 'sending_count']);
$total_bot_send = $total_message_bot_send + $total_template_bot_send;
// Groups and Bulk Contacts
$total_groups = total_rows(db_prefix() . 'whatsapp_groups');
$total_bulk_contacts = total_rows(db_prefix() . 'whatsapp_contact_group');

// Define message send limits
$message_send_limit = 1000; // Example limit for demonstration purposes
$current_send_percentage = ($total_bot_send / $message_send_limit) * 100;
$send_limit_reached = $total_bot_send >= $message_send_limit;

?>

<div class="widget relative mtop10 mbot10" id="widget" data-name="<?php echo _l('whatsbot_stats'); ?>">
    <div class="widget-dragger"></div>
    <div class="row animate__animated animate__fadeIn">

        <!-- Templates Statistics -->
        <div class="col-lg-2 col-md-6">
            <div class="panel panel-default rounded-lg shadow-lg no-margin transform transition duration-500 hover:scale-105">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-8">
                            <span class="text-muted tw-font-semibold text-uppercase"><?php echo _l('templates'); ?></span>
                            <h4 class="tw-mt-2 tw-mb-0 tw-font-semibold tw-truncate"><?php echo $total_templates; ?></h4>
                            <p class="tw-mt-2 tw-mb-0">
                                <span class="tw-font-semibold"><?php echo $total_approved_template; ?></span>
                                <?php echo _l('approved'); ?>
                            </p>
                        </div>
                        <span class="circle numbertext circle_warning">
                            <i class="fa-solid fa-scroll fa-xl"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leads Statistics -->
        <div class="col-lg-2 col-md-6">
            <div class="panel panel-default rounded-lg shadow-lg no-margin transform transition duration-500 hover:scale-105">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-8">
                            <span class="text-muted tw-font-semibold text-uppercase"><?php echo _l('leads'); ?></span>
                            <h4 class="tw-mt-2 tw-mb-0 tw-font-semibold tw-truncate"><?php echo $total_leads; ?></h4>
                            <p class="tw-mt-2 tw-mb-0">
                                <span class="tw-font-semibold"><?php echo $current_month_leads; ?></span>
                                <?php echo ' ' . _l('this_month'); ?>
                            </p>
                        </div>
                        <span class="circle numbertext circle_info">
                            <i class="fa-solid fa-user fa-xl"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contacts Statistics -->
        <div class="col-lg-2 col-md-6">
            <div class="panel panel-default rounded-lg shadow-lg no-margin transform transition duration-500 hover:scale-105">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-8">
                            <span class="text-muted tw-font-semibold text-uppercase"><?php echo _l('contacts'); ?></span>
                            <h4 class="tw-mt-2 tw-mb-0 tw-font-semibold tw-truncate"><?php echo $total_contacts; ?></h4>
                            <p class="tw-mt-2 tw-mb-0">
                                <span class="tw-font-semibold"><?php echo $current_month_contacts; ?></span>
                                <?php echo ' ' . _l('this_month'); ?>
                            </p>
                        </div>
                        <span class="circle numbertext circle_success">
                            <i class="fa-solid fa-check fa-xl"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bots Statistics -->
        <div class="col-lg-2 col-md-6">
            <div class="panel panel-default rounded-lg shadow-lg no-margin transform transition duration-500 hover:scale-105">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-8">
                            <span class="text-muted tw-font-semibold text-uppercase"><?php echo _l('bots'); ?></span>
                            <h4 class="tw-mt-2 tw-mb-0 tw-font-semibold tw-truncate"><?php echo $total_bots; ?></h4>
                            <p class="tw-mt-2 tw-mb-0">
                                <span class="tw-font-semibold"><?php echo $total_bot_send; ?></span>
                                <?php echo _l('messages_sent'); ?>
                            </p>
                        </div>
                        <span class="circle numbertext circle_default">
                            <i class="fa-solid fa-comment fa-xl"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usage Limit Statistics -->
        <div class="col-lg-2 col-md-6">
            <div class="panel panel-default rounded-lg shadow-lg no-margin transform transition duration-500 hover:scale-105">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-8">
                            <span class="text-muted tw-font-semibold text-uppercase"><?php echo _l('usage_limit'); ?></span>
                            <h4 class="tw-mt-2 tw-mb-0 tw-font-semibold tw-truncate"><?php echo $message_send_limit; ?></h4>
                            <p class="tw-mt-2 tw-mb-0 <?php echo $send_limit_reached ? 'text-danger' : 'text-muted'; ?>">
                                <?php echo $send_limit_reached ? _l('Limit reached!') : _l('Overall Usage: ') . round($current_send_percentage, 2) . '%'; ?>
                            </p>
                        </div>
                        <span class="circle numbertext circle_danger">
                            <i class="fa-solid fa-chart-line fa-xl"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
          <!-- Groups and Bulk Contacts Statistics -->
        <div class="col-lg-2 col-md-6">
            <div class="panel panel-default rounded-lg shadow-lg no-margin transform transition duration-500 hover:scale-105">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-8">
                            <span class="text-muted tw-font-semibold text-uppercase"><?php echo _l('bulk_contacts_groups'); ?></span>
                            <h4 class="tw-mt-2 tw-mb-0 tw-font-semibold tw-truncate"><?php echo $total_bulk_contacts; ?></h4>
                            <p class="tw-mt-2 tw-mb-0">
                                <span class="tw-font-semibold"><?php echo $total_groups; ?></span>
                                <?php echo _l('groups'); ?>
                            </p>
                        </div>
                        <span class="circle numbertext circle_primary">
                            <i class="fa-solid fa-users fa-xl"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
