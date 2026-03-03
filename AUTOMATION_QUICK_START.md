# Quick Start: Running & Testing in 15 Minutes

## 🚀 Fast Track (Copy-Paste Ready)

### 1. Pre-flight Check (1 min)
```bash
cd /home/rev/Development/projects/erp
bash test_automation.sh
```
✓ Should show all tests passing

### 2. Run Migration (1 min)
```bash
php index.php migrate
```
✓ Should complete without errors

### 3. Create Test Data (2 min)
```bash
mysql -u root -p erp << 'EOF'
INSERT INTO tblcart (userid, date, duedate, status, channel_id, processed_for_purchase, clientnote)
VALUES (1, NOW(), DATE(NOW()), 2, 1, 0, 'Test Order');

INSERT INTO tblcart_detailt (rel_id, product_id, quantity, unit_price)
VALUES (LAST_INSERT_ID(), 1, 5, 100);
EOF
```
✓ Order created

### 4. Enable Automation in Admin (2 min)
```
URL: http://localhost:8080/admin/ramos/settings/automation_schedule

Steps:
1. Check "Enable Scheduled Automation"
2. Select current hour (e.g., "15" if it's 3pm)
3. Click Save
```
✓ Saved successfully

### 5. Test Manual Trigger (1 min)
```
URL: http://localhost:8080/admin/ramos/automation
Click: Run Automation button
```
✓ Returns: success=true, orders_processed=1

### 6. Test Cron Trigger (1 min)
```bash
# Get cron key first
grep APP_CRON_KEY /home/rev/Development/projects/erp/application/config/app-config.php

# Replace YOUR_KEY with actual key
curl "http://localhost:8080/cron/YOUR_KEY"

# Check activity log
mysql -u root -p erp -e "SELECT message FROM tblactivity WHERE message LIKE '%RAMOS CRON%' ORDER BY date DESC LIMIT 1;"
```
✓ Should show: "[RAMOS CRON] Automation successful"

### 7. Verify Routes (2 min)
```bash
mysql -u root -p erp -e "SELECT COUNT(*) as routes FROM tblramos_routes WHERE route_date = DATE(NOW());"
```
✓ Should show: routes = 1 or more

---

## 📋 What Gets Tested

| Test | What | Expected | Status |
|------|------|----------|--------|
| **Setup** | Migration runs | Creates options + columns | ✓ |
| **Config** | Settings save | Options updated | ✓ |
| **Manual** | Manual trigger | Automation executes | ✓ |
| **Cron** | Cron URL | Same as manual | ✓ |
| **Routes** | Auto-generation | Routes created for today | ✓ |
| **Duplicate** | Same day cron | Prevented | ✓ |
| **Logging** | Activity log | [RAMOS CRON] entries | ✓ |
| **Errors** | No orders | Gracefully handled | ✓ |

---

## 🎯 Key URLs & Commands

### Admin Pages
```
Settings:      http://localhost:8080/admin/ramos/settings/automation_schedule
Automation:    http://localhost:8080/admin/ramos/automation
Dashboard:     http://localhost:8080/admin/ramos
```

### Database Queries
```sql
-- Check configuration
SELECT option_name, option_value FROM wp_options WHERE option_name LIKE 'ramos_%';

-- Check automation runs
SELECT id, cron_scheduled, status FROM tblramos_automation_runs ORDER BY id DESC LIMIT 5;

-- Check routes today
SELECT id, vehicle_label, route_date FROM tblramos_routes WHERE route_date = DATE(NOW());

-- Check activity log
SELECT message FROM tblactivity WHERE message LIKE '%RAMOS CRON%' ORDER BY date DESC LIMIT 5;
```

### Cron Trigger
```bash
curl "http://localhost:8080/cron/APP_CRON_KEY"
```

### Reset for Re-testing
```bash
mysql -u root -p erp -e "UPDATE wp_options SET option_value = '' WHERE option_name = 'ramos_last_automation_run_date';"
```

---

## 🔍 Files Changed

| File | Type | Purpose |
|------|------|---------|
| `migrations/001_...config.php` | Migration | Initialize options + columns |
| `helpers/ramos_automation_helper.php` | Helper | Reusable functions |
| `controllers/Automation.php` | Refactored | Uses helper |
| `controllers/Settings.php` | NEW | Settings management |
| `ramos.php` | Modified | Cron hook handler |
| `models/Automation_model.php` | Extended | Route count tracking |
| `language/.../ramos_lang.php` | Updated | Settings strings |

---

## 🐛 Troubleshooting

### Migration fails
```bash
# Check if columns already exist
mysql -u root -p erp -e "DESCRIBE tblramos_automation_runs\G" | grep -E "cron_scheduled|routes"
```

### Cron not triggering
```bash
# Verify key in config
cat application/config/app-config.php | grep APP_CRON_KEY

# Test URL directly
curl -v "http://localhost:8080/cron/YOUR_KEY"
```

### Routes not generating
```bash
# Check setting
mysql -u root -p erp -e "SELECT option_value FROM wp_options WHERE option_name = 'ramos_route_generate_on_success';"

# Check automation succeeded
mysql -u root -p erp -e "SELECT status FROM tblramos_automation_runs ORDER BY id DESC LIMIT 1;"
```

### Duplicate running same day
```bash
# Reset last run date
mysql -u root -p erp -e "UPDATE wp_options SET option_value = '' WHERE option_name = 'ramos_last_automation_run_date';"
```

---

## 📚 Documentation Files

| File | Purpose |
|------|---------|
| `SCHEDULED_AUTOMATION_IMPLEMENTATION.md` | Architecture & detailed overview |
| `SCHEDULED_AUTOMATION_QUICK_REF.md` | Configuration reference |
| `SETUP_RUN_AUTOMATION.md` | Step-by-step setup with SQL commands |
| `TESTING_SCHEDULED_AUTOMATION.md` | 10+ test scenarios |
| `test_automation.sh` | Automated verification script |
| **← You are here** | Quick start reference |

---

## ✅ Success Indicators

After running all tests, you should see:

1. ✓ Migration completes without errors
2. ✓ Settings page accessible and saves
3. ✓ Manual automation button works
4. ✓ Cron URL triggers automation
5. ✓ Routes generated for today
6. ✓ Activity log shows [RAMOS CRON] entries
7. ✓ cron_scheduled=1 for cron runs
8. ✓ Duplicate prevention prevents same-day reruns
9. ✓ All database columns created
10. ✓ No PHP errors or exceptions

---

## 🚢 Production Deployment

Once testing complete, in production:

1. **Set up real cron** (Hostinger/cPanel/Linux):
   ```
   0 8,14,18 * * * curl -s https://yourdomain.com/cron/APP_CRON_KEY > /dev/null 2>&1
   ```

2. **Update automation hours** in admin settings to match cron schedule

3. **Configure route parameters** for your business:
   - Max stops per route
   - Route name prefix
   - Start time

4. **Monitor first 24 hours** for any issues

5. **Check logs weekly** for errors

---

## 💡 Pro Tips

- **Test during slow hours** to see if automation handles load
- **Monitor activity log** for patterns of success/failure
- **Keep daily backups** before automation runs
- **Set alerts** for cron failures if possible
- **Log rotation** to keep activity log clean

---

## Need Help?

1. Check **SETUP_RUN_AUTOMATION.md** for step-by-step with SQL
2. Check **TESTING_SCHEDULED_AUTOMATION.md** for 10+ test scenarios
3. Run **test_automation.sh** for pre-flight verification
4. Check application logs: `application/logs/`
5. Review activity log: Admin → Activity

---

**Last Updated**: March 4, 2026  
**Status**: ✅ All Tests Passing  
**Ready for Production**: Yes (after cron setup)
