<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: WhatsApp con  Official Cloud API  
Description: WhatsApp con clientes y proveedores 
Version: 1.0
Autor URI: https://erei.se
*/

define('WHATSAPP_MODULE_NAME', 'whatsapp');
define('WHATSAPP_MODULE', 'whatsapp');

include(__DIR__ . '/vendor/autoload.php');

$CI = &get_instance();

// Register language files
register_language_files(WHATSAPP_MODULE_NAME, [WHATSAPP_MODULE_NAME]);

$viewuri = $_SERVER['REQUEST_URI'];
$CI->load->library(WHATSAPP_MODULE_NAME . '/WhatsappLibrary');
get_instance()->load->helper(WHATSAPP_MODULE_NAME . '/' . WHATSAPP_MODULE_NAME);
// Define constants for upload folders
define('WHATSAPP_MODULE_UPLOAD_FOLDER', 'uploads/' . WHATSAPP_MODULE_NAME);
define('WHATSAPP_MODULE_UPLOAD_URL', 'https://' . $_SERVER['HTTP_HOST'] . '/' . WHATSAPP_MODULE_UPLOAD_FOLDER . '/');

// Create upload directories if they don't exist
// Get the absolute path for the upload folder
$uploadFolderPath = FCPATH . WHATSAPP_MODULE_UPLOAD_FOLDER;

// Create necessary directories
create_directory_if_not_exists($uploadFolderPath);
create_directory_if_not_exists($uploadFolderPath . '/bot_files/');
create_directory_if_not_exists($uploadFolderPath . '/campaign/');
create_directory_if_not_exists($uploadFolderPath . '/template/');
create_directory_if_not_exists($uploadFolderPath . '/numbers/');

// Function to create a directory if it does not exist
function create_directory_if_not_exists($path)
{
    if (!is_dir($path)) {
        if (!mkdir($path, 0755, true)) {
            // Log the error for debugging
            log_message('error', 'Failed to create directory: ' . $path);
            die('Failed to create directory: ' . $path);
        } else {
            // Create an index.html file to prevent directory listing
            $fp = fopen($path . '/index.html', 'w');
            if ($fp) {
                fclose($fp);
            } else {
                log_message('error', 'Failed to create index.html in directory: ' . $path);
                die('Failed to create index.html in directory: ' . $path);
            }
        }
    }
}


    require_once __DIR__ . '/install.php';
    // NOTE: updates.php is disabled from running on every page load due to concurrent DDL issues
    // Table schema updates should be managed via migration system instead
    // require_once __DIR__ . '/updates.php';


// Function to handle module activation
register_activation_hook(WHATSAPP_MODULE_NAME, 'whatsapp_activation_hook');

function whatsapp_activation_hook()
{
    $CI = &get_instance();
    require_once __DIR__ . '/install.php';
    require_once __DIR__ . '/updates.php';
}

// Initialize permissions
hooks()->add_filter('staff_permissions', function ($permissions) {
    $viewGlobalName = _l('permission_view');

    // Define permission templates for easier reuse
    $allPermissionsArray = [
        'view'   => $viewGlobalName,
        'create' => _l('permission_create'),
        'edit'   => _l('permission_edit'),
        'delete' => _l('permission_delete'),
    ];
    
    // Define WhatsApp module permissions
    $permissions['whatsapp_contacts'] = [
        'name'         => _l('whatsapp_contacts'),
        'capabilities' => [
            'view'          => $viewGlobalName,
            'load_template' => _l('whatsapp_contacts'),
        ],
    ];
    $permissions['whatsapp_numbers'] = [
        'name'         => _l('whatsapp_numbers'),
        'capabilities' => [
            'view'          => $viewGlobalName,
            'load_template' => _l('whatsapp_numbers'),
        ],
    ];
    $permissions['whatsapp_bot'] = [
        'name'         => _l('whatsapp_bot'),
        'capabilities' => $allPermissionsArray,
    ];
    $permissions['whatsapp_template'] = [
        'name'         => _l('template'),
        'capabilities' => [
            'view'          => $viewGlobalName,
            'load_template' => _l('load_template'),
        ],
    ];
    $permissions['whatsapp_campaign'] = [
        'name'         => _l('campaigns'),
        'capabilities' => array_merge($allPermissionsArray, [
            'show' => _l('show_campaign'),
        ]),
    ];
    $permissions['whatsapp_chat'] = [
        'name'         => _l('chat'),
        'capabilities' => ['view' => $viewGlobalName],
    ];
    $permissions['whatsapp_log_activity'] = [
        'name'         => _l('log_activity'),
        'capabilities' => [
            'view'      => $viewGlobalName,
            'clear_log' => _l('clear_log'),
        ],
    ];
    $permissions['whatsapp_settings'] = [
        'name'         => _l('whatsapp_settings'),
        'capabilities' => ['view' => _l('view')],
    ];
    $permissions['quickreplies'] = [
        'name'         => _l('quick_replies'),
        'capabilities' => $allPermissionsArray,
    ];
    $permissions['whatsapp_groups'] = [
        'name'         => _l('whatsapp_groups'),
        'capabilities' => ['view' => $viewGlobalName],
    ];
    $permissions['whatsapp_docs'] = [
        'name'         => _l('documentation'),
        'capabilities' => ['view' => $viewGlobalName],
    ];
    
    return $permissions;
});

// Menu Items
hooks()->add_action('admin_init', function () {
    $CI = get_instance();

    // Define all possible permissions for WhatsApp modules
    $permissions = [
        'whatsapp_bots', 'whatsapp_template', 'whatsapp_campaign',
        'whatsapp_chat', 'whatsapp_log_activity', 'whatsapp_settings',
        'whatsapp_numbers', 'whatsapp_contacts', 'whatsapp_groups',
        'whatsapp_docs', 'quickreplies'
    ];

    // Check if the user has access to any WhatsApp section
    $hasAccess = false;
    foreach ($permissions as $permission) {
        if (staff_can('view', $permission)) {
            $hasAccess = true;
            break;
        }
    }

    // Menu badge with unread messages count
    $unreadMessages = whatsapp_unreadmessages();
    $whatsappName = _l('WhatsApp');
    if ($unreadMessages > 0) {
        $whatsappName .= ' (' . $unreadMessages . ')';
    }

    // Add main WhatsApp menu item if the user has access
    if ($hasAccess) {
        $CI->app_menu->add_sidebar_menu_item('whatsapp', [
            'slug'     => 'whatsapp',
            'name'     => $whatsappName,
            'icon'     => 'fa-brands fa-whatsapp',
            'href'     => '#',
            'position' => 1,
        ]);
    }

    // Define child menu items with Tailwind "Beta" and "New & Beta" badges
    $menuItems = [
        'whatsapp_connection' => [
            'slug'     => 'connection',
            'name'     => _l('whatsapp_connection'),
            'icon'     => 'fa-solid fa-link', // 🔗 Represents linking/connection clearly
            'href'     => admin_url(WHATSAPP_MODULE . '/connection'),
            'position' => 1,
        ],

        'whatsapp_chat' => [
            'slug'     => 'whatsapp_chat_integration',
            'name'     => _l('chat'),
            'icon'     => 'fa-solid fa-comment-dots',
            'href'     => admin_url(WHATSAPP_MODULE . '/interaction'),
            'position' => 1,
        ],
        'whatsapp_numbers' => [
            'slug'     => 'whatsapp_numbers',
            'name'     => _l('whatsapp_numbers'),
            'icon'     => 'fa-solid fa-address-book',
            'href'     => admin_url(WHATSAPP_MODULE . '/numbers'),
            'position' => 2,
        ],
        'whatsapp_contacts' => [
            'slug'     => 'bulk_contacts',
            'name'     => _l('bulk_contacts'),
            'icon'     => 'fa-solid fa-address-book',
            'href'     => admin_url(WHATSAPP_MODULE . '/BulkContacts'),
            'position' => 2,
        ],
        'whatsapp_groups' => [
            'slug'     => 'bulk_groups',
            'name'     => _l('bulk_groups'),
            'icon'     => 'fa-solid fa-users',
            'href'     => admin_url(WHATSAPP_MODULE . '/groups'),
            'position' => 3,
        ],
        'whatsapp_bots' => [
            'slug'     => 'whatsapp_bots',
            'name'     => _l('bots'),
            'icon'     => 'fa-solid fa-robot',
            'href'     => admin_url(WHATSAPP_MODULE . '/bots'),
            'position' => 4,
        ],
        'whatsapp_template' => [
            'slug'     => 'whatsapp_templates',
            'name'     => _l('templates'),
            'icon'     => 'fa-solid fa-folder-open',
            'href'     => admin_url(WHATSAPP_MODULE . '/templates'),
            'position' => 5,
        ],
        'whatsapp_campaign' => [
            'slug'     => 'campaigns',
            'name'     => _l('campaigns'),
            'icon'     => 'fa-solid fa-bullhorn',
            'href'     => admin_url(WHATSAPP_MODULE . '/campaigns'),
            'position' => 6,
        ],
        'quickreplies' => [
            'slug'     => 'quickreplies',
            'name'     => _l('quick_replies'),
            'icon'     => 'fa-solid fa-reply',
            'href'     => admin_url(WHATSAPP_MODULE . '/QuickReplies'),
            'position' => 7,
        ],
        'whatsapp_log_activity' => [
            'slug'     => 'whatsapp_activity_log',
            'name'     => _l('activity_log'),
            'icon'     => 'fa-solid fa-clipboard-list',
            'href'     => admin_url(WHATSAPP_MODULE . '/activity_log'),
            'position' => 8,
        ],
        'whatsapp_docs' => [
            'slug'     => 'whatsapp_documentation',
            'name'     => _l('documentation'),
            'icon'     => 'fa-solid fa-book',
            'href'     => admin_url(WHATSAPP_MODULE . '/documentation'),
            'position' => 9,
            'target'   => '_blank',
        ],
        'whatsapp_settings' => [
            'slug'     => 'whatsapp_interaction',
            'name'     => _l('settings'),
            'icon'     => 'fa-solid fa-sliders-h',
            'href'     => admin_url('settings?group=whatsapp_settings'),
            'position' => 10,
        ],
    ];

    // Add child menu items based on specific permissions
    foreach ($menuItems as $permission => $item) {
        if (staff_can('view', $permission)) {
            $CI->app_menu->add_sidebar_children_item('whatsapp', $item);
        }
    }

    // Add settings section for WhatsApp
    $CI->app->add_settings_section('whatsapp_settings', [
        'title'    => _l('settings_group_whatsapp_interaction'),
        'position' => 5,
        'children' => [
            [
                'name'     => _l('settings_group_whatsapp_interaction'),
                'view'     => 'whatsapp/admin/whatsapp_settings',
                'position' => 8,
                'icon'     => 'fa-solid fa-tools',
            ],
        ],
    ]);
});




// Head components
function whatsapp_add_head_components()
{
    $CI = &get_instance();
    $viewuri = $_SERVER['REQUEST_URI'];
    if (strpos($viewuri, 'admin/whatsapp/interaction') !== false) {
        echo '<link href="' . base_url('modules/whatsapp/assets/css/twailwind.css') . '" rel="stylesheet" type="text/css" />';
        echo '<link href="' . base_url('modules/whatsapp/assets/css/fa.css') . '" rel="stylesheet" type="text/css" />';
           echo '<link href="' . base_url('modules/whatsapp/assets/css/chat.css') . '" rel="stylesheet" type="text/css" />';
 }

}
hooks()->add_action('app_admin_footer', function () {
    $CI = &get_instance();
       $viewuri = $_SERVER['REQUEST_URI'];
 if (get_instance()->app_modules->is_active(WHATSAPP_MODULE)) {
        $CI->load->library('App_merge_fields');
        $merge_fields = $CI->app_merge_fields->all();
        echo '<script>
                var merge_fields = ' . json_encode($merge_fields) . '
            </script>';
        echo '<script src="' . module_dir_url(WHATSAPP_MODULE, 'assets/js/underscore-min.js') . '?v=' . $CI->app_scripts->core_version() . '"></script>';
        echo '<script src="' . module_dir_url(WHATSAPP_MODULE, 'assets/js/tribute.min.js') . '?v=' . $CI->app_scripts->core_version() . '"></script>';
        echo '<script src="' . module_dir_url(WHATSAPP_MODULE, 'assets/js/whatsapp.js') . '?v=' . $CI->app_scripts->core_version() . '"></script>';
        echo '<script src="' . module_dir_url(WHATSAPP_MODULE, 'assets/js/prism.js') . '?v=' . $CI->app_scripts->core_version() . '"></script>';

     
 }
});
// Hooks
hooks()->add_action('app_admin_head', 'whatsapp_add_head_components');
/**
 * Add additional settings for this module in the module list area
 * @param  array $actions current actions
 * @return array
 */



// delete campaign lead when lead deleted
hooks()->add_action('after_lead_deleted', function ($id) {
    get_instance()->db->delete(db_prefix() . 'whatsapp_campaign_data', ['rel_id' => $id, 'rel_type' => 'leads']);
});

// delete campaign contacts when contact deleted
hooks()->add_action('contact_deleted', function ($id, $result) {
    get_instance()->db->delete(db_prefix() . 'whatsapp_campaign_data', ['rel_id' => $id, 'rel_type' => 'contacts']);
}, 0, 2);


// add new created contact in campaign that is select all contacts
hooks()->add_action('contact_created', function ($id) {
    $campaigns = get_instance()->db->get_where(db_prefix() . 'whatsapp_campaigns', ['select_all' => '1', 'rel_type' => 'contacts'])->result_array();
    foreach ($campaigns as $campaign) {
        if (0 == $campaign['is_sent']) {
            $template = get_whatsapp_template($campaign['template_id']);
            get_instance()->db->insert(db_prefix() . 'whatsapp_campaign_data', [
                'campaign_id'       => $campaign['id'],
                'rel_id'            => $id,
                'rel_type'          => 'contacts',
                'header_data'    => $template['header_data_text'],
                'body_data'      => $template['body_data'],
                'footer_data'    => $template['footer_data'],
                'status'            => 1,
            ]);
        }
    }
});



// add widgets
hooks()->add_filter('get_dashboard_widgets', function ($widgets) {
    $new_widgets = [];
    $new_widgets[] = [
        'path'      => WHATSAPP_MODULE . '/widgets/whatsapp-widget',
        'container' => 'top-12',
    ];

    return array_merge($new_widgets, $widgets);
});
function check_whatsapp_cronjob_status() {
    // Get the last run time of the WhatsApp cronjob
    $last_run = get_option('whatsapp_campaign_cronjob_run_at'); // Ensure this option is set correctly

    // Check if $last_run is null or empty
    if (empty($last_run)) {
        return '<div class="alert alert-warning">
            Warning: The WhatsApp cronjob has never run. 
            Please configure your cronjob in the <a href="' . admin_url('settings?group=whatsapp_settings') . '">WhatsApp Settings</a>.
        </div>';
    }

    $last_run_timestamp = strtotime($last_run); // Convert to timestamp
    $current_time = time(); // Get current time as a timestamp

    // Check if the last run was more than 10 minutes ago (600 seconds)
    if ($last_run_timestamp && ($current_time - $last_run_timestamp) > 600) { 
        return '<div class="alert alert-warning">
            Warning: The WhatsApp cronjob has not run in the last 10 minutes. 
            Please ensure your cronjob is configured correctly. 
            You can set it up in the <a href="' . admin_url('settings?group=whatsapp_settings') . '">WhatsApp Settings</a>.
        </div>';
    }

    return ''; // No alert needed
}

// Add the alert to the dashboard rendering
hooks()->add_filter('before_start_render_dashboard_content', function () {
    $alert_message = check_whatsapp_cronjob_status();
    // Display the alert message if it's not empty
    if (!empty($alert_message)) {
        echo $alert_message;
    }
});


if (!is_dir(WHATSAPP_MODULE_UPLOAD_FOLDER)) {
    if (!mkdir(WHATSAPP_MODULE_UPLOAD_FOLDER, 0755, true)) {
        exit('Failed to create directory: ' . WHATSAPP_MODULE_UPLOAD_FOLDER);
    }
    $fp = fopen(WHATSAPP_MODULE_UPLOAD_FOLDER . '/index.html', 'w');
    fclose($fp);
}

hooks()->add_filter('get_upload_path_by_type', 'add_whatsapp_files_upload_path', 0, 2);
function add_whatsapp_files_upload_path($path, $type)
{
    switch ($type) {
        case 'bot':
            $path = WHATSAPP_MODULE_UPLOAD_FOLDER . '/bot_files/';
            break;
        case 'campaign':
            $path = WHATSAPP_MODULE_UPLOAD_FOLDER . '/campaign/';
            break;
        case 'template':
            $path = WHATSAPP_MODULE_UPLOAD_FOLDER . '/template/';
            break;
         case 'whatsapp_numbers':
            $path = WHATSAPP_MODULE_UPLOAD_FOLDER . '/numbers/';
            break;
        
        default:
            $path = $path;
            break;
    }
    return $path;
}

if(is_admin()){
    $CI = &get_instance();
    $unread = whatsapp_unreadmessages();
    if ($unread > 0) {
        $message = "Attention: You have $unread unread WhatsApp messages. Please review them at your earliest convenience.";

    get_instance()->session->set_flashdata('message-warning', $message);
    }
    
}
