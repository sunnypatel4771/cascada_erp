**Project Overview**
- **Type:**: Perfex CRM / ERP fork built on CodeIgniter (HMVC)
- **Primary framework:**: CodeIgniter (CI) core in `system/` (CI3-style layout)
- **Modular system:**: HMVC Modular Extensions (MX) via `application/core/App_Router` and `modules/` folder
- **Languages & Tools:**: PHP (>= 8.1), MySQL/MariaDB, Composer (PHP deps), Node/NPM (frontend builds with Laravel Mix and Tailwind/Vue)

**Quick Facts**
- **Base entry:**: `index.php`
- **App config:**: `application/config/app-config.php` (APP_BASE_URL, DB credentials, encryption key)
- **Main CI config:**: `application/config/config.php`
- **Database config:**: `application/config/database.php` (reads constants from `app-config.php`)
- **Modules path:**: `modules/` (registered in `config.php` via `modules_locations`)

**How this README is organized (for humans and AIs)**
- Overview & architecture
- File and folder map (top-level + `application/` + `modules/`)
- Database & sessions
- Build & run instructions (Docker & manual)
- Developer workflows (search patterns, where to edit, migrations)
- Debugging & common pitfalls
- AI-friendly pointers (entry points, key classes and search tokens)

**Architecture Summary**
- CodeIgniter core lives in `system/` and is unmodified for the most part.
- `application/core/` contains core app extensions: `App_Controller`, `App_Model`, `App_Loader`, `App_Router`, `App_Session` etc. These wrap or extend CI behaviors and are the right place to look for app-level overrides.
- `modules/` holds HMVC modules. Each module typically has `controllers/`, `models/`, `views/`, `helpers/`, `libraries/`, and `language/` folders.
- The app follows the classic CI request flow: `index.php` -> `system/core/CodeIgniter.php` -> Router -> Controller -> Model -> View.

**Folder Map (top-level)**
- `index.php`: CI front-controller.
- `Dockerfile`, `docker-compose.yml`, `entrypoint.sh`: containerization helpers (if you want to use Docker).
- `application/`: main app code (detailed below).
- `system/`: CodeIgniter framework source.
- `modules/`: HMVC modules (feature modules: `accounting`, `purchase`, `warehouse`, `omni_sales`, `ramos`, `whatsapp`, `openai`, ...).
- `assets/`, `builds/`, `uploads/`: public assets and uploaded files.
- `u447461315_ramos.sql`, `create-sessions-table.sql`: DB dumps / utility SQL.

**Important files inside `application/`**
- `application/config/app-config.php`: app constants (must be present). Key values:
  - `APP_BASE_URL` (base URL)
  - `APP_ENC_KEY` (encryption key)
  - DB constants: `APP_DB_HOSTNAME`, `APP_DB_USERNAME`, `APP_DB_PASSWORD`, `APP_DB_NAME`
  - `SESS_DRIVER` and `SESS_SAVE_PATH` (session handling)
- `application/config/config.php`: CI config (autoload, composer autoload, encryption key is read from `app-config.php`). Also sets `APP_MINIMUM_REQUIRED_PHP_VERSION`.
- `application/config/database.php`: builds `$db['default']` using `APP_DB_*` constants.
- `application/config/routes.php`: URL -> controller routing and custom routes.
- `application/core/`: custom core classes for this app (`App_Controller`, `App_Model`, `App_Router`, `App_Loader`, etc.). Modify cautiously.
- `application/controllers/`: controllers used by the app (and `application/controllers/admin/` for admin area).
- `application/models/`: core models.
- `application/libraries/`: app-specific libraries and wrappers (e.g., `App_email`, `App_modules`, session overrides, gateway adapters).
- `application/migrations/`: DB migrations used by the app. They are the canonical source of schema changes.

**Modules**
- Each directory under `modules/` is an HMVC module. Typical structure inside a module `modules/<module>/`:
  - `controllers/` - module controllers
  - `models/` - module models
  - `views/` - module views
  - `helpers/` - helper functions
  - `libraries/` - module-specific libraries and gateways
  - `language/` - translations
- Example modules: `modules/warehouse`, `modules/purchase`, `modules/accounting`, `modules/omni_sales`, `modules/ramos`, `modules/whatsapp`, `modules/openai`.

**Database & Sessions**
- DB engine: MySQL / MariaDB via `mysqli` driver by default.
- Encoding: `utf8mb4` and `utf8mb4_unicode_ci` as configured in `app-config.php` / `database.php`.
- DB prefix: `tbl` by default via `db_prefix()` helper. Table names like `tblsessions`, `tblclients`, etc.
- Sessions:
  - Default driver: `SESS_DRIVER` = `database` (see `application/config/app-config.php`).
  - Default session table: `SESS_SAVE_PATH` = `db_prefix().'sessions'` -> typically `tblsessions`.
  - If DB sessions are enabled and `tblsessions` is missing, the app will throw exceptions (there are logs showing this). Create the table by importing `create-sessions-table.sql` or run the migrations that create it (`application/migrations/122_version_122.php` etc.).

**Where features live and how they connect**
- Controllers: `application/controllers/` and `modules/<module>/controllers/`.
- Models: `application/models/` and `modules/<module>/models/`.
- Views: `application/views/` and `modules/<module>/views/`.
- Helpers & Libraries: loadable with `$this->load->helper('name')` and `$this->load->library('name')`. Module-specific ones live in module folders and are auto-loaded when the module runs.
- Modules communicate through:
  - Standard model calls and helpers
  - Shared libraries (e.g., `App_modules`, `App_mailer`)
  - Merge fields and language files (modules provide their own merge fields and language tokens)
  - Hooks defined in `application/hooks/` or `application/config/hooks.php` if present

**Search tokens & entry points (AI-friendly)**
- To quickly find important code paths search for these identifiers:
  - `App_Controller`, `App_Model`, `App_Loader` — base classes to inspect for app behavior.
  - `index.php` and `system/core/CodeIgniter.php` — request bootstrap.
  - `modules_locations` (in `config.php`) — where modules are mounted.
  - `SESS_DRIVER`, `SESS_SAVE_PATH`, `tblsessions` — session behavior.
  - `application/migrations/` — DB change history.
  - `application/config/routes.php` — route rules.
  - `$this->load->model(` and `$this->load->library(` — runtime dependencies.

**Developer setup & run (two approaches)**
Note: pick either Docker-based setup (recommended if you want the repo's intended environment) or manual local setup.

1) Docker (recommended if `docker-compose.yml` is complete for this project)
- Start services:
```bash
docker-compose up -d
```
- Install PHP composer packages (inside the container or locally):
```bash
# from repo root, use the application composer.json
composer install --working-dir=application
```
- Install Node packages and build assets (locally or in a node container):
```bash
npm install
npm run dev   # development
# or for production assets
npm run production
```
- If Docker provides a `php` container you can exec into it to run composer/npm or run commands via `docker-compose exec php bash`.

2) Manual (host) environment
- Requirements:
  - PHP >= 8.1 (the application explicitly checks and requires >= 8.1)
  - PHP extensions: `mysqli`, `mbstring`, `curl`, `json`, `openssl`, `gd`/`imagick` (for images), `zip`, `pdo` etc.
  - MySQL / MariaDB
  - Composer & Node/NPM
- Steps:
```bash
# 1. Set up DB: create database (name should match `APP_DB_NAME` in app-config.php)
mysql -u root -p
CREATE DATABASE erp_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
# 2. Import sample/full dataset if desired:
mysql -u root -p erp_staging < u447461315_ramos.sql
# 3. Ensure sessions table exists (if not importing a dump):
mysql -u root -p erp_staging < create-sessions-table.sql
# 4. Install PHP deps:
composer install --working-dir=application
# 5. Install JS deps and build
npm install
npm run dev
# 6. Set `APP_BASE_URL` in `application/config/app-config.php` to your host (e.g., http://localhost)
# 7. Serve the app (with your webserver or PHP built-in for quick tests):
php -S 0.0.0.0:8080 -t .
```

**Running migrations & schema**
- The app stores migrations in `application/migrations/`. There is also an administrative migration controller `application/controllers/Migration.php` (inspect it). Two ways to apply migrations:
  - Web/UI: if the app provides an admin migration tool, use it (login and check `admin/migrations` or `admin/tools`).
  - CLI: use the CI CLI entry point if configured for migrations. If unsure, import the SQL dump `u447461315_ramos.sql` or run the provided `create-sessions-table.sql` for sessions.
- Important migrations to note:
  - `application/migrations/122_version_122.php` — creates `tblsessions` if necessary.
  - `application/migrations/127_version_127.php`, `128`, `231` — handle session naming and config updates. Inspect files directly before running.

**Common debugging & troubleshooting**
- Fatal error: "Table '...tblsessions' doesn't exist": ensure `tblsessions` exists or switch `SESS_DRIVER` to `files` in `application/config/app-config.php`:
  - `define('SESS_DRIVER', 'files');` and `define('SESS_SAVE_PATH', NULL);` (only as temporary fix).
- Missing Composer packages: run `composer install --working-dir=application`.
- Frontend broken/not loading: run `npm install` and `npm run dev` (or `npm run production` for minified assets).
- 500 / blank pages: check `application/logs/` for logged errors (CI writes runtime logs there). Adjust `$config['log_threshold']` in `application/config/config.php` for more verbosity in development.
- PHP version check: `application/config/config.php` enforces `APP_MINIMUM_REQUIRED_PHP_VERSION` = `8.1`.

**Security & secrets**
- `application/config/app-config.php` contains:
  - `APP_ENC_KEY` (do not leak)
  - `APP_DB_*` credentials
- Never commit production credentials to public repositories. Use environment-specific config files or CI/CD secrets.

**Recommended dev workflow**
- Create a new branch per feature: `git checkout -b feat/your-feature`
- Use migrations to change DB schema; add migration PHP files to `application/migrations/`.
- Keep module changes confined to `modules/<module>/` when possible.
- Use `application/core/App_Controller` and `App_Model` as the base for custom controllers/models for shared behaviors.

**AI-friendly notes (how an LLM or code indexing tool should analyze this repo)**
- Priority scan order for fast understanding:
  1. `index.php` (bootstrap)
  2. `application/core/App_Controller.php`, `App_Model.php`, `App_Loader.php`, `App_Router.php` (app-level overrides)
  3. `application/config/app-config.php`, `config.php`, `database.php` (runtime configuration)
  4. `application/controllers/` (main entry controllers)
  5. `modules/` (feature modules — follow controllers -> models -> views)
  6. `application/migrations/` (DB schema changes)
- Useful static search tokens:
  - `App_Controller`, `App_Model`, `App_Loader`
  - `modules/`, `modules_locations`, `MX_Router`, `MX_Loader`
  - `SESS_DRIVER`, `SESS_SAVE_PATH`, `tblsessions`
  - `$this->load->model(`, `$this->load->library(`, `$this->load->helper(`

**Where to change common behaviors**
- Shared HTTP behavior, auth checks and menu rendering: `application/core/App_Controller.php` or `application/libraries/App_menu.php`.
- DB behavior & utilities: `application/core/App_Model.php` and models in `application/models/`.
- Autoload config: `application/config/autoload.php`.

**Examples: common quick tasks**
- Find where invoices are rendered:
  - Search for `class Invoice` in `application/controllers/` and `modules/*/controllers/`.
- Add a new menu item in admin: inspect `application/libraries/App_menu.php` and module registration code in `application/libraries/App_modules.php`.
- Add a new module: create `modules/<name>/controllers`, `models`, `views`, register language files and migrations.

**Files to inspect first when debugging a feature**
- `application/core/App_Controller.php` — base controller logic
- `application/config/routes.php` — route mappings
- `application/migrations/*` — DB prerequisites
- `modules/<module>/controllers/*` and `modules/<module>/models/*`

**Next recommended tasks I can do for you**
- Create a `README-DEVELOPER.md` (this file is it) and optionally:
  - (1) Add a dev `Makefile` or `scripts/` with handy commands (`composer install`, `npm install`, `import-db`).
  - (2) Run `docker-compose up` here and attempt a verification (I can run commands if you want).
  - (3) Generate a module map (CSV/JSON) listing modules and main controllers for quick navigation.

If you want me to proceed with any of the three optional tasks, tell me which one and I will continue.
