<?php

class Contact_model extends CI_Model {

    /**
     * Fetch all contacts without groups.
     *
     * @return array List of all contacts.
     */
    public function get_all_contacts() {
        return $this->db->get('whatsapp_contacts')->result();
    }

    /**
     * Fetch a single contact by ID.
     *
     * @param int $id Contact ID.
     * @return object|null Contact details, or null if not found.
     */
    public function get_contact($id) {
        return $this->db->get_where('whatsapp_contacts', ['id' => $id])->row();
    }
    
    public function get_contact_groups($contact_id) {
        $this->db->select('group_id');
        $this->db->from('whatsapp_contact_group');
        $this->db->where('contact_id', $contact_id);
        
        $query = $this->db->get();
        return array_column($query->result_array(), 'group_id'); // Return an array of group IDs
    }
    /**
     * Fetch all contacts with associated groups.
     * Groups are returned as a comma-separated list in the 'groups' field.
     *
     * @return array List of all contacts with their groups.
     */
public function get_all_contacts_with_groups() {
    // Define table names with prefixes
    $contacts_table = db_prefix() . 'whatsapp_contacts';
    $groups_table = db_prefix() . 'whatsapp_groups';
    $contact_group_table = db_prefix() . 'whatsapp_contact_group';

    // Perform the query
    $this->db->select("{$contacts_table}.id, 
                       {$contacts_table}.name, 
                       {$contacts_table}.phonenumber, 
                       {$contacts_table}.company_name, 
                       {$groups_table}.name as group_name")
             ->from($contacts_table)
             ->join($contact_group_table, "{$contacts_table}.id = {$contact_group_table}.contact_id", 'left')
             ->join($groups_table, "{$contact_group_table}.group_id = {$groups_table}.id", 'left')
             ->order_by("{$contacts_table}.name", "ASC"); // Optional: order by name for better presentation

    // Execute the query and get results
    $query = $this->db->get();
    $results = $query->result();

    // Process results to store groups in an array
    $contacts = [];
    foreach ($results as $row) {
        $contact_id = $row->id;

        if (!isset($contacts[$contact_id])) {
            $contacts[$contact_id] = [
                'id' => $row->id,
                'name' => $row->name,
                'phonenumber' => $row->phonenumber,
                'company_name' => $row->company_name,
                'groups' => []
            ];
        }

        if (!empty($row->group_name)) {
            $contacts[$contact_id]['groups'][] = $row->group_name;
        }
    }

    // Convert associative array to indexed array
    return array_values($contacts);
}



public function fix_unique_contacts() {
    // Get all contacts grouped by phone number to find duplicates
    $this->db->select('phonenumber, COUNT(id) as contact_count');
    $this->db->group_by('phonenumber');
    $query = $this->db->get(db_prefix() . 'whatsapp_contacts');

    // Loop through each duplicate contact
    $duplicates = $query->result();
    foreach ($duplicates as $duplicate) {
        if ($duplicate->contact_count > 1) {
            // Get all contact IDs with the same phone number
            $this->db->select('id');
            $this->db->where('phonenumber', $duplicate->phonenumber);
            $contact_ids = $this->db->get(db_prefix() . 'whatsapp_contacts')->result_array();

            // Keep the first contact and delete the rest
            $main_contact_id = $contact_ids[0]['id'];
            array_shift($contact_ids); // Remove the first one, as it's kept

            // Delete duplicate contacts
            foreach ($contact_ids as $contact_id) {
                $this->db->delete(db_prefix() . 'whatsapp_contacts', ['id' => $contact_id['id']]);
                // Optionally: Merge contact groups here if needed (this part can be handled in sync_group_ids)
            }

            // Update groups for the main contact (if needed, see next function)
            $this->sync_group_ids($main_contact_id);
        }
    }
}

public function sync_group_ids($contact_id = null) {
    // If no specific contact ID is passed, sync for all contacts
    if (!$contact_id) {
        // Get all contacts
        $this->db->select('contact_id');
        $this->db->distinct();
        $contacts = $this->db->get(db_prefix() . 'whatsapp_contact_group')->result_array();

        // Loop through each contact and sync groups
        foreach ($contacts as $contact) {
            $this->sync_group_ids($contact['contact_id']);
        }
    } else {
        // Ensure no duplicate group assignments for a specific contact
        $this->db->select('contact_id, group_id');
        $this->db->where('contact_id', $contact_id);
        $this->db->group_by('contact_id, group_id');
        $query = $this->db->get(db_prefix() . 'whatsapp_contact_group');

        // Check and delete duplicate entries
        $group_ids = [];
        foreach ($query->result() as $row) {
            if (in_array($row->group_id, $group_ids)) {
                // Delete the duplicate group assignment
                $this->db->delete(db_prefix() . 'whatsapp_contact_group', [
                    'contact_id' => $contact_id,
                    'group_id' => $row->group_id
                ]);
            } else {
                // Add group_id to the array to track unique group assignments
                $group_ids[] = $row->group_id;
            }
        }
    }
}

    /**
     * Create a new contact.
     *
     * @param array $data Contact data to insert.
     * @return bool|int Insert ID on success, false on failure.
     */
    public function create_contact($data) {
        if ($this->db->insert('whatsapp_contacts', $data)) {
            return $this->db->insert_id();
        }
        return false;
    }

    /**
     * Update an existing contact by ID.
     *
     * @param int $id Contact ID.
     * @param array $data Updated contact data.
     * @return bool True on success, false on failure.
     */
    public function update_contact($id, $data) {
        return $this->db->update('whatsapp_contacts', $data, ['id' => $id]);
    }

    /**
     * Delete a contact by ID.
     *
     * @param int $id Contact ID.
     * @return bool True on success, false on failure.
     */
    public function delete_contact($id) {
        return $this->db->delete('whatsapp_contacts', ['id' => $id]);
    }

    /**
     * Assign a single group to a contact.
     *
     * @param int $contact_id Contact ID.
     * @param int $group_id Group ID.
     * @return bool True on success, false on failure.
     */
    public function assign_group($contact_id, $group_id) {
        // Check if the contact is already assigned to the group
        $existing_assignment = $this->db->get_where('whatsapp_contact_group', [
            'contact_id' => $contact_id,
            'group_id' => $group_id
        ])->row();
    
        // If the assignment doesn't exist, insert it
        if (!$existing_assignment) {
            $data = [
                'contact_id' => $contact_id,
                'group_id' => $group_id
            ];
            return $this->db->insert('whatsapp_contact_group', $data);
        }
    
        // If the assignment already exists, return false or handle it accordingly
        return false; // or you could return a custom message or handle the situation as needed
    }


    /**
     * Assign multiple groups to a contact.
     * Clears existing groups before assigning new ones.
     *
     * @param int $contact_id Contact ID.
     * @param array $group_ids List of group IDs to assign.
     */
    public function assign_groups($contact_id, $group_ids) {
        // Clear existing groups for the contact
        $this->db->where('contact_id', $contact_id)->delete('whatsapp_contact_group');

        // Assign new groups
        foreach ($group_ids as $group_id) {
            $this->assign_group($contact_id, $group_id);
        }
    }
    
    
    /**
 * Fetch contacts by group ID.
 *
 * @param int $group_id Group ID.
 * @return array List of contacts in the specified group.
 */
public function get_contacts_by_group($group_id) {
    // Define table names with prefixes
    $contacts_table = db_prefix() . 'whatsapp_contacts';
    $contact_group_table = db_prefix() . 'whatsapp_contact_group';

    $this->db->select("{$contacts_table}.id, {$contacts_table}.name, {$contacts_table}.phonenumber, {$contacts_table}.company_name")
             ->from($contacts_table)
             ->join($contact_group_table, "{$contacts_table}.id = {$contact_group_table}.contact_id", 'inner')
             ->where("{$contact_group_table}.group_id", $group_id);

    $query = $this->db->get();
    return $query->result();
}

}
