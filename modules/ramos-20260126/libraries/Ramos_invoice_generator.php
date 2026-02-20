<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ramos_invoice_generator
{
    protected $ci;

    public function __construct()
    {
        $this->ci = &get_instance();

        $this->ci->load->model('ramos/orders_model', 'orders_model');
        $this->ci->load->model('ramos/order_items_model', 'order_items_model');
        $this->ci->load->model('ramos/pricing_model', 'pricing_model');
        $this->ci->load->model('ramos/inventory_model', 'inventory_model');
        $this->ci->load->model('invoices_model');
        $this->ci->load->model('clients_model');
        $this->ci->load->model('currencies_model');
        $this->ci->load->helper('invoices');
    }

    /**
     * Attempt to create an invoice for the supplied order.
     *
     * @param  int $orderId
     * @return array{success:bool, message:?string, invoice_id:?int, warnings:array}
     */
    public function generate_for_order(int $orderId): array
    {
        $order = $this->ci->orders_model->get($orderId);

        if (empty($order)) {
            return [
                'success'    => false,
                'message'    => null,
                'invoice_id' => null,
                'warnings'   => [],
            ];
        }

        if (!empty($order['invoice_id'])) {
            return [
                'success'    => false,
                'message'    => null,
                'invoice_id' => (int) $order['invoice_id'],
                'warnings'   => [],
            ];
        }

        if ($order['status'] !== RAMOS_ORDER_STATUS_READY) {
            return [
                'success'    => false,
                'message'    => null,
                'invoice_id' => null,
                'warnings'   => [],
            ];
        }

        $clientId = $order['client_id'] ? (int) $order['client_id'] : null;

        if (!$clientId && !empty($order['customer_reference'])) {
            $clientId = $this->resolveClientFromContact((int) $order['customer_reference']);

            if ($clientId && empty($order['client_id'])) {
                $this->ci->orders_model->update($orderId, ['client_id' => $clientId]);
            }
        }

        if (!$clientId) {
            return [
                'success'    => false,
                'message'    => sprintf(_l('ramos_invoicing_missing_customer'), $order['order_number']),
                'invoice_id' => null,
                'warnings'   => [],
            ];
        }

        $client = $this->ci->clients_model->get($clientId);
        if (!$client) {
            return [
                'success'    => false,
                'message'    => sprintf(_l('ramos_invoicing_missing_customer'), $order['order_number']),
                'invoice_id' => null,
                'warnings'   => [],
            ];
        }

        $items = $this->ci->order_items_model->get(null, ['order_id' => $orderId]);

        if (empty($items)) {
            return [
                'success'    => false,
                'message'    => null,
                'invoice_id' => null,
                'warnings'   => [],
            ];
        }

        $invoiceItems   = [];
        $warnings       = [];
        $currencyId     = null;
        $missingPrices  = 0;
        $itemOrder      = 1;

        foreach ($items as $item) {
            $inventoryId = !empty($item['inventory_item_id']) ? (int) $item['inventory_item_id'] : null;
            $inventory   = $inventoryId ? $this->ci->inventory_model->get($inventoryId) : null;

            $priceRule = $inventoryId ? $this->ci->pricing_model->get_price_for_customer($inventoryId, $clientId) : null;

            if ($priceRule) {
                $lineCurrency = $priceRule['currency'] ? (int) $priceRule['currency'] : null;
                if ($lineCurrency && $currencyId === null) {
                    $currencyId = $lineCurrency;
                }

                $unitPrice = (float) $priceRule['price'];
                if (!empty($priceRule['discount_percent'])) {
                    $unitPrice = $unitPrice * (1 - ((float) $priceRule['discount_percent'] / 100));
                }
            } else {
                $unitPrice = 0.00;
                $missingPrices++;
            }

            $invoiceItems[] = [
                'description'      => $item['item_name'],
                'long_description' => $unitPrice === 0 ? _l('ramos_invoicing_line_missing_price') : '',
                'qty'              => (float) $item['quantity'],
                'rate'             => $unitPrice,
                'unit'             => $inventory['unit'] ?? 'unit',
                'order'            => $itemOrder++,
                'taxname'          => [],
            ];
        }

        if ($missingPrices === count($items)) {
            return [
                'success'    => false,
                'message'    => sprintf(_l('ramos_invoicing_missing_prices'), $order['order_number'], $missingPrices),
                'invoice_id' => null,
                'warnings'   => [],
            ];
        }

        if ($currencyId === null) {
            $currencyId = $this->ci->clients_model->get_customer_default_currency($clientId);
            if (!$currencyId) {
                $currencyId = $this->ci->currencies_model->get_base_currency()->id;
            }
        }

        $invoiceData = [
            'clientid'             => $clientId,
            'date'                 => date('Y-m-d'),
            'duedate'              => date('Y-m-d', strtotime('+1 day')),
            'currency'             => $currencyId,
            'allowed_payment_modes'=> [],
            'newitems'             => $invoiceItems,
            'billing_street'       => clear_textarea_breaks($client->billing_street ?? ''),
            'billing_city'         => $client->billing_city ?? '',
            'billing_state'        => $client->billing_state ?? '',
            'billing_zip'          => $client->billing_zip ?? '',
            'billing_country'      => $client->billing_country ?? '',
            'shipping_street'      => clear_textarea_breaks($client->shipping_street ?? ''),
            'shipping_city'        => $client->shipping_city ?? '',
            'shipping_state'       => $client->shipping_state ?? '',
            'shipping_zip'         => $client->shipping_zip ?? '',
            'shipping_country'     => $client->shipping_country ?? '',
            'show_quantity_as'     => 1,
        ];

        $invoiceId = $this->ci->invoices_model->add($invoiceData);

        if (!$invoiceId) {
            return [
                'success'    => false,
                'message'    => sprintf(_l('ramos_invoicing_failed'), $order['order_number']),
                'invoice_id' => null,
                'warnings'   => $warnings,
            ];
        }

        $updatePayload = [
            'invoice_id'  => $invoiceId,
            'invoiced_at' => date('Y-m-d H:i:s'),
        ];

        $this->ci->orders_model->update($orderId, $updatePayload);

        if ($missingPrices > 0) {
            $warnings[] = sprintf(_l('ramos_invoicing_missing_prices'), $order['order_number'], $missingPrices);
        }

        return [
            'success'    => true,
            'message'    => sprintf(_l('ramos_invoicing_created'), format_invoice_number($invoiceId), $order['order_number']),
            'invoice_id' => $invoiceId,
            'warnings'   => $warnings,
        ];
    }

    protected function resolveClientFromContact(int $contactId): ?int
    {
        $row = $this->ci->db
            ->select('userid')
            ->from(db_prefix() . 'contacts')
            ->where('id', $contactId)
            ->get()
            ->row();

        if ($row && !empty($row->userid)) {
            return (int) $row->userid;
        }

        return null;
    }
}
