<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Fe_sat_send_email extends App_mail_template
{
    protected $for = 'customer';

    protected $document;
    protected $invoice;
    protected $contact;
    protected $files;

    public $slug = 'fe_sat_send_email';
    public $rel_type = 'perfex_fesat';

    public function __construct($document, $invoice, $contact, array $files = [])
    {
        parent::__construct();
        $this->document = $document;
        $this->invoice  = $invoice;
        $this->contact  = $contact;
        $this->files    = $files;
    }

    public function build()
    {
        foreach ($this->files as $filePath) {
            if (!$filePath || !is_file($filePath)) {
                continue;
            }
            $mime = function_exists('mime_content_type') ? mime_content_type($filePath) : 'application/octet-stream';
            $this->add_attachment([
                'attachment' => $filePath,
                'filename'   => basename($filePath),
                'type'       => $mime,
                'read'       => true,
            ]);
        }

        $this->to($this->contact->email)
            ->set_rel_id($this->invoice->id)
            ->set_merge_fields('client_merge_fields', $this->invoice->clientid, $this->contact->id)
            ->set_merge_fields('invoice_merge_fields', $this->invoice->id)
            ->set_merge_fields([
                '{fe_sat_uuid}'          => $this->document->digibox_uuid,
                '{fe_sat_invoice_number}' => format_invoice_number($this->invoice->id),
            ]);
    }
}
