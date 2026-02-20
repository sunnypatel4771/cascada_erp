<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Automation Model
 *
 * Handles database operations for automation runs tracking
 */
class Automation_model extends App_Model
{
    protected $table;

    public function __construct()
    {
        parent::__construct();
        $this->table = db_prefix() . 'ramos_automation_runs';
    }

    /**
     * Create a new automation run record
     *
     * @param  array $data
     * @return int Run ID
     */
    public function create_run(array $data): int
    {
        $payload = [
            'run_type' => $data['run_type'] ?? 'purchase_generation',
            'run_by'   => $data['run_by'] ?? get_staff_user_id(),
            'status'   => $data['status'] ?? 'running',
            'run_at'   => date('Y-m-d H:i:s'),
        ];

        $this->db->insert($this->table, $payload);

        return (int) $this->db->insert_id();
    }

    /**
     * Complete an automation run
     *
     * @param  int   $runId
     * @param  array $data
     * @return bool
     */
    public function complete_run(int $runId, array $data): bool
    {
        $payload = [
            'completed_at' => date('Y-m-d H:i:s'),
        ];

        if (isset($data['status'])) {
            $payload['status'] = $data['status'];
        }

        if (isset($data['total_orders_processed'])) {
            $payload['total_orders_processed'] = (int) $data['total_orders_processed'];
        }

        if (isset($data['total_purchase_orders_created'])) {
            $payload['total_purchase_orders_created'] = (int) $data['total_purchase_orders_created'];
        }

        if (isset($data['notes'])) {
            $payload['notes'] = $data['notes'];
        }

        if (isset($data['summary'])) {
            $payload['summary'] = is_array($data['summary']) ? json_encode($data['summary']) : $data['summary'];
        }

        $this->db->where('id', $runId);

        return $this->db->update($this->table, $payload);
    }

    /**
     * Get recent automation runs
     *
     * @param  int $limit
     * @return array
     */
    public function get_recent_runs(int $limit = 10): array
    {
        $this->db->select('ar.*, CONCAT(s.firstname, " ", s.lastname) as run_by_name');
        $this->db->from($this->table . ' ar');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = ar.run_by', 'left');
        $this->db->order_by('ar.run_at', 'DESC');
        $this->db->limit($limit);

        return $this->db->get()->result_array();
    }

    /**
     * Get automation run by ID
     *
     * @param  int $runId
     * @return array
     */
    public function get_run(int $runId): array
    {
        $this->db->select('ar.*, CONCAT(s.firstname, " ", s.lastname) as run_by_name');
        $this->db->from($this->table . ' ar');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = ar.run_by', 'left');
        $this->db->where('ar.id', $runId);

        $run = $this->db->get()->row_array();

        return $run ?: [];
    }

    /**
     * Get automation runs with filters
     *
     * @param  array $filters
     * @return array
     */
    public function get_runs(array $filters = []): array
    {
        $this->db->select('ar.*, CONCAT(s.firstname, " ", s.lastname) as run_by_name');
        $this->db->from($this->table . ' ar');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = ar.run_by', 'left');

        if (isset($filters['status'])) {
            $this->db->where('ar.status', $filters['status']);
        }

        if (isset($filters['run_type'])) {
            $this->db->where('ar.run_type', $filters['run_type']);
        }

        if (isset($filters['date_from'])) {
            $this->db->where('DATE(ar.run_at) >=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $this->db->where('DATE(ar.run_at) <=', $filters['date_to']);
        }

        $this->db->order_by('ar.run_at', 'DESC');

        if (isset($filters['limit'])) {
            $this->db->limit($filters['limit']);
        }

        return $this->db->get()->result_array();
    }
}
