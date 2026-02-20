<?php

class Group_model extends CI_Model {

    /**
     * Get all groups with their contact counts.
     *
     * @return array The list of groups with contact counts.
     */
public function get_all_groups() {
    // Step 1: Get all groups without contact count
    $groups = $this->db->get('whatsapp_groups')->result_array();

    // Step 2: Get all contact-group associations
    $this->db->select('group_id');
    $contactGroups = $this->db->get('whatsapp_contact_group')->result_array();

    // Step 3: Initialize contact counts for each group
    $groupCounts = array_count_values(array_column($contactGroups, 'group_id'));

    // Step 4: Attach contact count to each group
    foreach ($groups as &$group) {
        $group['contacts_count'] = isset($groupCounts[$group['id']]) ? $groupCounts[$group['id']] : 0;
    }

    return $groups;
}




    /**
     * Get a specific group by ID.
     *
     * @param int $id The group ID.
     * @return object|null The group data.
     */
    public function get_group($id) {
        return $this->db->get_where('whatsapp_groups', ['id' => $id])->row();
    }

    /**
     * Create a new group.
     *
     * @param array $data The group data.
     * @return bool Whether the insert was successful.
     */
    public function create_group($data) {
        return $this->db->insert('whatsapp_groups', $data);
    }

    /**
     * Update an existing group.
     *
     * @param int $id The group ID.
     * @param array $data The group data to update.
     * @return bool Whether the update was successful.
     */
    public function update_group($id, $data) {
        return $this->db->update('whatsapp_groups', $data, ['id' => $id]);
    }

    /**
     * Delete a group.
     *
     * @param int $id The group ID.
     * @return bool Whether the delete was successful.
     */
    public function delete_group($id) {
        return $this->db->delete('whatsapp_groups', ['id' => $id]);
    }
}
