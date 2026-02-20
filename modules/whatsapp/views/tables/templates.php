<?php

defined('BASEPATH') || exit('No direct script access allowed');

// Define the columns including new fields
$aColumns = [
    'id',
    'template_name',
    'language',
    'category',
    'status',
    'header_data_format', // Existing field
    'body_data',          // Existing field
    'parameter_format',   // New field
    'sub_category',       // New field
    'template_description', // New field
    'is_custom'           // New field
];

$where   = [];
$where[] = ' AND status = "APPROVED"';

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'whatsapp_templates';

$result  = data_tables_init($aColumns, $sIndexColumn, $sTable, [], $where);
$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    // Main columns
    $row[] = $aRow['id'];
    $row[] = $aRow['template_name'];
    $row[] = $aRow['language'];
    $row[] = $aRow['category'];

    // Format the status as a label
    if ('APPROVED' == $aRow['status']) {
        $status = '<span class="inline-block label label-success">' . $aRow['status'] . '</span>';
    } else {
        $status = '<span class="inline-block label label-default">' . $aRow['status'] . '</span>';
    }
    $row[] = $status;

    // Existing fields
    $row[] = $aRow['header_data_format'];
    $row[] = $aRow['body_data'];

    // New fields
    $row[] = $aRow['parameter_format'];       // e.g., Parameter Format
    $row[] = $aRow['sub_category'];            // e.g., Sub Category
    $row[] = $aRow['template_description'];    // e.g., Template Description

    // Format is_custom as Yes/No
    $row[] = ($aRow['is_custom'] == 1) ? 'Yes' : 'No';

    $output['aaData'][] = $row;
}

