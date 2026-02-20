<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Notifications_model extends App_Model
{
    protected $table;

    public function __construct()
    {
        parent::__construct();
        $this->table = db_prefix() . 'ramos_notifications';
    }

    public function record(string $type, string $title, string $message, array $data = []): int
    {
        $payload = $this->normalizePayload($type, $title, $message, $data);

        $this->db->insert($this->table, $payload);

        return (int) $this->db->insert_id();
    }

    public function ensure(string $type, string $title, string $message, array $data = []): int
    {
        $payload     = $this->normalizePayload($type, $title, $message, $data);
        $contextType = $payload['context_type'];
        $contextId   = $payload['context_id'];

        $existing = $this->find_open($type, $contextType, $contextId);

        if (!empty($existing)) {
            $payload['updated_at'] = date('Y-m-d H:i:s');

            $this->db->where('id', $existing['id']);
            $this->db->update($this->table, [
                'title'        => $payload['title'],
                'message'      => $payload['message'],
                'severity'     => $payload['severity'],
                'metadata'     => $payload['metadata'],
                'updated_at'   => $payload['updated_at'],
                'resolved_at'  => null,
                'resolved_by'  => null,
                'acknowledged_at' => null,
                'acknowledged_by' => null,
            ]);

            return (int) $existing['id'];
        }

        $this->db->insert($this->table, $payload);

        return (int) $this->db->insert_id();
    }

    public function get_recent(int $limit = 10): array
    {
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit($limit);

        return $this->db->get($this->table)->result_array();
    }

    public function acknowledge($id): bool
    {
        $now = date('Y-m-d H:i:s');

        $this->db->where('id', (int) $id);

        return $this->db->update($this->table, [
            'acknowledged_at' => $now,
            'acknowledged_by' => get_staff_user_id(),
        ]);
    }

    public function resolve($id, ?int $staffId = null): bool
    {
        $now = date('Y-m-d H:i:s');
        $staffId = $staffId && $staffId > 0 ? $staffId : null;

        $this->db->where('id', (int) $id);

        return $this->db->update($this->table, [
            'resolved_at' => $now,
            'resolved_by' => $staffId,
        ]);
    }

    public function resolve_by_context(string $type, ?string $contextType, $contextId, ?int $staffId = null): bool
    {
        $staffId = $staffId && $staffId > 0 ? $staffId : null;

        $this->db->where('type', $type);

        if ($contextType !== null) {
            $this->db->where('context_type', $contextType);
        } else {
            $this->db->where('context_type IS NULL', null, false);
        }

        if ($contextId === null) {
            $this->db->where('context_id IS NULL', null, false);
        } else {
            $this->db->where('context_id', (int) $contextId);
        }

        return $this->db->update($this->table, [
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolved_by' => $staffId,
        ]);
    }

    public function find_open(string $type, ?string $contextType, $contextId): array
    {
        $this->db->where('type', $type);
        $this->db->where('resolved_at IS NULL', null, false);

        if ($contextType !== null) {
            $this->db->where('context_type', $contextType);
        } else {
            $this->db->where('context_type IS NULL', null, false);
        }

        if ($contextId === null) {
            $this->db->where('context_id IS NULL', null, false);
        } else {
            $this->db->where('context_id', (int) $contextId);
        }

        $result = $this->db->get($this->table)->row_array();

        return $result ?: [];
    }

    public function get_open_by_type(string $type): array
    {
        $this->db->where('type', $type);
        $this->db->where('resolved_at IS NULL', null, false);

        return $this->db->get($this->table)->result_array();
    }

    public function resolve_missing_contexts(string $type, array $activeContextIds): void
    {
        $activeContextIds = array_map('intval', $activeContextIds);

        $open = $this->get_open_by_type($type);

        foreach ($open as $notification) {
            $contextId = isset($notification['context_id']) ? (int) $notification['context_id'] : null;

            if ($contextId !== null && !in_array($contextId, $activeContextIds, true)) {
                $this->resolve($notification['id'], null);
            }
        }
    }

    protected function normalizePayload(string $type, string $title, string $message, array $data): array
    {
        $severity = isset($data['severity']) ? trim((string) $data['severity']) : 'info';
        $contextType = isset($data['context_type']) ? trim((string) $data['context_type']) : null;
        $contextId = isset($data['context_id']) ? (int) $data['context_id'] : null;
        $metadata = isset($data['metadata']) ? $data['metadata'] : [];

        if (!is_array($metadata)) {
            $metadata = [$metadata];
        }

        return [
            'type'          => $type,
            'severity'      => $severity !== '' ? $severity : 'info',
            'title'         => $title,
            'message'       => $message,
            'context_type'  => $contextType !== '' ? $contextType : null,
            'context_id'    => $contextId > 0 ? $contextId : null,
            'metadata'      => !empty($metadata) ? json_encode($metadata) : null,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ];
    }
}
