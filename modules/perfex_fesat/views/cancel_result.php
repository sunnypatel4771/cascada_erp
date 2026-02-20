<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                <div class="panel_s">
                    <div class="panel-body">
                        <!-- Result Header -->
                        <div style="text-align:center;padding:20px;">
                            <?php if ($result['success']) { ?>
                                <h2 style="color:#4CAF50;">
                                    <i class="fa fa-check-circle" style="font-size:48px;"></i><br>
                                    <?= _l('fe_sat_cancel_success'); ?>
                                </h2>
                                <p class="text-muted"><?= htmlspecialchars($result['message'] ?? ''); ?></p>
                            <?php } else { ?>
                                <h2 style="color:#F44336;">
                                    <i class="fa fa-times-circle" style="font-size:48px;"></i><br>
                                    <?= _l('fe_sat_cancel_failed'); ?>
                                </h2>
                                <p class="text-danger"><strong><?= htmlspecialchars($result['message'] ?? ''); ?></strong></p>
                            <?php } ?>
                        </div>

                        <hr />

                        <!-- Comprehensive Debug Summary -->
                        <?php
                        // Get company information
                        $companyRfc = strtoupper(trim((string) get_option('company_vat')));
                        $companyName = get_option('company_name');

                        // Get cancellation details
                        $motivo = $payload['motivo'] ?? 'N/A';
                        $folioSustitucion = $payload['folio_sustitucion'] ?? '';
                        $motivoDescriptions = [
                            '01' => _l('fe_sat_cancel_motivo_01'),
                            '02' => _l('fe_sat_cancel_motivo_02'),
                            '03' => _l('fe_sat_cancel_motivo_03'),
                            '04' => _l('fe_sat_cancel_motivo_04'),
                        ];
                        ?>

                        <h2 style='color:#FF5722;border-bottom:3px solid #FF5722;padding-bottom:10px;margin-top:30px;'>
                            📊 CANCELLATION COMPREHENSIVE SUMMARY
                        </h2>

                        <style>
                            .summary-table {width:100%;border-collapse:collapse;margin:20px 0;font-family:monospace;}
                            .summary-table th {background:#2196F3;color:white;padding:12px;text-align:left;font-weight:bold;}
                            .summary-table td {padding:10px;border:1px solid #ddd;}
                            .summary-table tr:nth-child(even) {background:#f9f9f9;}
                            .summary-table tr:hover {background:#e3f2fd;}
                            .section-header {background:#4CAF50 !important;color:white !important;font-weight:bold;}
                            .highlight {background:#FFF9C4 !important;font-weight:bold;}
                            .success {color:#4CAF50;font-weight:bold;}
                            .error {color:#F44336;font-weight:bold;}
                        </style>

                        <table class='summary-table'>
                            <!-- API Endpoints Section -->
                            <tr class='section-header'><td colspan='2'>🌐 API ENDPOINTS & AUTHENTICATION</td></tr>
                            <tr><td style="width:250px;"><strong>Base URL</strong></td><td><?= htmlspecialchars(get_option('perfex_fesat_base_url')); ?></td></tr>
                            <tr><td><strong>Cancellation Endpoint</strong></td><td><?= htmlspecialchars($result['endpoint'] ?? 'https://testtimbrado.digibox.com.mx/api/cancelacioncfdi/cancelarcsdv2'); ?></td></tr>
                            <tr><td><strong>HTTP Status Code</strong></td><td><strong><?= htmlspecialchars($result['http_code'] ?? 'N/A'); ?></strong></td></tr>
                            <tr><td><strong>API Response</strong></td><td>
                                <?= $result['success'] ? '<span class="success">✓ SUCCESS</span>' : '<span class="error">✗ FAILED</span>'; ?>
                            </td></tr>

                            <!-- Company (Emisor) Information -->
                            <tr class='section-header'><td colspan='2'>🏢 COMPANY INFORMATION (EMISOR)</td></tr>
                            <tr class='highlight'><td><strong>Company RFC</strong></td><td><strong><?= htmlspecialchars($companyRfc); ?></strong></td></tr>
                            <tr class='highlight'><td><strong>Company Name</strong></td><td><strong><?= htmlspecialchars($companyName); ?></strong></td></tr>

                            <!-- Invoice & CFDI Details -->
                            <tr class='section-header'><td colspan='2'>📄 INVOICE & CFDI DETAILS</td></tr>
                            <tr><td><strong>Invoice ID</strong></td><td><?= htmlspecialchars($invoice->id); ?></td></tr>
                            <tr><td><strong>Invoice Number</strong></td><td><?= htmlspecialchars($invoice->number); ?></td></tr>
                            <tr class='highlight'><td><strong>UUID to Cancel</strong></td><td><strong><?= htmlspecialchars($document->digibox_uuid); ?></strong></td></tr>
                            <tr><td><strong>Original Timbrado Date</strong></td><td><?= htmlspecialchars($document->created_at ?? 'N/A'); ?></td></tr>

                            <!-- Cancellation Request Details -->
                            <tr class='section-header'><td colspan='2'>🚫 CANCELLATION REQUEST DETAILS</td></tr>
                            <tr class='highlight'><td><strong>Motivo (Reason)</strong></td><td><strong><?= htmlspecialchars($motivo); ?> - <?= htmlspecialchars($motivoDescriptions[$motivo] ?? 'Unknown'); ?></strong></td></tr>
                            <?php if ($motivo === '01' && !empty($folioSustitucion)) { ?>
                                <tr class='highlight'><td><strong>Folio Sustitución (Replacement UUID)</strong></td><td><strong><?= htmlspecialchars($folioSustitucion); ?></strong></td></tr>
                            <?php } ?>
                            <tr><td><strong>Cancellation Date</strong></td><td><?= date('Y-m-d H:i:s'); ?></td></tr>
                            <tr><td><strong>Requested By</strong></td><td><?= htmlspecialchars(get_staff_full_name(get_staff_user_id())); ?></td></tr>

                            <?php if (!empty($result['acuse_xml'])) { ?>
                                <!-- Acuse XML Response -->
                                <tr class='section-header'><td colspan='2'>📝 ACUSE XML (SAT RESPONSE)</td></tr>
                                <tr><td colspan='2'>
                                    <pre style='max-height:400px;overflow:auto;background:#f5f5f5;padding:10px;'><?= htmlspecialchars($result['acuse_xml']); ?></pre>
                                </td></tr>
                            <?php } ?>

                            <?php if (!$result['success']) { ?>
                                <!-- Error Information -->
                                <tr class='section-header'><td colspan='2'>❌ ERROR INFORMATION</td></tr>
                                <tr><td><strong>Error Message</strong></td><td><span style='color:red;font-weight:bold;'><?= htmlspecialchars($result['message'] ?? 'N/A'); ?></span></td></tr>
                                <?php if (!empty($result['body_excerpt'])) { ?>
                                    <tr><td><strong>Full Error Response</strong></td><td>
                                        <pre style='max-height:200px;overflow:auto;background:#ffebee;padding:10px;'><?= htmlspecialchars($result['body_excerpt']); ?></pre>
                                    </td></tr>
                                <?php } ?>
                            <?php } ?>
                        </table>

                        <!-- API Call Log Section -->
                        <?php if (!empty($result['api_log'])) { ?>
                            <h2 style='color:#FF5722;border-bottom:3px solid #FF5722;padding-bottom:10px;margin-top:30px;'>
                                🔄 ALL API CALLS TO DIGIBOX
                            </h2>

                            <table class='summary-table'>
                                <?php foreach ($result['api_log'] as $index => $call) {
                                    $callNum = $index + 1; ?>
                                    <tr><td colspan='2' style='background:#E3F2FD;padding:15px;'>
                                        <h4 style='margin:0 0 10px 0;color:#1976D2;'>
                                            API Call #<?= $callNum; ?>: <?= htmlspecialchars($call['type']); ?>
                                        </h4>
                                        <table style='width:100%;font-size:13px;' class='summary-table'>
                                            <tr><td style='width:180px;'><strong>Timestamp</strong></td><td><?= htmlspecialchars($call['timestamp']); ?></td></tr>
                                            <tr><td><strong>Method</strong></td><td><?= htmlspecialchars($call['method']); ?></td></tr>
                                            <tr><td><strong>URL</strong></td><td><?= htmlspecialchars($call['url']); ?></td></tr>
                                            <tr><td><strong>Headers</strong></td><td>
                                                <pre style='margin:0;background:#fff;padding:5px;max-height:300px;overflow:auto;'><?= htmlspecialchars(is_array($call['headers']) ? implode("\n", $call['headers']) : $call['headers']); ?></pre>
                                            </td></tr>
                                            <?php if (!empty($call['request_body'])) { ?>
                                                <tr><td><strong>Request Body</strong></td><td>
                                                    <pre style='margin:0;background:#fff;padding:5px;max-height:200px;overflow:auto;'><?= htmlspecialchars($call['request_body']); ?></pre>
                                                </td></tr>
                                            <?php } ?>
                                            <tr><td><strong>Response Code</strong></td><td>
                                                <strong style='color:<?= ($call['response_code'] >= 200 && $call['response_code'] < 300) ? 'green' : 'red'; ?>;'>
                                                    <?= htmlspecialchars($call['response_code']); ?>
                                                </strong>
                                            </td></tr>
                                            <tr><td><strong>Response Body</strong></td><td>
                                                <pre style='margin:0;background:#fff;padding:5px;max-height:400px;overflow:auto;'><?= htmlspecialchars($call['response_body']); ?></pre>
                                            </td></tr>
                                            <?php if ($call['error']) { ?>
                                                <tr><td><strong>Error</strong></td><td><span style='color:red;'><?= htmlspecialchars($call['error']); ?></span></td></tr>
                                            <?php } ?>
                                        </table>
                                    </td></tr>
                                <?php } ?>
                            </table>
                        <?php } ?>

                        <!-- Important Notes -->
                        <div style='background:#FFF9C4;border-left:4px solid #FFC107;padding:15px;margin:20px 0;'>
                            <h3 style='margin-top:0;color:#F57C00;'>💡 IMPORTANT NOTES</h3>
                            <?php if ($result['success']) { ?>
                                <ul>
                                    <li><strong>Status:</strong> Cancellation request submitted successfully</li>
                                    <li><strong>Next Step:</strong> Customer has 72 hours to accept/reject the cancellation</li>
                                    <li><strong>Auto-Accept:</strong> If customer doesn't respond within 72 hours, cancellation will be automatically accepted ("Positiva Ficta")</li>
                                    <li><strong>Database Status:</strong> Invoice marked as "cancellation_status = pending"</li>
                                    <?php if ($motivo === '01') { ?>
                                        <li><strong>Replacement Invoice:</strong> Remember to create the corrected invoice with UUID: <?= htmlspecialchars($folioSustitucion); ?></li>
                                    <?php } ?>
                                </ul>
                            <?php } else { ?>
                                <ul>
                                    <li><strong>Status:</strong> Cancellation request FAILED</li>
                                    <li><strong>Review:</strong> Check the error message and API call logs above</li>
                                    <li><strong>Common Issues:</strong>
                                        <ul>
                                            <li>CSD certificate files not configured or invalid</li>
                                            <li>UUID doesn't belong to this RFC</li>
                                            <li>UUID already cancelled</li>
                                            <li>Invalid motivo or missing folio sustitución</li>
                                        </ul>
                                    </li>
                                </ul>
                            <?php } ?>
                        </div>

                        <!-- Navigation Buttons -->
                        <hr />
                        <div class="text-center" style="margin:20px 0;">
                            <a href="<?= admin_url('invoices/list_invoices/' . $invoice->id); ?>" class="btn btn-primary">
                                <i class="fa fa-arrow-left"></i> <?= _l('fe_sat_cancel_back'); ?> to Invoice
                            </a>
                            <?php if (!$result['success']) { ?>
                                <a href="<?= admin_url('fe_sat/cancel-form/' . $invoice->id); ?>" class="btn btn-warning">
                                    <i class="fa fa-refresh"></i> Try Again
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
