## Agent Instruction: Ramos-php vs ERP comparison

### Environment / Runtime
- OS kernel: `linux 6.17.0-19-generic`
- Shell: `bash`
- Today: `Wednesday Mar 25, 2026`

### Workspace Repos (must use these exact roots)
- Repo A (Ramos): `/var/www/ramos-php`
- Repo B (ERP): `/home/usama-skakeel/Downloads/erp-staging-rev9/erp`
- Both are git repos.

### Initial git status snapshot (at conversation start)
- Repo A (`/var/www/ramos-php`) had these modified files:
  - `application/config/app-config.php`
  - `application/config/hooks.php`
  - `application/helpers/assets_helper.php`
  - `application/helpers/core_hooks_helper.php`
  - `application/helpers/fields_helper.php`
  - `application/hooks/InitModules.php`
  - `index.php`
  - `modules/ramos/language/english/ramos_lang.php`
  - `modules/ramos/views/picking/manage.php`
  - `modules/surveys/surveys.php`
  - `modules/whatsapp/whatsapp.php`
- Repo A also had untracked files:
  - `package-lock.json`
  - `postcss.config.js`
- Repo B (`/home/usama-skakeel/Downloads/erp-staging-rev9/erp`) had only:
  - `application/config/app-config.php`

### Recently viewed / open files (context)
- Repo A open in editor:
  - `application/config/app-config.php`
- Recently viewed (Repo A):
  - `u447461315_ramos.compat.sql`
  - `application/helpers/core_hooks_helper.php`
  - `system/core/Benchmark.php`

### Repo comparison results (hash-based, whole-tree)
How this was produced:
- Content hashing: `sha256` of every file (excluding only `.git`)
- Counted: all regular files under repo roots

Measured stats:
- Files in Repo A: `34199`
- Files in Repo B: `17150`
- Missing in Repo B (present only in Repo A): `18263`
- Missing in Repo A (present only in Repo B): `1214`
- Same-path but different content (different size/hash): `34`

Different-content files (34):
1. `application/config/app-config.php`
2. `application/config/constants.php`
3. `application/config/hooks.php`
4. `application/config/migration.php`
5. `application/controllers/Clients.php`
6. `application/helpers/assets_helper.php`
7. `application/helpers/core_hooks_helper.php`
8. `application/helpers/fields_helper.php`
9. `application/helpers/general_helper.php`
10. `application/helpers/sales_helper.php`
11. `application/hooks/InitModules.php`
12. `application/vendor/autoload.php`
13. `application/vendor/composer/InstalledVersions.php`
14. `application/vendor/composer/autoload_classmap.php`
15. `application/vendor/composer/autoload_real.php`
16. `application/vendor/composer/autoload_static.php`
17. `application/vendor/composer/installed.php`
18. `assets/builds/app.js`
19. `assets/builds/tailwind.css`
20. `index.php`
21. `modules/ramos/controllers/Automation.php`
22. `modules/ramos/controllers/Purchases.php`
23. `modules/ramos/controllers/Ramos_testing.php`
24. `modules/ramos/language/english/ramos_lang.php`
25. `modules/ramos/models/Automation_model.php`
26. `modules/ramos/models/Purchase_model.php`
27. `modules/ramos/models/Routes_model.php`
28. `modules/ramos/ramos.php`
29. `modules/ramos/views/picking/manage.php`
30. `modules/ramos/views/purchases/batch.php`
31. `modules/surveys/surveys.php`
32. `modules/whatsapp/updates.php`
33. `modules/whatsapp/whatsapp.php`
34. `webpack.mix.js`

### Notes about “full diff list”
The “missing” and “different content” lists include build artifacts and vendor files.
If the user wants only application-level differences, prefer excluding:
- `application/vendor/**`
- `modules/**/vendor/**`
- `assets/builds/**` (and optionally `webpack.mix.js` / other build outputs)

### User intent guidance
- When the user says “compare rampos-php to erp”, treat:
  - Repo A = `/var/www/ramos-php`
  - Repo B = `/home/usama-skakeel/Downloads/erp-staging-rev9/erp`

