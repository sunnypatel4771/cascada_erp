<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Fe_sat_cfdi_builder
{
    public function calculateTotals($invoice): array
    {
        $items = $invoice->items ?? [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $qty   = isset($item['qty']) ? (float) $item['qty'] : (float) ($item['quantity'] ?? 0);
            $rate  = isset($item['rate']) ? (float) $item['rate'] : (float) ($item['item_rate'] ?? 0);
            $line  = round($qty * $rate, 2);
            $subtotal += $line;
        }

        $subtotal = round($subtotal, 2);
        $iva      = round($subtotal * 0.16, 2);
        $total    = round($subtotal + $iva, 2);

        return [
            'subtotal'         => $subtotal,
            'iva'              => $iva,
            'total'            => $total,
            'amount_in_words'  => $this->amountToWords($total, $invoice->currency_name ?? 'MXN'),
        ];
    }

    public function build($invoice, array $formData): array
    {
        $errors = [];

        $totals = $this->calculateTotals($invoice);

        $serie        = trim((string) ($formData['serie'] ?? get_option('perfex_fesat_series_default')));
        $folio        = trim((string) ($formData['folio'] ?? get_option('perfex_fesat_folio_next')));
        $usoCfdi      = trim((string) ($formData['uso_cfdi'] ?? get_option('perfex_fesat_cfdi_use_default')));
        $paymentMethod= trim((string) ($formData['payment_method'] ?? get_option('perfex_fesat_payment_method')));
        $paymentForm  = trim((string) ($formData['payment_form'] ?? get_option('perfex_fesat_payment_form')));
        $currency     = trim((string) ($formData['currency'] ?? $invoice->currency_name ?? 'MXN'));
        $sandbox      = !empty($formData['sandbox']);

        // HARDCODED TEST DATA - Using generic public RFC for testing
        // TODO: Remove this and use real client data once testing is complete
        $customerRfc = 'XAXX010101000';      // Generic public RFC
        $customerName = 'PUBLICO EN GENERAL'; // Generic public name
        $customerRegimen = '616';             // Sin obligaciones fiscales

        // Original code - will be used after testing
        // $customerRfc  = strtoupper(trim((string) ($formData['customer_rfc'] ?? $invoice->client->vat ?? '')));
        // $customerName = trim((string) ($formData['customer_name'] ?? $invoice->client->company ?? ''));
        // $customerRegimen = trim((string) ($formData['customer_regimen'] ?? '601'));

        if (!empty($invoice->client) && empty($customerRegimen)) {
            $customerRegimen = '601';
        }

        $issuer = $this->resolveIssuer();
        if ($issuer['rfc'] === '') {
            $errors[] = _l('fe_sat_error_missing_company_rfc');
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors'  => $errors,
                'totals'  => $totals,
            ];
        }

        $conceptsXml = $this->buildConceptsXml($invoice->items ?? []);
        $trasladoBase = number_format($totals['subtotal'], 2, '.', '');
        $ivaAmount    = number_format($totals['iva'], 2, '.', '');

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $comprobante = $doc->createElement('cfdi:Comprobante');
        $comprobante->setAttribute('Version', '4.0');
        $comprobante->setAttribute('Serie', $serie ?: 'A');
        $comprobante->setAttribute('Folio', $folio ?: '1');
        $comprobante->setAttribute('Fecha', date('c'));
        $comprobante->setAttribute('SubTotal', number_format($totals['subtotal'], 2, '.', ''));
        // CFDI40111: Descuento attribute should only be included if there's an actual discount
        // For TipoDeComprobante='I', omit Descuento if it's 0.00
        // $comprobante->setAttribute('Descuento', '0.00');
        $comprobante->setAttribute('Moneda', $currency ?: 'MXN');
        $comprobante->setAttribute('Total', number_format($totals['total'], 2, '.', ''));
        $comprobante->setAttribute('TipoDeComprobante', 'I');
        $comprobante->setAttribute('Exportacion', '01');
        if ($paymentMethod) {
            $comprobante->setAttribute('MetodoPago', $paymentMethod);
        }
        if ($paymentForm) {
            $comprobante->setAttribute('FormaPago', $paymentForm);
        }
        $comprobante->setAttribute('LugarExpedicion', $issuer['zip']);
        $comprobante->setAttribute('xmlns:cfdi', 'http://www.sat.gob.mx/cfd/4');
        $comprobante->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $comprobante->setAttribute('xsi:schemaLocation', 'http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd');

        // InformacionGlobal node - Required when RFC is XAXX010101000 (Publico en General)
        if ($customerRfc === 'XAXX010101000') {
            $infoGlobal = $doc->createElement('cfdi:InformacionGlobal');
            $infoGlobal->setAttribute('Periodicidad', '01'); // 01 = Diario (Daily)
            $infoGlobal->setAttribute('Meses', date('m'));   // Current month
            $infoGlobal->setAttribute('Año', date('Y'));     // Current year
            $comprobante->appendChild($infoGlobal);
        }

        $emisor = $doc->createElement('cfdi:Emisor');
        $emisor->setAttribute('Rfc', $issuer['rfc']);
        $emisor->setAttribute('Nombre', mb_substr($issuer['name'], 0, 120));
        $emisor->setAttribute('RegimenFiscal', $issuer['regimen']);

        $receptor = $doc->createElement('cfdi:Receptor');
        $receptor->setAttribute('Rfc', $customerRfc);
        $receptor->setAttribute('Nombre', mb_substr($customerName, 0, 120));

        // DomicilioFiscalReceptor is required - must be valid 5-digit Mexican postal code from SAT catalog
        $customerZip = trim((string) ($invoice->client->zip ?? ''));
        if ($customerZip === '' || !preg_match('/^\d{5}$/', $customerZip)) {
            // 06000 is Cuauhtémoc, Mexico City - always valid in SAT catalog
            $customerZip = '06000';
        }
        $receptor->setAttribute('DomicilioFiscalReceptor', $customerZip);

        $receptor->setAttribute('RegimenFiscalReceptor', $customerRegimen ?: '601');

        // UsoCFDI must match the customer's Regimen Fiscal
        // For Regimen 616 (Sin obligaciones fiscales), use S01 (Sin efectos fiscales)
        $usoCfdiValue = $usoCfdi ?: 'G03';
        if ($customerRegimen === '616') {
            $usoCfdiValue = 'S01'; // Sin efectos fiscales - required for regimen 616
        }
        $receptor->setAttribute('UsoCFDI', $usoCfdiValue);

        $conceptos = $doc->createElement('cfdi:Conceptos');
        $fragment  = $doc->createDocumentFragment();
        $fragment->appendXML($conceptsXml);
        $conceptos->appendChild($fragment);

        $impuestos = $doc->createElement('cfdi:Impuestos');
        $impuestos->setAttribute('TotalImpuestosTrasladados', $ivaAmount);

        $traslados = $doc->createElement('cfdi:Traslados');
        $traslado  = $doc->createElement('cfdi:Traslado');
        $traslado->setAttribute('Base', $trasladoBase);
        $traslado->setAttribute('Impuesto', '002');
        $traslado->setAttribute('TipoFactor', 'Tasa');
        $traslado->setAttribute('TasaOCuota', '0.160000');
        $traslado->setAttribute('Importe', $ivaAmount);
        $traslados->appendChild($traslado);
        $impuestos->appendChild($traslados);

        $comprobante->appendChild($emisor);
        $comprobante->appendChild($receptor);
        $comprobante->appendChild($conceptos);
        $comprobante->appendChild($impuestos);

        $doc->appendChild($comprobante);
        $xml = $doc->saveXML();

        return [
            'success'         => true,
            'xml'             => $xml,
            'totals'          => $totals,
            'serie'           => $serie,
            'folio'           => $folio,
            'uso_cfdi'        => $usoCfdi ?: 'G03',
            'payment_method'  => $paymentMethod ?: '',
            'payment_form'    => $paymentForm ?: '',
            'currency'        => $currency ?: 'MXN',
            'sandbox'         => $sandbox,
            'customer'        => [
                'rfc'     => $customerRfc,
                'name'    => $customerName,
                'regimen' => $customerRegimen ?: '601',
            ],
            'errors'          => [],
        ];
    }

    protected function buildConceptsXml(array $items): string
    {
        $xml = '';
        foreach ($items as $item) {
            $qty   = isset($item['qty']) ? (float) $item['qty'] : (float) ($item['quantity'] ?? 0);
            $rate  = isset($item['rate']) ? (float) $item['rate'] : (float) ($item['item_rate'] ?? 0);
            $line  = number_format($qty * $rate, 2, '.', '');

            $conceptAttributes = [
                'ClaveProdServ' => '01010101',
                'NoIdentificacion' => $this->escapeAttribute(substr((string) ($item['item_name'] ?? $item['description'] ?? 'ITEM'), 0, 25)),
                'Cantidad' => number_format($qty, 2, '.', ''),
                'ClaveUnidad' => 'ACT',
                'Unidad' => 'ACT',
                'Descripcion' => $this->escapeAttribute(mb_substr((string) ($item['description'] ?? $item['item_name'] ?? 'Servicio'), 0, 100)),
                'ValorUnitario' => number_format($rate, 2, '.', ''),
                'Importe' => $line,
                'ObjetoImp' => '02',
            ];

            $conceptXml = '<cfdi:Concepto';
            foreach ($conceptAttributes as $attribute => $value) {
                $conceptXml .= ' ' . $attribute . '="' . $value . '"';
            }
            $conceptXml .= '>';
            $conceptXml .= '<cfdi:Impuestos><cfdi:Traslados><cfdi:Traslado Base="' . $line . '" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="' . number_format((float) $line * 0.16, 2, '.', '') . '"/></cfdi:Traslados></cfdi:Impuestos>';
            $conceptXml .= '</cfdi:Concepto>';

            $xml .= $conceptXml;
        }

        return $xml;
    }

    protected function resolveIssuer(): array
    {
        $companyName = get_option('companyname');
        $companyRfc  = strtoupper(trim((string) get_option('company_vat')));
        $regimen     = trim((string) get_option('perfex_fesat_company_regimen'));
        if ($regimen === '') {
            $regimen = '601';
        }
        $zip = trim((string) get_option('company_postal_code'));
        // Validate ZIP is 5 digits, use valid Mexican postal code as fallback
        if ($zip === '' || !preg_match('/^\d{5}$/', $zip)) {
            // 06000 is Cuauhtémoc, Mexico City - always valid in SAT catalog
            $zip = '06000';
        }

        return [
            'name'    => $companyName ?: 'Mi Empresa',
            'rfc'     => $companyRfc,
            'regimen' => $regimen,
            'zip'     => $zip,
        ];
    }

    protected function escapeAttribute($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected function amountToWords(float $amount, string $currency): string
    {
        if (!class_exists('NumberFormatter')) {
            return number_format($amount, 2) . ' ' . strtoupper($currency);
        }

        $formatter = new NumberFormatter('es_MX', NumberFormatter::SPELLOUT);
        $integer   = floor($amount);
        $cents     = round(($amount - $integer) * 100);
        $centsText = str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
        $words     = ucfirst($formatter->format($integer));

        $currencyLabel = strtoupper($currency) === 'USD' ? 'dólares' : 'pesos';

        return sprintf('%s %s %s/100 M.N.', $words, $currencyLabel, $centsText);
    }
}
