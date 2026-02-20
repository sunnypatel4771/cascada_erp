<?php

defined('BASEPATH') || exit('No direct script access allowed');

// Define the columns to be used in the data table
$aColumns = [
    'name',
    'bot_type',
    '`trigger`', // Wrap trigger in backticks to avoid conflicts with SQL keywords
    'rel_type',
    'reply_type', // New column for reply type
    'is_bot_active',
    'sending_count', // New column for sending count
];

// Define the primary index column and the table name
$sIndexColumn = 'id';
$sTable       = db_prefix() . 'whatsapp_bot';

// Initialize the data table with the specified columns
$result = data_tables_init($aColumns, $sIndexColumn, $sTable, [], [], ['id']);
$output = $result['output'];
$rResult = $result['rResult'];

// Loop through each row of results and format the data accordingly
foreach ($rResult as $aRow) {
    $row = [];

    // Column: Name
    $row[] = $aRow['name'];

    // Column: Bot Type (with helper function for label)
    $row[] = get_whatsapp_bot_types($aRow['bot_type'])['label'] ?? '-';

    // Column: Trigger
    $row[] = $aRow['trigger'];

    // Column: Related Type with color-coded labels
    $colorMap = [
        'leads'     => '#3a25e9',
        'contacts'  => '#ff4646',
        'customers' => '#7bf565',
        'other'     => '#aaaaaa',
    ];

    $color = $colorMap[$aRow['rel_type']] ?? $colorMap['other'];
    $row[] = sprintf(
        '<span class="label" style="color:%s; border:1px solid %s; background: %s;">%s</span>',
        $color,
        adjust_hex_brightness($color, 0.4),
        adjust_hex_brightness($color, 0.04),
        _l($aRow['rel_type'])
    );

    // Column: Reply Type (with helper function for label)
    $row[] = get_whatsapp_reply_triggers($aRow['reply_type'])['label'] ?? '-';

    // Column: Active Status Switch
    $checked = $aRow['is_bot_active'] == 1 ? 'checked' : '';
    $row[] = '<div class="onoffswitch">
                <input type="checkbox" data-switch-url="' . admin_url('whatsapp/bots/change_active_status') . '" name="onoffswitch" class="onoffswitch-checkbox" id="c_' . $aRow['id'] . '" data-id="' . $aRow['id'] . '" ' . $checked . '>
                <label class="onoffswitch-label" for="c_' . $aRow['id'] . '"></label>
            </div>';

    // Column: Sending Count
    $row[] = $aRow['sending_count'];

       // Column: Action Options (Edit, Duplicate, and Delete)
    $options = '<div class="tw-flex tw-items-center tw-space-x-3">';
    
    // Edit button
    if (staff_can('edit', 'whatsapp_bot')) {
        $options .= sprintf(
            '<a href="%s" data-toggle="tooltip" data-title="%s" class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700">
                <i class="fa-regular fa-pen-to-square fa-lg"></i>
            </a>',
            admin_url('whatsapp/bots/form/' . $aRow['id']),
            _l('edit')
        );
    }
    
    // Duplicate button
    if (staff_can('duplicate', 'whatsapp_bot')) {
        $options .= sprintf(
            '<a href="%s" data-toggle="tooltip" data-title="%s" class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700">
                <i class="fa-regular fa-copy fa-lg"></i>
            </a>',
            admin_url('whatsapp/bots/duplicate/' . $aRow['id']), // Use GET URL with bot ID
            _l('duplicate')
        );
    }


    if ($aRow['bot_type'] == 4) {
        $options .= sprintf(
            '<a href="%s" data-toggle="tooltip" data-title="%s" class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700">
                <i class="fa-solid fa-sitemap fa-lg"></i>
            </a>',
            admin_url('whatsapp/bots/botflow/' . $aRow['id']),
            _l('botflow')
        );
    }


    // Delete button
    if (staff_can('delete', 'whatsapp_message_bot')) {
        $options .= sprintf(
            '<a href="javascript:void(0)" data-id="%d" id="delete_message_bot" data-toggle="tooltip" data-title="%s" class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700 _delete">
                <i class="fa-regular fa-trash-can fa-lg"></i>
            </a>',
            $aRow['id'],
            _l('delete')
        );
    }
    
    // Default "-" if no permissions
    if (!staff_can('edit', 'whatsapp_bot') && !staff_can('delete', 'whatsapp_message_bot')) {
        $options .= '-';
    }
    
    $options .= '</div>';
    $row[] = $options;

    // Append the formatted row to the output data
    $output['aaData'][] = $row;
}
