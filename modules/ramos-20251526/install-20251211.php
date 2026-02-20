<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

if (!function_exists('ramos_install_charset_collation')) {
    function ramos_install_charset_collation()
    {
        $CI = &get_instance();

        return [
            $CI->db->char_set,
            $CI->db->dbcollat,
        ];
    }
}

if (!$CI->db->table_exists(db_prefix() . 'ramos_orders')) {
    [$charset, $collation] = ramos_install_charset_collation();

    $CI->db->query(
        'CREATE TABLE `' . db_prefix() . "ramos_orders` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_number` VARCHAR(50) NOT NULL,
            `customer_name` VARCHAR(191) NOT NULL,
            `customer_reference` INT UNSIGNED NULL,
            `client_id` INT UNSIGNED NULL,
            `delivery_address` TEXT NOT NULL,
            `delivery_datetime` DATETIME NULL,
            `priority` VARCHAR(20) NOT NULL DEFAULT 'normal',
            `status` VARCHAR(20) NOT NULL DEFAULT 'new',
            `notes` TEXT NULL,
            `import_batch` VARCHAR(36) NULL,
            `created_by` INT UNSIGNED NULL,
            `updated_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL,
            `invoice_id` INT UNSIGNED NULL,
            `invoiced_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_ramos_orders_order_number` (`order_number`),
            KEY `idx_ramos_orders_status` (`status`),
            KEY `idx_ramos_orders_priority` (`priority`),
            KEY `idx_ramos_orders_client` (`client_id`),
            KEY `idx_ramos_orders_invoice` (`invoice_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};"
    );
}

if ($CI->db->table_exists(db_prefix() . 'ramos_orders')) {
    $index = $CI->db->query('SHOW INDEX FROM `' . db_prefix() . "ramos_orders` WHERE Key_name = 'idx_ramos_orders_customer_reference'")->row();
    if (!$index) {
        $CI->db->query('CREATE INDEX `idx_ramos_orders_customer_reference` ON `' . db_prefix() . 'ramos_orders` (`customer_reference`);');
    }

    if (!$CI->db->field_exists('client_id', db_prefix() . 'ramos_orders')) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . "ramos_orders` ADD `client_id` INT UNSIGNED NULL AFTER `customer_reference`;");
        $CI->db->query('CREATE INDEX `idx_ramos_orders_client` ON `' . db_prefix() . 'ramos_orders` (`client_id`);');
    }

    if (!$CI->db->field_exists('invoice_id', db_prefix() . 'ramos_orders')) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . "ramos_orders` ADD `invoice_id` INT UNSIGNED NULL AFTER `updated_at`, ADD `invoiced_at` DATETIME NULL AFTER `invoice_id`;");
        $CI->db->query('CREATE INDEX `idx_ramos_orders_invoice` ON `' . db_prefix() . 'ramos_orders` (`invoice_id`);');
    }
}

if (!$CI->db->table_exists(db_prefix() . 'ramos_inventory_items')) {
    [$charset, $collation] = ramos_install_charset_collation();

    $CI->db->query(
        'CREATE TABLE `' . db_prefix() . "ramos_inventory_items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `item_name` VARCHAR(191) NOT NULL,
            `sku` VARCHAR(100) NULL,
            `unit` VARCHAR(50) NOT NULL DEFAULT 'unit',
            `quantity` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `safety_stock` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `buffer_percent` DECIMAL(5,2) NOT NULL DEFAULT 25.00,
            `notes` TEXT NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_by` INT UNSIGNED NULL,
            `updated_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_ramos_inventory_items_sku` (`sku`),
            KEY `idx_ramos_inventory_items_active` (`active`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};"
    );
}

if (!$CI->db->field_exists('supplier_id', db_prefix() . 'ramos_inventory_items')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "ramos_inventory_items` ADD `supplier_id` INT UNSIGNED NULL DEFAULT NULL AFTER `buffer_percent`;");
    $CI->db->query('CREATE INDEX `idx_ramos_inventory_supplier` ON `' . db_prefix() . 'ramos_inventory_items` (`supplier_id`);');
}

if (!$CI->db->table_exists(db_prefix() . 'ramos_suppliers')) {
    [$charset, $collation] = ramos_install_charset_collation();

    $CI->db->query(
        'CREATE TABLE `' . db_prefix() . "ramos_suppliers` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `supplier_name` VARCHAR(191) NOT NULL,
            `contact_name` VARCHAR(191) NULL,
            `phone` VARCHAR(100) NULL,
            `email` VARCHAR(191) NULL,
            `notes` TEXT NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_by` INT UNSIGNED NULL,
            `updated_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_ramos_suppliers_name` (`supplier_name`),
            KEY `idx_ramos_suppliers_active` (`active`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};"
    );
}

if (!$CI->db->table_exists(db_prefix() . 'ramos_order_items')) {
    [$charset, $collation] = ramos_install_charset_collation();

    $CI->db->query(
        'CREATE TABLE `' . db_prefix() . "ramos_order_items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_id` INT UNSIGNED NOT NULL,
            `inventory_item_id` INT UNSIGNED NULL,
            `item_name` VARCHAR(191) NOT NULL,
            `quantity` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `created_by` INT UNSIGNED NULL,
            PRIMARY KEY (`id`),
            KEY `idx_ramos_order_items_order` (`order_id`),
            KEY `idx_ramos_order_items_inventory` (`inventory_item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};"
    );
}

if (!$CI->db->table_exists(db_prefix() . 'ramos_purchase_batches')) {
    [$charset, $collation] = ramos_install_charset_collation();

    $CI->db->query(
        'CREATE TABLE `' . db_prefix() . "ramos_purchase_batches` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `batch_code` VARCHAR(50) NOT NULL,
            `supplier_id` INT UNSIGNED NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
            `total_items` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_by` INT UNSIGNED NULL,
            `approved_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `approved_at` DATETIME NULL,
            `sent_at` DATETIME NULL,
            `completed_at` DATETIME NULL,
            `notes` TEXT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_ramos_purchase_batches_code` (`batch_code`),
            KEY `idx_ramos_purchase_batches_supplier` (`supplier_id`),
            KEY `idx_ramos_purchase_batches_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};"
    );
}

if (!$CI->db->table_exists(db_prefix() . 'ramos_purchase_batch_items')) {
    [$charset, $collation] = ramos_install_charset_collation();

    $CI->db->query(
        'CREATE TABLE `' . db_prefix() . "ramos_purchase_batch_items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `batch_id` INT UNSIGNED NOT NULL,
            `inventory_item_id` INT UNSIGNED NULL,
            `order_item_id` INT UNSIGNED NULL,
            `requested_qty` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `received_qty` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `current_stock` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `safety_stock` DECIMAL(15,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_ramos_purchase_batch_items_batch` (`batch_id`),
            KEY `idx_ramos_purchase_batch_items_inventory` (`inventory_item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};"
    );
}

if (!$CI->db->field_exists('received_qty', db_prefix() . 'ramos_purchase_batch_items')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "ramos_purchase_batch_items` ADD `received_qty` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `requested_qty`;" );
}

if ($CI->db->field_exists('status', db_prefix() . 'ramos_purchase_batches')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "ramos_purchase_batches` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'draft';");
} else {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "ramos_purchase_batches` ADD `status` VARCHAR(20) NOT NULL DEFAULT 'draft' AFTER `supplier_id`;" );
}

if (!$CI->db->field_exists('sent_at', db_prefix() . 'ramos_purchase_batches')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "ramos_purchase_batches` ADD `sent_at` DATETIME NULL AFTER `approved_at`;" );
}

if (!$CI->db->field_exists('completed_at', db_prefix() . 'ramos_purchase_batches')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "ramos_purchase_batches` ADD `completed_at` DATETIME NULL AFTER `sent_at`;" );
}

if (!$CI->db->field_exists('notes', db_prefix() . 'ramos_purchase_batches')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "ramos_purchase_batches` ADD `notes` TEXT NULL AFTER `completed_at`;" );
}

if (!$CI->db->table_exists(db_prefix() . 'ramos_modules')) {
    [$charset, $collation] = ramos_install_charset_collation();

    $CI->db->query(
        'CREATE TABLE `' . db_prefix() . "ramos_modules` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `module_name` VARCHAR(100) NOT NULL,
            `display_name` VARCHAR(191) NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_ramos_modules_name` (`module_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};"
    );
}

if (!$CI->db->table_exists(db_prefix() . 'ramos_module_products')) {
    [$charset, $collation] = ramos_install_charset_collation();

    $CI->db->query(
        'CREATE TABLE `' . db_prefix() . "ramos_module_products` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `module_id` INT UNSIGNED NOT NULL,
            `inventory_item_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_ramos_module_products_unique` (`module_id`,`inventory_item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};"
    );
}

if (!$CI->db->table_exists(db_prefix() . 'ramos_module_staff')) {
    [$charset, $collation] = ramos_install_charset_collation();

    $CI->db->query(
        'CREATE TABLE `' . db_prefix() . "ramos_module_staff` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `module_id` INT UNSIGNED NOT NULL,
            `staff_id` INT UNSIGNED NOT NULL,
            `shift_started_at` DATETIME NULL,
            `shift_ended_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_ramos_module_staff_module` (`module_id`),
            KEY `idx_ramos_module_staff_staff` (`staff_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};"
    );
}

if ($CI->db->table_exists(db_prefix() . 'ramos_modules')) {
    $CI->db->from(db_prefix() . 'ramos_modules');
    $modulesCount = $CI->db->count_all_results();

    if ((int) $modulesCount === 0) {
        $defaults = [
            ['module_name' => 'module_a', 'display_name' => 'Picking Module A'],
            ['module_name' => 'module_b', 'display_name' => 'Picking Module B'],
            ['module_name' => 'module_c', 'display_name' => 'Picking Module C'],
            ['module_name' => 'module_d', 'display_name' => 'Picking Module D'],
        ];

        foreach ($defaults as $module) {
            $CI->db->insert(db_prefix() . 'ramos_modules', $module);
        }
    }
}

if (!$CI->db->table_exists(db_prefix() . 'ramos_pick_items')) {
    [$charset, $collation] = ramos_install_charset_collation();

    $CI->db->query(
        'CREATE TABLE `' . db_prefix() . "ramos_pick_items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_id` INT UNSIGNED NOT NULL,
            `order_item_id` INT UNSIGNED NOT NULL,
            `module_id` INT UNSIGNED NOT NULL,
            `required_qty` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `picked_qty` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `weight` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
            `notes` TEXT NULL,
            `updated_by` INT UNSIGNED NULL,
            `updated_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_ramos_pick_unique` (`order_item_id`,`module_id`),
            KEY `idx_ramos_pick_module` (`module_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};"
    );
}

if (!$CI->db->field_exists('notes', db_prefix() . 'ramos_pick_items')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "ramos_pick_items` ADD `notes` TEXT NULL AFTER `status`;" );
}

if (!$CI->db->table_exists(db_prefix() . 'ramos_routes')) {
    [$charset, $collation] = ramos_install_charset_collation();

    $CI->db->query(
        'CREATE TABLE `' . db_prefix() . "ramos_routes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `route_date` DATE NOT NULL,
            `start_time` TIME NULL,
            `vehicle_label` VARCHAR(191) NULL,
            `capacity` INT UNSIGNED NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `notes` TEXT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_ramos_routes_date` (`route_date`),
            KEY `idx_ramos_routes_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};"
    );
}

if (!$CI->db->table_exists(db_prefix() . 'ramos_route_stops')) {
    [$charset, $collation] = ramos_install_charset_collation();

    $CI->db->query(
        'CREATE TABLE `' . db_prefix() . "ramos_route_stops` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `route_id` INT UNSIGNED NOT NULL,
            `order_id` INT UNSIGNED NOT NULL,
            `stop_number` INT UNSIGNED NOT NULL DEFAULT 1,
            `eta` DATETIME NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_ramos_route_stops_unique` (`route_id`,`order_id`),
            KEY `idx_ramos_route_stops_route` (`route_id`),
            KEY `idx_ramos_route_stops_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};"
    );
}

if (!$CI->db->field_exists('vehicle_label', db_prefix() . 'ramos_routes')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "ramos_routes` ADD `vehicle_label` VARCHAR(191) NULL AFTER `start_time`;" );
}

if (!$CI->db->field_exists('capacity', db_prefix() . 'ramos_routes')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "ramos_routes` ADD `capacity` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `vehicle_label`;" );
}

if (!$CI->db->field_exists('notes', db_prefix() . 'ramos_routes')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "ramos_routes` ADD `notes` TEXT NULL AFTER `created_at`;" );
}

if (!$CI->db->table_exists(db_prefix() . 'ramos_notifications')) {
    [$charset, $collation] = ramos_install_charset_collation();

    $CI->db->query(
        'CREATE TABLE `' . db_prefix() . "ramos_notifications` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `type` VARCHAR(50) NOT NULL,
            `severity` VARCHAR(20) NOT NULL DEFAULT 'info',
            `title` VARCHAR(191) NOT NULL,
            `message` TEXT NOT NULL,
            `context_type` VARCHAR(50) NULL,
            `context_id` INT UNSIGNED NULL,
            `metadata` TEXT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL,
            `acknowledged_at` DATETIME NULL,
            `acknowledged_by` INT UNSIGNED NULL,
            `resolved_at` DATETIME NULL,
            `resolved_by` INT UNSIGNED NULL,
            PRIMARY KEY (`id`),
            KEY `idx_ramos_notifications_type` (`type`),
            KEY `idx_ramos_notifications_context` (`context_type`,`context_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};"
    );
} else {
    $notificationsTable = db_prefix() . 'ramos_notifications';
    $columns = $CI->db->list_fields($notificationsTable);

    $ensureColumn = function ($name, $definition) use ($CI, $notificationsTable, $columns) {
        if (!in_array($name, $columns, true)) {
            $CI->db->query('ALTER TABLE `' . $notificationsTable . '` ADD ' . $definition . ';');
        }
    };

    $ensureColumn('severity', " `severity` VARCHAR(20) NOT NULL DEFAULT 'info' AFTER `type`");
    $ensureColumn('title', " `title` VARCHAR(191) NOT NULL AFTER `severity`");
    $ensureColumn('message', " `message` TEXT NOT NULL AFTER `title`");
    $ensureColumn('context_type', " `context_type` VARCHAR(50) NULL AFTER `message`");
    $ensureColumn('context_id', " `context_id` INT UNSIGNED NULL AFTER `context_type`");
    $ensureColumn('metadata', " `metadata` TEXT NULL AFTER `context_id`");
    $ensureColumn('updated_at', " `updated_at` DATETIME NULL AFTER `created_at`");
    $ensureColumn('acknowledged_at', " `acknowledged_at` DATETIME NULL AFTER `updated_at`");
    $ensureColumn('acknowledged_by', " `acknowledged_by` INT UNSIGNED NULL AFTER `acknowledged_at`");
    $ensureColumn('resolved_at', " `resolved_at` DATETIME NULL AFTER `acknowledged_by`");
    $ensureColumn('resolved_by', " `resolved_by` INT UNSIGNED NULL AFTER `resolved_at`");

    $indexes = $CI->db->query('SHOW INDEX FROM `' . $notificationsTable . '`')->result_array();
    $hasTypeIndex    = false;
    $hasContextIndex = false;

    foreach ($indexes as $index) {
        if ($index['Key_name'] === 'idx_ramos_notifications_type') {
            $hasTypeIndex = true;
        }
        if ($index['Key_name'] === 'idx_ramos_notifications_context') {
            $hasContextIndex = true;
        }
    }

    if (!$hasTypeIndex) {
        $CI->db->query('CREATE INDEX `idx_ramos_notifications_type` ON `' . $notificationsTable . '` (`type`);');
    }

    if (!$hasContextIndex) {
        $CI->db->query('CREATE INDEX `idx_ramos_notifications_context` ON `' . $notificationsTable . '` (`context_type`,`context_id`);');
    }
}

if (!$CI->db->table_exists(db_prefix() . 'ramos_price_rules')) {
    [$charset, $collation] = ramos_install_charset_collation();

    $CI->db->query(
        'CREATE TABLE `' . db_prefix() . "ramos_price_rules` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `inventory_item_id` INT UNSIGNED NOT NULL,
            `customer_id` INT UNSIGNED NULL,
            `price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `currency` INT UNSIGNED NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `notes` TEXT NULL,
            `created_by` INT UNSIGNED NULL,
            `updated_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_ramos_price_unique` (`inventory_item_id`,`customer_id`),
            KEY `idx_ramos_price_customer` (`customer_id`),
            KEY `idx_ramos_price_inventory` (`inventory_item_id`),
            KEY `idx_ramos_price_active` (`active`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};"
    );
}
