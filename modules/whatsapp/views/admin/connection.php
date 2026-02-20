<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<div id="wrapper">
  <div class="content">
<div class="mx-auto bg-white shadow-lg rounded-lg p-6 space-y-6 border border-gray-100">
  <h2 class="text-3xl font-bold flex items-center text-gray-800 mb-2">
    <i class="fa-brands fa-whatsapp mr-2 text-green-500"></i> <?php echo _l('whatsapp_cloud_title'); ?>
  </h2>
  <p class="text-gray-500 text-sm"><?php echo _l('whatsapp_cloud_description'); ?></p>

  <!-- Status & Badges -->
  <div class="flex flex-wrap items-center gap-3">
    <span class="px-3 py-1 text-xs rounded-full font-medium
      <?php echo get_option('whatsapp_connection_status') == 'connected' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
      🌐 <?php echo _l('whatsapp_cloud_status'); ?>: <?php echo strtoupper(get_option('whatsapp_connection_status', 'disconnected')); ?>
    </span>
    <div id="token_info_badges" class="flex flex-wrap items-center gap-2 text-xs"></div>
  </div>

  <!-- Credentials -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
      <label class="block text-sm font-semibold text-gray-700">🏢 <?php echo _l('whatsapp_business_id'); ?></label>
      <input id="business_id" type="password" value="<?php echo html_escape(get_option('whatsapp_business_id')); ?>"
             class="mt-1 block w-full border rounded-md p-2 focus:ring focus:ring-blue-200 focus:border-blue-500" placeholder="<?php echo _l('whatsapp_business_id_placeholder'); ?>">
    </div>
    <div>
      <label class="block text-sm font-semibold text-gray-700">🔐 <?php echo _l('whatsapp_access_token'); ?></label>
      <input id="api_key" type="password" value="<?php echo html_escape(get_option('whatsapp_access_token')); ?>"
             class="mt-1 block w-full border rounded-md p-2 focus:ring focus:ring-blue-200 focus:border-blue-500" placeholder="<?php echo _l('whatsapp_access_token_placeholder'); ?>">
    </div>
  </div>

  <!-- Buttons -->
  <div class="flex flex-wrap gap-3 mt-4">
    <?php $isConnected = get_option('whatsapp_connection_status') === 'connected'; ?>
    <button id="connect_btn"
            onclick="connectWhatsappCloudApi()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md shadow inline-flex items-center transition disabled:opacity-50 disabled:cursor-not-allowed"
            <?php echo $isConnected ? 'disabled' : ''; ?>>
      <i class="fa fa-link mr-2"></i> <?php echo _l('whatsapp_connect_now'); ?>
    </button>
    <button onclick="disconnectConnection()"
            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-md shadow inline-flex items-center transition">
      <i class="fa fa-unlink mr-2"></i> <?php echo _l('whatsapp_disconnect'); ?>
    </button>
  </div>

  <!-- Permissions -->
  <div id="permission_checklist" class="text-sm text-gray-700 space-y-1 mt-4"></div>

  <!-- Output -->
  <div id="output_area" class="hidden space-y-6">
    <div>
      <h3 class="text-lg font-semibold mb-2 text-green-700 flex items-center">
        📞 <?php echo _l('whatsapp_phone_numbers'); ?>
      </h3>
      <div id="phone_cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"></div>
    </div>

    <div>
      <h3 class="text-lg font-semibold mb-2 text-yellow-600 flex items-center">
        🌐 <?php echo _l('whatsapp_webhook_title'); ?>
      </h3>
      <div id="webhook_status_area">
        <pre id="webhook_output" class="bg-yellow-50 border rounded p-3 text-sm hidden"></pre>
      </div>

      <div id="webhook_form" class="bg-gray-50 p-4 rounded border mt-4 space-y-3 hidden">
        <label class="block text-sm font-medium"><?php echo _l('whatsapp_callback_url'); ?></label>
        <input type="text" id="webhook_url" class="mt-1 w-full border rounded p-2" value="<?php echo base_url('whatsapp/webhook/getdata'); ?>">

        <label class="block text-sm font-medium"><?php echo _l('whatsapp_verify_token'); ?></label>
        <div class="flex items-center gap-2 mt-1">
          <input type="text" id="verify_token" class="flex-1 border rounded p-2"
                 value="<?php echo html_escape(get_option('whatsapp_webhook_token')); ?>" placeholder="<?php echo _l('whatsapp_verify_token_placeholder'); ?>">
          <button onclick="generateRandomToken()" type="button" class="bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded text-sm">
            🎲 <?php echo _l('generate_token'); ?>
          </button>
        </div>

        <button onclick="registerWebhook()" type="button"
                class="mt-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition">
          ✅ <?php echo _l('whatsapp_register_webhook'); ?>
        </button>
      </div>

      <!-- Webhook Actions -->
      <div class="flex flex-wrap gap-3 mt-4">
        <button onclick="unregisterWebhook()" class="bg-red-600 text-white px-4 py-2 rounded shadow transition hover:bg-red-700">
          ❌ <?php echo _l('whatsapp_unregister_webhook'); ?>
        </button>
        <button onclick="toggleDebugLog()" class="bg-gray-700 text-white px-4 py-2 rounded shadow transition hover:bg-gray-800">
          🔍 <?php echo _l('whatsapp_toggle_debug'); ?>
        </button>
      </div>
    </div>

    <div>
      <h3 class="text-lg font-semibold mb-2 flex items-center text-gray-700">
        <i class="fa fa-code mr-2"></i> <?php echo _l('whatsapp_debug_json'); ?>
      </h3>
      <pre id="raw_json_box" class="bg-gray-100 p-3 rounded text-sm overflow-auto max-h-96 hidden"></pre>
    </div>
  </div>
</div>
  </div>
</div>
<?php init_tail(); ?>

<script>
$(function () {
  const WhatsappCloud = {
    scopesRequired: [
      'pages_show_list',
      'business_management',
      'pages_messaging',
      'whatsapp_business_management',
      'whatsapp_business_messaging',
      'whatsapp_business_manage_events'
    ],

    init: function () {
      this.token = $('#api_key').val().trim();
      this.businessId = $('#business_id').val().trim();
      this.isConnected = "<?php echo get_option('whatsapp_connection_status') === 'connected' ? 'yes' : 'no'; ?>";

      if (this.isConnected === 'yes' && this.token && this.businessId) {
        $('#connect_btn').addClass('hidden');
        this.connect();
      }
    },

    connect: function () {
      const token = $('#api_key').val().trim();
      const businessId = $('#business_id').val().trim();

      if (!token || !businessId) {
        alert_float('danger', 'Please enter both Access Token and Business ID.');
        return;
      }

      $('#output_area, #webhook_form, #webhook_output').addClass('hidden');

      $.post(admin_url + 'whatsapp/connection_status', {
        token: token,
        business_id: businessId
      }, (res) => {
        const result = typeof res === 'string' ? JSON.parse(res) : res;

        if (!result.success) {
          alert_float('danger', result.message || 'Connection failed.');
          return;
        }

        alert_float('success', result.message || 'Connected successfully.');
        this.renderScopes(result.permissions);
        this.renderPhones(result.phones);
        this.renderWebhook(result.webhook);
        this.renderTokenMeta(result.permissions);

        $('#raw_json_box').text(JSON.stringify(result, null, 2)).addClass('hidden');
        $('#output_area').removeClass('hidden');
        $('#connect_btn').addClass('hidden');

        window._CURRENT_WABA_ID = result.waba_id;
      }).fail((xhr) => {
        alert_float('danger', 'Connection failed: ' + xhr.responseText);
      });
    },

    renderScopes: function (permissions) {
      const scopes = permissions?.data?.scopes || [];
      const html = this.scopesRequired.map(scope =>
        `<li>${scopes.includes(scope) ? '✅' : '❌'} ${scope}</li>`).join('');
      $('#permission_checklist').html(`<strong>Permissions Check:</strong><ul class="ml-4 list-disc">${html}</ul>`);
    },

    renderPhones: function (phones) {
      const phoneHTML = (phones?.data || []).map(p => `
        <div class="p-4 bg-white shadow rounded border">
          <h4 class="font-bold text-lg">${p.display_phone_number}</h4>
          <p class="text-sm text-gray-600">Status: ${p.code_verification_status}</p>
          <p class="text-sm text-gray-600">Quality: ${p.quality_rating}</p>
          <p class="text-sm text-gray-600">Tier: ${p.throughput.level}</p>
        </div>
      `).join('');
      $('#phone_cards').html(phoneHTML || '<div>No phone numbers found.</div>');
    },

    renderWebhook: function (webhook) {
      const info = webhook?.data?.[0];
      const html = info
        ? `<p class="text-green-600 font-semibold">✅ Webhook is registered</p>`
        : `<p class="text-red-500 font-semibold">❌ Webhook not registered</p>`;
      $('#webhook_output').html(html).removeClass('hidden');
      if (!info) $('#webhook_form').removeClass('hidden');
    },

    registerWebhook: function () {
      const token = $('#api_key').val().trim();
      const wabaId = window._CURRENT_WABA_ID;
      const callbackUrl = $('#webhook_url').val().trim();
      const verifyToken = $('#verify_token').val().trim();

      if (!callbackUrl || !verifyToken) {
        return alert_float('warning', 'Webhook URL and Verify Token are required.');
      }

      $.post(admin_url + 'whatsapp/register_webhook', {
        token: token,
        waba_id: wabaId,
        callback_url: callbackUrl,
        verify_token: verifyToken
      }, () => {
        alert_float('success', 'Webhook registered successfully.');
        this.connect();
      }).fail((xhr) => {
        alert_float('danger', 'Webhook registration failed: ' + xhr.responseText);
      });
    },

    unregisterWebhook: function () {
      const token = $('#api_key').val().trim();
      const wabaId = window._CURRENT_WABA_ID;

      if (!wabaId) {
        return alert_float('danger', 'WABA ID missing. Please connect first.');
      }

      $.post(admin_url + 'whatsapp/unregister_webhook', {
        token: token,
        waba_id: wabaId
      }, () => {
        alert_float('success', 'Webhook unregistered.');
        this.connect();
      }).fail((xhr) => {
        alert_float('danger', 'Failed to unregister webhook: ' + xhr.responseText);
      });
    },
renderTokenMeta: function (permissions) {
  const data = permissions?.data || {};
  let html = '';

  // 📱 App Name
  if (data.application) {
    html += `<span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full inline-flex items-center gap-1">
      📱 <strong>${data.application}</strong>
    </span>`;
  }

  // 🔒 Token Validity
  if (typeof data.is_valid !== 'undefined') {
    const isValid = data.is_valid;
    const color = isValid ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
    const emoji = isValid ? '✅' : '❌';
    html += `<span class="${color} px-3 py-1 rounded-full inline-flex items-center gap-1">
      ${emoji} Token: ${isValid ? 'Valid' : 'Invalid'}
    </span>`;
  }

  // ⏳ Expiry Date
  if (data.expires_at && data.expires_at !== 0) {
    const exp = new Date(data.expires_at * 1000);
    const now = new Date();
    const diff = Math.ceil((exp - now) / (1000 * 60 * 60 * 24));
    const danger = diff < 5;
    html += `<span class="${danger ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-800'} px-3 py-1 rounded-full inline-flex items-center gap-1">
      ⏰ Expires in: ${diff} day${diff !== 1 ? 's' : ''}
    </span>`;
  }

  // 📆 Data Access Expiry
  if (data.data_access_expires_at && data.data_access_expires_at !== 0) {
    const accessExp = new Date(data.data_access_expires_at * 1000);
    const now = new Date();
    const diffAccess = Math.ceil((accessExp - now) / (1000 * 60 * 60 * 24));
    const warn = diffAccess < 5;
    html += `<span class="${warn ? 'bg-orange-100 text-orange-700' : 'bg-indigo-100 text-indigo-800'} px-3 py-1 rounded-full inline-flex items-center gap-1">
      📆 Access in: ${diffAccess} day${diffAccess !== 1 ? 's' : ''}
    </span>`;
  }

  $('#token_info_badges').html(html || '<span class="text-gray-400">ℹ️ No token info available</span>');
},
    disconnect: function () {
      if (!confirm('Are you sure you want to disconnect WhatsApp Cloud API?')) return;

      $.post(admin_url + 'whatsapp/disconnect', {}, () => {
        alert_float('success', 'Disconnected successfully.');
        location.reload();
      }).fail((xhr) => {
        alert_float('danger', 'Disconnect failed: ' + xhr.responseText);
      });
    },

    generateRandomToken: function () {
      const token = 'WHTKN_' + Math.random().toString(36).substring(2, 10).toUpperCase();
      $('#verify_token').val(token);
    },

    toggleDebug: function () {
      $('#raw_json_box').toggleClass('hidden');
    }
  };

  // Bind global functions to buttons
  window.connectWhatsappCloudApi = () => WhatsappCloud.connect();
  window.disconnectConnection = () => WhatsappCloud.disconnect();
  window.registerWebhook = () => WhatsappCloud.registerWebhook();
  window.unregisterWebhook = () => WhatsappCloud.unregisterWebhook();
  window.generateRandomToken = () => WhatsappCloud.generateRandomToken();
  window.toggleDebugLog = () => WhatsappCloud.toggleDebug();

  // Auto-init
  WhatsappCloud.init();
});
</script>
