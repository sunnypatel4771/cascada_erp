<?php
$sources  = get_instance()->leads_model->get_source();
$statuses = get_instance()->leads_model->get_status();
$staff    = get_instance()->staff_model->get('', ['active' => 1]);
?>

<div class="">
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" href="#general-settings" role="tab" data-toggle="tab"><?php echo _l('General Settings'); ?></a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#lead-settings" role="tab" data-toggle="tab"><?php echo _l('Lead Settings'); ?></a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#openai-settings" role="tab" data-toggle="tab"><?php echo _l('OpenAI Integration'); ?></a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#advanced-settings" role="tab" data-toggle="tab"><?php echo _l('Advanced Settings'); ?></a>
        </li>
    </ul>

    <div class="tab-content">
<!-- General Settings -->
<div class="tab-pane active" id="general-settings" role="tabpanel">
    <div class="row">
        <!-- WhatsApp Business Account ID -->
        <div class="col-md-4">
            <?php echo render_input(
                'settings[whatsapp_business_id]',
                '📱 WhatsApp Business ID',
                get_option('whatsapp_business_id'),
                'password'
            ); ?>
        </div>
        <!-- WhatsApp Business Account ID -->
        <div class="col-md-4">
            <?php echo render_input(
                'settings[whatsapp_business_account_id]',
                '📱 WhatsApp Business Account ID',
                get_option('whatsapp_business_account_id'),
                'password'
            ); ?>
        </div>
        <!-- Access Token -->
        <div class="col-md-4">
            <?php echo render_input(
                'settings[whatsapp_access_token]',
                '🔑 Access Token',
                get_option('whatsapp_access_token'),
                'password'
            ); ?>
        </div>

        <!-- Connection Method -->
        <div class="col-md-4">
            <div class="form-group">
                <label for="settings[whatsapp_connection_method]">🔗 Connection Method</label>
                <select class="form-control selectpicker" name="settings[whatsapp_connection_method]" data-width="100%">
                    <option value="manual" <?php echo get_option('whatsapp_connection_method') == 'manual' ? 'selected' : ''; ?>>Manual</option>
                </select>
            </div>
        </div>
    </div>

    <br>

    <div class="row">
        <!-- Webhook URL -->
        <div class="col-md-6">
            <div class="form-group">
                <label for="whatsapp_webhook_url">🌐 Webhook URL</label>
                <div class="input-group">
                    <input type="text" id="whatsapp_webhook_url" class="form-control" value="<?php echo base_url('whatsapp/webhook/getdata'); ?>" readonly>
                    <span class="input-group-btn">
                        <button class="btn btn-default" type="button" onclick="copyToClipboard('#whatsapp_webhook_url')">
                            <i class="fa fa-copy"></i>
                        </button>
                    </span>
                </div>
            </div>
        </div>

        <!-- Webhook Token -->
        <div class="col-md-6">
            <?php echo render_input(
                'settings[whatsapp_webhook_token]',
                '🔑 Webhook Token',
                get_option('whatsapp_webhook_token')
            ); ?>
        </div>
    </div>

    <!-- Hidden Config ID -->
    <div class="row" style="display:none;">
        <div class="col-md-4">
            <?php echo render_input(
                'settings[whatsapp_config_id]',
                '🔒 Config ID',
                get_option('whatsapp_config_id'),
                'password'
            ); ?>
        </div>
    </div>
</div>

<!-- Lead Settings -->
<div class="tab-pane" id="lead-settings" role="tabpanel">
    <div class="row">
        <!-- Lead Status -->
        <div class="col-md-4">
            <div class="form-group">
                <label for="settings[whatsapp_lead_status]">📊 Lead Status</label>
                <select class="form-control selectpicker" name="settings[whatsapp_lead_status]" data-width="100%">
                    <?php foreach ($statuses as $status) { ?>
                        <option value="<?php echo $status['id']; ?>" <?php echo (get_option('whatsapp_lead_status') == $status['id']) ? 'selected' : ''; ?>>
                            <?php echo $status['name']; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <!-- Lead Source -->
        <div class="col-md-4">
            <div class="form-group">
                <label for="settings[whatsapp_lead_source]">🌍 Lead Source</label>
                <select class="form-control selectpicker" name="settings[whatsapp_lead_source]" data-width="100%">
                    <?php foreach ($sources as $source) { ?>
                        <option value="<?php echo $source['id']; ?>" <?php echo (get_option('whatsapp_lead_source') == $source['id']) ? 'selected' : ''; ?>>
                            <?php echo $source['name']; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <!-- Assigned Staff -->
        <div class="col-md-4">
            <div class="form-group">
                <label for="settings[whatsapp_lead_assigned]">👨‍💼 Assigned Staff</label>
                <select class="form-control selectpicker" name="settings[whatsapp_lead_assigned]" data-width="100%">
                    <?php foreach ($staff as $staff_member) { ?>
                        <option value="<?php echo $staff_member['staffid']; ?>" <?php echo (get_option('whatsapp_lead_assigned') == $staff_member['staffid']) ? 'selected' : ''; ?>>
                            <?php echo $staff_member['firstname'] . ' ' . $staff_member['lastname']; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
    </div>
</div>




        <!-- OpenAI Integration -->
<div class="tab-pane" id="openai-settings" role="tabpanel">
    <div class="row">
        <!-- Enable OpenAI Integration -->
<div class="col-md-6">
    <div class="form-group">
        <label for="settings[whatsapp_openai_status]">
            <?php echo _l('Enable OpenAI Integration'); ?> 🤖
        </label>
        <select class="form-control selectpicker" name="settings[whatsapp_openai_status]" data-width="100%">
            <option value="disable" <?php echo (get_option('whatsapp_openai_status') == 'disable') ? 'selected' : ''; ?>>
                <?php echo _l('Disable'); ?> ❌
            </option>
            <option value="manual" <?php echo (get_option('whatsapp_openai_status') == 'manual') ? 'selected' : ''; ?>>
                <?php echo _l('Manual'); ?> ⚙️
            </option>
            <option value="auto" <?php echo (get_option('whatsapp_openai_status') == 'auto') ? 'selected' : ''; ?>>
                <?php echo _l('Auto'); ?> 🚀
            </option>
        </select>
    </div>
</div>


        <!-- OpenAI Token -->
<div class="col-md-6">
    <div class="form-group">
        <label for="settings[whatsapp_openai_token]">
            <?php echo _l('OpenAI Token'); ?> 🔑
        </label>
        <?php echo render_input('settings[whatsapp_openai_token]', '', get_option('whatsapp_openai_token'), 'text', ['autocomplete' => 'off']); ?>
    </div>
</div>

    </div>
</div>

<!-- Advanced Settings -->
<div class="tab-pane" id="advanced-settings" role="tabpanel">
    <div class="row">
        <!-- Cron Job Command -->
        <div class="col-md-6">
            <div class="form-group">
                <label><?php echo _l('Cron Job Command'); ?> 🖥️</label>
                <div class="input-group">
                    <input type="text" id="cronjob" value="<?php echo 'wget -q -O- ' . base_url('whatsapp/webhook/send_campaign'); ?>" class="form-control" readonly>
                    <span class="input-group-btn">
                        <button class="btn btn-default" type="button" onclick="copyToClipboard('#cronjob')">
                            <i class="fa fa-copy"></i>
                        </button>
                    </span>
                </div>
            </div>
        </div>

        <!-- Reload Interval -->
        <div class="col-md-3">
            <div class="form-group">
                <label for="settings[whatsapp_reload_interval]"><?php echo _l('Reload Interval (minutes) ⏱️'); ?></label>
                <input type="number" name="settings[whatsapp_reload_interval]" class="form-control" min="1" value="<?php echo get_option('whatsapp_reload_interval') ?: 5; ?>">
            </div>
        </div>

        <!-- Load More Chats Limit -->
        <div class="col-md-3">
            <div class="form-group">
                <label for="settings[whatsapp_load_more_limit]"><?php echo _l('Load More Chats Limit 📈'); ?></label>
                <input type="number" name="settings[whatsapp_load_more_limit]" class="form-control" min="1" value="<?php echo get_option('whatsapp_load_more_limit') ?: 20; ?>">
            </div>
        </div>
    </div>
<br>
    <div class="row">
        <!-- Enable Webhooks Forward -->
        <div class="col-md-4">
            <div class="form-group">
                <label for="enable_webhooks_select"><?php echo _l('Enable Webhooks 🔌'); ?></label>
                <select id="enable_webhooks_select" class="form-control selectpicker" name="settings[enable_webhooks]" data-width="100%">
                    <option value="1" <?php echo ('1' == get_option('enable_webhooks')) ? 'selected' : ''; ?>><?php echo _l('Enable'); ?></option>
                    <option value="0" <?php echo ('0' == get_option('enable_webhooks')) ? 'selected' : ''; ?>><?php echo _l('Disable'); ?></option>
                </select>
            </div>
        </div>

        <!-- Webhooks URL Input -->
        <div class="col-md-4">
            <div class="form-group">
                <label for="settings[webhooks_url]">
                    <i class="fa fa-question-circle padding-5"></i>
                    <?php echo _l('Webhooks Forward URL 🌐'); ?>
                </label>
                <?php echo render_input('settings[webhooks_url]', '', get_option('webhooks_url'), 'text'); ?>
            </div>
        </div>

        <!-- Blueticks Status Dropdown -->
        <div class="col-md-4">
            <div class="form-group">
                <label for="settings[whatsapp_blueticks_status]">
                    <i class="fa fa-question-circle padding-5"></i>
                    <?php echo _l('WhatsApp Blueticks Status ✔️'); ?>
                </label>
                <select class="form-control selectpicker" data-width="100%" name="settings[whatsapp_blueticks_status]">
                    <option value="disable" <?php echo (get_option('whatsapp_blueticks_status') == 'disable') ? 'selected' : ''; ?>><?php echo _l('Disable'); ?></option>
                    <option value="enable" <?php echo (get_option('whatsapp_blueticks_status') == 'enable') ? 'selected' : ''; ?>><?php echo _l('Enable'); ?></option>
                </select>
            </div>
        </div>
    </div>
</div>


    </div>

</div>

<script>
    function copyToClipboard(element) {
        var $temp = $("<input>");
        $("body").append($temp);
        $temp.val($(element).val()).select();
        document.execCommand("copy");
        $temp.remove();
    }
</script>