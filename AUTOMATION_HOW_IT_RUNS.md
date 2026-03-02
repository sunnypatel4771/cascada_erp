# How Automation is Running - Complete Analysis

## Overview

The automation process in Ramos is a **Purchase Order (PO) Generation System** that analyzes inventory vs order demand and automatically creates purchase orders for suppliers.

---

## Automation Workflow (11 Steps)

### Step 1: Get Unprocessed Orders
```php
$unprocessedOrders = $this->automation_model->get_unprocessed_omni_orders();
```

**Source**: `tblcart` (omni_sales orders)
**Filters**:
- Status != 5 (not cancelled)
- processed_for_purchase = 0 or NULL
- channel_id IN (1,2,4,6)
- original_order_id IS NULL (not return orders)

**Returns**: All orders ready to be analyzed for purchase needs

---

### Step 2: Create Automation Run Record
```php
$runId = $this->automation_model->create_run([
    'run_type' => 'purchase_generation',
    'run_by'   => get_staff_user_id(),
    'status'   => 'running'
]);
```

**Purpose**: Track this automation execution
**Records**: Automation run start, user who ran it, status

---

### Step 3: Calculate Required Quantities
```php
$requiredQuantities = $this->automation_model->get_required_quantities_from_omni_orders($orderIds);
```

**Source**: `tblcart_detailt` (order line items)
**Calculates**: How many units of each product are needed across all unprocessed orders
**Returns**: Array keyed by product_id with total quantity required

---

### Step 4: Get Current Inventory
```php
$inventoryMap = $this->automation_model->get_warehouse_inventory_by_product();
```

**Source**: `tblinventory_manage` (warehouse module)
**Aggregates**: Stock quantities by commodity_id (product_id)
**Returns**: Current stock levels for each product

---

### Step 5: Get Open Purchase Quantities
```php
$openPurchaseQuantities = $this->purchase_model->get_open_purchase_quantities(['draft', 'sent', 'partial']);
```

**Source**: Purchase batches table
**Filters**: Draft, sent, or partial purchase orders (items already ordered but not received)
**Purpose**: Don't re-order items that are already on order
**Returns**: Quantities of items in open purchase orders

---

### Step 6: Build Deficit Report
```php
$deficitGroups = $this->purchase_model->build_supplier_deficits(
    $requiredQuantities,
    $inventoryMap,
    $openPurchaseQuantities
);
```

**Calculation**:
```
Needed = Required - Current Stock - Open Purchase Quantities
If Needed > 0: Item needs to be purchased
```

**Groups Results**: By supplier ID
**Returns**: Array of items grouped by supplier, showing what needs to be purchased

---

### Step 7: Get Suppliers and Map Them
```php
$suppliers = $this->suppliers_model->get();
foreach ($deficitGroups as $supplierId => &$group) {
    $group['supplier'] = $supplierMap[$supplierId] ?? null;
}
```

**Purpose**: Attach full supplier details to each group
**Includes**: Supplier name, priority, contact info, etc.

---

### Step 8: Sort by Supplier Priority
```php
uasort($deficitGroups, function($a, $b) {
    $priorityA = isset($a['supplier']['priority']) ? (int) $a['supplier']['priority'] : 999;
    $priorityB = isset($b['supplier']['priority']) ? (int) $b['supplier']['priority'] : 999;
    return $priorityA <=> $priorityB;
});
```

**Logic**: Lower number = higher priority
**Result**: Batches created in priority order
**Purpose**: Purchase from high-priority suppliers first

---

### Step 9: Create Draft Purchase Batches
```php
foreach ($deficitGroups as $supplierId => $group) {
    $batchId = $this->purchase_model->create_batch(
        $supplierId ?: null,
        $batchItems
    );
}
```

**Action**: Creates draft purchase batches in the system
**Status**: Draft (not sent to supplier yet)
**Items**: Grouped by supplier with quantities to order
**Returns**: Array of created batch IDs

---

### Step 10: Mark Orders as Processed
```php
$this->automation_model->mark_omni_orders_as_processed($orderIds, $runId);
```

**Updates**: `tblcart` table, sets `processed_for_purchase = 1`
**Purpose**: Mark orders so they aren't re-analyzed in next automation run
**Links**: Orders to automation run record

---

### Step 11: Complete Automation Run
```php
$this->automation_model->complete_run($runId, [
    'status'  => 'completed',
    'total_orders_processed' => count($orderIds),
    'total_purchase_orders_created' => count($createdBatches),
    'summary' => json_encode([...])
]);
```

**Updates**: Automation run record with results
**Records**: Number of orders processed, POs created, batch IDs

---

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────┐
│ User Triggers: Ramos → Automation → "Run Automation"│
└─────────────────┬───────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────┐
│ Step 1: Get Unprocessed Orders                       │
│ FROM tblcart WHERE processed_for_purchase = 0       │
│ Returns: Array of order IDs to analyze             │
└─────────────────┬───────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────┐
│ Step 2: Create Automation Run Record                │
│ INSERT INTO ramos_automation_runs                   │
│ Tracks execution progress                          │
└─────────────────┬───────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────┐
│ Step 3: Calculate Needed Quantities                 │
│ FROM tblcart_detailt GROUP BY product_id           │
│ SUM all quantities needed from orders              │
└─────────────────┬───────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────┐
│ Step 4: Get Current Inventory                       │
│ FROM tblinventory_manage GROUP BY commodity_id      │
│ Current stock levels per product                   │
└─────────────────┬───────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────┐
│ Step 5: Get Open Purchase Orders                    │
│ FROM purchase_batches WHERE status IN (...)         │
│ Don't double-order items already on order          │
└─────────────────┬───────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────┐
│ Step 6: Calculate Deficits                          │
│ Needed = Required - Current - Open                  │
│ Group by supplier                                  │
└─────────────────┬───────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────┐
│ Step 7-8: Sort by Supplier Priority                 │
│ Higher priority suppliers first                     │
└─────────────────┬───────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────┐
│ Step 9: Create Draft Purchase Batches               │
│ INSERT INTO purchase_batches                        │
│ Creates purchase orders (draft status)             │
└─────────────────┬───────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────┐
│ Step 10: Mark Orders as Processed                   │
│ UPDATE tblcart SET processed_for_purchase = 1       │
│ Flag orders so not re-analyzed                     │
└─────────────────┬───────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────┐
│ Step 11: Complete Automation Run                    │
│ UPDATE ramos_automation_runs SET status = completed │
│ Record final counts and results                    │
└─────────────────┬───────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────┐
│ Result: Purchase Batches Created (Draft Status)    │
│ Staff can review, edit, and send to suppliers      │
└─────────────────────────────────────────────────────┘
```

---

## Current Behavior vs Original Requirement

### What Automation DOES:
✅ Analyzes **omni_sales orders** (tblcart)
✅ Calculates inventory deficits
✅ Creates **purchase orders** by supplier priority
✅ Marks orders as processed

### What Automation DOES NOT DO:
❌ Does NOT analyze **ERP portal orders** (tblinvoices)
❌ Does NOT create routes
❌ Does NOT consider customer priority
❌ Does NOT consider delivery zones
❌ Does NOT consider delivery times

---

## Important Notes

### Automation ≠ Routes
- **Automation**: Creates purchase orders from suppliers
- **Routes**: Groups orders for delivery to customers
- **Two separate workflows** - one doesn't affect the other

### ERP Orders Missing from Automation
Current automation only processes:
- `tblcart` (omni_sales) orders ✅
- NOT `tblinvoices` (ERP portal) orders ❌

**If needed**: Would require separate code to include ERP orders

### Order Source Limitation
```php
$unprocessedOrders = $this->automation_model->get_unprocessed_omni_orders();
// This ONLY gets from tblcart
// Does NOT get from tblinvoices
```

---

## Configuration Needed

Automation works with existing settings:
- ✅ Supplier priorities
- ✅ Warehouse inventory
- ✅ Product definitions
- ✅ Safety stock levels (if configured)

No special automation configuration is needed.

---

## Key Differences from Original System

### Old (Ramos Module Orders)
- Had `priority` field (high, normal, low)
- Orders table: `ramos_orders`
- Items table: `ramos_order_items`

### New (Omni_sales Orders)
- No priority field on orders
- Orders table: `tblcart`
- Items table: `tblcart_detailt`

**Impact on automation**: Works the same way, just different table names

---

## Summary

**Automation Process**: Analyzes orders → Calculates deficits → Creates purchase orders

**Trigger**: Manual (user clicks "Run Automation")

**Orders Processed**: Only omni_sales orders (`tblcart`)

**Output**: Draft purchase order batches, ready to send to suppliers

**Next Step**: Staff reviews and sends batches to suppliers

---

## Future Enhancements

### To include ERP orders in automation:
1. Update `get_unprocessed_omni_orders()` to also query `tblinvoices`
2. Ensure ERP order items can be queried same way
3. Handle different data structures between systems

### To include customer priority/zones:
1. Add priority field to omni_sales orders (or custom field)
2. Modify sorting to include customer priority
3. Store priority in order details for routes to use
