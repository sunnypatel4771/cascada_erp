# ERP Priority & Zone - Quick Reference

## What Changed?

### ✅ ERP Orders Now Support:
- **Priority** (1-9 scale) - Control delivery order
- **Zone Assignment** - Group orders by location
- **Smart Route Sorting** - High-priority orders get earlier stops

### ✅ New Database Columns:
```
tblinvoices.priority (INT, default=5)
tblinvoices.zone (VARCHAR(100), default=NULL)
```

### ✅ Default Values for New ERP Orders:
```
priority = 5    (Normal)
zone = NULL     (Must be assigned)
```

---

## Common Tasks

### Assign Zone to Multiple ERP Orders
```sql
UPDATE tblinvoices 
SET zone = 'Minerva'
WHERE clientnote LIKE '%portal%' 
  AND zone IS NULL 
  AND status = 1;
```

### Find Orders Missing Zone Assignment
```sql
SELECT id, number, duedate 
FROM tblinvoices 
WHERE zone IS NULL 
  AND clientnote LIKE '%portal%'
  AND status = 1;
```

### Update Single Order
```sql
UPDATE tblinvoices 
SET priority = 2, zone = 'Andares'
WHERE id = 123;
```

### Check Zone Distribution
```sql
SELECT zone, COUNT(*) as count 
FROM tblinvoices 
WHERE clientnote LIKE '%portal%' 
  AND status = 1
GROUP BY zone;
```

### Check Priority Distribution
```sql
SELECT priority, COUNT(*) as count 
FROM tblinvoices 
WHERE clientnote LIKE '%portal%' 
  AND status = 1
GROUP BY priority;
```

---

## Priority Levels

| 1-3 | High (rush orders) |
| 4-6 | Normal (standard) |
| 7-9 | Low (bulk/flex) |

---

## Route Generation Behavior

**Routes are automatically sorted by:**
1. Zone first (grouping)
2. Priority second (within zone)
3. Delivery time third
4. Order ID last (tiebreaker)

---

## Testing

1. Assign zones to some ERP orders
2. Go to Ramos → Routes
3. Click "Generate Routes"
4. Verify routes grouped by zone
5. Within each zone, high-priority orders appear first

---

## Code Methods

```php
// Update priority/zone
$this->automation_model->update_erp_order_priority_zone(
    $invoiceId, 
    $priority,  // 1-9 or null
    $zone       // zone name or null
);

// Find unassigned zones
$this->automation_model->get_erp_orders_needing_zone_assignment();
```

---

## Files Modified

- ✅ `application/migrations/332_add_priority_zone_to_invoices.php` (NEW)
- ✅ `application/config/migration.php` (updated version)
- ✅ `application/controllers/Clients.php` (added defaults)
- ✅ `modules/ramos/models/Routes_model.php` (priority sorting)
- ✅ `modules/ramos/models/Automation_model.php` (new methods)

All syntax validated ✅
