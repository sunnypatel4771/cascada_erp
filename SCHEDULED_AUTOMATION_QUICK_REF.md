# Scheduled Automation Quick Reference

## Setup Checklist

- [ ] Run migration: `php index.php migrate`
- [ ] Navigate to Ramos → Settings → Automation Schedule
- [ ] Enable "Enable Scheduled Automation" checkbox
- [ ] Select hours (e.g., 08:00, 14:00, 18:00)
- [ ] Set max stops per route (recommend: 10)
- [ ] Ensure "Auto-generate routes on success" is checked
- [ ] Click Save
- [ ] Verify settings saved

## How It Works

```
Each Hour (via external cron)
    ↓
Check: Automation enabled & correct hour & not today?
    ↓
YES → Execute Automation (system run_by=0)
    ↓
Success? → Generate Routes for Today
    ↓
Routes Created ✓
```

## Configuration

### Admin Settings Page
- **URL**: `/admin/ramos/settings/automation_schedule`
- **Requires**: `edit` permission on ramos module

### Configuration Options
```php
// Enable/disable
get_option('ramos_automation_schedule_enabled'); // 0 or 1

// Schedule hours (JSON)
get_option('ramos_automation_schedule_hours'); // '[8,14,18]'

// Route generation
get_option('ramos_route_generate_on_success'); // 0 or 1
get_option('ramos_default_max_stops'); // '10'
get_option('ramos_default_route_prefix'); // 'Route'
get_option('ramos_default_route_start_time'); // '08:00:00'

// Last run tracking
get_option('ramos_last_automation_run_date'); // 'YYYY-MM-DD'
```

## Helper Functions

### Execute Automation
```php
$this->load->helper('ramos/ramos_automation');
$result = ramos_execute_automation($staff_id); // 0 = cron, else = staff ID
// Returns: ['success', 'run_id', 'orders_processed', 'batches_created', ...]
```

### Generate Routes
```php
$result = ramos_generate_routes_for_today();
// Returns: ['success', 'route_ids', 'routes_count']
```

### Check If Should Run
```php
if (ramos_should_run_scheduled_automation()) {
    // Automation will run now
}
```

## Database Tables

### tblramos_automation_runs
**New columns**:
- `cron_scheduled` (TINYINT) - 1 if run by cron, 0 if manual
- `routes_generated_count` (INT) - number of routes created after automation

## Activity Logging

Look for these in activity logs:
- `[RAMOS CRON] Automation successful: X orders, Y batches`
- `[RAMOS CRON] Routes generated: Z routes created`
- `[RAMOS CRON] Automation failed: <reason>`

## Manual Trigger

Users can still manually trigger automation from dashboard:
- **URL**: `/admin/ramos/automation` → Click "Run Automation"
- Runs as staff user (tracked in automation run record)
- Full permission checks applied

## Troubleshooting

### Automation Not Running
1. Check if enabled: `get_option('ramos_automation_schedule_enabled')`
2. Check if correct hour: `get_option('ramos_automation_schedule_hours')`
3. Check if already ran today: `get_option('ramos_last_automation_run_date')`
4. Verify cron is calling `/cron/APP_CRON_KEY`

### Routes Not Generating
1. Check `ramos_route_generate_on_success` is enabled
2. Verify automation actually succeeded (check activity log)
3. Confirm unrouted orders exist for today
4. Check zone/priority fields are populated

### Settings Not Saving
1. Ensure you have `edit` permission on ramos module
2. Check browser console for AJAX errors
3. Verify Settings controller is accessible at `/admin/ramos/settings`
4. Check PHP error logs

## Files Modified

| File | Change |
|------|--------|
| `modules/ramos/migrations/001_add_scheduled_automation_config.php` | NEW - Migration |
| `modules/ramos/helpers/ramos_automation_helper.php` | NEW - Helper functions |
| `modules/ramos/controllers/Automation.php` | Refactored - Uses helper |
| `modules/ramos/controllers/Settings.php` | NEW - Settings management |
| `modules/ramos/models/Automation_model.php` | Added `update_run_routes()` |
| `modules/ramos/ramos.php` | Added cron hook handler |
| `modules/ramos/views/settings/automation_schedule.php` | NEW - Settings UI |
| `modules/ramos/language/english/ramos_lang.php` | Added 13 language strings |

## Cron Configuration Example

### For cPanel/WHM:
```
0 */1 * * * curl -s https://yourdomain.com/cron/YOUR_CRON_KEY > /dev/null 2>&1
```

### For Linux crontab:
```bash
0 */1 * * * /usr/bin/curl -s https://yourdomain.com/cron/YOUR_CRON_KEY > /dev/null 2>&1
```

This runs the cron check every hour. The system will only execute automation at configured hours.

## Example Schedule

**Typical setup** for 3 daily automation runs:

```
Automation Hours: 8, 14, 18
Max Stops: 10
Route Prefix: "Route"
Start Time: 08:00
```

**What happens**:
- **08:00** - Automation runs → Routes generated for today
- **14:00** - Automation runs → Routes updated
- **18:00** - Final automation → Final route generation

## Performance Notes

- Automation typically takes 1-3 seconds for 50-100 orders
- Route generation adds another 1-2 seconds
- No database locking (safe to run multiple cron threads)
- Duplicate prevention ensures once-per-day execution
- Activity logs all actions for audit trail

## Support

For issues:
1. Check `/application/logs/` for errors
2. Review activity log for `[RAMOS CRON]` entries
3. Verify cron key matches `APP_CRON_KEY` constant
4. Test manual automation first
