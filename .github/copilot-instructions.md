# AI Coding Agent Instructions - Perfex CRM/ERP

## Architecture Overview

This is a **Perfex CRM fork** built on **CodeIgniter 3.1.11** using **HMVC (Hierarchical Model-View-Controller)** via MX (Modular Extensions).

```
index.php (bootstrap)
  ↓
system/core/CodeIgniter.php (CI3 core)
  ↓
App_Router (extends MX_Router) routes to modules or main app
  ↓
Controllers → Models → Views
```

### Key Architectural Layers

1. **System Core** (`system/` - CI3): Unmodified CodeIgniter framework (v3.1.11). Do not edit directly.

2. **Application Core** (`application/core/`): App-level overrides extending CI classes:
   - `App_Controller`: Base for all controllers; handles auth, locale, autoload models
   - `App_Model`: Base for models; sets timezone, DB reconnect on each instance
   - `App_Loader`: Extends MX_Loader with custom view-override logic for themes
   - `App_Router`: Minimal wrapper extending MX_Router for HMVC routing
   - `App_Security`, `App_Session`, `App_Input`: Additional overrides

3. **HMVC Modules** (`modules/<module>/`): Self-contained feature modules with own controllers, models, views, helpers, libraries. Examples: `warehouse`, `purchase`, `ramos`, `omni_sales`, `accounting`. Each module can have migrations in `modules/<module>/migrations/`.

4. **Configuration** (`application/config/`):
   - `app-config.php` (CRITICAL): DB credentials, base URL, encryption key. Must exist; app fails without it.
   - `config.php`: CI config, modules locations, PHP version check (≥ 8.1)
   - `database.php`: Built from `app-config.php` constants
   - `migration.php`: Sets target version (currently 332 for v3.3.2)

---

## Critical File Locations & Patterns

### Entry Points
- **`index.php`**: Front controller; must remain at repo root
- **`application/config/app-config.php`**: Must define `APP_BASE_URL`, `APP_DB_*` constants, `APP_ENC_KEY`, `SESS_DRIVER`, `SESS_SAVE_PATH`

### Core Extension Points
- **`application/core/App_Controller.php`**: Add shared controller logic, auth checks, locale loading
- **`application/core/App_Model.php`**: Add shared model behaviors (timezone, DB reconnect)
- **`application/libraries/App.php`**: System-wide utility methods, version checks, hooks dispatcher
- **`application/config/routes.php`**: Route overrides and custom mappings

### Database & Migrations
- **Migrations**: Store in `application/migrations/` (sequential format: `001_*.php`, `002_*.php`, etc.)
- **Module migrations**: Store in `modules/<module>/migrations/` (same format)
- **Target version**: Set in `application/config/migration.php` → `$config['migration_version']`
- **Runners**: 
  - Web/UI: `application/controllers/Migration.php` (admin interface)
  - CLI: `php index.php migrate` or programmatic via `$this->migration->current()`
  - Auto: Migration runs on app init if `$config['migration_auto_latest'] = true`

### Database Naming
- **Table prefix**: `tbl` (via `db_prefix()` helper). Table names: `tblclients`, `tblsessions`, `tblinvoices`, etc.
- **Charset**: `utf8mb4` and `utf8mb4_unicode_ci`
- **Sessions table**: `tblsessions` (required if `SESS_DRIVER = 'database'`); create via SQL or migration

---

## Developer Workflows

### Setup
```bash
# 1. Install PHP dependencies
composer install --working-dir=application

# 2. Install Node/NPM dependencies and build frontend assets
npm install
npm run dev              # development with source maps
npm run production       # minified for production

# 3. Database setup (manual)
mysql -u root -p erp < u447461315_ramos.sql  # import dump
# OR
mysql -u root -p erp < create-sessions-table.sql  # sessions only

# 4. Verify config
# - Check application/config/app-config.php exists and has correct DB credentials
# - Set APP_BASE_URL to your host (e.g., http://localhost)

# 5. Run app
php -S 0.0.0.0:8080 -t .  # Quick test server
# OR use Apache/Nginx with rewrite rules (.htaccess for Apache)
```

### Running Migrations
```bash
php index.php migrate                          # runs to target version in config
php index.php migrate --version=330            # rollback/forward to v330
php index.php migrate --module=ramos           # module-specific migration
```

### Building Frontend
```bash
npm run watch           # watch mode, rebuilds on file change
npm run watch-poll      # for environments that don't support inotify (Docker)
npm run dev             # single build
npm run production      # minified, production-ready
```

### Debugging
- **Logs**: `application/logs/` (CI writes errors here)
- **Log threshold**: Adjust `$config['log_threshold']` in `application/config/config.php` (0=off, 1=errors, 2=debug, 3=info, 4=all)
- **Sessions issue**: If "table tblsessions doesn't exist", either:
  - Switch to file-based sessions: `define('SESS_DRIVER', 'files');`
  - Import `create-sessions-table.sql` or run migration
- **Missing Composer/NPM**: Run `composer install --working-dir=application` or `npm install`

---

## Code Patterns & Conventions

### Controller Pattern
```php
// File: application/controllers/MyController.php or modules/<module>/controllers/My.php
class MyController extends App_Controller {  // App_Controller extends CI_Controller
    public function __construct() {
        parent::__construct();
        $this->load->model('my_model');
    }
    
    public function index() {
        // Auth check (handled by App_Controller hooks)
        $data['items'] = $this->my_model->get_all();
        $this->load->view('my_view', $data);
    }
}
```

### Model Pattern
```php
// File: application/models/My_model.php
class My_model extends App_Model {  // App_Model extends CI_Model
    public function get_all() {
        return $this->db->get('mytable')->result_array();
    }
}
```

### Cross-Module Loading
```php
// In any controller/model, load another module's model:
$this->load->model('warehouse/warehouse_model');
$this->warehouse_model->method();

// Or helper:
$this->load->helper('warehouse/warehouse_helper');
```

### Database Queries
- Always use **prepared statements** with `$this->db->where()`, `$this->db->get()`, etc. (CI's query builder auto-escapes)
- Never concatenate user input into raw SQL
- Use `db_prefix()` helper for table names: `$this->db->get(db_prefix().'invoices')`

### Views & Theme Overrides
- **View lookup**: App searches `application/views/` then module paths via `modules_locations`
- **Theme override**: Place overrides in `application/views/my_` prefix (handled by `App_Loader`)
- Example: Override `warehouse/views/list.php` → create `application/views/my_list.php`

### Localization (i18n)
- **Language files**: `application/language/` (default) or `modules/<module>/language/`
- **Function**: Use `_l('language_key')` to load language strings
- **Load language**: `$this->load->language('mymodule')` auto-loads from current locale folder
- **Locales**: English (`en`), others defined in DB; set via `GLOBALS['locale']` in `App_Controller`

### Hooks & Filters
- **Hooks system**: Custom hook dispatcher (not CI's hooks). Use `hooks()->do_action()` and `hooks()->apply_filters()`
- **Common hooks**: `pre_controller_constructor`, `app_init`, `app_view_data`, `before_update_database`
- **Example**: 
  ```php
  hooks()->do_action('my_custom_action', $data);
  $modified = hooks()->apply_filters('my_filter', $value);
  ```

---

## Module Example: `ramos` (Routes/Delivery Management)

Located in `modules/ramos/`:
- **Controllers**: `modules/ramos/controllers/Ramos.php` (main dashboard), others for features
- **Models**: `modules/ramos/models/Dashboard_model.php`, `Notifications_model.php`, etc.
- **Views**: `modules/ramos/views/` (HTML/Smarty templates)
- **Libraries**: `modules/ramos/libraries/` (e.g., routing algorithms, integrations)
- **Helpers**: `modules/ramos/helpers/` (utility functions for routes)
- **Migrations**: `modules/ramos/migrations/` (schema changes specific to ramos)

**Access pattern**: `/ramos` routes to `Ramos` controller, `/ramos/dashboard` to dashboard action.

---

## Important Configuration Constants

| Constant | Location | Purpose |
|----------|----------|---------|
| `APP_BASE_URL` | `app-config.php` | Base URL for the app |
| `APP_DB_HOSTNAME` | `app-config.php` | Database host |
| `APP_DB_NAME` | `app-config.php` | Database name |
| `APP_ENC_KEY` | `app-config.php` | Encryption key (keep secret) |
| `SESS_DRIVER` | `app-config.php` | `'database'` or `'files'` |
| `SESS_SAVE_PATH` | `app-config.php` | Path for file sessions or table name |
| `RAMOS_MODULE_NAME` | Module constant | Module identifier (e.g., 'ramos') |
| `APP_MINIMUM_REQUIRED_PHP_VERSION` | `config.php` | Enforced PHP version (8.1) |

---

## Common Tasks & Search Tokens

### Find Code Fast
- **All controllers**: Search `extends App_Controller` or `extends AdminController`
- **All models**: Search `extends App_Model`
- **Module routing**: Search `modules_locations` in `config.php`
- **Database config**: Search `APP_DB_` in `app-config.php`
- **Autoload classes**: Search `$autoload['libraries']` in `application/config/autoload.php`

### Add Feature to Module
1. Create controller in `modules/<module>/controllers/`
2. Create model in `modules/<module>/models/`
3. Create views in `modules/<module>/views/`
4. If schema needed: Add migration in `modules/<module>/migrations/`
5. Register permissions/menu in module setup/install file

### Change Database Schema
1. Create migration file: `application/migrations/XXX_description.php`
2. Implement `up()` and `down()` methods
3. Update `migration.php` → `$config['migration_version']` to new number
4. Run: `php index.php migrate`

### Override Default Behavior
- **Auth/menu/locale**: Modify `application/core/App_Controller.php`
- **Model defaults**: Modify `application/core/App_Model.php`
- **View rendering**: Modify `application/core/App_Loader.php`
- **Routing**: Modify `application/config/routes.php` or `App_Router`

---

## Testing & Validation

### Check Setup
```bash
php -S 0.0.0.0:8080 -t .  # Start dev server
# Visit http://localhost:8080/index.php
# Should load dashboard or redirect to login
```

### Run Migrations Check
```bash
php index.php migrate                    # Should complete without errors
# Check application/logs/ for any issues
```

### Verify SQL
```sql
-- Check session table exists
SHOW TABLES LIKE '%sessions%';

-- Check DB version
SELECT * FROM tblmigrations;

-- Verify encoding
SHOW CREATE TABLE tblclients;
```

### Debug Performance
- Enable CI profiler in `App_Controller::__construct()`: `$this->output->enable_profiler(TRUE);`
- Check query count and execution time in browser output

---

## Security Notes

- **Never** commit `application/config/app-config.php` with real credentials to public repos
- **Always** use prepared statements (CI query builder handles this)
- **Validate** user input via `$this->input->get()`, `$this->input->post()` (CI auto-escapes)
- **Check permissions** in controllers via `staff_can()` or `user_can()` helpers
- **Encryption key** (`APP_ENC_KEY`) must be random and kept secret

---

## Key Files to Know

| Path | Purpose |
|------|---------|
| `index.php` | Bootstrap, entry point |
| `application/core/App_Controller.php` | Base controller with auth, locale, hooks |
| `application/core/App_Model.php` | Base model with DB setup |
| `application/config/app-config.php` | CRITICAL: DB and runtime config |
| `application/config/config.php` | CI core config, modules locations |
| `application/migrations/` | Core database schema history |
| `modules/ramos/` | Example feature module (routes/delivery) |
| `application/libraries/App.php` | System-wide utilities and version management |
| `application/libraries/App_Migration.php` | Migration runner |
| `README-DEVELOPER.md` | Detailed developer documentation |
| `DOCUMENTATION_INDEX.md` | Index of all docs (ERP orders/routes implementation) |

---

## Third-Party Integrations & Gateways

### Payment Gateways
Located in `application/controllers/gateways/`:
- **Stripe** (`Stripe.php`, `Stripe_ideal.php`): Credit card, iDEAL payments via Stripe SDK
- **PayPal** (`Paypal.php`, `Paypal_checkout.php`): PayPal Standard and Checkout flows
- **2Checkout** (`Two_checkout.php`): 2Checkout payment processor
- **Braintree** (`Braintree.php`): Braintree payment gateway
- **Mollie** (`Mollie.php`): European payment processor
- **Payu Money** (`Payu_money.php`): Indian payment gateway
- **Authorize.net** (`Authorize_acceptjs.php`): Authorize.net AcceptJS
- **Instamojo** (`Instamojo.php`): Indian payment gateway

**Payment Gateway Libraries**:
- `application/libraries/Stripe_core.php` - Base Stripe integration
- `application/libraries/Stripe_subscriptions.php` - Recurring billing via Stripe
- Gateways follow the pattern: detect callback → validate → update DB → redirect

### WhatsApp Integration
Located in `modules/whatsapp/`:
- **WhatsappLibrary** (`libraries/WhatsappLibrary.php`): Main WhatsApp Cloud API wrapper
- **Sms_whatsapp_gateway** (`libraries/Sms_whatsapp_gateway.php`): SMS gateway implementation extending `App_sms`
- **Whatsapp_aeiou** (`libraries/Whatsapp_aeiou.php`): Alternative WhatsApp provider integration
- **Models**: `whatsapp_interaction_model.php` stores message history
- Uses **Guzzle HTTP** client for API calls

**Pattern**: Gateway classes extend `App_sms` and implement `send($number, $message)` method.

### OpenAI Integration
Located in `modules/openai/`:
- **Fine_tuner** (`libraries/Fine_tuner.php`): OpenAI fine-tuning and API calls
- Used for AI-powered features (content generation, analysis)
- Check module's `composer.json` for OpenAI SDK dependencies

### omni_sales Module
Located in `modules/omni_sales/`:
- Multi-channel sales integration (e.g., marketplace orders, portal orders)
- Follows same HMVC pattern as other modules
- Models integrate with order, invoice, and payment systems

### Payment Callback Handling
Payment callbacks/webhooks typically:
1. Verify signature/authenticity from payment provider
2. Update order/invoice status in DB
3. Log transaction in `tblpayments`
4. Trigger notifications (email, SMS, WhatsApp)
5. Fire hooks like `payment_received` for custom logic

**Key tables**: `tblpayments`, `tblpayment_attempts`, `tblinvoices`, `tblorders`

---

## Deployment on Hostinger (File Manager)

Since you're deploying via Hostinger file manager:

### Pre-Deployment Steps (Local)
```bash
# 1. Build all frontend assets locally
npm install
npm run production  # Minified, optimized for production

# 2. Remove unnecessary files before upload
rm -rf node_modules/           # Don't upload npm packages
rm -rf application/logs/*      # Clear local logs
rm application/config/app-config.php  # Don't commit secrets
```

### Hostinger Setup via File Manager
1. **Upload project files** to public_html (or your domain root)
2. **Create database**:
   - Via Hostinger cPanel → MySQL Databases
   - Record credentials (host, name, user, password)
3. **Create/edit `application/config/app-config.php`** (copy from `app-config-sample.php`):
   ```php
   define('APP_BASE_URL', 'https://yourdomain.com/');
   define('APP_DB_HOSTNAME', 'your-mysql-host');
   define('APP_DB_USERNAME', 'db-user');
   define('APP_DB_PASSWORD', 'db-password');
   define('APP_DB_NAME', 'db-name');
   define('APP_ENC_KEY', 'random-32-char-string');
   define('SESS_DRIVER', 'database');
   define('SESS_SAVE_PATH', db_prefix().'sessions');
   ```
4. **Import database schema**:
   - Via cPanel → phpMyAdmin
   - Import `u447461315_ramos.sql` to initialize schema
   - Or run `create-sessions-table.sql` for sessions-only setup
5. **Verify via browser**:
   - Visit `https://yourdomain.com/` → should load app or redirect to login
   - Check `application/logs/` via file manager for any PHP errors

### Hostinger File Manager - Common Tasks
- **Edit config**: Right-click `app-config.php` → Edit Code
- **Check logs**: Navigate to `application/logs/` → Download/view error logs
- **Update code**: Upload new files via drag-drop to replace old ones
- **Run migrations** (if needed via web interface): 
  - If app has admin migration tool, use it: `/admin/tools/migrations` or similar
  - Otherwise, import SQL dump during setup

### Hostinger Limitations & Workarounds
- **No CLI access** (no `php index.php` commands): Migrations must be run via web UI or SQL imports
- **File uploads via browser** can be slow for large files (>50MB): Compress and split if needed
- **PHP version**: Check Hostinger cPanel → Select PHP version (ensure ≥ 8.1)
- **Extensions**: Ensure `mysqli`, `mbstring`, `curl`, `json`, `gd` are enabled (usually default)
- **Permissions**: File manager handles permissions automatically; focus on folder structure

### After Deployment - Verify
```
✓ App loads at https://yourdomain.com/
✓ Login page displays correctly
✓ DB connection works (check application/logs/ for errors)
✓ Sessions table exists: phpMyAdmin → tblsessions
✓ Assets load (CSS, JS not 404)
✓ No "Table tblsessions doesn't exist" errors
```

---

## When in Doubt

1. Read `README-DEVELOPER.md` for detailed guidance
2. Search codebase for similar patterns using search tokens above
3. Check `application/logs/` for runtime errors
4. Review migrations in `application/migrations/` to understand schema
5. Look at working modules (e.g., `warehouse`, `ramos`) for code examples
6. Run `php index.php migrate` to ensure DB is up-to-date (local development only)
7. For Hostinger deployments, verify DB credentials and permissions via cPanel
