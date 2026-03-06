# Laravel Task Scheduler Setup - Hostinger Deployment

## Overview

Your autonomous automation and route generation now uses **Laravel Task Scheduler** instead of OS-level cron configuration. This is more reliable and easier to configure.

## How It Works

1. **Single Cron Job** (runs every 5 minutes):
   ```bash
   */5 * * * * curl -s https://yourdomain.com/scheduler/run?key=YOUR_CRON_KEY
   ```

2. **Scheduler Controller** checks what needs to run based on your settings
3. **Only executes tasks at configured times** (8am, 2pm, 6pm, etc.)
4. **Prevents duplicate runs** (only once per day per hour)

---

## Setup Steps

### 1. Install Composer Dependencies ✅ (Already Done)
```bash
composer require illuminate/console
```

### 2. Set Your Cron Key

In `application/config/app-config.php`, ensure you have:
```php
define('APP_CRON_KEY', 'your-super-secret-key-here-change-this');
```

Generate a secure key:
```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

### 3. Configure in Hostinger cPanel

1. Go to **cPanel → Cron Jobs**
2. Add a new cron job:
   ```bash
   */5 * * * * curl -s https://yourdomain.com/scheduler/run?key=YOUR_CRON_KEY > /dev/null 2>&1
   ```
3. Replace `YOUR_CRON_KEY` with your actual key from step 2
4. Save the cron job

### 4. Configure Automation in Admin Panel

1. Go to **Ramos → Automation → Settings**
2. Enable "Enable Automation"
3. Choose your schedule:
   - **Run Daily**: Every day at specified hour
   - **Specific Day**: Monday, Tuesday, etc.
4. Select Hour (0-23)
5. Select Minutes (0, 10, 20, 30, 40, 50)
6. Configure Route Generation settings
7. Save

---

## Testing the Scheduler

### Test 1: Check Scheduler Status
```bash
curl "https://yourdomain.com/scheduler/status?key=YOUR_CRON_KEY"
```

Should return:
```json
{
  "enabled": true,
  "tasks": [
    {
      "name": "Automation",
      "schedule": "Daily at 08:00",
      "next_run": "2026-03-06 08:00:00"
    }
  ],
  "last_run": "2026-03-05",
  "current_time": "2026-03-05 15:30:00"
}
```

### Test 2: Manual Scheduler Run
```bash
curl "https://yourdomain.com/scheduler/run?key=YOUR_CRON_KEY"
```

Should return:
```json
{
  "success": true,
  "message": "Scheduler executed",
  "timestamp": "2026-03-05 15:30:00"
}
```

### Test 3: Check Activity Log
In your admin panel, go to **Activity Log** and search for `[SCHEDULER]` entries:
```
[SCHEDULER] Automation successful: 5 orders, 2 batches, 1 routes generated
[SCHEDULER] Registered 1 automation task(s)
```

---

## Automation Flow

```
Every 5 Minutes:
    ↓
curl /scheduler/run?key=KEY
    ↓
Controller receives request
    ↓
Validates access (checks APP_CRON_KEY)
    ↓
Initializes Laravel Scheduler
    ↓
Registers configured tasks
    ↓
Checks if any tasks should run at THIS moment
    ↓
If YES:
    - Execute automation as system user (run_by_id=0)
    - Generate routes if enabled
    - Update last_automation_run_date
    - Log to activity: "[SCHEDULER] Success..."
    ↓
If NO:
    - Nothing happens
    - Waits for next configured time
    ↓
Done ✓
```

---

## Configuration Reference

### Admin Settings Saved
- `ramos_automation_schedule_enabled` - 0/1
- `ramos_automation_schedule_run_daily` - 0/1
- `ramos_automation_schedule_date` - Day name (monday, tuesday, etc.)
- `ramos_automation_schedule_hour` - 0-23
- `ramos_automation_schedule_minutes` - 0, 10, 20, 30, 40, 50
- `ramos_route_generate_on_success` - 0/1
- `ramos_default_max_stops` - 1-100
- `ramos_default_route_prefix` - String (e.g., "Route")
- `ramos_last_automation_run_date` - YYYY-MM-DD (prevents duplicate daily runs)

---

## Troubleshooting

### Automation Not Running

**Check 1: Is cron job active in cPanel?**
```bash
# In cPanel → Cron Jobs, verify your job is listed and enabled
```

**Check 2: Is automation enabled in admin?**
```
Go to Ramos → Automation → Settings
Verify "Enable Automation" is checked
```

**Check 3: Did you miss the cron time?**
```
If set to 08:00 and it's 08:01, it will run in ~4 minutes
Scheduler checks every 5 minutes
```

**Check 4: Activity log for errors**
```
Go to Admin → Activity Log
Search for [SCHEDULER]
Look for error messages
```

### Test Scheduler Directly

```bash
# Change to your domain and key
curl -v "https://yourdomain.com/scheduler/run?key=YOUR_CRON_KEY"

# Should see HTTP 200 and JSON response
```

### Manual Run (Without Cron)

If you want to test automation immediately:
1. Go to **Ramos → Automation → Dashboard**
2. Click **"Trigger Automation"** button
3. Check the recent runs table

---

## Hostinger-Specific Tips

### Finding cPanel
1. Log in to Hostinger Account
2. Click "Hosting" in sidebar
3. Click your domain
4. Click "Manage" or "Control Panel"
5. Look for "cPanel" option

### Cron Job Monitoring
- Hostinger logs cron execution in cPanel
- Failed jobs show error messages
- Check email notifications from Hostinger about cron failures

### URL Access
- Must use HTTPS (https://yourdomain.com)
- Must include cron key in URL
- Example: `https://yourdomain.com/scheduler/run?key=abc123xyz789`

### Activity Logging
All scheduler actions logged to:
- Activity table (visible in admin)
- Application logs: `application/logs/`
- Prefixed with `[SCHEDULER]` for easy filtering

---

## Example Schedules

### Twice Daily (8am & 2pm)
1. Set "Run Daily" checkbox
2. Set Hour to 8
3. Set Minutes to 00
4. Cron will execute: 08:00 and next instance at 14:00

Wait... actually, the current interface only allows ONE hour per configuration. To schedule multiple times, you'd need to:

**Option A: Multiple Cron Jobs** (Recommended)
```bash
0 8 * * * curl -s https://yourdomain.com/scheduler/run?key=KEY > /dev/null 2>&1
0 14 * * * curl -s https://yourdomain.com/scheduler/run?key=KEY > /dev/null 2>&1
0 18 * * * curl -s https://yourdomain.com/scheduler/run?key=KEY > /dev/null 2>&1
```

This is actually simpler than the every-5-minutes approach!

---

## Support

For issues:
1. Check `application/logs/` for PHP errors
2. Search activity log for `[SCHEDULER]` entries
3. Test `/scheduler/status?key=KEY` endpoint
4. Verify cron key matches `APP_CRON_KEY` in config

---

## Next Steps

1. ✅ Install Composer package (`composer require illuminate/console`)
2. ✅ Create Scheduler.php controller
3. ⏳ Set APP_CRON_KEY in app-config.php
4. ⏳ Add cron job in cPanel
5. ⏳ Configure automation in admin settings
6. ⏳ Test with `/scheduler/status?key=KEY`
