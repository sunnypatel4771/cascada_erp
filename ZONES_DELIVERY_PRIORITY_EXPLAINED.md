# Zones, Delivery Times & Customer Priority in Routes

## 1. ZONES (Grouping Routes)

### What Are Zones?
Zones are geographic regions used to organize and group routes together. Routes are generated per zone, so all deliveries in the same zone are assigned to the same route.

### Where Zones Come From

**For Omni_Sales Orders (tblcart):**
- **Source:** Custom Field ID 3: "Zona" 
- **Table:** `tblcustomfieldsvalues` where `fieldid = 3`
- **Stored:** Associated with customer (via `c.userid`)
- **Options:** Minerva, Andares, Providencia, Plaza Sol, Sur
- **Query:** 
  ```sql
  SELECT cfv.value as zona 
  FROM tblcart c
  LEFT JOIN tblcustomfieldsvalues cfv ON cfv.relid = c.userid AND cfv.fieldid = 3
  ```

**For ERP Portal Orders (tblinvoices):**
- **Source:** No custom field (ERP invoices don't have zone assignment)
- **Default:** "No Zone" (all ERP orders default to this)
- **Reason:** ERP portal customers haven't been assigned to zones yet
- **Impact:** All ERP orders group together in routes, separate from omni_sales routes

### Route Generation Logic (Zone Grouping)

```
Step 1: Fetch unprocessed orders for specified date
Step 2: Group ALL orders by zone
Step 3: For each zone separately:
        - Chunk zone's orders into groups (max stops per route)
        - Create route for each chunk
        - Label route as "Route - ZoneName" (or "Route - ZoneName 1", "Route - ZoneName 2" for multiple chunks)
Step 4: Sort within each route by delivery date
```

**Example:**
- 50 omni_sales orders in "Minerva" zone → Creates 2 routes (25 each)
  - "Route - Minerva 1" (25 stops)
  - "Route - Minerva 2" (25 stops)
- 12 omni_sales orders in "Andares" zone → Creates 1 route
  - "Route - Andares" (12 stops)
- 28 ERP orders (no zone) → Creates 2 routes (15 each)
  - "Route - No Zone 1" (15 stops)
  - "Route - No Zone 2" (13 stops)

---

## 2. DELIVERY TIME (ETA - Estimated Time of Arrival)

### How ETA is Calculated

**If order has a due date that matches the route date:**
- ETA = Order's due date/time
- Priority on route: Ordered by earliest due date first

**If no due date or different date:**
- ETA = Base route time + (stop position × 20 minutes)
- Example: Route starts 08:00, Stop 1 gets 08:00, Stop 2 gets 08:20, Stop 3 gets 08:40, etc.

### Base Route Time Calculation

```
Base Route Start Time = Earliest delivery due date of orders in that route chunk
                        OR Specified start time parameter
                        (whichever is earlier)
```

**Example:**
```
Route creation parameters:
- Date: 2026-03-05
- Start time: 08:00
- Max stops: 25

If orders in the route have due dates:
- Order A: 2026-03-05 07:30
- Order B: 2026-03-05 09:00
- Order C: 2026-03-05 08:30

→ Route starts at 07:30 (earliest due date)
→ Order A assigned ETA 07:30
→ Order C assigned ETA 07:50 (07:30 + 20 min)
→ Order B assigned ETA 08:10 (07:30 + 40 min)
```

**Code location:** `/modules/ramos/models/Routes_model.php` lines 285-306

---

## 3. CUSTOMER PRIORITY

### Current Status: NOT IMPLEMENTED

#### Data Available But Not Used

**For Omni_Sales Orders:**
- **Source:** Custom Field ID 4: "Prioridad"
- **Table:** `tblcustomfieldsvalues` where `fieldid = 4`
- **Options:** 1-9 (numeric priority levels)
- **Stored:** Per customer (via `c.userid`)
- **Currently:** Only used by automation, not by routes

**For ERP Portal Orders:**
- **Source:** NO priority field available
- **Reason:** ERP invoices have no customer-level priority assignment
- **Status:** Customers have no priority hierarchy in ERP system

#### How Routes Currently Sort Orders (Ignoring Priority)

Within each zone, orders are sorted by:
1. **Delivery date/time** (due date orders first, nulls last)
2. **Order ID** (tiebreaker)

**Code location:** `/modules/ramos/models/Routes_model.php` lines 226-255

#### What Would Be Needed to Implement Priority

To use customer priority in route generation:

1. **For Omni_Sales:** Use existing custom field (fieldid=4)
   ```sql
   SELECT cfv.value as priority
   FROM tblcart c
   LEFT JOIN tblcustomfieldsvalues cfv ON cfv.relid = c.userid AND cfv.fieldid = 4
   ```

2. **For ERP Portal:** Add priority field
   - Option A: Add custom field to tblinvoices
   - Option B: Link ERP customers to omni_sales customers (share priority)
   - Option C: Create new priority system for ERP

3. **Update sorting in `generate_routes()`:**
   ```php
   usort($orders, function($a, $b) {
       // Sort by priority first (high to low)
       $aPriority = (int)($a['priority'] ?? 9);
       $bPriority = (int)($b['priority'] ?? 9);
       if ($aPriority !== $bPriority) {
           return $aPriority <=> $bPriority; // Lower number = higher priority
       }
       
       // Then by delivery date
       $aHasDate = !empty($a['delivery_datetime']);
       $bHasDate = !empty($b['delivery_datetime']);
       // ... rest of sorting
   });
   ```

---

## 4. DATABASE SCHEMA SUMMARY

### Custom Fields for Zones and Priority

```
tblcustomfields:
├── id=3  fieldto='customers'  name='Zona'      options='Minerva,Andares,Providencia,Plaza Sol,Sur'
├── id=4  fieldto='customers'  name='Prioridad' options='1,2,3,4,5,6,7,8,9'
└── id=5  fieldto='vendors'    name='Prioridad' (for suppliers)

tblcustomfieldsvalues:
├── relid = customer user ID
├── fieldid = 3 (Zona)
└── value = zone name (e.g., "Minerva")
```

### Route Tables

```
tblramos_routes:
├── id                    (route identifier)
├── route_date            (date of delivery)
├── start_time            (calculated start time for the route)
├── vehicle_label         (e.g., "Route - Minerva 1")
├── capacity              (max stops - from parameter)
├── status                (draft, dispatched, completed)
├── created_by / created_at

tblramos_route_stops:
├── route_id              (FK to tblramos_routes)
├── order_id              (ID from tblcart or tblinvoices)
├── stop_number           (position 1, 2, 3... in route)
├── eta                   (calculated delivery time)
├── status                (pending, completed)
```

### Order Tables

```
tblcart (Omni_sales):
├── id
├── phonenumber (customer name)
├── address (delivery address)
├── duedate (delivery date/time)
├── userid (links to customer - for zone/priority lookup)
├── channel_id (must be 1,2,4,6)
└── status (must not be 5 - cancelled)

tblinvoices (ERP):
├── id
├── number (order number)
├── clientid (customer ID)
├── shipping_street (delivery address)
├── duedate (delivery date/time)
├── status (1=unpaid, 2=paid, etc.)
└── clientnote (contains 'portal' for ERP orders)
```

---

## 5. WORKFLOW SUMMARY

### Route Generation Flow

```
User clicks "Generate Routes" with:
  ├── Date: 2026-03-05
  ├── Start Time: 08:00
  ├── Max Stops: 25 per route
  └── Prefix: "Route"

System processes:
  │
  ├─ Fetch Omni_Sales orders (tblcart)
  │  ├── Where: status != 5, channel_id IN (1,2,4,6)
  │  ├── Where: not already in routes
  │  ├── Where: duedate IS NULL OR DATE(duedate) = 2026-03-05
  │  └── Join: Custom field Zona (fieldid=3)
  │
  ├─ Fetch ERP orders (tblinvoices)
  │  ├── Where: status = 1 (unpaid)
  │  ├── Where: not already in routes
  │  ├── Where: duedate IS NULL OR DATE(duedate) = 2026-03-05
  │  └── Default zona: "No Zone"
  │
  ├─ Combine & Sort orders
  │  ├── Group by zone
  │  ├── Sort by duedate, then order ID
  │  └── Chunk by max_stops
  │
  ├─ Create routes (one per zone per chunk)
  │  ├── Route starts at earliest due date OR specified start time
  │  ├── Label: "Route - ZoneName" (or "...1", "...2" for multiples)
  │  └── Status: draft
  │
  └─ Create route stops (one per order)
     ├── Stop number: position in route (1, 2, 3...)
     ├── ETA: order duedate OR (start_time + stop_position*20min)
     └── Status: pending
```

---

## 6. KEY CONFIGURATION PARAMETERS

| Parameter | Source | Default | Purpose |
|-----------|--------|---------|---------|
| route_date | User input | Today | Date of deliveries to include |
| start_time | User input | 08:00:00 | When routes begin (if no duedate) |
| max_stops | User input | 25 | Orders per route chunk |
| prefix | User input | "Route" | Route label prefix |
| zone field | Custom field ID 3 | (none for ERP) | Group factor for routes |
| priority field | Custom field ID 4 | (unused) | Currently not sorting by this |

---

## 7. CURRENT LIMITATIONS

| Limitation | Impact | Suggested Fix |
|-----------|--------|---------------|
| ERP orders have no zone assignment | All ERP orders in one "No Zone" route | Add zone field to ERP order creation, or link to omni customer |
| ERP orders have no priority | Can't prioritize urgent ERP deliveries | Add priority field to ERP invoice or customer |
| Priority not used in route sorting | Routes don't reflect customer VIP status | Update sorting logic in `generate_routes()` |
| No preferred driver assignment | Any driver can take any route | Add driver_id to route or route preferences |
| No vehicle type requirement | Heavy orders may go on inappropriate vehicles | Add vehicle type requirement to orders |
| No hard time windows | Only "soft" ETA based on duedate | Add pickup/delivery window fields to orders |

---

## 8. CODE LOCATIONS

| Feature | File | Lines |
|---------|------|-------|
| Zone grouping | `/modules/ramos/models/Routes_model.php` | 226-255 |
| ETA calculation | `/modules/ramos/models/Routes_model.php` | 285-306 |
| Route creation loop | `/modules/ramos/models/Routes_model.php` | 280-355 |
| Custom field config | `tblcustomfields` | id=3,4 |
| Route generation entry point | `/modules/ramos/controllers/Routes.php` | `generate()` method |
