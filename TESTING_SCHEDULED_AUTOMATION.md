# Testing & Running Scheduled Automation

## Quick Start (5 Minutes)

### 1. Run Migration
```bash
cd /home/rev/Development/projects/erp
php index.php migrate
```

Expected output: Migration completes without errors, tables/options created.

### 2. Access Settings Page
```
URL: http://localhost:8080/admin/ramos/settings/automation_schedule
(Or navigate: Admin → Ramos → Settings → Automation Schedule)
```

### 3. Configure Automation
- Check: "Enable Scheduled Automation"
- Select Hours: Check the **current hour** (e.g., if it's 3pm, select "15")
- Keep defaults for route parameters
- Click: Save Settings

### 4. Create Test Data
Create an unprocessed order in omni_sales:
```sql
-- Insert test cart order (omni_sales)
INSERT INTO tblcart (userid, date, clientnote, duedate, status, channel_id, processed_for_purchase)
VALUES (1, NOW(), 'test order', DATE(NOW()), 2, 1, 0);

-- Get the order ID
SELECT id FROM tblcart WHERE clientnote = 'test order' LIMIT 1;

-- Add a cart item (replace ORDER_ID with actual ID)
INSERT INTO tblcart_detailt (rel_id, product_id, quantity, unit_price)
VALUES (ORDER_ID, 1, 10, 100);
```

### 5. Trigger Cron
```bash
curl "http://localhost:8080/cron/test_key"
```

(Replace `test_key` with your actual `APP_CRON_KEY` from `application/config/app-config.php`)

### 6. Verify Success
- Check: Automation dashboard shows new run
- Check: Routes created for today
- Check: Activity log shows `[RAMOS CRON]` entries

---

## Detailed Testing Guide

### Test 1: Configuration Storage

**Purpose**: Verify settings are saved correctly

**Steps**:
```php
// In any CI controller
$this->load->helper('ramos/ramos_automation');

// Check saved values
echo get_option('ramos_automation_schedule_enabled'); // Should output: 1
echo get_option('ramos_automation_schedule_hours'); // Should output: JSON like [8,14,15]
echo get_option('ramos_default_max_stops'); // Should output: 10
```

**Expected Results**:
- All options stored correctly in database
- JSON array properly formatted
- No errors

---

### Test 2: Helper Function - Check If Should Run

**Purpose**: Verify duplicate prevention logic

**Test Case 1: First run of the day**
```php
// Assuming automation enabled and correct hour
$this->load->helper('ramos/ramos_automation');
$should_run = ramos_should_run_scheduled_automation();
echo $should_run ? "YES - should run" : "NO - should not run";
// Expected: YES
```

**Test Case 2: Already ran today**
```php
// After first automation run (option set to today's date)
$should_run = ramos_should_run_scheduled_automation();
echo $should_run ? "YES" : "NO";
// Expected: NO
```

**Test Case 3: Disabled**
```php
update_option('ramos_automation_schedule_enabled', '0');
$should_run = ramos_should_run_scheduled_automation();
echo $should_run ? "YES" : "NO";
// Expected: NO
```

**Test Case 4: Wrong hour**
```php
update_option('ramos_automation_schedule_enabled', '1');
update_option('ramos_automation_schedule_hours', json_encode([2, 5, 10])); // 2am, 5am, 10am
update_option('ramos_last_automation_run_date', ''); // Reset
$should_run = ramos_should_run_scheduled_automation();
echo $should_run ? "YES" : "NO";
// Expected: NO (current hour not in list)
```

---

### Test 3: Execute Automation (AJAX)

**Purpose**: Verify manual automation still works

**URL**: `http://localhost:8080/admin/ramos/automation/run` (POST)

**Method**: AJAX POST with cURL or browser console

**Browser Console**:
```javascript
fetch('/admin/ramos/automation/run', {
    method: 'POST',
    headers: {'X-Requested-With': 'XMLHttpRequest'}
})
.then(r => r.json())
.then(d => console.log(d));
```

**Expected Results**:
```json
{
    "success": true,
    "run_id": 123,
    "orders_processed": 5,
    "batches_created": 2,
    "batch_ids": [45, 46]
}
```

**Check Database**:
```sql
SELECT * FROM tblramos_automation_runs 
WHERE id = 123 
ORDER BY id DESC LIMIT 1\G
-- Should show: cron_scheduled = 0, status = completed
```

---

### Test 4: Execute Automation Helper (Cron)

**Purpose**: Verify helper function works correctly

**Setup**:
```php
// Create a test controller or run directly
$this->load->helper('ramos/ramos_automation');

// Ensure automation enabled and current hour matches
update_option('ramos_automation_schedule_enabled', '1');
$current_hour = date('H');
update_option('ramos_automation_schedule_hours', json_encode([(int)$current_hour]));
update_option('ramos_last_automation_run_date', '');

// Execute
$result = ramos_execute_automation(0); // 0 = cron
var_dump($result);
```

**Expected Results**:
```php
Array (
    'success' => true|false,
    'run_id' => int,
    'orders_processed' => int,
    'batches_created' => int,
    'batch_ids' => array,
    'message' => string
)
```

**Check Database**:
```sql
SELECT id, run_by, cron_scheduled, status, total_orders_processed, total_purchase_orders_created
FROM tblramos_automation_runs 
ORDER BY id DESC LIMIT 1\G
-- cron_scheduled should be 1
-- run_by should be 0
```

---

### Test 5: Route Generation

**Purpose**: Verify routes are created after automation

**Setup**:
```php
// Create test cart orders first
INSERT INTO tblcart (userid, date, duedate, status, channel_id, processed_for_purchase)
VALUES (1, NOW(), DATE(NOW()), 2, 1, 0),
       (2, NOW(), DATE(NOW()), 2, 1, 0);

// Set max stops to 1 to test multiple routes
update_option('ramos_default_max_stops', '1');
update_option('ramos_default_route_prefix', 'TestRoute');
update_option('ramos_default_route_start_time', '09:00:00');
```

**Execute**:
```php
$this->load->helper('ramos/ramos_automation');
$result = ramos_generate_routes_for_today();
var_dump($result);
```

**Expected Results**:
```php
Array (
    'success' => true,
    'route_ids' => [1, 2],
    'routes_count' => 2
)
```

**Check Database**:
```sql
SELECT id, vehicle_label, route_date, start_time, capacity, status
FROM tblramos_routes 
WHERE route_date = DATE(NOW())
ORDER BY id DESC LIMIT 5\G

-- Should show routes with:
-- vehicle_label: TestRoute - Zone X N
-- start_time: 09:00:00
-- capacity: 1
-- status: draft
```

---

### Test 6: Full Cron Hook Flow

**Purpose**: Test complete integration (automation + routes)

**Setup**:
```php
// In ramos module bootstrap or test controller
update_option('ramos_automation_schedule_enabled', '1');
update_option('ramos_route_generate_on_success', '1');
update_option('ramos_last_automation_run_date', '');

$current_hour = date('H');
update_option('ramos_automation_schedule_hours', json_encode([(int)$current_hour]));

// Create test orders
INSERT INTO tblcart (userid, date, duedate, status, channel_id, processed_for_purchase)
VALUES (1, NOW(), DATE(NOW()), 2, 1, 0);
```

**Trigger Cron**:
```bash
curl "http://localhost:8080/cron/YOUR_CRON_KEY"
```

**Expected Results**:
1. Automation runs → purchase orders created
2. Routes generated → routes for today created
3. Last run date updated → prevents duplicate runs

**Check Activity Log**:
```sql
SELECT action, message FROM tblactivity 
WHERE message LIKE '%RAMOS CRON%'
ORDER BY date DESC LIMIT 10;

-- Expected messages:
-- [RAMOS CRON] Automation successful: 1 orders processed, 1 batches created
-- [RAMOS CRON] Generated 2 routes for 2026-03-04
```

**Check Database**:
```sql
-- Verify automation run marked as cron
SELECT cron_scheduled, routes_generated_count FROM tblramos_automation_runs
WHERE cron_scheduled = 1 ORDER BY id DESC LIMIT 1;
-- Expected: cron_scheduled=1, routes_generated_count=2

-- Verify routes created
SELECT COUNT(*) as route_count FROM tblramos_routes
WHERE route_date = DATE(NOW());
-- Expected: >0

-- Verify orders processed
SELECT COUNT(*) as processed FROM tblcart
WHERE processed_for_purchase = 1 AND DATE(date) = DATE(NOW());
-- Expected: >0
```

---

### Test 7: Duplicate Prevention

**Purpose**: Verify automation doesn't run twice same day

**Step 1**: First run
```bash
curl "http://localhost:8080/cron/YOUR_CRON_KEY"
# Watch: Automation executes, sets ramos_last_automation_run_date = today
```

**Step 2**: Second run (same hour, same day)
```bash
curl "http://localhost:8080/cron/YOUR_CRON_KEY"
# Watch: Should skip, log shows it was already run today
```

**Verify**:
```sql
SELECT COUNT(*) FROM tblactivity 
WHERE date >= CONCAT(DATE(NOW()), ' 00:00:00')
AND message LIKE '%RAMOS CRON%';
-- Should show only 1 automation message, not 2
```

---

### Test 8: Permission Checks

**Purpose**: Verify permissions work correctly

**Manual AJAX (requires edit permission)**:
```javascript
// As user WITHOUT create permission
fetch('/admin/ramos/automation/run', {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}})
.then(r => r.json())
.then(d => console.log(d));
// Expected: {success: false, message: "Access Denied"}
```

**Settings Page (requires edit permission)**:
```
// Try to access as user WITHOUT edit permission
http://localhost:8080/admin/ramos/settings/automation_schedule
// Expected: Access Denied message
```

---

### Test 9: Error Handling

**Purpose**: Verify graceful error handling

**Test Case 1: No unprocessed orders**
```php
// Ensure no unprocessed orders
DELETE FROM tblcart WHERE processed_for_purchase = 0;
DELETE FROM tblinvoices WHERE status = 1 AND processed_for_purchase = 0;

// Execute
$result = ramos_execute_automation(0);
var_dump($result);
// Expected: success = false, message = "No orders to process"
```

**Test Case 2: Invalid configuration**
```php
// Corrupt JSON in schedule hours
update_option('ramos_automation_schedule_hours', 'invalid json');

$should_run = ramos_should_run_scheduled_automation();
// Expected: Uses default [8,14,18], doesn't crash
```

**Test Case 3: Missing model**
```php
// Delete Automation_model (simulate)
// Try to run automation
// Expected: Logs error, catches exception gracefully
```

---

## Database Verification Commands

### Check All Scheduled Automation Data

```sql
-- Configuration options
SELECT option_name, option_value FROM wp_options 
WHERE option_name LIKE 'ramos_%' 
ORDER BY option_name;

-- Last 10 automation runs
SELECT id, run_by, cron_scheduled, status, total_orders_processed, 
       total_purchase_orders_created, routes_generated_count, created_at
FROM tblramos_automation_runs 
ORDER BY id DESC LIMIT 10\G

-- Verify new columns exist
DESCRIBE tblramos_automation_runs;
-- Should show: cron_scheduled (TINYINT), routes_generated_count (INT)

-- Check processed orders
SELECT id, processed_for_purchase, automation_run_id, status
FROM tblcart 
WHERE processed_for_purchase = 1 
ORDER BY id DESC LIMIT 5;

-- Check created purchase batches
SELECT id, supplier_id, status, created_at
FROM tblramos_purchase_batches
ORDER BY id DESC LIMIT 5;

-- Check generated routes
SELECT id, vehicle_label, route_date, start_time, capacity, status
FROM tblramos_routes
WHERE route_date = DATE(NOW())
ORDER BY id;
```

---

## Troubleshooting During Testing

### Issue: Migration fails

**Solution**:
```bash
# Check if table already has columns
mysql -u root -p -e "DESCRIBE erp.tblramos_automation_runs;" | grep -E "cron_scheduled|routes_generated_count"

# If columns exist, manually verify data can be inserted
mysql -u root -p -e "INSERT INTO erp.tblramos_automation_runs (run_by, cron_scheduled, status) VALUES (0, 1, 'test');"
```

### Issue: Cron key not working

**Solution**:
```php
// Check APP_CRON_KEY in config
defined('APP_CRON_KEY') && die(APP_CRON_KEY); // Abort to see the key

// Or query directly
SELECT option_value FROM wp_options WHERE option_name = 'app_cron_key';
```

### Issue: Routes not generated

**Solution**:
```sql
-- Check if orders exist for today
SELECT id, duedate, channel_id, status FROM tblcart 
WHERE DATE(duedate) = DATE(NOW()) AND status != 5;

-- Check route generation setting
SELECT option_value FROM wp_options 
WHERE option_name = 'ramos_route_generate_on_success';

-- Check if automation actually succeeded
SELECT status FROM tblramos_automation_runs 
ORDER BY id DESC LIMIT 1;
```

### Issue: Duplicate prevention not working

**Solution**:
```sql
-- Check last run date
SELECT option_value FROM wp_options 
WHERE option_name = 'ramos_last_automation_run_date';

-- Should be today's date if ran successfully
-- Manually reset for testing:
UPDATE wp_options SET option_value = '' 
WHERE option_name = 'ramos_last_automation_run_date';
```

---

## Performance Testing

### Measure Automation Speed

```php
$start = microtime(true);
$result = ramos_execute_automation(0);
$elapsed = microtime(true) - $start;
echo "Automation took: {$elapsed} seconds";
// Expected: < 3 seconds for typical order volume
```

### Measure Route Generation Speed

```php
$start = microtime(true);
$result = ramos_generate_routes_for_today();
$elapsed = microtime(true) - $start;
echo "Route generation took: {$elapsed} seconds";
// Expected: < 2 seconds for typical volumes
```

---

## Automated Test Script

Create file: `application/controllers/test_automation.php`

```php
<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Test_automation extends App_Controller {
    public function index() {
        if (ENVIRONMENT !== 'development') {
            show_error('Testing disabled in production');
        }

        $this->load->helper('ramos/ramos_automation');
        
        echo "<h2>Scheduled Automation Tests</h2>";
        
        // Test 1: Configuration
        echo "<h3>Test 1: Configuration Storage</h3>";
        echo "Automation enabled: " . get_option('ramos_automation_schedule_enabled') . "<br>";
        echo "Schedule hours: " . get_option('ramos_automation_schedule_hours') . "<br>";
        echo "Max stops: " . get_option('ramos_default_max_stops') . "<br><br>";
        
        // Test 2: Check if should run
        echo "<h3>Test 2: Should Run Check</h3>";
        $should_run = ramos_should_run_scheduled_automation();
        echo "Should run now? " . ($should_run ? "YES" : "NO") . "<br>";
        echo "Last run date: " . get_option('ramos_last_automation_run_date') . "<br><br>";
        
        // Test 3: Execute automation
        echo "<h3>Test 3: Execute Automation (Cron)</h3>";
        $result = ramos_execute_automation(0);
        echo "Success: " . ($result['success'] ? "YES" : "NO") . "<br>";
        echo "Orders processed: " . $result['orders_processed'] . "<br>";
        echo "Batches created: " . $result['batches_created'] . "<br>";
        if ($result['run_id']) {
            echo "Run ID: " . $result['run_id'] . "<br>";
        }
        echo "Message: " . $result['message'] . "<br><br>";
        
        // Test 4: Generate routes
        if ($result['success']) {
            echo "<h3>Test 4: Generate Routes</h3>";
            $routes = ramos_generate_routes_for_today();
            echo "Success: " . ($routes['success'] ? "YES" : "NO") . "<br>";
            echo "Routes created: " . $routes['routes_count'] . "<br>";
            echo "Route IDs: " . implode(', ', $routes['route_ids']) . "<br>";
        }
    }
}
```

**Access**: `http://localhost:8080/test_automation`

---

## Summary Checklist

- [ ] Migration runs without errors
- [ ] Settings page accessible and saves correctly
- [ ] Manual automation trigger works (AJAX)
- [ ] Helper functions return correct results
- [ ] Cron hook handler executes successfully
- [ ] Routes generate after successful automation
- [ ] Routes skip on automation failure
- [ ] Duplicate prevention prevents same-day reruns
- [ ] Activity logs show `[RAMOS CRON]` entries
- [ ] Database tables and columns created correctly
- [ ] `cron_scheduled` flag set correctly (0 = manual, 1 = cron)
- [ ] `routes_generated_count` updated correctly
- [ ] Permission checks work for settings page
- [ ] Error handling graceful (no crashes)

