<?php defined('BASEPATH') || exit('No direct script access allowed'); ?>
<?php init_head(); 

$sources  = get_instance()->leads_model->get_source();
$statuses = get_instance()->leads_model->get_status();
$staff    = get_instance()->staff_model->get('', ['active' => 1]);
?>

<div id="wrapper">
    <div class="content">
        <?php echo form_open_multipart(admin_url('whatsapp/campaigns/save'), ['id' => 'campaign_form']); ?>
        <input type="hidden" name="id" id="id" value="<?php echo $campaign['id'] ?? ''; ?>" class="temp_id">
        <h4 class="tw-mt-0 tw-font-semibold tw-text-lg tw-text-neutral-700"><?php echo _l('send_new_campaign'); ?></h4>
        <div class="row mbot20">
            <div class="col-md-6">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-neutral-700 no-margin"><?php echo _l('campaign'); ?></h4>
                        <div class="clearfix"></div>
                        <hr class="hr-panel-separator">
                        <?php echo render_input('name', 'campaign_name', $campaign['name'] ?? '', '',  ['autocomplete' => 'off']); ?>
                        <?php echo render_select('rel_type', whatsapp_get_rel_type(), ['key', 'name'], 'relation_type', $campaign['rel_type'] ?? ''); ?>
                        <?php echo render_select('template_id', $templates, ['id', 'template_name', 'language'], 'template', $campaign['template_id'] ?? ''); ?>
                        <div class="relation_wrapper hide">
                            <hr class="hr-panel-separator">
                            
                        <?php
                        
                        // Assuming $leads and $contacts are arrays of data fetched from the database
                        
                        // Process $leads array
                        foreach ($leads as &$lead) {
                            $lead['display_name'] = $lead['name'] . ' - ' . $lead['company'] . ' - ' . $lead['phonenumber'];
                        }
                        unset($lead); // Break the reference to avoid issues
                        
                        // Process $contacts array
                        foreach ($contacts as &$contact) {
                            $contact['display_name'] = $contact['firstname'] . ' ' . $contact['lastname'] . ' - ' . $contact['phonenumber'];
                        }
                        unset($contact); // Break the reference to avoid issues
                        
                        // Fixing the Leads Dropdown
                        echo render_select(
                            'lead_ids[]', 
                            $leads, 
                            ['id', 'display_name'], 
                            'leads', 
                            $campaign['lead_ids'] ?? '', 
                            ['multiple' => true, 'data-actions-box' => true, 'data-width' => '100%', 'data-live-search' => true], 
                            [], 
                            'hide lead_ids', 
                            '', 
                            false
                        );
                        
                        // Fixing the Contacts Dropdown
                        echo render_select(
                            'contact_ids[]', 
                            $contacts, 
                            ['id', 'display_name'], 
                            'contacts', 
                            $campaign['contact_ids'] ?? '', 
                            ['multiple' => true, 'data-actions-box' => true, 'data-width' => '100%', 'data-live-search' => true], 
                            [], 
                            'hide contact_ids', 
                            '', 
                            false
                        );
                        
                        ?>      
                        <?php echo render_select(
                                'group_id', // Single select name
                                (array) $groups,
                                ['id', 'name'],
                                'whatsapp_groups',
                                $campaign['group_id'] ?? '', // Set selected value if available
                                [
                                    'data-actions-box' => true,
                                    'data-width' => '100%',
                                    'data-live-search' => true
                                ],
                                [], // Additional attributes if needed
                                'hide group_id', // Additional classes if needed
                                '', // No default selected value here as we are setting it in $campaign
                                false
                            ); 
                            ?>
                          <div class="filter-wrapper hide">
                                <div class="col-sm-3">
                                    <label><?php echo _l('Lead Status'); ?></label>
                                    <select id="filter_status_id" class="selectpicker" data-width="100%" data-live-search="true">
                                        <option value=""><?php echo _l('All Statuses'); ?></option>
                                        <?php foreach ($statuses as $status) { ?>
                                            <option value="<?php echo $status['id']; ?>"><?php echo $status['name']; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            
                                <div class="col-sm-3">
                                    <label><?php echo _l('Assigned Staff'); ?></label>
                                    <select id="filter_assigned_id" class="selectpicker" data-width="100%" data-live-search="true">
                                        <option value=""><?php echo _l('All Staff'); ?></option>
                                        <?php foreach ($staff as $staff_member) { ?>
                                            <option value="<?php echo $staff_member['staffid']; ?>"><?php echo $staff_member['firstname'] . ' ' . $staff_member['lastname']; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            
                                <div class="col-sm-3">
                                    <label><?php echo _l('Lead Source'); ?></label>
                                    <select id="filter_source_id" class="selectpicker" data-width="100%" data-live-search="true">
                                        <option value=""><?php echo _l('All Sources'); ?></option>
                                        <?php foreach ($sources as $source) { ?>
                                            <option value="<?php echo $source['id']; ?>"><?php echo $source['name']; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>


                            <hr class="hr-panel-separator">
                        </div>
                        <?php echo render_datetime_input('scheduled_send_time', 'scheduled_send_time', $campaign['scheduled_send_time'] ?? '', ['data-date-min-date' => date('Y-m-d')]); ?>
                    </div>
                </div>
                <div class="variableDetails hide">
                    <div class="panel_s">
                        <div class="panel-body">
                            <div class="tw-flex tw-justify-between tw-items-center">
                                <h4 class="tw-mt-0 tw-font-semibold tw-text-neutral-700 no-margin"><?php echo _l('variables'); ?></h4>
                                <span class="text-muted"><?php echo _l('merge_field_note'); ?></span>
                            </div>
                            <div class="clearfix"></div>
                            <hr class="hr-panel-separator">
                            <div class="variables"></div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="variableDetails hide">
                <div class="col-md-6">
                    <div class="row" id="preview_message">
                        <div class="col-md-12">
                            <div class="panel_s">
                                <div class="panel-body">
                                    <h4 class="tw-mt-0 tw-font-semibold tw-text-neutral-700 no-margin"><?php echo _l('preview'); ?></h4>
                                    <div class="clearfix"></div>
                                    <hr class="hr-panel-separator">
                                    <div class="padding" style='background: url(" <?php echo module_dir_url(WHATSAPP_MODULE, 'assets/images/bg.jpg'); ?>");'>
                                        <div class="wtc_panel previewImage"></div>
                                        <div class="panel_s no-margin">
                                            <div class="panel-body previewmsg"></div>
                                        </div>
                                        <div class="previewBtn"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="panel_s">
                                <div class="panel-body">
                                    <h4 class="tw-mt-0 tw-font-semibold tw-text-neutral-700 no-margin"><?php echo _l('send_campaign'); ?></h4>
                                    <div class="clearfix"></div>
                                    <hr class="hr-panel-separator">
                                    <p><?php echo _l('send_to'); ?> : <span class="totalCount"></span></p>
                                    <button type="submit" class="btn btn-danger mtop15"><?php echo _l('send_campaign'); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php echo form_close(); ?>
    </div>
</div>
<?php init_tail(); ?>
<script>
"use strict";

// Form validation
appValidateForm($('#campaign_form'), {
    'name': 'required',
    'template_id': 'required',
    'rel_type': 'required',
    'lead_ids[]': {
        required: {
            depends: function() {
                return $('#rel_type').val() == 'leads';
            }
        }
    },
    'contact_ids[]': {
        required: {
            depends: function() {
                return $('#rel_type').val() == 'contacts';
            }
        }
    },
    'group_id[]': {
        required: {
            depends: function() {
                return $('#rel_type').val() == 'whatsapp_groups';
            }
        }
    }
});

// Update fields based on relation type selection
$('#rel_type').on('change', function() {
    const selectedType = $(this).val();
    toggleRelationTypeFields(selectedType);
});

// Update total count when specific IDs are selected
$('#lead_ids\\[\\], #contact_ids\\[\\], #group_id\\[\\]').on('change', function() {
    updateTotalCount();
});

// Toggle fields based on selected relation type
function toggleRelationTypeFields(selectedType) {
    // Show the filter-wrapper only if the relation type is leads
    if (selectedType === 'leads') {
        $('.filter-wrapper').removeClass('hide');
    } else {
        $('.filter-wrapper').addClass('hide');
    }

    // Show or hide specific fields
    $('.relation_wrapper').removeClass('hide');
    $('.lead_ids, .contact_ids, .group_id').addClass('hide');

    if (selectedType === 'leads') {
        $('.lead_ids').removeClass('hide');
    } else if (selectedType === 'contacts') {
        $('.contact_ids').removeClass('hide');
    } else if (selectedType === 'whatsapp_groups') {
        $('.group_id').removeClass('hide');
    } else {
        $('.relation_wrapper').addClass('hide');
    }
}

// Update the total count display
function updateTotalCount() {
    let selectedCount = 0;
    const relType = $('#rel_type').val();

    if (relType === 'leads') {
        selectedCount = $('#lead_ids\\[\\] option:selected').length;
    } else if (relType === 'contacts') {
        selectedCount = $('#contact_ids\\[\\] option:selected').length;
    } else if (relType === 'whatsapp_groups') {
        selectedCount = $('#group_id\\[\\] option:selected').length;
    }

    $('.totalCount').text(selectedCount + ' ' + $('#rel_type :selected').text());
}
function fetchFilteredLeads() {
    const statusId = $('#filter_status_id').val();
    const assignedId = $('#filter_assigned_id').val();
    const sourceId = $('#filter_source_id').val();

    $.ajax({
        url: admin_url + 'whatsapp/campaigns/get_filtered_leads',
        type: 'POST',
        data: {
            status_id: statusId,
            assigned_id: assignedId,
            source_id: sourceId,
        },
        success: function (response) {
            const leads = JSON.parse(response);
            const leadSelect = $('#lead_ids\\[\\]');

            leadSelect.empty();

            leads.forEach(lead => {
                leadSelect.append(new Option(lead.name + ' (' + lead.phonenumber + ')', lead.id));
            });
            leadSelect.selectpicker('refresh');
            updateTotalCount();
        }
    });
}


// Event listeners for filter changes
$('#filter_status_id, #filter_assigned_id, #filter_source_id').on('change', function () {
    fetchFilteredLeads();
});

// Update the total count display
function updateTotalCount() {
    const selectedCount = $('#lead_ids\\[\\] option:selected').length;
    $('.totalCount').text(selectedCount + ' leads selected');
}

// Initial trigger to set the form on load if editing
<?php if (isset($campaign)) { ?>
    $('#template_id,  #rel_type').trigger('change');
<?php } ?>

</script>
