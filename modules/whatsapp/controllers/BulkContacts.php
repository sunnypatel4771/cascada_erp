<?php

class BulkContacts extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Contact_model');
        $this->load->model('Group_model');
    }

    public function index()
    {
        $this->Contact_model->fix_unique_contacts();
        $data['contacts'] = $this->Contact_model->get_all_contacts_with_groups();
        $data['groups'] = $this->Group_model->get_all_groups();
        $this->load->view('bulkcontacts/index', $data);
    }

    public function create()
    {
        $data['groups'] = $this->Group_model->get_all_groups();
        $this->load->view('bulkcontacts/form', $data);
    }

    public function edit($id)
    {
        $data['contact'] = $this->Contact_model->get_contact($id);
        $data['groups'] = $this->Group_model->get_all_groups();
        $data['contact_groups'] = $this->Contact_model->get_contact_groups($id); // Fetch associated groups
        $this->load->view('bulkcontacts/form', $data);
    }

    public function store()
    {
        $data = $this->input->post();

        // Store groups separately and remove from contact data
        $groups = isset($data['groups']) ? $data['groups'] : [];
        unset($data['groups']);

        // Create contact
        $contact_id = $this->Contact_model->create_contact($data);

        // Assign groups to the contact if provided
        if ($contact_id && !empty($groups)) {
            $this->Contact_model->assign_groups($contact_id, $groups);
        }

        $this->session->set_flashdata('success', 'Contact created successfully.');
        redirect('whatsapp/BulkContacts');
    }

    public function update($id)
    {
        $data = $this->input->post();

        // Store groups separately and remove from contact data
        $groups = isset($data['groups']) ? $data['groups'] : [];
        unset($data['groups']);

        // Update contact
        $this->Contact_model->update_contact($id, $data);

        // Update group assignments
        $this->Contact_model->assign_groups($id, $groups);

        $this->session->set_flashdata('success', 'Contact updated successfully.');
        redirect('whatsapp/BulkContacts');
    }

    public function delete($id)
    {
        if ($this->Contact_model->delete_contact($id)) {
            $this->session->set_flashdata('success', 'Contact deleted successfully.');
        } else {
            $this->session->set_flashdata('error', 'Error deleting contact.');
        }
        redirect('whatsapp/BulkContacts');
    }

public function import() {
    // Check if a file was uploaded
    if (!empty($_FILES['file']['tmp_name'])) {
        $file = $_FILES['file']['tmp_name'];
        $groups = $this->input->post('groups') ?? [];
        $import_errors = [];

        if (($handle = fopen($file, 'r')) !== false) {
            fgetcsv($handle); // Skip header row
            $this->db->trans_start();

            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $contactData = [
                    'name' => $data[0],
                    'phonenumber' => $data[1],
                    'company_name' => $data[2],
                    'address' => $data[3],
                    'city' => $data[4],
                    'state' => $data[5],
                    'country' => $data[6],
                    'description' => $data[7]
                ];

                // Check if the phone number already exists
                $existing_contact = $this->db->get_where('whatsapp_contacts', ['phone' => $data[1]])->row();

                if ($existing_contact) {
                    // If the contact exists, get its ID
                    $contact_id = $existing_contact->id;

                    // Assign groups to the existing contact
                    if (!empty($groups)) {
                        // Assign only groups that are not already linked
                        $this->Contact_model->assign_groups($contact_id, $groups);
                    }
                } else {
                    // Insert new contact and get its ID
                    $contact_id = $this->Contact_model->create_contact($contactData);
                    if ($contact_id && !empty($groups)) {
                        // Assign groups to the new contact
                        $this->Contact_model->assign_groups($contact_id, $groups);
                    } else {
                        $import_errors[] = "Failed to import contact: " . json_encode($contactData);
                    }
                }
            }

            fclose($handle);
            $this->db->trans_complete();

            if ($this->db->trans_status() && empty($import_errors)) {
                $this->session->set_flashdata('success', 'Contacts imported successfully with group assignments.');
            } else {
                $this->session->set_flashdata('error', 'Some contacts could not be imported.');
                log_message('error', 'Import errors: ' . print_r($import_errors, true));
            }
        } else {
            $this->session->set_flashdata('error', 'Failed to open uploaded file.');
        }
    } else {
        $this->session->set_flashdata('error', 'No file uploaded.');
    }

    redirect('whatsapp/BulkContacts');
}



    public function bulk_delete()
    {
        $contact_ids = $this->input->post('contact_ids');
        if (!empty($contact_ids)) {
            $this->db->trans_start();
            foreach ($contact_ids as $contact_id) {
                $this->Contact_model->delete_contact($contact_id);
            }
            $this->db->trans_complete();

            if ($this->db->trans_status()) {
                $this->session->set_flashdata('success', 'Selected contacts deleted successfully.');
            } else {
                $this->session->set_flashdata('error', 'Error occurred while deleting contacts.');
            }
        } else {
            $this->session->set_flashdata('error', 'No contacts selected for deletion.');
        }

        redirect('whatsapp/BulkContacts');
    }

    public function bulk_assign_groups()
    {
        $contact_ids = explode(',', $this->input->post('contact_ids'));
        $group_ids = $this->input->post('bulk_groups');

        if (!empty($contact_ids) && !empty($group_ids)) {
            $this->db->trans_start();
            foreach ($contact_ids as $contact_id) {
                $this->Contact_model->assign_groups($contact_id, $group_ids);
            }
            $this->db->trans_complete();

            if ($this->db->trans_status()) {
                $this->session->set_flashdata('success', 'Groups assigned to selected contacts successfully.');
            } else {
                $this->session->set_flashdata('error', 'Error occurred while assigning groups.');
            }
        } else {
            $this->session->set_flashdata('error', 'Please select both contacts and groups to assign.');
        }

        redirect('whatsapp/BulkContacts');
    }
}
