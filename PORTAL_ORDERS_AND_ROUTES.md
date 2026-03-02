# Customer Orders from Portal: Route Consideration Analysis

## Quick Answer
**YES** - Customer orders created on `/home.php` (portal) **ARE** being considered for route generation.

---

## Evidence

### 1. Customer Order Creation (Portal Checkout)
**Location**: `modules/omni_sales/models/Omni_sales_model.php::check_out()` (Line 486)

When a customer logs in and creates an order through the portal (home.php → checkout):
```php
$channel_id = 2;  // Line 493
$data_cart['channel_id'] = $channel_id;
$data_cart['channel'] = 'portal';

$this->db->insert(db_prefix() . 'cart', $data_cart);
```

**Key Point**: Portal orders get `channel_id = 2`

---

### 2. Route Generation Filtering
**Location**: `modules/ramos/models/Routes_model.php::generate_routes()` (Line 174)

When routes are generated:
```php
$this->db->where('c.channel_id IN (1,2,4,6)', null, false); // Valid sales channels
```

**Interpretation**:
- Channel ID 1, 2, 4, 6 are all included
- Channel ID 2 = **Portal orders** (where customers create orders)
- This filter **INCLUDES** portal orders

---

### 3. Sales Channels Breakdown

Based on code analysis, the sales channels are:
- **Channel 1**: Unknown (included in route generation)
- **Channel 2**: **Portal/Client Portal** ✅ (Included in routes)
- **Channel 3**: Not included (possibly excluded for a reason)
- **Channel 4**: Unknown (included in route generation)
- **Channel 6**: Unknown (included in route generation)

---

## Complete Route Generation Query Flow

```
Customer submits order from portal (home.php)
        ↓
Order inserted into tblcart with channel_id = 2
        ↓
Route generation triggered
        ↓
Query filters: 
    - status != 5 (not cancelled)
    - channel_id IN (1,2,4,6) ← INCLUDES channel 2 ✅
    - original_order_id IS NULL (not return orders)
    - NOT already in routes
    - duedate matches specified date
        ↓
Orders grouped by Zone (customer.zona custom field)
        ↓
Orders chunked into max 10 per route
        ↓
Routes created and displayed
```

---

## Full Query Conditions for Route Inclusion

For a customer order to be included in route generation, it must satisfy ALL of these:

```php
c.status != 5                                    // Not cancelled
c.channel_id IN (1,2,4,6)                       // Valid channel ✅
c.original_order_id IS NULL                     // Not a return order
rs.id IS NULL                                   // Not already assigned to a route
(c.duedate IS NULL OR DATE(c.duedate) = date)  // Due today or no due date
```

**Portal orders (channel_id=2) PASS all these filters** ✅

---

## Order Lifecycle for Portal Orders

```
1. Customer creates order at home.php portal
   └─ channel_id = 2, channel = 'portal'

2. Order stored in tblcart with items in tblcart_detailt
   └─ status = 0 (default status)

3. Manual route generation triggered
   └─ Query selects all tblcart orders with channel_id IN (1,2,4,6)
   └─ Portal orders (2) are included

4. Orders grouped by zone and chunked into routes
   └─ Max 10 per route (or custom limit)
   └─ One route per zone, or multiple if zone has >10 customers

5. Routes available for picking, packing, and delivery
```

---

## Important Considerations

### What's NOT Considered:
1. **Cancelled orders** (status = 5)
2. **Return orders** (original_order_id IS NOT NULL)
3. **Orders already assigned to routes**

### What Happens with Channel ID 3 or 5?
- These channels are **EXCLUDED** from route generation
- Possibly for: admin-only orders, internal transfers, or special channel types

### Zone Assignment:
- Routes are grouped by `Zona` custom field (fieldid = 3) from customer data
- If customer has no zone set, labeled as "No Zone"
- Multiple routes created per zone if >10 orders

---

## Related Order Sources Also Included

The route query includes orders from multiple channels:
- **Channel 1**: (source unclear - possibly another sales method)
- **Channel 2**: **Portal orders** ✓
- **Channel 4**: (source unclear)
- **Channel 6**: (source unclear - possibly B2B or special orders)

All are treated equally in route generation - no priority distinction at the query level.

---

## Summary Answer

**To directly answer your question:**

**✅ YES - Customer orders created on home.php ARE considered for routes.**

Customer-created portal orders:
1. Get assigned `channel_id = 2`
2. Are explicitly included in the route generation query (`WHERE channel_id IN (1,2,4,6)`)
3. Are grouped by customer zone
4. Are chunked into max 10-customer routes
5. Are available for the delivery workflow

There is **NO special filtering that excludes portal orders** from route generation. They are treated as regular orders alongside orders from other valid sales channels.
