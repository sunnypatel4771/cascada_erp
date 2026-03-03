# Step-by-Step: Test & Run Scheduled Automation

## Prerequisites
- PHP 8.1+ ✓ (verified)
- Database connection configured ✓ (verified)
- All files in place ✓ (verified via test script)

---

## Phase 1: Database Setup (2 minutes)

### Step 1.1: Run Migration
```bash
cd /home/rev/Development/projects/erp
php index.php migrate
```

**Expected output**:
```
Migration files found.
Application version: 3.0.0
Current migration version: 332 (or higher)
Migration completed successfully.
```

**What it does**:
- Creates 7 configuration options in `wp_options` table
- Adds 2 new columns to `tblramos_automation_runs` table
- Initializes default settings

### Step 1.2: Verify Database Changes
```bash
# Check options created
mysql -u root -p erp << 'EOF'
SELECT option_name, option_value FROM wp_options 
WHERE option_name LIKE 'ramos_%' ORDER BY option_name;
EOF
```

**Expected result** (7 rows):
```
ramos_automation_schedule_enabled       | 0
ramos_automation_schedule_hours         | [8,14,18]
ramos_default_max_stops                 | 10
ramos_default_route_prefix              | Route
ramos_default_route_start_time          | 08:00:00
ramos_last_automation_run_date          | 
ramos_route_generate_on_success         | 1
```

```bash
# Check new columns in automation_runs table
mysql -u root -p erp << 'EOF'
DESCRIBE tblramos_automation_runs;
EOF
```

**Expected**: Should show `cron_scheduled` and `routes_generated_count` columns

---

## Phase 2: Configuration Setup (3 minutes)

### Step 2.1: Login to Admin Dashboard
```
URL: http://localhost:8080/admin
(Or your actual domain)
```

### Step 2.2: Navigate to Settings
```
Path: Ramos → Settings → Automation Schedule

Or direct URL: http://localhost:8080/admin/ramos/settings/automation_schedule
```

### Step 2.3: Enable Automation
1. **Check**: "Enable Scheduled Automation"
2. **Select hours**: Choose your current hour (e.g., if it's 3pm, select "15")
3. **Keep defaults**:
   - Max stops: 10
   - Route prefix: Route
   - Auto-generate routes: checked

4. **Click**: Save Settings

**Expected response**:
```
✓ Automation schedule settings saved successfully.
```

### Step 2.4: Verify Settings Saved
```bash
mysql -u root -p erp << 'EOF'
SELECT option_value FROM wp_options 
WHERE option_name = 'ramos_automation_schedule_enabled';

SELECT option_value FROM wp_options 
WHERE option_name = 'ramos_automation_schedule_hours';
EOF
```

**Expected**:
```
1
[YOUR_SELECTED_HOURS]
```

---

## Phase 3: Create Test Data (2 minutes)

### Step 3.1: Insert Test Cart Order
```bash
mysql -u root -p erp << 'EOF'
-- Create a test order from omni_sales (tblcart)
INSERT INTO tblcart (userid, date, duedate, status, channel_id, processed_for_purchase, clientnote)
VALUES (1, NOW(), DATE(NOW()), 2, 1, 0, 'Automation Test Order');

-- Get the order ID
SELECT LAST_INSERT_ID() as order_id;
EOF
```

**Save the order_id** for next step.

### Step 3.2: Add Items to Order
```bash
# Replace ORDER_ID with the ID from previous step
mysql -u root -p erp << 'EOF'
INSERT INTO tblcart_detailt (rel_id, product_id, quantity, unit_price)
VALUES (ORDER_ID, 1, 5, 100.00);
EOF
```

### Step 3.3: Verify Test Data
```bash
mysql -u root -p erp << 'EOF'
SELECT id, userid, duedate, status, processed_for_purchase 
FROM tblcart 
WHERE clientnote = 'Automation Test Order';
EOF
```

**Expected**:
```
| id | userid | duedate    | status | processed_for_purchase |
| 99 | 1      | 2026-03-04 | 2      | 0                      |
```

---

## Phase 4: Test Manual Automation (2 minutes)

### Step 4.1: Verify Automation Dashboard Works
```
URL: http://localhost:8080/admin/ramos/automation
```

You should see:
- "Unprocessed Orders" count
- "Run Automation" button

### Step 4.2: Trigger Manual Automation
1. Click "Run Automation" button
2. Wait 2-3 seconds for response

**Expected result**:
```json
{
  "success": true,
  "run_id": 1,
  "orders_processed": 1,
  "purchase_orders_created": 1,
  "message": "Automation completed: 1 order(s) processed, 1 purchase order(s) created"
}
```

### Step 4.3: Verify Results
```bash
# Check automation run created
mysql -u root -p erp << 'EOF'
SELECT id, run_by, cron_scheduled, status, total_orders_processed, total_purchase_orders_created
FROM tblramos_automation_runs
ORDER BY id DESC LIMIT 1;
EOF
```

**Expected**:
```
| id | run_by | cron_scheduled | status    | total_orders_processed | total_purchase_orders_created |
| 1  | 1      | 0              | completed | 1                      | 1                             |
```

```bash
# Check order marked as processed
mysql -u root -p erp << 'EOF'
SELECT id, processed_for_purchase, automation_run_id
FROM tblcart
WHERE clientnote = 'Automation Test Order';
EOF
```

**Expected**:
```
| id | processed_for_purchase | automation_run_id |
| 99 | 1                      | 1                 |
```

---

## Phase 5: Test Scheduled Automation (3 minutes)

### Step 5.1: Reset for Testing
```bash
# Delete previous run data to test fresh
mysql -u root -p erp << 'EOF'
-- Reset automation run date
UPDATE wp_options SET option_value = '' 
WHERE option_name = 'ramos_last_automation_run_date';

-- Create fresh test order
INSERT INTO tblcart (userid, date, duedate, status, channel_id, processed_for_purchase, clientnote)
VALUES (2, NOW(), DATE(NOW()), 2, 1, 0, 'Cron Test Order');

-- Add item
INSERT INTO tblcart_detailt (rel_id, product_id, quantity, unit_price)
VALUES (LAST_INSERT_ID(), 2, 3, 50.00);
EOF
```

### Step 5.2: Find Your Cron Key
```bash
# Get the APP_CRON_KEY from config
grep "APP_CRON_KEY" /home/rev/Development/projects/erp/application/config/app-config.php
```

**Example output**:
```php
define('APP_CRON_KEY', 'your-secret-key-here');
```

### Step 5.3: Trigger Cron Job
```bash
# Replace YOUR_CRON_KEY with actual key
curl -v "http://localhost:8080/cron/YOUR_CRON_KEY"
```

**Expected output**:
```
< HTTP/1.1 200 OK
< Content-Type: text/html; charset=UTF-8

(No output from successful cron - check logs instead)
```

### Step 5.4: Check Activity Log
```bash
# View last RAMOS CRON activities
mysql -u root -p erp << 'EOF'
SELECT date, action, message FROM tblactivity
WHERE message LIKE '%RAMOS CRON%'
ORDER BY date DESC LIMIT 5;
EOF
```

**Expected output** (one of these):
```
Automation successful:
2026-03-04 15:30:45 | activity | [RAMOS CRON] Automation successful: 1 orders processed, 1 batches created

Routes generated:
2026-03-04 15:30:46 | activity | [RAMOS CRON] Generated 1 routes for 2026-03-04

Already ran today:
(No [RAMOS CRON] entry - duplicate prevention working)
```

### Step 5.5: Verify Cron Automation Run
```bash
# Check automation run marked as cron
mysql -u root -p erp << 'EOF'
SELECT id, run_by, cron_scheduled, status, routes_generated_count
FROM tblramos_automation_runs
WHERE cron_scheduled = 1
ORDER BY id DESC LIMIT 1;
EOF
```

**Expected**:
```
| id | run_by | cron_scheduled | status    | routes_generated_count |
| 2  | 0      | 1              | completed | 1                      |
```

**Note**: `run_by = 0` = system (cron), `cron_scheduled = 1` = triggered by cron

---

## Phase 6: Test Route Generation (2 minutes)

### Step 6.1: Verify Routes Created
```bash
# Check routes for today
mysql -u root -p erp << 'EOF'
SELECT id, vehicle_label, route_date, start_time, capacity, status
FROM tblramos_routes
WHERE route_date = DATE(NOW())
ORDER BY id DESC LIMIT 5;
EOF
```

**Expected**:
```
| id | vehicle_label | route_date | start_time | capacity | status |
| 1  | Route         | 2026-03-04 | 08:00:00   | 10       | draft  |
```

### Step 6.2: Verify Route Stops
```bash
# Check stops assigned to routes
mysql -u root -p erp << 'EOF'
SELECT rs.id, rs.route_id, rs.order_id, rs.stop_number, rs.status
FROM tblramos_route_stops rs
JOIN tblramos_routes r ON r.id = rs.route_id
WHERE r.route_date = DATE(NOW())
ORDER BY rs.id;
EOF
```

**Expected**:
```
| id | route_id | order_id | stop_number | status  |
| 1  | 1        | 99       | 1           | pending |
```

---

## Phase 7: Test Duplicate Prevention (2 minutes)

### Step 7.1: Trigger Cron Again (Same Day)
```bash
curl -v "http://localhost:8080/cron/YOUR_CRON_KEY"
```

### Step 7.2: Verify No Duplicate Run
```bash
# Count [RAMOS CRON] entries today
mysql -u root -p erp << 'EOF'
SELECT COUNT(*) as cron_count FROM tblactivity
WHERE message LIKE '%RAMOS CRON%'
AND DATE(date) = DATE(NOW());
EOF
```

**Expected**: Should NOT increase (duplicate prevention working)

```bash
# Check last run date still set
mysql -u root -p erp << 'EOF'
SELECT option_value FROM wp_options 
WHERE option_name = 'ramos_last_automation_run_date';
EOF
```

**Expected**: Should show today's date

---

## Phase 8: Test Error Handling (1 minute)

### Step 8.1: Test with No Unprocessed Orders
```bash
# Create a fresh start with no unprocessed orders
mysql -u root -p erp << 'EOF'
UPDATE wp_options SET option_value = '' 
WHERE option_name = 'ramos_last_automation_run_date';

DELETE FROM tblcart WHERE clientnote LIKE '%Test%Order%';
EOF

# Trigger cron
curl "http://localhost:8080/cron/YOUR_CRON_KEY"

# Check log for error message
mysql -u root -p erp << 'EOF'
SELECT message FROM tblactivity
WHERE message LIKE '%RAMOS CRON%'
ORDER BY date DESC LIMIT 1;
EOF
```

**Expected message**:
```
[RAMOS CRON] No unprocessed orders
```

---

## Summary: Testing Checklist

### ✓ Phase 1: Database
- [ ] Migration runs without errors
- [ ] 7 new options in wp_options table
- [ ] 2 new columns in tblramos_automation_runs

### ✓ Phase 2: Configuration
- [ ] Settings page loads
- [ ] Settings save successfully
- [ ] Options reflect saved values

### ✓ Phase 3: Test Data
- [ ] Test order created in tblcart
- [ ] Test order item created in tblcart_detailt
- [ ] Order has processed_for_purchase = 0

### ✓ Phase 4: Manual Automation
- [ ] Manual run button works
- [ ] Automation executes successfully
- [ ] Order marked as processed
- [ ] Purchase batch created
- [ ] cron_scheduled = 0 in run record

### ✓ Phase 5: Scheduled Automation
- [ ] Cron URL triggers successfully
- [ ] Activity log shows [RAMOS CRON] entries
- [ ] Automation runs as system (run_by=0)
- [ ] cron_scheduled = 1 in run record

### ✓ Phase 6: Route Generation
- [ ] Routes created for today
- [ ] Route stops assigned correctly
- [ ] routes_generated_count updated

### ✓ Phase 7: Duplicate Prevention
- [ ] Second cron call same day doesn't duplicate
- [ ] Last run date prevents reruns

### ✓ Phase 8: Error Handling
- [ ] Gracefully handles no unprocessed orders
- [ ] No crashes or exceptions

---

## Troubleshooting Quick Fixes

| Problem | Solution |
|---------|----------|
| Migration fails | Check table already has columns: `DESCRIBE tblramos_automation_runs` |
| Cron not triggering | Verify cron URL correct: `grep APP_CRON_KEY application/config/app-config.php` |
| Routes not generating | Check `ramos_route_generate_on_success` is '1' in options |
| No [RAMOS CRON] logs | Verify automation succeeded first (check regular automation logs) |
| Settings won't save | Ensure you have 'edit' permission on ramos module |
| Automation runs twice | Reset: `UPDATE wp_options SET option_value = '' WHERE option_name = 'ramos_last_automation_run_date'` |

---

## Next: Production Setup

Once testing passes:

1. **Configure Real Cron** (Hostinger/cPanel):
   ```
   0 8,14,18 * * * curl -s https://yourdomain.com/cron/APP_CRON_KEY > /dev/null
   ```

2. **Disable Debug Mode** (if enabled during testing)

3. **Monitor Logs** Weekly for issues

4. **Adjust Schedule** Based on order volume

**Done!** 🎉 Scheduled automation is now running.
