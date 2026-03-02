# Route Generation Analysis - Manual Run of Automation

## Executive Summary

**Current Status:** The route generation is NOT being triggered during manual automation runs.

The automation process (`modules/ramos/controllers/Automation.php::run()`) handles **purchase order generation only**. There is NO route generation happening as part of this automation.

Routes are generated separately via a **manual trigger** in the Routes controller (`modules/ramos/controllers/Routes.php::generate()`), which is a separate workflow from the purchase automation.

---

## Client Requirements vs Current Implementation

### Client Rules for Route Generation:
1. **Delivery Time** - Primary sorting factor
2. **Zone** - Routes grouped by zone
3. **Customer Priority** - Routes ordered by customer priority
4. **Route Limit** - Maximum 10 customers per route (can have multiple routes in same zone)
5. **Route Display** - Display in order of customer priority
6. **Stop Reordering** - Customers can move within same route or to another route
7. **Dispatch Tracking** - Activation to track on-time departure

---

## Current Route Generation Logic

### Location
`/home/rev/Development/projects/erp/modules/ramos/models/Routes_model.php::generate_routes()`

### Current Implementation (PARTIAL COMPLIANCE):

#### ✅ Implemented Correctly:
1. **Zone-Based Grouping**: Routes ARE grouped by zone (Zona field from customer)
   ```php
   // Group orders by zone first, then chunk each zone's orders
   $ordersByZone = [];
   foreach ($orders as $order) {
       $zone = trim((string) ($order['zona'] ?? ''));
       ...
       $ordersByZone[$zone][] = $order;
   }
   ```

2. **Delivery Time Consideration**: Earliest delivery time is used as route start time
   ```php
   foreach ($chunk as $order) {
       if (!empty($order['delivery_datetime'])) {
           $deliveryTs = strtotime($order['delivery_datetime']);
           if ($deliveryTs !== false && date('Y-m-d', $deliveryTs) === $date) {
               if ($earliestDelivery === null || $deliveryTs < $earliestDelivery) {
                   $earliestDelivery = $deliveryTs;
               }
           }
       }
   }
   ```

3. **Customer Limit**: Maximum customers per route (default 10, parameterized)
   ```php
   $chunks = array_chunk($zoneOrders, $maxStops); // maxStops defaults to 10
   ```

4. **Multiple Routes per Zone**: Supports multiple routes in same zone via chunking
   ```php
   $chunks = array_chunk($zoneOrders, $maxStops); // Creates multiple chunks if zone has >10
   $zoneLabel = count($chunks) > 1
       ? $zoneName . ' ' . ($chunkIndex + 1)
       : $zoneName;
   ```

#### ❌ NOT Implemented (Client Requirements):
1. **Customer Priority Ordering**: 
   - ❌ No customer priority field is being fetched or used for sorting within routes
   - Current ordering uses: Zone → Delivery Time → Order ID
   - Should use: Zone → Delivery Time → **Customer Priority** → Order ID
   - **Old Implementation** (in Routes_model-20251216.php) used: `FIELD(o.priority, 'high','normal','low')` ordering
   
   ```php
   // CURRENT ordering (in route generation):
   $this->db->order_by('cfv.value', 'ASC');           // Zone
   $this->db->order_by('c.duedate IS NULL', 'ASC');   // Delivery time nullability
   $this->db->order_by('c.duedate', 'ASC');           // Delivery time
   $this->db->order_by('c.id', 'ASC');                // Order ID
   
   // OLD ordering (ramos_orders - CORRECT approach):
   $this->db->order_by("FIELD(o.priority, 'high','normal','low')", '', false); // Priority
   $this->db->order_by('o.delivery_datetime IS NULL', 'ASC', false);          // Delivery time
   $this->db->order_by('o.delivery_datetime', 'ASC');                         // Delivery time
   $this->db->order_by('o.id', 'ASC');
   ```

2. **Display Order by Priority**: 
   - ❌ Routes are NOT displayed in order of customer priority
   - No priority field is being tracked or displayed
   - Old ramos_orders table had a `priority` field with values: 'high', 'normal', 'low'

3. **Customer Priority Field Mapping**:
   - ❌ No customer priority field is being retrieved from omni_sales/cart system
   - **NEED TO FIND**: Where priority is stored in omni_sales (possible sources):
     - Custom field on customer (like Zona is fieldid 3)
     - Custom field on order (tblcart)
     - Field in tblcustomers table (e.g., tier, vip_status, priority)
     - Field in tblcart table

4. **Route Stop Reordering**:
   - ✅ Structure supports this (stop_number field), but no UI/logic for enforcement

5. **Dispatch Tracking**:
   - ✅ Partial: Route status tracking exists (draft, dispatched, completed)
   - But no specific "departure time" validation logic

---

## Query Analysis

### Current Query in Routes_model::generate_routes()

```php
$this->db->select('c.id, c.order_number, c.phonenumber as customer_name, 
                  c.address as delivery_address, c.duedate as delivery_datetime, 
                  c.userid');
$this->db->select('cfv.value as zona', false);
$this->db->from(db_prefix() . 'cart c');
$this->db->join($this->stopsTable . ' rs', 'rs.order_id = c.id', 'left');
$this->db->join(db_prefix() . 'customfieldsvalues cfv', 
                'cfv.relid = c.userid AND cfv.fieldid = 3', 'left'); // Zona
$this->db->where('c.status !=', 5); // Exclude cancelled
$this->db->where('c.channel_id IN (1,2,4,6)', null, false); // Valid channels
$this->db->where('c.original_order_id IS NULL', null, false); // Not returns
$this->db->where('rs.id IS NULL', null, false); // Not already in routes
$this->db->group_start();
    $this->db->where('c.duedate IS NULL', null, false);
    $this->db->or_where('DATE(c.duedate) = ...', null, false);
$this->db->group_end();
$this->db->order_by('cfv.value', 'ASC');
$this->db->order_by('c.duedate IS NULL', 'ASC', false);
$this->db->order_by('c.duedate', 'ASC');
$this->db->order_by('c.id', 'ASC');
```

**Issue**: No customer priority field is being selected or ordered by.

---

## Related Files

### Controllers:
- `modules/ramos/controllers/Automation.php` - Handles purchase order automation (NO route generation)
- `modules/ramos/controllers/Routes.php` - Manual route generation trigger

### Models:
- `modules/ramos/models/Routes_model.php` - Contains route generation logic
- `modules/ramos/models/Automation_model.php` - Handles automation runs (no routes)

### Database Tables:
- `tblcart` - Orders (omni_sales)
- `tblcart_detailt` - Order items
- `tblcustomfieldsvalues` - Customer custom fields (including Zona)
- `tbl{prefix}_ramos_routes` - Route headers
- `tbl{prefix}_ramos_route_stops` - Route stops/orders

---

## Recommendations for Compliance

### 1. **Identify Customer Priority Field**
   - Determine where customer priority is stored in omni_sales
   - Could be in: `tblcustomers`, `tblcart`, or custom fields
   - Check if priority is: VIP status, tier level, or custom field

### 2. **Update Route Query**
   ```php
   // Add customer priority field to SELECT
   $this->db->select('c.id, c.order_number, ...., 
                     customer_priority_field as customer_priority');
   
   // Add to ORDER BY (after zone, before delivery time)
   $this->db->order_by('cfv.value', 'ASC');           // Zone
   $this->db->order_by('customer_priority', 'ASC');   // Customer Priority
   $this->db->order_by('c.duedate IS NULL', 'ASC');   // Delivery time nullability
   $this->db->order_by('c.duedate', 'ASC');           // Delivery time
   ```

### 3. **Store Priority in Route Stops**
   ```php
   // When inserting stops, also store priority for route display
   $this->db->insert($this->stopsTable, [
       'route_id'    => $routeId,
       'order_id'    => (int) $order['id'],
       'stop_number' => $position + 1,
       'eta'         => $etaValue,
       'status'      => 'pending',
       'customer_priority' => $order['customer_priority'], // ADD THIS
   ]);
   ```

### 4. **Update Route Display**
   - Ensure routes show customers in priority order
   - Add visual indicators for high-priority customers

### 5. **Add Dispatch Validation**
   - Implement on-time departure tracking
   - Add alerts if route departure is delayed

### 6. **Update Automation Integration** (if needed)
   - If routes should auto-generate after automation:
     ```php
     // In Automation.php::run() after completing orders
     $this->load->model('ramos/routes_model', 'routes_model');
     $this->routes_model->generate_routes($date, $startTime, $maxStops);
     ```

---

## Current Workflow

```
Manual Automation Trigger
        ↓
    Automation.php::run()
        ↓
    • Gets unprocessed omni_sales orders
    • Calculates inventory deficits
    • Creates purchase orders (by supplier priority)
    • Marks orders as processed
        ↓
    [AUTOMATION COMPLETE - No routes generated]
        ↓
    Separate Manual Trigger Required:
    Routes.php::generate()
        ↓
    Routes_model::generate_routes()
        ↓
    Routes created by Zone + Delivery Time
    (Customer priority NOT considered)
```

---

## Summary

The current route generation is **50% compliant** with client requirements:

✅ **Correct**:
- Zone-based grouping
- Delivery time consideration
- 10-customer limit per route
- Multiple routes per zone support

❌ **Missing**:
- Customer priority sorting/display
- Integration with automation process
- Priority-based route ordering

**Action Required**: Identify and implement customer priority field in route generation query and stop insertion.
