<?php
defined('BASEPATH') || exit('No direct script access allowed');

class Botflow_model extends CI_Model
{
    protected $table = 'botflows';

    public function __construct()
    {
        parent::__construct();
        $this->table = db_prefix() . $this->table;
    }

    // Create a new bot flow
    public function create($data)
    {
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    // Read a bot flow by ID
    public function get($id)
    {
        return $this->db->where('id', $id)->get($this->table)->row_array();
    }

    // Read all bot flows
    public function get_all()
    {
        return $this->db->get($this->table)->result_array();
    }

    // Update a bot flow by ID
    public function update($id, $data)
    {
        return $this->db->where('id', $id)->update($this->table, $data);
    }

    // Delete a bot flow by ID
    public function delete($id)
    {
        return $this->db->where('id', $id)->delete($this->table);
    }
}
