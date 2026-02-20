<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo _l('ramos_facturacion_remision_title'); ?> - <?php echo html_escape($order['order_number']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            text-transform: uppercase;
        }
        .header .subtitle {
            color: #666;
            margin-top: 5px;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-section h2 {
            font-size: 14px;
            margin: 0 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
        }
        .info-row {
            display: flex;
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            width: 120px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            font-weight: bold;
            background-color: #f5f5f5;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .signature-section {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo _l('ramos_facturacion_remision_title'); ?></h1>
        <div class="subtitle"><?php echo get_option('companyname'); ?></div>
    </div>

    <div class="info-section">
        <h2><?php echo _l('ramos_facturacion_order_info'); ?></h2>
        <div class="info-row">
            <span class="info-label"><?php echo _l('ramos_facturacion_order_number'); ?>:</span>
            <span><?php echo html_escape($order['order_number']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label"><?php echo _l('ramos_facturacion_date'); ?>:</span>
            <span><?php echo date('d/m/Y H:i'); ?></span>
        </div>
    </div>

    <div class="info-section">
        <h2><?php echo _l('ramos_facturacion_customer_info'); ?></h2>
        <div class="info-row">
            <span class="info-label"><?php echo _l('ramos_facturacion_customer'); ?>:</span>
            <span><?php echo html_escape($order['client_company'] ?: $order['phonenumber']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label"><?php echo _l('ramos_facturacion_address'); ?>:</span>
            <span><?php echo html_escape($order['address']); ?></span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th><?php echo _l('ramos_facturacion_col_product'); ?></th>
                <th class="text-center"><?php echo _l('ramos_facturacion_col_unit'); ?></th>
                <th class="text-center"><?php echo _l('ramos_facturacion_col_pedido'); ?></th>
                <th class="text-center"><?php echo _l('ramos_facturacion_col_surtido'); ?></th>
                <th class="text-center"><?php echo _l('ramos_facturacion_col_peso'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $totalItems = 0;
            foreach ($order['items'] as $item) :
                $totalItems++;
            ?>
                <tr>
                    <td><?php echo html_escape($item['item_name']); ?></td>
                    <td class="text-center"><?php echo html_escape($item['unit'] ?? 'pz'); ?></td>
                    <td class="text-center"><?php echo number_format((float) $item['quantity'], 2); ?></td>
                    <td class="text-center"><?php echo number_format((float) ($item['picked_qty'] ?? $item['quantity']), 2); ?></td>
                    <td class="text-center"><?php echo number_format((float) ($item['weight'] ?? 0), 2); ?> kg</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" class="text-right">
                    <?php echo _l('ramos_facturacion_total_products'); ?>: <?php echo $totalItems; ?>
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p><?php echo _l('ramos_facturacion_remision_note'); ?></p>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line"><?php echo _l('ramos_facturacion_delivered_by'); ?></div>
        </div>
        <div class="signature-box">
            <div class="signature-line"><?php echo _l('ramos_facturacion_received_by'); ?></div>
        </div>
    </div>
</body>
</html>
