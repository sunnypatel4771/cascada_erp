# Scheduled Automation & Route Generation Implementation

**Status**: ✅ Complete  
**Date**: March 4, 2026  
**Commit**: auto 6737d15

## Overview

Implemented a complete scheduled automation and route generation system that runs at configurable times (e.g., 8am, 2pm, 6pm) to automatically:
1. Execute purchase order automation based on inventory vs order demand
2. Generate delivery routes for the same day on successful automation

## Architecture

### Data Flow

```
System Cron (via Cron.php)
    ↓
after_cron_run hook
    ↓
ramos_scheduled_automation_and_routes() (in ramos.php)
    ↓
Check: enabled? correct hour? not already today?
    ↓
ramos_execute_automation(0) [system run_by_id=0]
    ↓
[Automation succeeds]
    ↓
ramos_generate_routes_for_today()
    ↓
Routes created for today
    ↓
Update automation run record with routes_generated_count
    ↓
Mark last_automation_run_date = today
```

## Files Created/Modified

### 1. Migration
**File**: `modules/ramos/migrations/001_add_scheduled_automation_config.php`
- Creates configuration options in options table:
  - `ramos_automation_schedule_enabled` (0/1)
  - `ramos_automation_schedule_hours` (JSON: [8,14,18])
  - `ramos_default_max_stops` (default: 10)
  - `ramos_default_route_prefix` (default: "Route")
  - `ramos_route_generate_on_success` (0/1)
  - `ramos_last_automation_run_date` (tracks daily execution)
  - `ramos_default_route_start_time` (default: 08:00:00)
- Adds columns to `tblramos_automation_runs`:
  - `cron_scheduled` (TINYINT) - marks if run via cron
  - `routes_generated_count` (INT) - number of routes created

### 2. Helper Functions
**File**: `modules/ramos/helpers/ramos_automation_helper.php`
**Functions**:

#### `ramos_execute_automation($run_by_id = 0)`
- Reusable automation execution logic
- Parameter: `$run_by_id` (0 = system/cron, else = staff ID)
- Returns: `['success' => bool, 'run_id' => int, 'orders_processed' => int, 'batches_created' => int, ...]`
- Marks as `cron_scheduled = 1` when `$run_by_id = 0`
- Steps:
  1. Get unprocessed orders (omni_sales + ERP)
  2. Calculate required quantities
  3. Get current warehouse inventory
  4. Calculate purchase deficits by supplier
  5. Create draft purchase batches
  6. Mark orders as processed
  7. Complete run record with summary

#### `ramos_generate_routes_for_today()`
- Generates routes for today's date
- Uses configurable parameters from options:
  - `ramos_default_max_stops`
  - `ramos_default_route_prefix`
  - `ramos_default_route_start_time`
- Returns: `['success' => bool, 'route_ids' => [], 'routes_count' => int]`

#### `ramos_should_run_scheduled_automation()`
- Checks if automation should run now
- Validations:
  - Enabled? (`ramos_automation_schedule_enabled === '1'`)
  - Correct hour? (current hour in `ramos_automation_schedule_hours`)
  - Not already today? (`last_automation_run_date !== today`)
- Returns: `bool`

#### `_ramos_merge_quantities($omniQuantities, $erpQuantities)`
- Helper to combine quantities from both order sources
- Used by both AJAX and cron paths

### 3. Refactored Controllers

#### `modules/ramos/controllers/Automation.php`
**Changes**:
- `run()` method now calls `ramos_execute_automation(get_staff_user_id())`
- Keeps all permission checks and AJAX validation
- Removed duplicate `_merge_quantities()` method (moved to helper)
- `analyze()` method updated to use `_ramos_merge_quantities()` helper

#### `modules/ramos/controllers/Settings.php` (NEW)
- Settings page for managing automation schedule
- Methods:
  - `automation_schedule()` - Display and handle settings form
  - `_save_automation_schedule_settings()` - Process form submission
- Validates:
  - Schedule hours (0-23)
  - Max stops (1-100)
  - Time format (HH:MM)

### 4. Cron Hook Handler
**File**: `modules/ramos/ramos.php`
**Added Hook**: `hooks()->add_action('after_cron_run', 'ramos_scheduled_automation_and_routes');`

**Function**: `ramos_scheduled_automation_and_routes($manually = false)`
- Called by system cron at each configured hour
- Checks if conditions are met before executing
- If automation succeeds:
  - Calls `ramos_generate_routes_for_today()`
  - Updates automation run with `routes_generated_count`
  - Sets `ramos_last_automation_run_date = today`
- Logs all events to activity log with `[RAMOS CRON]` prefix
- Skips route generation if automation fails

### 5. Admin Settings UI
**File**: `modules/ramos/views/settings/automation_schedule.php`
**Features**:
- Toggle: Enable scheduled automation
- Multi-select: Schedule hours (8-23, formatted as "08:00 (AM)")
- Display: Last automation run date
- Toggle: Auto-generate routes on success
- Input: Default max stops per route (1-100)
- Input: Default route name prefix
- Input: Default route start time
- Submit: Saves via AJAX to Settings controller

### 6. Model Updates
**File**: `modules/ramos/models/Automation_model.php`
**New Method**:
- `update_run_routes($runId, $routesCount)` - Updates `routes_generated_count` after route generation

### 7. Language Strings
**File**: `modules/ramos/language/english/ramos_lang.php`
**Added Strings** (13 new keys):
- Settings page titles and descriptions
- Form labels and help text
- Saved/error messages

## Configuration Options

All stored in `wp_options` table (prefixed with `ramos_`):

| Option | Type | Default | Purpose |
|--------|------|---------|---------|
| `automation_schedule_enabled` | string (0/1) | 0 | Enable/disable scheduled automation |
| `automation_schedule_hours` | JSON array | [8,14,18] | Hours to run automation (0-23) |
| `default_max_stops` | string (int) | 10 | Max customers per route |
| `default_route_prefix` | string | Route | Route name prefix |
| `route_generate_on_success` | string (0/1) | 1 | Auto-generate routes after success |
| `default_route_start_time` | string (HH:MM:SS) | 08:00:00 | Route start time |
| `last_automation_run_date` | string (YYYY-MM-DD) | empty | Tracks daily execution |

## Usage Flow

### 1. Initial Setup
- Admin navigates to Ramos → Settings → Automation Schedule
- Configures automation hours (e.g., 8, 14, 18)
- Sets route parameters (max stops, prefix, start time)
- Enables scheduled automation
- Clicks Save

### 2. System Cron Execution
Each hour (triggered by external cron/scheduler):
```bash
curl "https://yoursite.com/cron/APP_CRON_KEY"
```

The cron system calls all `after_cron_run` hooks, including:
```php
ramos_scheduled_automation_and_routes()
```

### 3. Automation Execution
If conditions are met (enabled, correct hour, not already today):
- `ramos_execute_automation(0)` runs as system (run_by_id = 0)
- Purchase orders generated from unprocessed orders
- Orders marked as processed
- Automation run record created

### 4. Route Generation (on success)
Immediately after automation completes:
- `ramos_generate_routes_for_today()` runs
- Routes created for today using configured parameters
- Route count updated in automation run record
- `ramos_last_automation_run_date` set to today

### 5. Manual Trigger (unchanged)
Users can still manually trigger automation from Automation dashboard:
- Goes through `Automation::run()` → `ramos_execute_automation(staff_id)`
- Works exactly as before with full permission checks
- Marked as `cron_scheduled = 0` in run record

## Permission Model

- **Cron runs**: System-level execution (run_by_id = 0), no permission checks
- **Manual runs**: Require `create` permission on ramos module
- Settings page: Requires `edit` permission on ramos module
- All runs tracked with audit flag (`cron_scheduled`)

## Duplicate Prevention

- Checks `ramos_last_automation_run_date` option
- Only runs if date !== today's date
- Automatically reset when automation succeeds
- Prevents multiple automation runs same day even if cron called multiple times

## Logging

All automated actions logged to activity table with `[RAMOS CRON]` prefix:
- Automation success/failure with order/batch counts
- Route generation success/failure with route count
- Errors with exception messages

## Error Handling

1. **Automation fails**:
   - Automation run marked as `failed`
   - Route generation skipped
   - Error logged with message
   - Manual retry still possible from dashboard

2. **Route generation fails**:
   - Error logged separately
   - Automation still marked successful
   - Can retry manual route generation

3. **Configuration missing/invalid**:
   - Uses sensible defaults
   - Logs warnings
   - Automation still attempts to run

## Testing Recommendations

1. **Enable automation**: Set `ramos_automation_schedule_enabled = 1`
2. **Set test hour**: Set `ramos_automation_schedule_hours = [current_hour]`
3. **Create test data**: Add unprocessed orders
4. **Trigger cron**: `curl /cron/APP_CRON_KEY`
5. **Verify**:
   - Check automation runs in Automation dashboard
   - Verify routes generated for today
   - Check activity log for `[RAMOS CRON]` entries
   - Verify `routes_generated_count` in automation run record

## Future Enhancements

1. **Configurable delays**: Add delay between automation and route generation
2. **Per-zone scheduling**: Different schedules for different zones
3. **Retry logic**: Auto-retry failed automations
4. **Performance**: Background job queue for large order volumes
5. **Notifications**: Send notifications on automation completion
6. **Webhooks**: Trigger external systems on automation events

## API Integration

The refactored code enables easy integration with external systems:

```php
// From any controller/model
$this->load->helper('ramos/ramos_automation');

// Execute automation programmatically
$result = ramos_execute_automation($staff_id);
if ($result['success']) {
    $routes = ramos_generate_routes_for_today();
}
```

## Notes

- Automation runs as "system" user (run_by_id = 0) when triggered by cron
- Manual runs retain staff user ID for accountability
- Cron scheduling is controlled by external system (cron/scheduler)
- This implementation only provides the callback hooks and configuration
- System must have `/cron/APP_CRON_KEY` endpoint accessible for cron triggers
