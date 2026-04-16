# Ramos staff permissions (module level)

This project uses Perfex CRM’s staff capability system for the Ramos Operations module. Permissions are **not** split per screen (for example, separate toggles for Inventory vs Routes); a staff member gets the Ramos capability group as a whole.

## Registered capabilities

Defined in [`modules/ramos/ramos.php`](../modules/ramos/ramos.php) in `ramos_register_permissions()`:

| Capability | Typical meaning |
|------------|-----------------|
| `view` | Can open Ramos pages and see the sidebar menu (combined with Perfex “global” view semantics). |
| `create` | Can create new records where the controller checks `staff_can('create', RAMOS_MODULE_NAME)`. |
| `edit` | Can update records and perform actions gated by `staff_can('edit', RAMOS_MODULE_NAME)`. |
| `delete` | Can delete where gated by `staff_can('delete', RAMOS_MODULE_NAME)`. |
| `manage_shifts` | Extra capability when present in your installed `ramos.php` variant (shift-related features). |

`RAMOS_MODULE_NAME` is the string `ramos` (see the same file).

## Where enforcement happens

Controllers under `modules/ramos/controllers/` call `staff_can(..., RAMOS_MODULE_NAME)` in `__construct()` or per action. Examples:

- [`modules/ramos/controllers/Inventory.php`](../modules/ramos/controllers/Inventory.php) — `view` on index; `create` / `edit` / `delete` on mutations.
- [`modules/ramos/controllers/Suppliers.php`](../modules/ramos/controllers/Suppliers.php) — same pattern.

The admin sidebar entries for Ramos are only added if the user passes `staff_can('view', RAMOS_MODULE_NAME)` (see `ramos_init_admin_menu()` in the same `ramos.php` file).

## How administrators assign access

1. Log in as an administrator.
2. Go to **Setup → Staff → Roles** (or edit an individual staff member).
3. Open the **Permissions** / capabilities section and find the group labeled for Ramos (language key `ramos_permission_group`).
4. Enable the capabilities that role should have (`view` is required for any Ramos UI access).

Other modules (for example **Purchase**, **Warehouse**) use their own permission names via `has_permission()` or their module’s registration; they are independent of Ramos unless explicitly integrated.
