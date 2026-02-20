<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class QuickReply_model extends CI_Model {

    private $table = 'quick_replies';

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    public function get_all_replies() {
        return $this->db->get($this->table)->result();
    }

    public function get_reply($id) {
        return $this->db->get_where($this->table, ['id' => $id])->row();
    }

    public function insert_reply($data) {
        return $this->db->insert($this->table, $data);
    }

    public function update_reply($id, $data) {
        return $this->db->where('id', $id)->update($this->table, $data);
    }

    public function delete_reply($id) {
        return $this->db->delete($this->table, ['id' => $id]);
    }
}
?>
