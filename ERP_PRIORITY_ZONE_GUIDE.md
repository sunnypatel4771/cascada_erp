# ERP Orders: Priority & Zone Assignment Guide

## What Was Added

### 1. Database Columns (tblinvoices)
- **priority** (INT, default=5): Priority level 1-9
  - 1-3: High priority
  - 4-6: Normal priority
  - 7-9: Low priority
- **zone** (VARCHAR(100), default=NULL): Geographic zone assignment
  - Options: Minerva, Andares, Providencia, Plaza Sol, Sur (or custom)

### 2. Auto-Assignment from Customer Profile ✨ NEW
When creating ERP orders (from customer portal or Ramos personnel), the system now **automatically**:
- Reads customer's **Zona** (Zone) from profile custom field
- Reads customer's **Prioridad** (Priority) from profile custom field
- Uses validated values for the new order
- Falls back to system default zone (Minerva) if customer has no zone assigned
- Validates priority against 1-9 range, defaults to 5 (normal) if invalid

**No more NULL zones!** All ERP orders now have zones immediately upon creation.

### 3. Routes Now Include Priority-Based Sorting
When generating routes, orders are now sorted by:
1. **Zone** (geographic grouping first)
2. **Priority** (1=highest, 9=lowest)
3. **Delivery Date/Time** (earliest first)
4. **Order ID** (tiebreaker)

This means high-priority orders get earlier stops in each route.

### 4. ERP Orders Auto-Populated
New ERP invoices created now get:
- **priority**: From customer's Prioridad custom field → defaults to 5 (normal)
- **zone**: From customer's Zona custom field → defaults to Minerva (system fallback)

---

## How Zone & Priority Are Assigned

### Automatic Assignment (New Orders)
When a customer creates an order via the portal or Ramos personnel creates an order:

1. System reads customer's **Zona** custom field (fieldid=3)
2. System reads customer's **Prioridad** custom field (fieldid=4)
3. Validates both values:
   - Zone must match one of: Minerva, Andares, Providencia, Plaza Sol, Sur
   - Priority must be numeric 1-9
4. **If customer has no zone → Uses system fallback: Minerva**
5. **If customer has no priority → Uses default: 5 (Normal)**
6. Order created with validated zone and priority immediately

### Manual Assignment (Existing Orders)

If you need to change zone/priority for an existing order:

#### Option A: Direct Database Update (Quick)
```sql
UPDATE tblinvoices SET priority = 2, zone = 'Minerva' WHERE id = 123;
```

#### Option B: Using Automation_model (In Code)
```php
$this->automation_model->update_erp_order_priority_zone(
    $invoiceId = 123,
    $priority = 2,      // 1-9, null to skip
    $zone = 'Minerva'   // zone name, null to skip
);
```

#### Option C: Backfill Existing Orders from Customer Profiles
Update all unpaid ERP orders with customer's current zone/priority:
```php
$result = $this->automation_model->backfill_erp_orders_from_customer_profile();
// Returns: [
//   'total_orders' => 45,
//   'updated' => 23,
//   'details' => [...]
// ]
```

## Finding Orders That Need Assignment

### Find Orders Still Without Zones
(Rare now since new orders auto-assign, but useful for checking legacy orders)

```sql
SELECT i.id, i.number, cl.company, i.date, i.duedate, i.priority, i.zone
FROM tblinvoices i
LEFT JOIN tblclients cl ON cl.userid = i.clientid
WHERE i.zone IS NULL
  AND i.status = 1
  AND i.clientnote LIKE '%portal%'
ORDER BY i.date DESC;
```

### Get Orders Where Zone Differs from Customer Profile
Find orders that need zone updates from customer profile changes:

```sql
SELECT i.id, i.number, cl.company, i.zone, 
       (SELECT cfv.value FROM tblcustomfieldsvalues cfv 
        WHERE cfv.relid = cl.userid AND cfv.fieldid = 3) as customer_zone
FROM tblinvoices i
LEFT JOIN tblclients cl ON cl.userid = i.clientid
WHERE i.status = 1
  AND i.clientnote LIKE '%portal%'
  AND i.zone != (SELECT cfv.value FROM tblcustomfieldsvalues cfv 
                 WHERE cfv.relid = cl.userid AND cfv.fieldid = 3);
```

### Use Model Method
```php
// Find unpaid orders with NULL zones (should be rare)
$orders_needing_zones = $this->automation_model->get_erp_orders_needing_zone_assignment();

// Backfill all orders from customer profiles
$result = $this->automation_model->backfill_erp_orders_from_customer_profile();
echo "Updated " . $result['updated'] . " of " . $result['total_orders'] . " orders";
```

---

## Route Generation Behavior with Auto-Assigned Zones

### Before (Manual Zone Assignment)
```
Route Generation for 2026-03-05:
  Zone: "No Zone"  ← All ERP orders grouped together, hard to manage
    ├─ Order ERP-001 (priority=5, duedate=2026-03-05 10:00)
    ├─ Order ERP-002 (priority=5, duedate=2026-03-05 14:00)
    ├─ Order ERP-003 (priority=5, duedate=2026-03-05 09:00)
    └─ Order ERP-004 (priority=5, duedate=2026-03-05 11:00)
    
Result: "Route - No Zone 1" with 4 stops (sorted by duedate only)
```

### After (Auto-Assigned from Customer Profile) ✨
```
Route Generation for 2026-03-05:
  Zone: "Minerva"  ← From customer Zona custom field
    ├─ Order ERP-001 (priority=2, duedate=2026-03-05 10:00)  ← HIGH priority
    ├─ Order ERP-003 (priority=4, duedate=2026-03-05 09:00)  ← NORMAL priority
    └─ Order ERP-004 (priority=5, duedate=2026-03-05 11:00)
    
  Zone: "Andares"  ← From customer Zona custom field
    └─ Order ERP-002 (priority=1, duedate=2026-03-05 14:00)  ← URGENT priority
    
Results:
  - "Route - Minerva 1" (3 stops)
  - "Route - Andares 1" (1 stop)
  
Benefits:
  ✓ Orders grouped by customer's assigned zone immediately
  ✓ No "No Zone" catch-all group
  ✓ Priority from customer profile ensures correct delivery order
  ✓ No manual admin work needed
```

## Priority Scale Explanation

| Priority | Use Case | Examples |
|----------|----------|----------|
| 1-2 | VIP / Urgent | Emergency orders, key accounts |
| 3 | High | Same-day delivery required |
| 4-5 | Normal | Standard next-day delivery |
| 6 | Low-Normal | Regular orders |
| 7-8 | Low | Can wait, flexible timing |
| 9 | Very Low | Bulk orders with flex delivery |

**Example Assignment Strategy:**
```
Customer Type          → Priority
Premium Account        → 2
Regular Account        → 5
Wholesale/Bulk         → 8
On-Hold/Pending        → 6 (wait to process)
Rush/Same-Day Request  → 1
```

---

## ETA (Delivery Time) Behavior

### ETA Calculation (Same for Omni & ERP)
```
IF order has duedate matching route date:
  ETA = order.duedate (or duedate time if specified)
ELSE:
  ETA = route_start_time + (stop_position × 20 minutes)
```

### Route Start Time
```
= MIN(earliest_duedate_in_route, specified_start_time)
```

**Example:**
```
Route created for 2026-03-05, start 08:00, max 25 stops

Zone: Minerva (3 orders)
  - Order A: duedate=2026-03-05 09:30
  - Order B: duedate=2026-03-05 10:00
  - Order C: duedate=2026-03-05 (no time)

Result:
  Route start time = 09:30 (earliest duedate)
  Stop 1 (Order A): ETA = 09:30
  Stop 2 (Order B): ETA = 09:50 (09:30 + 20 min)
  Stop 3 (Order C): ETA = 10:10 (09:30 + 40 min)
```

---

## Code Locations

| Feature | File | Lines |
|---------|------|-------|
| Zone/Priority Constants | `/application/config/constants.php` | 217-228 |
| Validation Helpers | `/application/helpers/general_helper.php` | 1106-1188 |
| Auto-Assign in Order Creation | `/application/controllers/Clients.php` | 188-218 |
| Priority/Zone Sorting | `/modules/ramos/models/Routes_model.php` | 235-262 |
| Backfill Method | `/modules/ramos/models/Automation_model.php` | 467-527 |
| Update Single Order | `/modules/ramos/models/Automation_model.php` | 421-441 |
| Find Unassigned Orders | `/modules/ramos/models/Automation_model.php` | 450-464 |

---

## Testing the Feature

### Step 1: Set Up Customer Profile
1. Go to **Clients → Client Details**
2. Scroll to custom fields section
3. Set customer's **Zona** (Zone) field: Select one of (Minerva, Andares, Providencia, Plaza Sol, Sur)
4. Set customer's **Prioridad** (Priority) field: Select 1-9 (1=urgent, 5=normal, 9=low)
5. Click **Save**

**Example:** Customer ABASTOS-RAMOS
- Zona: Minerva
- Prioridad: 2 (High priority)

### Step 2: Create ERP Order
1. Go to **Clients → (select customer)**
2. Click **"Create New Order"** (customer portal style)
3. Add items to order
4. Click **"Save New Order"**
5. Order is created automatically with:
   - **zone** = Minerva (from customer profile)
   - **priority** = 2 (from customer profile)

### Step 3: Verify in Database
```sql
SELECT id, number, clientid, zone, priority
FROM tblinvoices
WHERE clientnote LIKE '%portal%'
ORDER BY id DESC LIMIT 5;
```

Should show orders with zone/priority populated from customer profiles.

### Step 4: Generate Routes
1. Go to **Ramos → Routes**
2. Click **"Generate Routes"**
3. Set:
   - Date: Today or future date
   - Start Time: 08:00
   - Max Stops: 25
4. Submit

Routes should now group orders by customer's assigned zones:
- **Route - Minerva 1** (orders from Minerva zone customers)
- **Route - Andares 1** (orders from Andares zone customers)
- etc.

### Step 5: Verify Route Ordering
Within each route, orders should be sorted by priority:
1. Priority 1-2 orders appear first (high priority)
2. Priority 3-6 orders appear middle (normal priority)
3. Priority 7-9 orders appear last (low priority)

---

## Backfill Existing Orders

If you have existing ERP orders that need zone/priority assignments from customer profiles:

### Option A: Using Admin Code (Recommended)
Create an admin controller method or run directly:

```php
// Load automation model
$this->load->model('ramos/automation_model');

// Run backfill
$result = $this->automation_model->backfill_erp_orders_from_customer_profile();

// Results
echo "Total orders processed: " . $result['total_orders'] . "\n";
echo "Orders updated: " . $result['updated'] . "\n";
echo "Details: " . json_encode($result['details']) . "\n";
```

Returns:
```json
{
  "total_orders": 45,
  "updated": 23,
  "details": [
    {"invoice_id": 5, "zone": "Minerva", "priority": 5},
    {"invoice_id": 6, "zone": "Andares", "priority": 2},
    ...
  ]
}
```

### Option B: Direct Database Update
For immediate updates without loading CI framework:

```sql
-- Update all unpaid portal orders with customer's zone
UPDATE tblinvoices i
SET zone = COALESCE(
    (SELECT cfv.value FROM tblcustomfieldsvalues cfv 
     WHERE cfv.relid = i.clientid AND cfv.fieldid = 3),
    'Minerva'  -- fallback zone
)
WHERE i.status = 1 
  AND i.clientnote LIKE '%portal%'
  AND i.zone IS NULL;
```

---

## Troubleshooting

### "Order created but zone is NULL or Minerva (fallback)"

**Possible causes:**
1. Customer's **Zona** custom field is not set
2. Customer's **Zona** custom field has an invalid value
3. Custom field uses wrong slug (`customers_zona` vs `customers_zona`)

**Solution:**
```sql
-- Check customer's zone field value
SELECT cfv.value 
FROM tblcustomfieldsvalues cfv
WHERE cfv.relid = {customer_id}
  AND cfv.fieldid = 3;  -- fieldid 3 = Zona

-- If empty or invalid, update customer profile manually via admin UI
-- Or fix via SQL:
UPDATE tblcustomfieldsvalues 
SET value = 'Minerva'
WHERE relid = {customer_id} AND fieldid = 3;
```

### "Order created but priority is 5 (default)"

**Possible causes:**
1. Customer's **Prioridad** custom field is not set
2. Customer's **Prioridad** custom field is not numeric 1-9
3. Custom field returns string instead of number

**Solution:**
```sql
-- Check customer's priority field value
SELECT cfv.value 
FROM tblcustomfieldsvalues cfv
WHERE cfv.relid = {customer_id}
  AND cfv.fieldid = 4;  -- fieldid 4 = Prioridad

-- Validate it's numeric 1-9
UPDATE tblcustomfieldsvalues 
SET value = '5'
WHERE relid = {customer_id} AND fieldid = 4;
```

### "Migration fails with 'Duplicate key name'"

**Cause:** Index already exists in database

**Solution:** Already fixed in migration file - uses `IF NOT EXISTS` check

### "Helper functions not found - undefined function error"

**Cause:** `general_helper.php` not loaded

**Solution:** Helpers auto-load via `$autoload['helpers']` in `config/autoload.php`. Verify general_helper is in the autoload list.

### "Backfill method not found"

**Cause:** `Automation_model` not loaded in controller

**Solution:**
```php
// Load model first
$this->load->model('ramos/automation_model');

// Then call backfill
$result = $this->automation_model->backfill_erp_orders_from_customer_profile();
```

---

## Quick Reference for Developers

### Function Signatures

```php
// Get validated zone from customer profile
$zone = get_validated_customer_zone($customer_id, $fallback = null);
// Returns: zone name or DEFAULT_DELIVERY_ZONE ('Minerva')

// Get validated priority from customer profile
$priority = get_validated_customer_priority($customer_id, $default = null);
// Returns: priority level 1-9 or DEFAULT_PRIORITY_LEVEL (5)

// Validate zone exists in allowed list
$is_valid = is_valid_delivery_zone('Minerva');
// Returns: true/false

// Backfill all ERP orders from customer profiles
$result = $this->automation_model->backfill_erp_orders_from_customer_profile();
// Returns: ['total_orders' => int, 'updated' => int, 'details' => array]
```

### Database Field IDs

| Field | ID | Table | Type | Slug |
|-------|----|----|------|------|
| Horario (Schedule) | 2 | tblcustomfields | select | customers_horario |
| Zona (Zone) | 3 | tblcustomfields | select | customers_zona |
| Prioridad (Priority) | 4 | tblcustomfields | select | customers_prioridad |

### Constants

| Name | Value | Used For |
|------|-------|----------|
| `VALID_DELIVERY_ZONES` | Array of 5 zones | Validate zone assignments |
| `DEFAULT_DELIVERY_ZONE` | 'Minerva' | Fallback when customer has no zone |
| `DEFAULT_PRIORITY_LEVEL` | 5 | Fallback when customer has no priority |

---

## Omni_Sales vs ERP: Zone & Priority Comparison

| Aspect | Omni_Sales | ERP |
|--------|-----------|-----|
| Priority Source | Custom field (fieldid=4) | Custom field (fieldid=4) → invoice.priority |
| Zone Source | Custom field (fieldid=3) | Custom field (fieldid=3) → invoice.zone |
| Default Priority | Per customer custom field | Per customer custom field → 5 (fallback) |
| Default Zone | Per customer custom field | Per customer custom field → Minerva (fallback) |
| When Assigned | At route generation (from profile) | **At order creation** (auto-populated from profile) |
| Editable Via | Customer profile → Custom Fields | Customer profile → Custom Fields (auto-synced) |
| Fallback Behavior | Uses "No Zone" if not set | Uses DEFAULT_DELIVERY_ZONE if not set |
| Used in Routes | YES (priority sorting enabled) | YES (priority sorting enabled) |
| Used in Automation | YES | YES |

**Key Difference:** ERP orders auto-populate zone/priority at creation time from customer profile, while omni_sales orders read from profile at route generation time. Both respect customer profile settings.

## Future Enhancements (Optional)

1. **Admin UI for Zone/Priority Editing**
   - Add form fields to invoice edit page for manual override
   - Show source indicator: "(from customer profile)" vs "(fallback)"
   - Allow bulk reassignment from invoice list

2. **Automatic Zone Suggestion**
   - Analyze shipping address and suggest zone to customer during portal order creation
   - Or auto-assign zone based on geographic coordinates

3. **Customer Portal Priority Selection**
   - Allow customers to mark orders as "Urgent" during creation
   - Map to priority level 1-3 automatically

4. **Schedule Window Integration** (Horario field)
   - Currently stored but not used in route generation
   - Could validate delivery must occur within customer's preferred time window
   - Add ETA window enforcement in route completion

5. **Priority-Based SLA**
   - Route must be completed within X hours for priority 1-2
   - Display SLA status on route view

6. **Zone Synchronization**
   - When customer's Zona custom field changes, auto-update pending ERP orders
   - Keep order zone consistent with customer profile


