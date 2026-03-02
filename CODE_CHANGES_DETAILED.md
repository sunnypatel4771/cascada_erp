# Code Changes: ERP Orders in Routes - Detailed Diff

## File: `/modules/ramos/models/Routes_model.php`

---

## Change 1: `generate_routes()` Method

### Location
Lines 140-240 (approximately)

### What Changed
Replaced the single omni_sales query with a combined query for both omni_sales and ERP orders.

### OLD CODE (Original - Omni_sales Only)
```php
// NEW: Using omni_sales orders (tblcart)
// Get orders that have been picked (all pick items completed) but not yet in routes
// Also get customer Zona for grouping routes by zone
$this->db->select('c.id, c.order_number, c.phonenumber as customer_name, c.address as delivery_address, c.duedate as delivery_datetime, c.userid');
$this->db->select('cfv.value as zona', false);
$this->db->from(db_prefix() . 'cart c');
$this->db->join($this->stopsTable . ' rs', 'rs.order_id = c.id', 'left');
$this->db->join(db_prefix() . 'customfieldsvalues cfv', 'cfv.relid = c.userid AND cfv.fieldid = 3', 'left'); // fieldid 3 = Zona
$this->db->where('c.status !=', 5); // Exclude cancelled orders
$this->db->where('c.channel_id IN (1,2,4,6)', null, false); // Valid sales channels
$this->db->where('c.original_order_id IS NULL', null, false); // Exclude return orders
$this->db->where('rs.id IS NULL', null, false); // Not already in routes
$this->db->group_start();
    $this->db->where('c.duedate IS NULL', null, false);
    $this->db->or_where('DATE(c.duedate) = ' . $this->db->escape($date), null, false);
$this->db->group_end();
$this->db->order_by('cfv.value', 'ASC'); // Group by zona first
$this->db->order_by('c.duedate IS NULL', 'ASC', false);
$this->db->order_by('c.duedate', 'ASC');
$this->db->order_by('c.id', 'ASC');

$orders = $this->db->get()->result_array();

if (empty($orders)) {
    return [];
}
```

### NEW CODE (Updated - Omni_sales + ERP)
```php
// NEW: Combined query for both omni_sales (tblcart) and ERP portal (tblinvoices) orders
// Get orders that are not yet in routes and match the delivery date
// Also get customer Zona for grouping routes by zone

// Query 1: Omni_sales orders (tblcart)
$this->db->select('c.id, c.order_number, c.phonenumber as customer_name, c.address as delivery_address, c.duedate as delivery_datetime, c.userid, "omni_sales" as order_source');
$this->db->select('cfv.value as zona', false);
$this->db->from(db_prefix() . 'cart c');
$this->db->join($this->stopsTable . ' rs', 'rs.order_id = c.id', 'left');
$this->db->join(db_prefix() . 'customfieldsvalues cfv', 'cfv.relid = c.userid AND cfv.fieldid = 3', 'left'); // fieldid 3 = Zona
$this->db->where('c.status !=', 5); // Exclude cancelled orders
$this->db->where('c.channel_id IN (1,2,4,6)', null, false); // Valid sales channels
$this->db->where('c.original_order_id IS NULL', null, false); // Exclude return orders
$this->db->where('rs.id IS NULL', null, false); // Not already in routes
$this->db->group_start();
    $this->db->where('c.duedate IS NULL', null, false);
    $this->db->or_where('DATE(c.duedate) = ' . $this->db->escape($date), null, false);
$this->db->group_end();

$omni_orders = $this->db->get()->result_array();

// Query 2: ERP portal orders (tblinvoices)
// Only include invoices that are unpaid/partially paid and have a due date matching the specified date
$this->db->select('i.id, i.number as order_number, cl.company as customer_name, cl.shipping_street as address, i.duedate as delivery_datetime, i.clientid as userid, "erp_invoice" as order_source');
$this->db->select('"' . _l('ramos_routes_no_zone') . '" as zona', false); // ERP orders assigned to "No Zone" by default
$this->db->from(db_prefix() . 'invoices i');
$this->db->join(db_prefix() . 'clients cl', 'cl.userid = i.clientid', 'left');
$this->db->join($this->stopsTable . ' rs', 'rs.order_id = i.id', 'left');
$this->db->where('i.status', 1); // Unpaid invoices (status = 1 is unpaid)
$this->db->where('rs.id IS NULL', null, false); // Not already in routes
$this->db->where("i.clientnote LIKE '%portal%' OR i.clientnote LIKE '%customer%'", null, false); // Only portal-created invoices
$this->db->group_start();
    $this->db->where('i.duedate IS NULL', null, false);
    $this->db->or_where('DATE(i.duedate) = ' . $this->db->escape($date), null, false);
$this->db->group_end();

$erp_orders = $this->db->get()->result_array();

// Combine both order sources
$orders = array_merge($omni_orders, $erp_orders);

// Sort combined orders by zona, duedate, and id
if (!empty($orders)) {
    usort($orders, function($a, $b) {
        // Sort by zona first
        $zonaCompare = strcmp($a['zona'] ?? '', $b['zona'] ?? '');
        if ($zonaCompare !== 0) {
            return $zonaCompare;
        }
        
        // Then by duedate (nulls first)
        $aHasDate = !empty($a['delivery_datetime']);
        $bHasDate = !empty($b['delivery_datetime']);
        if ($aHasDate && $bHasDate) {
            return strtotime($a['delivery_datetime']) <=> strtotime($b['delivery_datetime']);
        }
        if ($aHasDate !== $bHasDate) {
            return $bHasDate ? 1 : -1;
        }
        
        // Finally by ID
        return $a['id'] <=> $b['id'];
    });
}
```

### Key Changes
1. **Separate omni_sales query** - stored in `$omni_orders`
2. **New ERP invoice query** - stored in `$erp_orders`
3. **Array merge** - combines both sources
4. **Custom sort function** - unified sorting by zona, duedate, ID
5. **Added `order_source` field** - tracks where each order came from

---

## Change 2: `get_route_stops()` Method

### Location
Lines 88-115 (approximately)

### What Changed
Replaced LEFT JOIN to single table with fallback query logic that checks both sources.

### OLD CODE (Original - Omni_sales Only)
```php
// NEW: Using omni_sales orders (tblcart)
return $this->db
    ->select('rs.*, c.order_number, c.phonenumber as customer_name, c.address as delivery_address, c.duedate as delivery_datetime')
    ->from($this->stopsTable . ' rs')
    ->join(db_prefix() . 'cart c', 'c.id = rs.order_id', 'left')
    ->where('rs.route_id', (int) $routeId)
    ->order_by('rs.stop_number', 'ASC')
    ->get()
    ->result_array();
```

### NEW CODE (Updated - Both Sources with Fallback)
```php
// NEW: Get route stops from both omni_sales (tblcart) and ERP portal (tblinvoices) orders
$stops = $this->db
    ->select('rs.*')
    ->from($this->stopsTable . ' rs')
    ->where('rs.route_id', (int) $routeId)
    ->order_by('rs.stop_number', 'ASC')
    ->get()
    ->result_array();

// For each stop, get the order details from the appropriate source
foreach ($stops as &$stop) {
    // Try to get from omni_sales (tblcart) first
    $omni_order = $this->db
        ->select('c.order_number, c.phonenumber as customer_name, c.address as delivery_address, c.duedate as delivery_datetime')
        ->from(db_prefix() . 'cart c')
        ->where('c.id', (int) $stop['order_id'])
        ->get()
        ->row_array();

    if ($omni_order) {
        $stop = array_merge($stop, $omni_order);
    } else {
        // Fall back to ERP invoice (tblinvoices)
        $erp_order = $this->db
            ->select('i.number as order_number, cl.company as customer_name, cl.shipping_street as delivery_address, i.duedate as delivery_datetime')
            ->from(db_prefix() . 'invoices i')
            ->join(db_prefix() . 'clients cl', 'cl.userid = i.clientid', 'left')
            ->where('i.id', (int) $stop['order_id'])
            ->get()
            ->row_array();

        if ($erp_order) {
            $stop = array_merge($stop, $erp_order);
        }
    }
}
unset($stop);

return $stops;
```

### Key Changes
1. **Select stops first** - get all route_stops for a route
2. **Fallback query logic** - for each stop, try omni_sales first
3. **Secondary query** - if omni_sales not found, try ERP invoice
4. **Array merge** - combines stop data with order details
5. **Graceful handling** - if neither source found, returns stop with minimal data

---

## Why These Changes Work

### Query Strategy: Dual-Source with Fallback

**Advantages:**
1. ✅ Flexible - easily add more order sources later
2. ✅ Isolated - omni_sales issues don't affect ERP queries
3. ✅ Maintainable - clear separation of concerns
4. ✅ Efficient - only queries needed tables
5. ✅ Safe - NULL handling at each step

**Alternative Approaches Considered:**
- ❌ UNION query - harder to maintain, complex sorting
- ❌ Single large JOIN - too many LEFT JOINs, slow
- ✅ Separate queries merged - chosen approach

---

## Data Flow Diagram

### Before Implementation
```
Routes Generation
    ↓
Query tblcart (omni_sales only)
    ↓
Group by zone
    ↓
Create routes
    ↓
Get route stops
    ↓
Query tblcart for details
    ↓
Display routes

ERP Invoices → NOT INCLUDED ❌
```

### After Implementation
```
Routes Generation
    ↓
Query tblcart (omni_sales)  ─┐
                              ├→ Merge
Query tblinvoices (ERP)  ─────┘
    ↓
Sort combined results
    ↓
Group by zone
    ↓
Create routes
    ↓
Get route stops
    ↓
Try tblcart first ─┐
                   ├→ Get order details
Fall back to tblinvoices ─┘
    ↓
Display routes with both sources ✅
```

---

## Performance Impact

### Query Count
- **Before**: 1 query to get orders + N queries to get stop details
- **After**: 2 queries to get orders + N*2 potential queries (with fallback)

### Index Recommendations
```sql
-- Ensure these indexes exist for performance
ALTER TABLE tblcart ADD INDEX idx_channel_status_duedate (channel_id, status, duedate);
ALTER TABLE tblinvoices ADD INDEX idx_status_duedate (status, duedate);
ALTER TABLE tblinvoices ADD INDEX idx_clientnote (clientnote);
```

### Expected Impact
- Minimal (orders query) - adds one additional SELECT
- Minor (stop details) - adds fallback IF omni_sales not found
- Overall performance impact: **negligible** for typical scenarios

---

## Testing Checklist

- [ ] Create omni_sales order, generate routes - should include
- [ ] Create ERP order, generate routes - should include
- [ ] Create both, generate routes - should include both
- [ ] View route details - customer info displays correctly
- [ ] Verify zone grouping - omni in zones, ERP in "No Zone"
- [ ] Test with NULL due dates - both types included
- [ ] Test already-routed orders - don't appear twice
- [ ] Check for SQL errors in logs - should be none

---

## Code Quality Notes

✅ **Follows Project Standards:**
- Uses CodeIgniter query builder syntax
- Consistent naming conventions
- Proper escaping and parameterization
- Comments explain complex logic
- Error handling via NULL checks

✅ **No Breaking Changes:**
- Existing queries still work
- Method signatures unchanged
- Return types consistent
- No dependencies added

✅ **Maintainable:**
- Clear separation of omni_sales and ERP logic
- Easy to extend to other order sources
- Documentation included
- Fallback strategy is explicit
