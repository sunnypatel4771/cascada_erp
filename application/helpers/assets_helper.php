<?php

defined('BASEPATH') or exit('No direct script access allowed');

hooks()->add_action('admin_auth_init', 'init_admin_auth_assets');
hooks()->add_action('app_admin_assets', '_init_admin_assets');

function app_build_asset_exists($file)
{
    return file_exists(FCPATH . 'assets/builds/' . ltrim($file, '/'));
}

function init_admin_assets()
{
    hooks()->do_action('app_admin_assets');
}

function init_customers_area_assets()
{
    // Used by themes to add assets
    hooks()->do_action('app_client_assets');

    hooks()->do_action('app_client_assets_added');
}

function init_admin_auth_assets()
{
    $CI        = &get_instance();
    $groupName = 'admin-auth';

    add_favicon_link_asset($groupName);

    $CI->app_css->add('reset-css', 'assets/css/reset.min.css', $groupName, ['inter-font']);
    $CI->app_css->add('inter-font', 'assets/plugins/inter/inter.css', $groupName);
    $CI->app_css->add('bootstrap-css', 'assets/plugins/bootstrap/css/bootstrap.min.css', $groupName);

    if (is_rtl()) {
        $CI->app_css->add('bootstrap-rtl-css', 'assets/plugins/bootstrap-arabic/css/bootstrap-arabic.min.css', $groupName);
    }

    if (app_build_asset_exists('tailwind.css')) {
        $CI->app_css->add('tailwind-css', base_url($CI->app_css->core_file('assets/builds', 'tailwind.css')) . '?v=' . $CI->app_css->core_version(), $groupName, ['bootstrap-css']);
    }
}

function _init_admin_assets()
{
    $CI = &get_instance();

    // Javascript
    $hasVendorAdminBuild = app_build_asset_exists('vendor-admin.js');

    if ($hasVendorAdminBuild) {
        $CI->app_scripts->add('vendor-js', 'assets/builds/vendor-admin.js');
    } else {
        $CI->app_scripts->add('jquery-js', 'assets/plugins/jquery/jquery.min.js');
        $CI->app_scripts->add('bootstrap-js', 'assets/plugins/bootstrap/js/bootstrap.min.js', 'admin', ['jquery-js']);
    }

    $CI->app_scripts->add('jquery-migrate-js', 'assets/plugins/jquery/jquery-migrate.' . (ENVIRONMENT === 'production' ? 'min.' : '') . 'js');

    add_datatables_js_assets();
    add_moment_js_assets();
    add_bootstrap_select_js_assets();

    $CI->app_scripts->add('tinymce-js', 'assets/plugins/tinymce/tinymce.min.js');

    add_jquery_validation_js_assets();

    if (get_option('pusher_realtime_notifications') == 1) {
        $CI->app_scripts->add('pusher-js', 'https://js.pusher.com/8.2.0/pusher.min.js');
    }

    add_dropbox_js_assets();
    add_google_api_js_assets();

    $CI->app_scripts->add(
        'jquery-ui-js',
        'assets/plugins/jquery-ui/jquery-ui' . (ENVIRONMENT === 'production' ? '.min' : '') . '.js',
        'admin',
        [$hasVendorAdminBuild ? 'vendor-js' : 'jquery-js']
    );

    $CI->app_scripts->add(
        'dropzone-js',
        'assets/plugins/dropzone/min/dropzone.min.js',
        'admin',
        [$hasVendorAdminBuild ? 'vendor-js' : 'jquery-js']
    );

    $CI->app_scripts->add(
        'metismenu-js',
        'assets/plugins/metisMenu/metisMenu.min.js',
        'admin',
        [$hasVendorAdminBuild ? 'vendor-js' : 'jquery-js']
    );

    // Required by main.js (it calls $.Shortcuts.start/stop).
    // In ramos-php the compiled common.js bundle is missing, so we must ensure hotkeys are loaded explicitly.
    $CI->app_scripts->add(
        'hotkeys-js',
        'assets/plugins/internal/hotkeys/hotkeys.js',
        'admin',
        [$hasVendorAdminBuild ? 'vendor-js' : 'jquery-js']
    );

    if (app_build_asset_exists('common.js')) {
        $CI->app_scripts->add('common-js', 'assets/builds/common.js');
    } else {
        $CI->app_scripts->add('common-js', base_url($CI->app_scripts->core_file('assets/js', 'app.js')) . '?v=' . $CI->app_css->core_version());
    }

    $CI->app_scripts->add(
        'app-js',
        base_url($CI->app_scripts->core_file('assets/js', 'main.js')) . '?v=' . $CI->app_css->core_version(),
        'admin',
        [$hasVendorAdminBuild ? 'vendor-js' : 'bootstrap-js', 'datatables-js', 'bootstrap-select-js', 'tinymce-js', 'jquery-migrate-js', 'jquery-validation-js', 'moment-js', 'common-js', 'hotkeys-js', 'jquery-ui-js', 'dropzone-js', 'metismenu-js']
    );

    if (app_build_asset_exists('app.js')) {
        $CI->app_scripts->add('app-v3', 'assets/builds/app.js');
    }

    // CSS
    add_favicon_link_asset();

    $CI->app_css->add('reset-css', 'assets/css/reset.min.css');
    $CI->app_css->add('inter-font', 'assets/plugins/inter/inter.css', 'admin', ['reset-css']);
    if (app_build_asset_exists('vendor-admin.css')) {
        $CI->app_css->add('vendor-css', 'assets/builds/vendor-admin.css', 'admin', ['reset-css']);
    } else {
        $CI->app_css->add('bootstrap-css', 'assets/plugins/bootstrap/css/bootstrap.min.css', 'admin', ['reset-css']);
        // Required for selectpicker: hides native <select> and styles the dropdown (missing from vendor bundle fallback)
        $CI->app_css->add(
            'bootstrap-select-css',
            'assets/plugins/bootstrap-select/css/bootstrap-select' . (ENVIRONMENT === 'production' ? '.min' : '') . '.css',
            'admin',
            ['bootstrap-css']
        );
    }

    $CI->app_css->add(
        'jquery-ui-css',
        'assets/plugins/jquery-ui/jquery-ui' . (ENVIRONMENT === 'production' ? '.min' : '') . '.css',
        'admin'
    );

    $CI->app_css->add('dropzone-css', 'assets/plugins/dropzone/min/dropzone.min.css', 'admin');
    $CI->app_css->add('metismenu-css', 'assets/plugins/metisMenu/metisMenu.min.css', 'admin');

    $CI->app_css->add('fontawesome-css', 'assets/plugins/font-awesome/css/fontawesome.min.css');
    $CI->app_css->add('fontawesome-brands', 'assets/plugins/font-awesome/css/brands.min.css');
    $CI->app_css->add('fontawesome-solid', 'assets/plugins/font-awesome/css/solid.min.css');
    $CI->app_css->add('fontawesome-regular', 'assets/plugins/font-awesome/css/regular.min.css');

    if (is_rtl()) {
        $CI->app_css->add('bootstrap-rtl-css', 'assets/plugins/bootstrap-arabic/css/bootstrap-arabic.min.css');
    }

    if (app_build_asset_exists('tailwind.css')) {
        $CI->app_css->add('tailwind-css', base_url($CI->app_css->core_file('assets/builds', 'tailwind.css')) . '?v=' . $CI->app_css->core_version());
    }

    $appCssDeps = app_build_asset_exists('tailwind.css') ? ['tailwind-css'] : [];
    $CI->app_css->add('app-css', base_url($CI->app_css->core_file('assets/css', 'style.css')) . '?v=' . $CI->app_css->core_version(), 'admin', $appCssDeps);

    if (file_exists(FCPATH . 'assets/css/custom.css')) {
        $CI->app_css->add('custom-css', base_url('assets/css/custom.css'), 'admin', ['app-css']);
    }

    hooks()->do_action('app_admin_assets_added');
}


function add_calendar_assets($group = 'admin')
{
    $locale = $GLOBALS['locale'];
    $CI     = &get_instance();

    $CI->app_scripts->add('fullcalendar-js', 'assets/plugins/fullcalendar/lib/main.min.js', $group);

    if ($locale != 'en' && file_exists(FCPATH . 'assets/plugins/fullcalendar/lib/locales/' . $locale . '.js')) {
        $CI->app_scripts->add('fullcalendar-lang-js', 'assets/plugins/fullcalendar/lib/locales/' . $locale . '.js', $group);
    }

    $CI->app_css->add('fullcalendar-css', 'assets/plugins/fullcalendar/lib/main.min.css', $group);
}

function add_moment_js_assets($group = 'admin')
{
    if (app_build_asset_exists('moment.min.js')) {
        get_instance()->app_scripts->add('moment-js', 'assets/builds/moment.min.js', $group);
    } else {
        get_instance()->app_scripts->add('moment-js', 'assets/plugins/moment/moment-with-locales.js', $group);
    }
}

function add_favicon_link_asset($group = 'admin')
{
    $favIcon = get_option('favicon');

    if ($favIcon != '' && !file_exists(FCPATH . 'uploads/company/' . $favIcon)) {
        $favIcon = '';
    }

    if ($favIcon != '') {
        $favPath = 'uploads/company/' . $favIcon;
        get_instance()->app_css->add('favicon', [
        'path'       => $favPath,
        'version'    => false,
        'attributes' => [
            'rel'  => 'shortcut icon',
            'type' => false,
        ],
        ], $group);
        get_instance()->app_css->add('favicon-apple-touch-icon', [
        'path'       => $favPath,
        'version'    => false,
        'attributes' => [
            'rel'  => 'apple-touch-icon”',
            'type' => false,
        ],
        ], $group);
    }
}

function add_jquery_validation_js_assets($group = 'admin')
{
    $CI          = &get_instance();
    $locale      = $GLOBALS['locale'];
    $localeUpper = strtoupper($locale);

    $jqValidationBase = 'assets/plugins/jquery-validation/';
    $CI->app_scripts->add('jquery-validation-js', $jqValidationBase . 'jquery.validate.min.js', $group);

    if ($locale != 'en') {
        if (file_exists(FCPATH . $jqValidationBase . 'localization/messages_' . $locale . '.min.js')) {
            $CI->app_scripts->add('jquery-validation-lang-js', $jqValidationBase . 'localization/messages_' . $locale . '.min.js', $group);
        } elseif (file_exists(FCPATH . $jqValidationBase . 'localization/messages_' . $locale . '_' . $localeUpper . '.min.js')) {
            $CI->app_scripts->add('jquery-validation-lang-js', $jqValidationBase . 'localization/messages_' . $locale . '_' . $localeUpper . '.min.js', $group);
        }
    }
}

function add_bootstrap_select_js_assets($group = 'admin')
{
    $CI           = &get_instance();
    $locale       = $GLOBALS['locale'];
    $localeUpper  = strtoupper($locale);
    $bsSelectBase = 'assets/plugins/bootstrap-select/js/';
    if (app_build_asset_exists('bootstrap-select.min.js')) {
        $CI->app_scripts->add('bootstrap-select-js', 'assets/builds/bootstrap-select.min.js', $group);
    } else {
        $CI->app_scripts->add('bootstrap-select-js', 'assets/plugins/bootstrap-select/js/bootstrap-select.js', $group);
    }

    if ($locale != 'en') {
        if (file_exists(FCPATH . $bsSelectBase . 'i18n/defaults-' . $locale . '.min.js')) {
            $CI->app_scripts->add('bootstrap-select-lang-js', $bsSelectBase . 'i18n/defaults-' . $locale . '.min.js', $group);
        } elseif (file_exists(FCPATH . $bsSelectBase . 'i18n/defaults-' . $locale . '_' . $localeUpper . '.min.js')) {
            $CI->app_scripts->add('bootstrap-select-lang-js', $bsSelectBase . 'i18n/defaults-' . $locale . '_' . $localeUpper . '.min.js', $group);
        }
    }
}

function add_dropbox_js_assets($group = 'admin')
{
    if (get_option('dropbox_app_key') != '') {
        get_instance()->app_scripts->add('dropboxjs', [
            'path'       => 'https://www.dropbox.com/static/api/2/dropins.js',
            'attributes' => [
                'data-app-key' => get_option('dropbox_app_key'),
            ],
        ], $group);
    }
}

function add_google_api_js_assets($group = 'admin')
{
    if (get_option('enable_google_picker') == '1') {
        get_instance()->app_scripts->add('google-gsi-js', [
            'path'       => 'https://accounts.google.com/gsi/client',
            'attributes' => [
                'defer',
            ],
        ], $group);
        get_instance()->app_scripts->add('google-js', [
            'path'       => 'https://apis.google.com/js/api.js?onload=onGoogleApiLoad',
            'attributes' => [
                'defer',
            ],
        ], $group);
    }
}


function add_admin_tickets_js_assets()
{
    $CI = &get_instance();
    $CI->app_scripts->add(
        'tickets-js',
        base_url($CI->app_scripts->core_file('assets/js', 'tickets.js')) . '?v=' . $CI->app_scripts->core_version(),
        'admin',
        ['app-js']
    );
}

function add_datatables_js_assets($group = 'admin')
{
    get_instance()->app_scripts->add('datatables-js', 'assets/plugins/datatables/datatables.min.js', $group);
}

function app_compile_css($group = 'admin')
{
    return get_instance()->app_css->compile($group);
}

function app_compile_scripts($group = 'admin')
{
    return get_instance()->app_scripts->compile($group);
}