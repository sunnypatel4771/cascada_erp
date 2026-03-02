# What's Needed to Consider ERP Orders in Automation

## Summary

To include ERP orders (from `tblinvoices`) in automation, **3 key changes** are needed:

1. **Modify `Automation_model.php`** - Add methods to query ERP invoices
2. **Update `Automation.php` controller** - Merge both order sources
3. **Handle order marking** - Track which system each order comes from

---

## Current State (Omni_sales Only)

### What Automation Currently Does
```php
// File: /modules/ramos/models/Automation_model.php

public function get_unprocessed_omni_orders(): array
{
    $this->db->select('c.*');
    $this->db->from(db_prefix() . 'cart c');
    $this->db->where('c.status !=', 5); // Exclude cancelled
    $this->db->where('(c.processed_for_purchase IS NULL OR c.processed_for_purchase = 0)');
    $this->db->where('c.channel_id IN (1,2,4,6)'); // Only valid channels
    return $this->db->get()->result_array();
}

public function get_required_quantities_from_omni_orders(array $orderIds): array
{
    // SELECT from tblcart_detailt
    $this->db->select('cd.product_id, SUM(cd.quantity)');
    $this->db->from(db_prefix() . 'cart_detailt cd');
    $this->db->where_in('cd.cart_id', $orderIds);
    return $this->db->get()->result_array();
}

public function mark_omni_orders_as_processed(array $orderIds, int $automationRunId): bool
{
    // UPDATE tblcart SET processed_for_purchase = 1
    $this->db->where_in('id', $orderIds);
    $this->db->update(db_prefix() . 'cart', $updateData);
    return $this->db->affected_rows() > 0;
}
```

**Limitation**: Only queries `tblcart` (omni_sales), ignores `tblinvoices` (ERP)

---

## ERP Orders Structure

### Where ERP Orders Live
```
tblinvoices (order headers)
├─ id (invoice ID) 
├─ clientid (customer)
├─ date (order date)
├─ total
├─ status (0=draft, 1=sent/unpaid, 2=paid, 3=partial)
├─ clientnote (contains "Order created from customer portal")
└─ recurring (null for portal orders)

tblinvoiceitems (order line items)
├─ id
├─ invoiceid (links to tblinvoices.id)
├─ itemid (product ID)
├─ qty (quantity ordered)
├─ rate (unit price)
└─ description
```

### Key Differences from Omni_sales

| Aspect | Omni_sales (tblcart) | ERP (tblinvoices) |
|--------|----------------------|-------------------|
| Header table | `tblcart` | `tblinvoices` |
| Items table | `tblcart_detailt` | `tblinvoiceitems` |
| Item ID field | `product_id` | `itemid` |
| Status values | 0-5 | 0-3 (different meanings) |
| Channel tracking | `channel_id` | None (no channel field) |
| Portal flag | implicit (channel) | `clientnote LIKE '%portal%'` |
| Processing flag | `processed_for_purchase` | None (would need to add) |

---

## Changes Required

### 1. Add New Methods to `Automation_model.php`

#### Method 1: Get Unprocessed ERP Orders

```php
/**
 * Get unprocessed ERP portal orders
 *
 * ERP orders are distinguished by having clientnote like "portal"
 * No processed_for_purchase field exists, so use status = 1 (unpaid)
 * and exclude orders already analyzed
 *
 * @return array
 */
public function get_unprocessed_erp_orders(): array
{
    $this->db->select('i.id, i.clientid, i.date as datecreator, i.total, i.status');
    $this->db->select('"invoice" as order_source'); // Mark as ERP
    $this->db->from(db_prefix() . 'invoices i');
    $this->db->where('i.status', 1); // Status 1 = sent/unpaid
    $this->db->where('i.clientnote IS NOT NULL', null, false);
    $this->db->where('i.clientnote LIKE "%portal%"', null, false); // Portal orders only
    $this->db->where('i.recurring IS NULL', null, false); // No recurring
    $this->db->where('i.recurring_ends_on IS NULL', null, false); // Not recurring
    // Note: Could also filter by invoice_template = 0 if portal orders use specific template
    $this->db->order_by('i.date', 'ASC');
    
    return $this->db->get()->result_array();
}

/**
 * Count unprocessed ERP portal orders
 *
 * @return int
 */
public function count_unprocessed_erp_orders(): int
{
    $this->db->from(db_prefix() . 'invoices');
    $this->db->where('status', 1); // Unpaid
    $this->db->where('clientnote IS NOT NULL', null, false);
    $this->db->where('clientnote LIKE "%portal%"', null, false);
    $this->db->where('recurring IS NULL', null, false);
    
    return $this->db->count_all_results();
}
```

#### Method 2: Get Quantities from ERP Orders

```php
/**
 * Get required quantities from ERP invoice items
 *
 * ERP invoices use itemid instead of product_id
 * Need to map itemid to inventory system if needed
 *
 * @param array $invoiceIds Array of invoice IDs
 * @return array Keyed by itemid (product_id)
 */
public function get_required_quantities_from_erp_orders(array $invoiceIds): array
{
    if (empty($invoiceIds)) {
        return [];
    }

    // Get invoice items
    $this->db->select('ii.itemid as inventory_item_id, SUM(ii.qty) as required_qty');
    $this->db->from(db_prefix() . 'invoiceitems ii');
    $this->db->where_in('ii.invoiceid', $invoiceIds);
    $this->db->where('ii.itemid IS NOT NULL', null, false);
    $this->db->where('ii.itemid > 0', null, false);
    $this->db->group_by('ii.itemid');

    $results = $this->db->get()->result_array();

    $quantities = [];
    foreach ($results as $row) {
        $itemId = (int) $row['inventory_item_id'];
        if ($itemId > 0) {
            $quantities[$itemId] = (float) $row['required_qty'];
        }
    }

    return $quantities;
}
```

#### Method 3: Mark ERP Orders as Processed

**Challenge**: ERP `tblinvoices` table doesn't have a `processed_for_purchase` field

**Solution Options**:

**Option A: Add column to tblinvoices (Recommended)**
```php
/**
 * Add processed_for_purchase tracking to ERP orders
 * 
 * Run once via migration or admin panel
 */
public function add_erp_processing_column(): bool
{
    $table = db_prefix() . 'invoices';
    
    if ($this->db->field_exists('processed_for_purchase', $table)) {
        return true; // Already exists
    }
    
    $this->db->query("ALTER TABLE {$table} ADD COLUMN processed_for_purchase TINYINT(1) DEFAULT 0 AFTER status");
    $this->db->query("ALTER TABLE {$table} ADD COLUMN automation_run_id INT NULL AFTER processed_for_purchase");
    
    return true;
}

/**
 * Mark ERP orders as processed
 */
public function mark_erp_orders_as_processed(array $invoiceIds, int $automationRunId): bool
{
    if (empty($invoiceIds)) {
        return false;
    }

    $updateData = [
        'processed_for_purchase' => 1,
        'automation_run_id'      => $automationRunId
    ];

    $this->db->where_in('id', $invoiceIds);
    $this->db->update(db_prefix() . 'invoices', $updateData);

    return $this->db->affected_rows() > 0;
}
```

**Option B: Track externally (Alternative)**
```php
/**
 * Create tracking table for ERP order automation
 * 
 * Useful if you can't modify tblinvoices
 */
public function create_erp_automation_tracking_table(): bool
{
    $this->db->query("
        CREATE TABLE IF NOT EXISTS " . db_prefix() . "ramos_erp_automation_tracking (
            id INT PRIMARY KEY AUTO_INCREMENT,
            invoice_id INT NOT NULL,
            automation_run_id INT NOT NULL,
            processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (automation_run_id) REFERENCES " . db_prefix() . "ramos_automation_runs(id)
        )
    ");
    
    return true;
}

/**
 * Record ERP order processing in tracking table
 */
public function track_erp_processing(array $invoiceIds, int $automationRunId): bool
{
    $data = [];
    foreach ($invoiceIds as $invoiceId) {
        $data[] = [
            'invoice_id'       => $invoiceId,
            'automation_run_id' => $automationRunId
        ];
    }
    
    return $this->db->insert_batch(db_prefix() . 'ramos_erp_automation_tracking', $data);
}
```

---

### 2. Update Automation Controller

#### Current Code (Omni_sales Only)
```php
// File: /modules/ramos/controllers/Automation.php - run() method

public function run()
{
    $unprocessedOrders = $this->automation_model->get_unprocessed_omni_orders();
    
    // ... process only omni orders ...
    
    $this->automation_model->mark_omni_orders_as_processed($orderIds, $runId);
}
```

#### New Code (Both Sources)
```php
public function run()
{
    // Get BOTH omni_sales AND ERP orders
    $omniOrders = $this->automation_model->get_unprocessed_omni_orders();
    $erpOrders = $this->automation_model->get_unprocessed_erp_orders();
    
    // Merge and process together
    $allOrders = array_merge($omniOrders, $erpOrders);
    $orderIds = array_column($allOrders, 'id');
    
    // Get quantities from BOTH sources
    $omniQuantities = $this->automation_model->get_required_quantities_from_omni_orders($omniIds);
    $erpQuantities = $this->automation_model->get_required_quantities_from_erp_orders($erpIds);
    
    // Merge quantities
    $requiredQuantities = array_merge_add($omniQuantities, $erpQuantities);
    
    // ... process as before ...
    
    // Mark BOTH as processed
    if (!empty($omniIds)) {
        $this->automation_model->mark_omni_orders_as_processed($omniIds, $runId);
    }
    if (!empty($erpIds)) {
        $this->automation_model->mark_erp_orders_as_processed($erpIds, $runId);
    }
}
```

---

## Step-by-Step Implementation Plan

### Phase 1: Database Preparation (Optional but Recommended)
1. Add `processed_for_purchase` column to `tblinvoices`
2. Add `automation_run_id` column to `tblinvoices`
3. Or: Create external tracking table if can't modify invoices

### Phase 2: Code Changes
1. Add `get_unprocessed_erp_orders()` to `Automation_model.php`
2. Add `get_required_quantities_from_erp_orders()` to `Automation_model.php`
3. Add `mark_erp_orders_as_processed()` to `Automation_model.php`
4. Update `Automation.php` controller `run()` method to merge both sources

### Phase 3: Testing
1. Create ERP order via portal
2. Run automation
3. Verify ERP order included in deficit calculations
4. Verify ERP order marked as processed
5. Verify purchase orders created for ERP items

### Phase 4: Dashboard Updates
1. Update `count_unprocessed_omni_orders()` call to include ERP count
2. Update UI to show "X omni + Y ERP orders unprocessed"

---

## Complexity Assessment

### Low Complexity Items
✅ Add model methods (5 simple queries)
✅ Merge arrays in controller
✅ Call mark_processed for both sources

### Medium Complexity Items
⚠️ Handle different item ID field names (product_id vs itemid)
⚠️ Map ERP items to inventory system if needed
⚠️ Decide on processing flag strategy

### High Complexity Items
❌ None - fairly straightforward once data structure understood

---

## Estimated Effort

- **Database setup**: 5 minutes (if adding column)
- **Code changes**: 30-45 minutes
- **Testing**: 20-30 minutes
- **Total**: ~1 hour

---

## Data Mapping Reference

When merging omni_sales and ERP orders, key field mappings:

```
Omni_sales → ERP
┌──────────────────────────────────────────┐
│ tblcart.id → tblinvoices.id              │
│ tblcart.clientid → tblinvoices.clientid  │
│ tblcart.datecreator → tblinvoices.date   │
│ tblcart.total → tblinvoices.total        │
│ tblcart_detailt.cart_id → tblinvoiceitems.invoiceid │
│ tblcart_detailt.product_id → tblinvoiceitems.itemid │
│ tblcart_detailt.quantity → tblinvoiceitems.qty      │
└──────────────────────────────────────────┘
```

---

## Important Considerations

### 1. Item ID Compatibility
- Omni_sales uses `product_id` (maps to tblitems.id)
- ERP uses `itemid` (also maps to tblitems.id)
- Both should work with warehouse inventory system

### 2. Status Differences
```
Omni_sales (tblcart)
├─ 0 = Cart
├─ 1 = Ordered
├─ 2 = Confirmed
├─ 3 = Shipped
├─ 4 = Delivered
└─ 5 = Cancelled

ERP (tblinvoices)
├─ 0 = Draft
├─ 1 = Sent/Unpaid ← Use this for unprocessed
├─ 2 = Paid
└─ 3 = Partial
```

### 3. Supplier Mapping
- Omni_sales items may have supplier links
- ERP items may or may not have suppliers defined
- May need fallback logic to handle missing suppliers

### 4. Zone/Priority
- Omni_sales orders have zone assignments
- ERP orders don't
- Need to decide: Use customer's default zone, or "No Zone"

---

## Risk Mitigation

**Risk 1**: Duplicate processing of ERP orders
- **Mitigation**: Ensure processed_for_purchase flag is set correctly

**Risk 2**: Item ID mapping fails
- **Mitigation**: Log mismatches, skip items that don't map

**Risk 3**: Breaks existing omni_sales automation
- **Mitigation**: Array merge adds ERP to existing logic, doesn't change omni handling

**Risk 4**: Supplier information missing
- **Mitigation**: Null checks when grouping by supplier

---

## Summary

To include ERP orders in automation:

1. **Add 3 methods** to `Automation_model.php`
   - Get unprocessed ERP orders
   - Get quantities from ERP orders
   - Mark ERP orders processed

2. **Update 1 controller method** (`Automation.php::run()`)
   - Merge omni + ERP orders
   - Merge quantities
   - Mark both as processed

3. **Optional database change**
   - Add `processed_for_purchase` column to `tblinvoices` for tracking

**Effort**: ~1 hour implementation + testing

**Risk**: Low (additive, doesn't break existing)

**Impact**: Both order systems now contribute to purchase order generation
