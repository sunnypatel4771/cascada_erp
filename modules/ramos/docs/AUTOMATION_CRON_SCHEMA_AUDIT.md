# Ramos Automation Cron - Schema Audit

## Cron job used by automation

The automation at `/admin/ramos/automation/settings` does **not** run via a separate endpoint.
It runs from the global app cron hook:

- `php index.php cron`

Ramos listens to `after_cron_run` and executes `ramos_maybe_run_scheduled_automation()`.

Recommended system crontab:

```cron
*/5 * * * * cd /var/www/ramos-php && /usr/bin/php index.php cron >/dev/null 2>&1
```

## Schema audit scope

Checked tables/columns required by:

- `modules/ramos/helpers/ramos_automation_helper.php`
- `modules/ramos/models/Automation_model.php`
- `modules/ramos/models/Ramos_purchase_model.php`
- `modules/ramos/migrations/001_add_scheduled_automation_config.php`

## Audit result

### Present

- `tblcart`: `processed_for_purchase`, `processed_at`, `automation_run_id`
- `tblinvoices`: `processed_for_purchase`, `automation_run_id`, `priority`, `zone`
- `tblramos_automation_runs` base columns (`run_type`, `status`, `run_by`, `run_at`, etc.)
- `tblramos_purchase_batches`
- `tblramos_purchase_batch_items`
- `tblramos_suppliers.priority`
- `tblinventory_manage`, `tblitems`, `tblitemable`
- schedule option keys:
  - `ramos_automation_schedule_enabled`
  - `ramos_automation_schedule_run_daily`
  - `ramos_automation_schedule_date`
  - `ramos_automation_schedule_hour`
  - `ramos_automation_schedule_minutes`
  - `ramos_automation_schedule_end_hour`
  - `ramos_automation_schedule_end_minutes`
  - `ramos_automation_schedule_hours`

### Missing (fixed)

- `tblramos_automation_runs.cron_scheduled`
- `tblramos_automation_runs.routes_generated_count`
- `tbloptions` record `ramos_last_automation_run_date`

## Fix script

Created SQL fix script:

- `modules/ramos/docs/automation_schema_fixes.sql`

Applied to DB `ranos-php` on audit run date.
