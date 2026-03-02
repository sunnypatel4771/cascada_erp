# Before and After: Route Generation with ERP Orders

## Summary
Routes now include BOTH omni_sales orders (tblcart) AND ERP portal orders (tblinvoices).

---

## BEFORE: Only Omni_sales Orders

### Route Generation Query
```php
// ONLY queries tblcart (omni_sales orders)
$this->db->select('c.id, c.order_number, c.phonenumber as customer_name, 
                  c.address as delivery_address, c.duedate as delivery_datetime, c.userid');
$this->db->from(db_prefix() . 'cart c');
$this->db->where('c.channel_id IN (1,2,4,6)');
// ... more filters ...
$orders = $this->db->get()->result_array();
```

### Problem
```
Omni_sales Orders Created → tblcart → ✅ INCLUDED in routes
ERP Portal Orders Created → tblinvoices → ❌ IGNORED by routes
```

### Result
- Routes only had omni_sales orders
- ERP portal orders were never delivered
- Two separate order systems, one with delivery workflow, one without

---

## AFTER: Combined Omni_sales + ERP Orders

### Route Generation Query
```php
// Query 1: Omni_sales orders
$this->db->select('c.id, c.order_number, c.phonenumber as customer_name, 
                  c.address as delivery_address, c.duedate as delivery_datetime, 
                  c.userid, "omni_sales" as order_source');
$this->db->from(db_prefix() . 'cart c');
$this->db->where('c.channel_id IN (1,2,4,6)');
// ... filters ...
$omni_orders = $this->db->get()->result_array();

// Query 2: ERP portal orders (NEW)
$this->db->select('i.id, i.number as order_number, cl.company as customer_name, 
                  cl.shipping_street as address, i.duedate as delivery_datetime, 
                  i.clientid as userid, "erp_invoice" as order_source');
$this->db->from(db_prefix() . 'invoices i');
$this->db->where('i.status', 1); // Unpaid
$this->db->where("i.clientnote LIKE '%portal%'"); // Portal-created
// ... filters ...
$erp_orders = $this->db->get()->result_array();

// Combine both sources
$orders = array_merge($omni_orders, $erp_orders);
```

### Solution
```
Omni_sales Orders Created → tblcart → ✅ INCLUDED in routes
ERP Portal Orders Created → tblinvoices → ✅ NOW INCLUDED in routes
```

### Result
- Routes include ALL orders from both systems
- Both order types get delivered
- Single unified delivery workflow for all orders

---

## Detailed Comparison

### Order Source: Omni_sales Portal

**Before**:
```
Customer → /omni_sales/omni_sales_client/
        → Creates order
        → Saved to tblcart with channel_id=2
        → ✅ Included in routes
```

**After**:
```
Customer → /omni_sales/omni_sales_client/
        → Creates order
        → Saved to tblcart with channel_id=2
        → ✅ Still included in routes (unchanged)
```

---

### Order Source: ERP Portal

**Before**:
```
Customer → http://erp.local/home.php
        → Creates order via "New Order" form
        → Saved to tblinvoices
        → ❌ IGNORED by routes
        → No delivery workflow
```

**After**:
```
Customer → http://erp.local/home.php
        → Creates order via "New Order" form
        → Saved to tblinvoices
        → ✅ NOW INCLUDED in routes
        → Full delivery workflow available
```

---

## Zone Assignment

### Omni_sales Orders
```
Before: Zone from customer custom field (fieldid 3)
After:  Zone from customer custom field (fieldid 3)
Status: ✅ Unchanged
```

### ERP Portal Orders
```
Before: Not considered for routes
After:  Assigned to "No Zone" group
        (Can be enhanced later to support zona custom fields)
Status: ✅ New functionality
```

---

## Route Generation Example

### Before: 5 Omni_sales Orders

```
Date: 2026-03-02
Request: Generate routes

Query tblcart:
  Order 1 (Zone: North) ← From omni_sales
  Order 2 (Zone: North) ← From omni_sales
  Order 3 (Zone: South) ← From omni_sales
  Order 4 (Zone: South) ← From omni_sales
  Order 5 (Zone: East)  ← From omni_sales

Result: 3 routes created (1 North, 1 South, 1 East)

ERP Portal Orders: IGNORED ❌
  - Order A from tblinvoices (duedate: 2026-03-02)
  - Order B from tblinvoices (duedate: 2026-03-02)
  - Order C from tblinvoices (duedate: 2026-03-02)
```

### After: 5 Omni_sales + 3 ERP Orders

```
Date: 2026-03-02
Request: Generate routes

Query tblcart:
  Order 1 (Zone: North)     ← From omni_sales
  Order 2 (Zone: North)     ← From omni_sales
  Order 3 (Zone: South)     ← From omni_sales
  Order 4 (Zone: South)     ← From omni_sales
  Order 5 (Zone: East)      ← From omni_sales

Query tblinvoices:
  Order A (Zone: No Zone)   ← From ERP portal (NEW)
  Order B (Zone: No Zone)   ← From ERP portal (NEW)
  Order C (Zone: No Zone)   ← From ERP portal (NEW)

Combined and sorted:
  Zone: East        → Route 1 (1 order)
  Zone: No Zone     → Route 2 (3 orders - all ERP)
  Zone: North       → Route 3 (2 orders)
  Zone: South       → Route 4 (2 orders)

Result: 4 routes created
  - Route 1: East zone (1 omni_sales order)
  - Route 2: No Zone (3 ERP orders) ← NEW
  - Route 3: North zone (2 omni_sales orders)
  - Route 4: South zone (2 omni_sales orders)
```

---

## get_route_stops() Changes

### Before
```php
// Only checked tblcart
->join(db_prefix() . 'cart c', 'c.id = rs.order_id', 'left')
->select('c.order_number, c.phonenumber as customer_name, ...')
```

### After
```php
// Check both sources with fallback logic
foreach ($stops as &$stop) {
    // Try tblcart first
    $omni_order = query tblcart WHERE id = $stop['order_id'];
    
    if ($omni_order) {
        // Use omni_sales data
        $stop = merge_with_omni_data;
    } else {
        // Fall back to tblinvoices
        $erp_order = query tblinvoices WHERE id = $stop['order_id'];
        if ($erp_order) {
            // Use ERP data
            $stop = merge_with_erp_data;
        }
    }
}
```

---

## Backward Compatibility

✅ **All existing functionality preserved:**
- Existing omni_sales routes work unchanged
- Zone grouping for omni_sales remains the same
- Route display logic unchanged
- No database migrations required
- No configuration changes needed

---

## Testing Scenarios

### Scenario 1: Only Omni_sales Orders
```
Setup: 3 omni_sales orders, 0 ERP orders
Result: Routes work as before ✅
```

### Scenario 2: Only ERP Orders
```
Setup: 0 omni_sales orders, 3 ERP orders
Result: Routes created in "No Zone" group ✅
```

### Scenario 3: Mixed Orders (NEW)
```
Setup: 3 omni_sales orders (2 North, 1 South) + 2 ERP orders
Result: 
  - North zone: 2 orders (omni_sales)
  - No Zone: 2 orders (ERP)
  - South zone: 1 order (omni_sales)
Status: ✅ All included
```

---

## Summary of Benefits

| Feature | Before | After |
|---------|--------|-------|
| Omni_sales order routes | ✅ | ✅ |
| ERP portal order routes | ❌ | ✅ |
| Combined zone grouping | N/A | ✅ |
| Route stop details | Omni only | Both sources |
| Database changes needed | No | No |
| Code backward compatible | N/A | ✅ |
| Customer visible changes | No | No |
