# Quick Reference Card - ERP Orders in Routes

## What Changed
✅ Routes now include BOTH omni_sales orders AND ERP portal orders

## File Modified
📁 `/modules/ramos/models/Routes_model.php`

## How It Works

### Route Generation Now:
```
1. Query tblcart (omni_sales)
2. Query tblinvoices (ERP) ← NEW
3. Merge results
4. Sort by zone, duedate, id
5. Group by zone
6. Create routes (max 10 per zone)
```

### Zone Assignment:
- **Omni_sales**: From customer zona custom field
- **ERP**: "No Zone" (grouped together)

## Testing It

### Create ERP Order:
```
1. Go to http://erp.local/
2. Home page → "New Order" form
3. Add items
4. Click "Save New Order"
```

### Generate Routes:
```
1. Ramos → Routes
2. Click "Generate routes"
3. Select date
4. Should see BOTH order types
```

## ERP Order Criteria

Must have ALL of these:
- ✅ In `tblinvoices` table
- ✅ Status = 1 (unpaid)
- ✅ clientnote contains 'portal'
- ✅ duedate matches generation date (or NULL)
- ✅ Not already in a route

## Quick SQL Check

```sql
-- Count ERP orders that would be routed
SELECT COUNT(*) 
FROM tblinvoices 
WHERE status = 1 
AND clientnote LIKE '%portal%'
AND (duedate IS NULL OR DATE(duedate) = CURDATE());
```

## If It Doesn't Work

1. Check invoice status: `SELECT status FROM tblinvoices WHERE id = [order_id];` → Must be 1
2. Check client note: `SELECT clientnote FROM tblinvoices WHERE id = [order_id];` → Must have 'portal'
3. Check due date: `SELECT duedate FROM tblinvoices WHERE id = [order_id];` → Must match route date
4. Check route stops: `SELECT * FROM tbl{prefix}ramos_route_stops WHERE order_id = [order_id];` → Must be empty

## Documentation Files

| File | Purpose |
|------|---------|
| `COMPLETION_SUMMARY.md` | ← Start here |
| `TESTING_ERP_ORDERS_IN_ROUTES.md` | Detailed testing |
| `ROUTE_GENERATION_CHANGES.md` | Technical details |
| `CODE_CHANGES_DETAILED.md` | Exact code changes |

## Key Info

**No database changes needed** - Pure code enhancement
**Fully backward compatible** - Existing routes work unchanged
**Ready to test** - All syntax validated

---

## One-Liner Summary

Routes now include ERP orders from `tblinvoices` alongside omni_sales orders from `tblcart`, with ERP orders grouped in "No Zone".
