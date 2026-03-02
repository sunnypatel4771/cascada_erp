# Route Generation Changes - Including ERP Portal Orders

## Changes Made

### Modified File: `/modules/ramos/models/Routes_model.php`

#### Change 1: Updated `generate_routes()` method

**What Changed:**
- The route generation query now includes BOTH omni_sales orders (tblcart) AND ERP portal orders (tblinvoices)
- Combined results from both sources before grouping by zone and creating routes

**Implementation Details:**

1. **Omni_sales Query** (Original):
   - Selects from `tblcart`
   - Filters by `channel_id IN (1,2,4,6)`
   - Gets customer zone from custom fields

2. **ERP Portal Query** (NEW):
   - Selects from `tblinvoices`
   - Filters for unpaid invoices (`status = 1`)
   - Only includes invoices marked as "portal-created" (checked via clientnote field)
   - Assigns default zone "No Zone" since ERP orders don't have zone field
   - Gets customer company name and shipping address

3. **Combined Processing**:
   ```php
   // Both order sources merged
   $orders = array_merge($omni_orders, $erp_orders);
   
   // Sorted by zona, duedate, and id
   usort($orders, function($a, $b) { ... });
   ```

**Why This Matters:**
- Both order systems (omni_sales portal and ERP main portal) now feed into the same routing system
- All orders get grouped by zone and chunked into max 10-customer routes
- No orders are left out of the delivery workflow

---

#### Change 2: Updated `get_route_stops()` method

**What Changed:**
- Now retrieves order details from either tblcart OR tblinvoices depending on the order source
- Uses fallback logic: tries omni_sales first, then ERP if not found

**Implementation:**
```php
// For each stop, determine which table to query from
foreach ($stops as &$stop) {
    // Try omni_sales (tblcart) first
    $omni_order = $this->db->select(...)
                           ->from(db_prefix() . 'cart c')
                           ->where('c.id', $stop['order_id'])
                           ->get()
                           ->row_array();
    
    if ($omni_order) {
        $stop = array_merge($stop, $omni_order);
    } else {
        // Fall back to ERP invoice (tblinvoices)
        $erp_order = $this->db->select(...)
                              ->from(db_prefix() . 'invoices i')
                              ->where('i.id', $stop['order_id'])
                              ->get()
                              ->row_array();
        
        if ($erp_order) {
            $stop = array_merge($stop, $erp_order);
        }
    }
}
```

**Why This Matters:**
- Route displays work for both order types
- Customer names, addresses, and delivery times are retrieved correctly
- No changes needed to the UI/views - they work with both sources transparently

---

## How It Works Now

### Route Generation Flow (Updated):

```
Manual "Generate Routes" trigger
    ↓
Routes_model::generate_routes($date, $startTime, $maxStops)
    ↓
Query omni_sales orders:
    FROM tblcart
    WHERE channel_id IN (1,2,4,6)
    AND status != 5
    AND duedate matches $date
    ↓
Query ERP portal orders:
    FROM tblinvoices
    WHERE status = 1 (unpaid)
    AND clientnote LIKE '%portal%'
    AND duedate matches $date
    ↓
Combine both result sets
    ↓
Sort by: Zone → Due Date → Order ID
    ↓
Group orders by Zone
    ↓
Chunk each zone into max 10-customer routes
    ↓
Create routes with stops
    ↓
Routes ready for picking, packing, delivery ✅
```

---

## ERP Portal Orders - Inclusion Criteria

For an ERP portal order to be included in routes, it must:

1. ✅ Be stored in `tblinvoices` table
2. ✅ Have `status = 1` (unpaid invoices)
3. ✅ Have `clientnote` containing "portal" or "customer"
4. ✅ Have a `duedate` matching the specified generation date (or NULL for any date)
5. ✅ Not already be assigned to a route
6. ✅ Have a valid client/customer linked

---

## Important Notes

### Zone Assignment for ERP Orders
- ERP portal orders are assigned to **"No Zone"** by default
- They don't have access to the customer zona custom field (which is for omni_sales)
- This ensures they're grouped together and don't interfere with zone-based routing
- **Future Enhancement**: Could integrate ERP customers with the zona custom field system

### Order Source Tracking
- Each order now has an `order_source` field indicating whether it came from "omni_sales" or "erp_invoice"
- This helps with debugging and future enhancements
- The source field is NOT stored in database, just used during processing

### Backward Compatibility
- All existing omni_sales routes continue to work unchanged
- No database migrations needed
- Existing route data is not affected

---

## Testing Checklist

- [ ] Create an omni_sales order via portal → Should appear in routes
- [ ] Create an ERP portal order via home.php → Should appear in routes
- [ ] Generate routes → Should include both order types
- [ ] View route stops → Should display customer info for both types
- [ ] Verify zone grouping → Omni_sales by actual zone, ERP in "No Zone"
- [ ] Verify chunking → Max 10 orders per route works for combined results
- [ ] Test with different dates → duedate filtering works correctly

---

## Future Enhancements

1. **Zone Assignment for ERP Orders**
   - Add custom field support for ERP customers
   - Map ERP customers to zonas for better routing

2. **Priority Ordering**
   - Implement customer priority sorting (as per client requirements)
   - Currently missing for both order types

3. **Automation Integration**
   - Update `Automation_model::get_unprocessed_omni_orders()` to also include ERP orders if needed

4. **Order Source Indicators**
   - UI badges/icons showing order source (omni_sales vs ERP)
   - Filter routes by order source

---

## Files Modified

- ✅ `/modules/ramos/models/Routes_model.php`
  - Modified: `generate_routes()` method (lines 140-240)
  - Modified: `get_route_stops()` method (lines 88-115)
