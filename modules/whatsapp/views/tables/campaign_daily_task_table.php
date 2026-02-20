<?php

defined('BASEPATH') || exit('No direct script access allowed');

$aColumns = [];
if (isset($rel_type) && 'leads' == $rel_type) {
    $aColumns[] = db_prefix().'leads.phonenumber as phonenumber';
    $aColumns[] = db_prefix().'leads.name as name';
} elseif (isset($rel_type) && 'contacts' == $rel_type) {
    $aColumns[] = db_prefix().'contacts.phonenumber as phonenumber';
    $aColumns[] = db_prefix().'contacts.firstname as name';
}
$aColumns[] = db_prefix().'whatsapp_campaign_data.status as status';

$join = [
    'LEFT JOIN '.db_prefix().'leads ON '.db_prefix().'leads.id = '.db_prefix().'whatsapp_campaign_data.rel_id',
    'LEFT JOIN '.db_prefix().'contacts ON '.db_prefix().'contacts.id = '.db_prefix().'whatsapp_campaign_data.rel_id',
    'LEFT JOIN '.db_prefix().'whatsapp_campaigns ON '.db_prefix().'whatsapp_campaigns.id = '.db_prefix().'whatsapp_campaign_data.campaign_id',
    'LEFT JOIN '.db_prefix().'whatsapp_templates ON '.db_prefix().'whatsapp_campaigns.template_id = '.db_prefix().'whatsapp_templates.id',
];

$where = [];

$where[] = 'AND campaign_id = '.$id;

$sIndexColumn = 'id';
$sTable       = db_prefix().'whatsapp_campaign_data';

$additionalSelect = [
    db_prefix().'whatsapp_campaign_data.id',
    db_prefix().'whatsapp_campaign_data.rel_id',
    db_prefix().'contacts.userid',
    db_prefix().'whatsapp_campaigns.header_params',
    db_prefix().'whatsapp_campaigns.body_params',
    db_prefix().'whatsapp_campaigns.footer_params',
    db_prefix().'whatsapp_templates.header_params_count',
    db_prefix().'whatsapp_templates.body_params_count',
    db_prefix().'whatsapp_templates.footer_params_count',
    db_prefix().'whatsapp_campaign_data.header_data',
    db_prefix().'whatsapp_campaign_data.body_data',
    db_prefix().'whatsapp_campaign_data.footer_data',
    db_prefix().'whatsapp_campaign_data.response_message',
    db_prefix().'contacts.lastname',
];

$result  = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, $additionalSelect);
$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    $row[] = $aRow['phonenumber'];

    $row[] = $aRow['name'].' '.((isset($rel_type) && 'contacts' == $rel_type) ? $aRow['lastname'] : '');

    $message = whatsappParseText($rel_type, 'header', $aRow);
    $message .= whatsappParseText($rel_type, 'body', $aRow);
    $message .= whatsappParseText($rel_type, 'footer', $aRow);

    $row[] = $message;

    $status = whatsapp_campaign_status($aRow['status']);
    $row[]  = '<div><span class="label '.$status['label_class'].'" data-toggle="tooltip" data-title="'.$aRow['response_message'].'">'.$status['label'].'</span></div>';

    $output['aaData'][] = $row;
}
