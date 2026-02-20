<?php

defined('BASEPATH') or exit('No direct script access allowed');

$route['perfex_fesat']                           = 'perfex_fesat/FeSat/index';
$route['perfex_fesat/settings']                  = 'perfex_fesat/FeSat/settings';
$route['perfex_fesat/test-connection']           = 'perfex_fesat/FeSat/test_connection';
$route['perfex_fesat/form/(:num)']               = 'perfex_fesat/FeSat/form/$1';
$route['perfex_fesat/generate/(:num)']           = 'perfex_fesat/FeSat/generate/$1';
$route['perfex_fesat/view-file/(:num)/(:any)']   = 'perfex_fesat/FeSat/view_file/$1/$2';
$route['perfex_fesat/send-email/(:num)']         = 'perfex_fesat/FeSat/send_email/$1';
$route['perfex_fesat/api/callback']              = 'perfex_fesat/FeApi/callback';

$route['admin/perfex_fesat']                     = 'perfex_fesat/FeSat/index';
$route['admin/perfex_fesat/settings']            = 'perfex_fesat/FeSat/settings';
$route['admin/perfex_fesat/test-connection']     = 'perfex_fesat/FeSat/test_connection';
$route['admin/perfex_fesat/form/(:num)']         = 'perfex_fesat/FeSat/form/$1';
$route['admin/perfex_fesat/generate/(:num)']     = 'perfex_fesat/FeSat/generate/$1';
$route['admin/perfex_fesat/view-file/(:num)/(:any)'] = 'perfex_fesat/FeSat/view_file/$1/$2';
$route['admin/perfex_fesat/send-email/(:num)']   = 'perfex_fesat/FeSat/send_email/$1';
