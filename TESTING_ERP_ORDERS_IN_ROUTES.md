# Quick Reference: Testing ERP Orders in Routes

## How to Test

### Step 1: Create an ERP Portal Order
1. Login as customer at `http://erp.local/`
2. Go to Home page
3. Scroll to "New Order" section (right side)
4. Add items (product name, quantity, rate)
5. Click "Save New Order"
6. Order is created as invoice in tblinvoices

### Step 2: Verify Database Entry
```sql
-- Check if invoice was created
SELECT id, number, clientid, status, duedate, clientnote 
FROM tblcart 
WHERE duedate = CURDATE() 
AND status = 1 
AND clientnote LIKE '%portal%';
```

Expected: Should return the invoice you just created

### Step 3: Generate Routes
1. Go to Ramos Module → Routes
2. Click "Generate routes"
3. Select a date that matches order's duedate
4. Click "Generate"

### Step 4: Verify Routes Included Both
1. View the generated routes
2. Routes should now include:
   - Orders from omni_sales portal (tblcart)
   - Orders from ERP portal (tblinvoices)

### Step 5: Check Route Stops
1. Click on a route to view details
2. View individual stops
3. Should display customer info from either:
   - Omni_sales: phonenumber, address from tblcart
   - ERP: company, shipping_street from tblclients

---

## Database Queries

### Find ERP Portal Orders for Routing
```sql
SELECT 
    i.id,
    i.number as order_number,
    cl.company as customer_name,
    cl.shipping_street as address,
    i.duedate as delivery_datetime,
    i.status,
    i.clientnote
FROM tblinvoices i
LEFT JOIN tblclients cl ON cl.userid = i.clientid
WHERE i.status = 1  -- unpaid
AND i.duedate = CURDATE()
AND i.clientnote LIKE '%portal%'
ORDER BY i.duedate ASC;
```

### Find Omni_sales Orders for Routing
```sql
SELECT 
    c.id,
    c.order_number,
    c.phonenumber as customer_name,
    c.address as delivery_address,
    c.duedate as delivery_datetime,
    c.channel_id
FROM tblcart c
WHERE c.status != 5  -- not cancelled
AND c.channel_id IN (1,2,4,6)
AND c.original_order_id IS NULL  -- not return
AND (c.duedate IS NULL OR DATE(c.duedate) = CURDATE())
ORDER BY c.duedate ASC;
```

### Find All Orders in Routes
```sql
SELECT 
    rs.route_id,
    rs.order_id,
    rs.stop_number,
    CASE 
        WHEN c.id IS NOT NULL THEN 'omni_sales'
        WHEN i.id IS NOT NULL THEN 'erp_invoice'
        ELSE 'unknown'
    END as order_source,
    c.order_number as omni_order_number,
    i.number as erp_order_number,
    c.phonenumber as omni_customer,
    cl.company as erp_customer
FROM tbl{prefix}ramos_route_stops rs
LEFT JOIN tblcart c ON c.id = rs.order_id
LEFT JOIN tblinvoices i ON i.id = rs.order_id
LEFT JOIN tblclients cl ON cl.userid = i.clientid
WHERE rs.route_id = [ROUTE_ID]
ORDER BY rs.stop_number ASC;
```

---

## Troubleshooting

### Issue: ERP Orders Not Appearing in Routes

**Check 1: Invoice Status**
```sql
SELECT status FROM tblinvoices WHERE id = [ORDER_ID];
-- Should return: 1 (unpaid)
-- If != 1, change status to 1
```

**Check 2: Client Note**
```sql
SELECT clientnote FROM tblinvoices WHERE id = [ORDER_ID];
-- Should contain: 'portal' or 'customer'
-- If not, update it
```

**Check 3: Due Date**
```sql
SELECT duedate FROM tblinvoices WHERE id = [ORDER_ID];
-- Should match route generation date
-- Or be NULL (means any date)
```

**Check 4: Not Already in Route**
```sql
SELECT rs.* FROM tbl{prefix}ramos_route_stops rs
WHERE rs.order_id = [ERP_ORDER_ID];
-- Should return: empty result
-- If returns rows, order is already assigned to a route
```

### Issue: ERP Orders Appearing But Customer Info Missing

Check `get_route_stops()` query:
```sql
-- Should return customer from tblclients
SELECT i.number, cl.company, cl.shipping_street, i.duedate
FROM tblinvoices i
LEFT JOIN tblclients cl ON cl.userid = i.clientid
WHERE i.id = [ORDER_ID];
```

If company/street are NULL:
- Check that client has proper shipping address set
- May need to update client profile

---

## Expected Behavior After Changes

### Before Update
```
Route Generation:
  - Omni_sales orders only
  - ERP orders never included
  - Routes ignore tblinvoices
```

### After Update
```
Route Generation:
  - Omni_sales orders from tblcart ✅
  - ERP orders from tblinvoices ✅
  - Both sorted by zone, duedate, id ✅
  - Routes created for both sources ✅
```

---

## SQL to Monitor Changes

### Monitor Order Creation
```sql
-- See new invoices created
SELECT id, number, clientid, duedate, clientnote, DATE(date) as created
FROM tblinvoices
WHERE DATE(date) >= DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY date DESC;

-- See new cart orders
SELECT id, order_number, userid, duedate, channel_id, DATE(datecreator) as created
FROM tblcart
WHERE DATE(datecreator) >= DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY datecreator DESC;
```

### Monitor Route Generation
```sql
-- See created routes
SELECT id, route_date, start_time, vehicle_label, capacity
FROM tbl{prefix}ramos_routes
WHERE DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY created_at DESC;

-- See stops and order sources
SELECT 
    r.id as route_id,
    r.vehicle_label,
    rs.stop_number,
    rs.order_id,
    CASE 
        WHEN c.id IS NOT NULL THEN 'omni_sales'
        WHEN i.id IS NOT NULL THEN 'erp_invoice'
    END as source
FROM tbl{prefix}ramos_routes r
LEFT JOIN tbl{prefix}ramos_route_stops rs ON r.id = rs.route_id
LEFT JOIN tblcart c ON c.id = rs.order_id
LEFT JOIN tblinvoices i ON i.id = rs.order_id
WHERE DATE(r.created_at) >= DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY r.created_at DESC, rs.stop_number ASC;
```

---

## Files Modified

- ✅ `/modules/ramos/models/Routes_model.php`
  - `generate_routes()` - Added ERP invoice query
  - `get_route_stops()` - Added fallback to ERP invoices

---

## Rollback Instructions (If Needed)

### To revert to omni_sales only:

Restore original query in `Routes_model.php::generate_routes()`:
```php
// Remove the combined query and replace with:
$this->db->select('c.id, c.order_number, c.phonenumber as customer_name, c.address as delivery_address, c.duedate as delivery_datetime, c.userid');
$this->db->select('cfv.value as zona', false);
$this->db->from(db_prefix() . 'cart c');
$this->db->join($this->stopsTable . ' rs', 'rs.order_id = c.id', 'left');
$this->db->join(db_prefix() . 'customfieldsvalues cfv', 'cfv.relid = c.userid AND cfv.fieldid = 3', 'left');
// ... original where clauses ...
$orders = $this->db->get()->result_array();
```

Restore `get_route_stops()`:
```php
// Remove the foreach loop and use original join:
return $this->db
    ->select('rs.*, c.order_number, c.phonenumber as customer_name, c.address as delivery_address, c.duedate as delivery_datetime')
    ->from($this->stopsTable . ' rs')
    ->join(db_prefix() . 'cart c', 'c.id = rs.order_id', 'left')
    ->where('rs.route_id', (int) $routeId)
    ->order_by('rs.stop_number', 'ASC')
    ->get()
    ->result_array();
```
