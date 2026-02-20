<?php

defined('BASEPATH') || exit('No direct script access allowed');

// Columns for the DataTable without Name and Template Name fields
$aColumns = [
    db_prefix() . 'whatsapp_activity_log.id as id', // Log ID
    'category',                                     // Category
    'response_code',                               // Response Code
    db_prefix() . 'whatsapp_activity_log.rel_type as rel_type', // Related Type
    'recorded_at',                                 // Recorded At
    '1',                                           // Placeholder for Actions
];

// No joins or additional selects are needed if we are not displaying dynamic names.
$join = []; 
$additionalSelect = [];

// Define the table and index column
$sTable = db_prefix() . 'whatsapp_activity_log';
$sIndexColumn = 'id';
$where = []; // Add conditions here if necessary

// Fetch data using the DataTables helper
$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, $additionalSelect);
$output = $result['output'];
$rResult = $result['rResult'];

// Build the DataTable rows
foreach ($rResult as $aRow) {
    $row = [];

    // Log ID
    $row[] = htmlspecialchars($aRow['id'], ENT_QUOTES, 'UTF-8');

    // Category (using language translation if available)
    $row[] = _l($aRow['category']);

    // Response Code with status labels
    $responseColor = 'label-default';
    if ($aRow['response_code'] >= 200 && $aRow['response_code'] <= 299) {
        $responseColor = 'label-success';
    } elseif ($aRow['response_code'] >= 300 && $aRow['response_code'] <= 399) {
        $responseColor = 'label-info';
    } elseif ($aRow['response_code'] >= 400 && $aRow['response_code'] <= 499) {
        $responseColor = 'label-warning';
    } elseif ($aRow['response_code'] >= 500) {
        $responseColor = 'label-danger';
    }
    $row[] = '<span class="label ' . $responseColor . '">' . htmlspecialchars($aRow['response_code'], ENT_QUOTES, 'UTF-8') . '</span>';

    // Related Type
    $row[] = _l($aRow['rel_type']);

    // Recorded At
    $row[] = htmlspecialchars($aRow['recorded_at'], ENT_QUOTES, 'UTF-8');

    // Actions (View and Delete buttons)
    $options = '<div class="d-flex align-items-center gap-2">';
    $options .= '<a href="' . admin_url('whatsapp/view_log_details/' . $aRow['id']) . '" class="btn btn-primary btn-icon"><i class="fa fa-eye"></i></a>';
    if (staff_can('clear_log', 'whatsapp_log_activity')) {
        $options .= '<a href="' . admin_url('whatsapp/delete_log/' . $aRow['id']) . '" data-id="' . htmlspecialchars($aRow['id'], ENT_QUOTES, 'UTF-8') . '" class="btn btn-danger btn-icon _delete"><i class="fa fa-trash"></i></a>';
    }
    $options .= '</div>';
    $row[] = $options;

    // Append the row to the output data
    $output['aaData'][] = $row;
}
