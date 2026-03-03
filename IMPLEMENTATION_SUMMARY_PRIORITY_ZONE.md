# ERP Orders: Priority & Zone Implementation - Complete

## ✅ What Was Implemented

### 1. Database Schema Changes
**File:** `application/migrations/332_add_priority_zone_to_invoices.php`

Added to `tblinvoices` table:
- `priority` (INT, default=5) - Priority level 1-9
- `zone` (VARCHAR(100), default=NULL) - Geographic zone assignment
- `idx_zone_priority` - Index for query performance

**Status:** ✅ ACTIVE - Columns exist in database

### 2. ERP Invoice Creation
**File:** `application/controllers/Clients.php` (lines 190-220)

When ERP orders are created from the customer portal:
- Default priority = 5 (Normal)
- Default zone = NULL (must be assigned by admin)

**Status:** ✅ IMPLEMENTED

### 3. Routes Generation with Priority Sorting
**File:** `modules/ramos/models/Routes_model.php`

**Updated Query (Lines 214-228):**
- Now fetches ERP invoice `priority` and `zone` fields
- Uses COALESCE to default missing zones to "No Zone"
- Uses COALESCE to default missing priority to 5

**Updated Sorting Logic (Lines 235-262):**
Routes now sort orders by:
1. Zone (grouping)
2. **Priority (1=highest, 9=lowest)** ← NEW
3. Delivery date/time
4. Order ID

**Status:** ✅ IMPLEMENTED & TESTED

### 4. New Methods in Automation_model
**File:** `modules/ramos/models/Automation_model.php`

Added three methods:

1. **`update_erp_order_priority_zone($invoiceId, $priority, $zone)`**
   - Updates priority and/or zone for a single ERP invoice
   - Validates priority to 1-9 range
   - Allows partial updates (pass null to skip)

2. **`get_erp_orders_needing_zone_assignment()`**
   - Returns all unpaid ERP portal orders without zones
   - Used to identify which orders need admin attention
   - Sorted by date (newest first)

3. (Existing) **`get_unprocessed_erp_orders()`**
   - Already uses zone/priority in route generation

**Status:** ✅ IMPLEMENTED & VALIDATED

---

## 🔄 How It Works Now

### Route Generation Flow with Priority
```
User clicks "Generate Routes" for 2026-03-05
  │
  ├─ Fetch omni_sales orders (tblcart) with zone from custom field
  ├─ Fetch ERP orders (tblinvoices) with zone + priority fields
  │
  ├─ Combine both sources
  │
  ├─ Sort by:
  │  1. Zone (e.g., "Minerva", "Andares", "No Zone")
  │  2. Priority (1=highest, 9=lowest)
  │  3. Delivery date/time
  │  4. Order ID
  │
  ├─ Group into routes by zone
  │
  └─ Create routes with proper ETA assignment
```

### Example Result with Priority
```
Given orders:
  ERP-001: zone=Minerva, priority=2, duedate=10:00
  ERP-002: zone=Minerva, priority=5, duedate=09:00
  ERP-003: zone=Andares, priority=1, duedate=14:00

Routes created:
  Route - Minerva 1:
    Stop 1: ERP-001 (priority=2, earliest stop despite later duedate)
    Stop 2: ERP-002 (priority=5, later stop)
  
  Route - Andares 1:
    Stop 1: ERP-003 (priority=1, only order)
```

---

## 📋 Test Data Setup

### Current Test Invoices (IDs 26-30)
```
ID | Zone       | Priority | Status
26 | Minerva    | 2        | Ready for routing
27 | Minerva    | 2        | Ready for routing
28 | Minerva    | 2        | Ready for routing
29 | Andares    | 5        | Ready for routing
30 | Andares    | 5        | Ready for routing
```

### Remaining Unassigned
```sql
SELECT id, number, zone, priority 
FROM tblinvoices 
WHERE zone IS NULL AND clientnote LIKE '%portal%';
```

---

## 🔧 Usage Examples

### Assign Priority & Zone to Single Order
```php
// In controller or model
$this->automation_model->update_erp_order_priority_zone(
    invoiceId: 123,
    priority: 3,      // High priority
    zone: 'Minerva'
);
```

### Find Orders Needing Zone Assignment
```php
$unassigned = $this->automation_model->get_erp_orders_needing_zone_assignment();
// Returns: [
//   ['id' => 31, 'number' => 'INV-xxx', 'customer_name' => 'Company ABC', 
//    'priority' => 5, 'zone' => null],
//   ...
// ]
```

### Direct Database Update (Quick)
```sql
UPDATE tblinvoices SET zone = 'Providencia', priority = 1 WHERE id = 123;
```

---

## 📊 Priority Scale Guide

| Level | Type | Use Case |
|-------|------|----------|
| 1 | 🔴 Critical | Emergency/VIP same-day |
| 2 | 🟠 High | Important account, rush |
| 3 | 🟡 Above-Normal | Time-sensitive |
| 4 | 🟢 Normal-High | Standard next-day |
| 5 | 🟢 Normal | Regular orders |
| 6 | 🔵 Below-Normal | Can delay slightly |
| 7 | 🟣 Low | Bulk/non-urgent |
| 8 | 🟣 Very Low | Warehouse stock |
| 9 | ⚫ Minimal | Very bulk/test orders |

---

## 🎯 ETA Behavior (No Change)

ETAs still calculated the same way for both omni and ERP:
- If order has duedate matching route date → use that as ETA
- Otherwise → start_time + (position × 20 minutes)

The difference: **Priority now affects POSITION in the route**, which affects the ETA.

```
Example with priority affecting ETA:
  Route start: 08:00, 3 stops

  Without priority:
    Stop 1 (Order A): 08:00
    Stop 2 (Order B): 08:20
    Stop 3 (Order C): 08:40

  With priority (C is priority 1, highest):
    Stop 1 (Order C): 08:00  ← High priority gets first slot
    Stop 2 (Order A): 08:20
    Stop 3 (Order B): 08:40
```

---

## 🚀 Next Steps / Testing

### To Test Route Generation with Priority:
1. Go to Ramos → Routes
2. Click "Generate Routes"
3. Set date/time/max stops
4. Click "Generate"
5. Verify routes are:
   - Grouped by zone (Minerva, Andares, etc.)
   - Within each zone, high-priority orders appear first
   - ETAs calculated correctly

### To Assign Zones to More ERP Orders:
```sql
-- Find all unassigned ERP orders
SELECT id, number, duedate 
FROM tblinvoices 
WHERE zone IS NULL 
  AND clientnote LIKE '%portal%'
  AND status = 1;

-- Assign in bulk by due date
UPDATE tblinvoices 
SET zone = 'Minerva', priority = 5
WHERE zone IS NULL 
  AND clientnote LIKE '%portal%'
  AND DATE(duedate) = '2026-03-05';
```

---

## 📝 File Changes Summary

| File | Changes | Status |
|------|---------|--------|
| `/application/migrations/332_add_priority_zone_to_invoices.php` | New migration file | ✅ Created |
| `/application/config/migration.php` | Updated version to 332 | ✅ Updated |
| `/application/controllers/Clients.php` | Added priority/zone defaults | ✅ Updated |
| `/modules/ramos/models/Routes_model.php` | Added priority to ERP query & sorting | ✅ Updated |
| `/modules/ramos/models/Automation_model.php` | Added 2 new methods | ✅ Updated |

**All PHP syntax validated:** ✅ PASS

---

## 🔗 Related Documentation

- `ZONES_DELIVERY_PRIORITY_EXPLAINED.md` - Detailed zone/priority/ETA explanation
- `ERP_PRIORITY_ZONE_GUIDE.md` - Complete implementation guide with examples

---

## ⚠️ Important Notes

1. **Zone Assignment is Optional**
   - ERP orders without zones default to "No Zone"
   - They still get included in routes
   - All "No Zone" orders group together

2. **Priority is Per-Order**
   - Priority set on invoice (not customer)
   - Can be changed anytime before route generation
   - No retroactive changes to existing routes

3. **Omni_Sales Still Uses Custom Fields**
   - Omni orders get zone from fieldid=3
   - Omni orders get priority from fieldid=4
   - Both are per-customer (not per-order)
   - This integration respects existing omni structure

4. **Automation System**
   - Still processes both order types
   - Now respects zone/priority when creating batches
   - No changes needed to automation workflow

---

## ✨ Benefits Achieved

✅ ERP orders can now be prioritized (rush vs. standard)
✅ ERP orders grouped into geographic zones
✅ High-priority ERP orders get earlier stops in routes
✅ ETAs automatically adjust based on priority
✅ Automation respects priority when creating batches
✅ Both order systems (omni + ERP) fully integrated with priority

