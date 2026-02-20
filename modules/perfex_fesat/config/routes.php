<?php

defined('BASEPATH') or exit('No direct script access allowed');

$route['fe_sat']                           = 'perfex_fesat/FeSat/index';
$route['fe_sat/settings']                  = 'perfex_fesat/FeSat/settings';
$route['fe_sat/test-connection']           = 'perfex_fesat/FeSat/test_connection';
$route['fe_sat/form/(:num)']               = 'perfex_fesat/FeSat/form/$1';
$route['fe_sat/generate/(:num)']           = 'perfex_fesat/FeSat/generate/$1';
$route['fe_sat/view-file/(:num)/(:any)']   = 'perfex_fesat/FeSat/view_file/$1/$2';
$route['fe_sat/send-email/(:num)']         = 'perfex_fesat/FeSat/send_email/$1';
$route['fe_sat/cancel-form/(:num)']        = 'perfex_fesat/FeSat/cancel_form/$1';
$route['fe_sat/cancel/(:num)']             = 'perfex_fesat/FeSat/cancel/$1';
$route['fe_sat/api/callback']              = 'perfex_fesat/FeApi/callback';

$route['admin/fe_sat']                     = 'perfex_fesat/FeSat/index';
$route['admin/fe_sat/settings']            = 'perfex_fesat/FeSat/settings';
$route['admin/fe_sat/test-connection']     = 'perfex_fesat/FeSat/test_connection';
$route['admin/fe_sat/form/(:num)']         = 'perfex_fesat/FeSat/form/$1';
$route['admin/fe_sat/generate/(:num)']     = 'perfex_fesat/FeSat/generate/$1';
$route['admin/fe_sat/view-file/(:num)/(:any)'] = 'perfex_fesat/FeSat/view_file/$1/$2';
$route['admin/fe_sat/send-email/(:num)']   = 'perfex_fesat/FeSat/send_email/$1';
$route['admin/fe_sat/cancel-form/(:num)']  = 'perfex_fesat/FeSat/cancel_form/$1';
$route['admin/fe_sat/cancel/(:num)']       = 'perfex_fesat/FeSat/cancel/$1';
