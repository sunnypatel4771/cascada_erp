# Installation Verification Checklist

## ✅ Files Created/Modified

- [x] Composer package installed: `illuminate/console`
- [x] New Controller: `application/controllers/Scheduler.php`
- [x] Updated: `modules/ramos/controllers/Automation.php`
- [x] View Ready: `modules/ramos/views/automation/settings.php`

## 📋 Pre-Deployment Checklist

### Phase 1: Local Testing (Development)
- [ ] Verify Scheduler.php loads without errors
- [ ] Test `/scheduler/status` endpoint (should 403 - no key)
- [ ] Test `/scheduler/status?key=test` endpoint
- [ ] Verify Activity Log shows scheduler entries

### Phase 2: Configuration
- [ ] Set APP_CRON_KEY in `application/config/app-config.php`
- [ ] Generate random 32-character key
- [ ] Save and verify config loads

### Phase 3: Admin Settings
- [ ] Go to Ramos → Automation → Settings
- [ ] Configure automation schedule
- [ ] Enable automation
- [ ] Select hour and minutes
- [ ] Save settings
- [ ] Verify saved to database options table

### Phase 4: Hostinger Deployment
- [ ] Upload all files to Hostinger
- [ ] Verify file permissions are correct
- [ ] Run Composer on server: `composer install`
- [ ] Test `/scheduler/status?key=YOUR_KEY` endpoint

### Phase 5: Cron Configuration
- [ ] Go to cPanel → Cron Jobs
- [ ] Add cron job with correct URL and key
- [ ] Save cron job
- [ ] Wait for cron to execute (5 minutes or at scheduled hour)
- [ ] Check Activity Log for [SCHEDULER] entries

### Phase 6: Monitoring (First 24 Hours)
- [ ] Check Activity Log hourly for automation runs
- [ ] Verify routes are generated
- [ ] Verify last run date updates
- [ ] Check for any error messages
- [ ] Monitor application logs: `application/logs/`

## 🔍 Verification Tests

### Test 1: Scheduler Endpoint Access
```bash
# Should return 403 (missing key)
curl https://yourdomain.com/scheduler/status

# Should return 200 + JSON (with valid key)
curl https://yourdomain.com/scheduler/status?key=YOUR_CRON_KEY
```

Expected Response:
```json
{
  "enabled": true,
  "tasks": [...],
  "last_run": "2026-03-05",
  "current_time": "2026-03-05 15:30:00"
}
```

### Test 2: Database Options
```sql
SELECT option_name, option_value FROM wp_options 
WHERE option_name LIKE 'ramos_automation%' 
ORDER BY option_name;
```

Should show:
- `ramos_automation_schedule_enabled` = 1
- `ramos_automation_schedule_hour` = your selected hour
- `ramos_automation_schedule_minutes` = your selected minutes
- `ramos_automation_schedule_run_daily` = 1 or 0
- `ramos_automation_schedule_date` = day name or empty

### Test 3: Activity Log
```sql
SELECT date, message FROM tblactivity 
WHERE message LIKE '%SCHEDULER%'
ORDER BY date DESC LIMIT 10;
```

Should show recent entries like:
- `[SCHEDULER] Registered X automation task(s)`
- `[SCHEDULER] Automation successful: X orders, X batches, X routes`

### Test 4: Automation Run Record
```sql
SELECT id, cron_scheduled, status, run_at, total_orders_processed
FROM tblramos_automation_runs
WHERE cron_scheduled = 1
ORDER BY id DESC LIMIT 5;
```

Should show recent cron-scheduled runs.

## 🚨 Common Issues & Fixes

### Issue: 404 Not Found on /scheduler/status
**Fix**: 
- Verify `application/controllers/Scheduler.php` exists
- Restart web server or clear CodeIgniter cache
- Check URL format: `https://yourdomain.com/scheduler/status?key=KEY`

### Issue: 403 Forbidden
**Fix**:
- Verify `APP_CRON_KEY` is defined in config
- Verify key in URL matches config exactly
- Check for trailing/leading spaces in key

### Issue: Automation not running at scheduled time
**Fix**:
- Verify cron job exists in cPanel
- Verify cron job is enabled
- Wait for cron scheduler (usually runs on :00 of each hour)
- Check Activity Log for errors: `SELECT * FROM tblactivity WHERE message LIKE '%SCHEDULER%'`

### Issue: Routes not generating
**Fix**:
- Verify `ramos_route_generate_on_success` = 1
- Check if automation succeeded (check status response)
- Verify unprocessed orders exist for today

### Issue: Duplicate automation runs
**Fix**:
- Check `ramos_last_automation_run_date` in options
- Should equal today's date after first run
- If wrong, manually update: `UPDATE wp_options SET option_value = CURDATE() WHERE option_name = 'ramos_last_automation_run_date'`

## 📊 Monitoring Dashboard

### Key Metrics to Watch
1. **Last Automation Run**: 
   - Should update once per day (or per configured schedule)
   - Check: `GET /scheduler/status?key=KEY` → `last_run` field

2. **Automation Runs Per Day**: 
   - `SELECT COUNT(*) FROM tblramos_automation_runs WHERE DATE(run_at) = CURDATE() AND cron_scheduled = 1`

3. **Success Rate**: 
   - `SELECT COUNT(*) FROM tblramos_automation_runs WHERE status = 'completed' AND DATE(run_at) = CURDATE()`

4. **Routes Generated**: 
   - `SELECT SUM(routes_generated_count) FROM tblramos_automation_runs WHERE DATE(run_at) = CURDATE()`

## 🔧 Configuration Commands

### Generate Secure Cron Key
```bash
php -r "echo 'APP_CRON_KEY: ' . bin2hex(random_bytes(32)) . PHP_EOL;"
```

### Test Scheduler Directly
```bash
# List pending tasks
curl "https://yourdomain.com/scheduler/status?key=YOUR_KEY" | jq .

# Execute scheduler
curl "https://yourdomain.com/scheduler/run?key=YOUR_KEY" | jq .
```

### Database Maintenance
```sql
-- Clear duplicate runs
DELETE FROM tblramos_automation_runs 
WHERE cron_scheduled = 1 AND DATE(run_at) = CURDATE() 
AND id NOT IN (
  SELECT MAX(id) FROM tblramos_automation_runs 
  WHERE DATE(run_at) = CURDATE() GROUP BY DATE(run_at)
);

-- Reset last run date
UPDATE wp_options SET option_value = '' 
WHERE option_name = 'ramos_last_automation_run_date';
```

## 📞 Support Resources

- **Laravel Scheduler Docs**: https://laravel.com/docs/10.x/scheduling
- **CodeIgniter 3 Docs**: https://codeigniter.com/userguide3/
- **Hostinger cPanel Docs**: https://support.hostinger.com/en/articles/

## ✨ Final Sign-Off

| Item | Status | Notes |
|------|--------|-------|
| Composer installed | ✅ | illuminate/console v10.49 |
| Scheduler controller created | ✅ | Full featured implementation |
| Automation controller updated | ✅ | Saves new schedule format |
| Settings UI ready | ✅ | Simple 3-dropdown interface |
| Documentation complete | ✅ | Setup guide + quick reference |
| Ready for production | ⏳ | After completing checklist |

---

**Last Updated**: March 5, 2026  
**Version**: 1.0  
**Status**: Ready for Hostinger Deployment
