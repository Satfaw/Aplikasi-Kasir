# Filament v3 → v4 Upgrade Plan

**Current state**
- `filament/filament`: 3.3.55 (constraint `^3.2`)
- `bezhansalleh/filament-shield`: 3.9.10 (constraint `^3.9`)
- `filament/notifications`: 3.3.55 (constraint `^3.3`) — standalone package (absorbed into the main `filament/filament` package in v4, no longer a separate install)
- `flowframe/laravel-trend`: 0.5.0 — **compatible with Laravel 13**, no change needed
- PHP: 8.4 ✅ (v4 requires PHP 8.2+)
- Laravel: 13.34.0 ✅ (v4 requires Laravel 11.28+)

---

## Section 1 – Composer Changes

### 1a. Install the automated upgrade script (dev dependency, temporary)
```
composer require filament/upgrade:"^4.0" -W --dev
```

### 1b. Run the upgrade script
```
vendor/bin/filament-v4
```
The script will output additional `composer require` commands — run those commands as directed.

### 1c. Final dependency constraints in `composer.json`

| Package | Old constraint | New constraint | Notes |
|---|---|---|---|
| `filament/filament` | `^3.2` | `^4.0` | Core upgrade |
| `bezhansalleh/filament-shield` | `^3.9` | `^4.0` | v4.0.0 released; supports Filament v4 stable. Requires re-running `shield:setup` after update. |
| `filament/notifications` | `^3.3` | **remove entirely** | Absorbed into `filament/filament` core in v4. Keeping it will cause a conflict. |
| `flowframe/laravel-trend` | `^0.5.0` | `^0.5.0` | Already compatible with Laravel 13 — no change needed. |
| `php` | `^8.3` | `^8.2` | (optional) v4 minimum is 8.2; current 8.3 constraint is fine to keep |

Exact `composer require` commands:
```bash
# Remove the now-separate notifications package
composer remove filament/notifications

# Upgrade filament core
composer require filament/filament:"^4.0" -W --no-update

# Upgrade shield to v4
composer require bezhansalleh/filament-shield:"^4.0" -W --no-update

# Actually update everything
composer update
```

### 1d. Remove the upgrade package when done
```
composer remove filament/upgrade --dev
```

---

## Section 2 – Per-File Migration Checklist

### `composer.json`
- [ ] Remove `filament/notifications` from `require`.
- [ ] Change `filament/filament` constraint to `^4.0`.
- [ ] Change `bezhansalleh/filament-shield` constraint to `^4.0`.
- [ ] The `post-autoload-dump` hook already calls `php artisan filament:upgrade` — keep it.

---

### `app/Providers/Filament/AdminPanelProvider.php`

**Breaking changes in v4:**
- `Filament\Http\Middleware\DisableBladeIconComponents` has been **removed** from Filament v4. Delete this import and remove it from the `middleware()` array.
- `Filament\Http\Middleware\DispatchServingFilamentEvent` has been **removed** from Filament v4. Delete this import and remove it from the `middleware()` array.
- `Illuminate\Foundation\Http\Middleware\PreventRequestForgery` — the class name is unchanged, but **in v4 the generated panel provider uses `VerifyCsrfToken` as the middleware alias**. The underlying class is the same; no change strictly required, but for consistency update import to `Illuminate\Foundation\Http\Middleware\VerifyCsrfToken` (or leave as-is, both work).
- `AuthenticateSession` import: in v4 the generated providers use `Illuminate\Session\Middleware\AuthenticateSession` — already correct here.
- `FilamentShieldPlugin::make()` is unchanged; the class is still in the same namespace.

**Changes needed:**
- [ ] Remove `use Filament\Http\Middleware\DisableBladeIconComponents;`
- [ ] Remove `DisableBladeIconComponents::class,` from the `middleware()` array.
- [ ] Remove `use Filament\Http\Middleware\DispatchServingFilamentEvent;`
- [ ] Remove `DispatchServingFilamentEvent::class,` from the `middleware()` array.

---

### `app/Filament/Resources/OrderResource.php`

**Breaking changes in v4:**

1. **`Section` and `Group` layout default column span** — In v4, `Section` and `Group` no longer span all columns by default. The form currently uses `->columnSpan(2)` and `->columnSpan(1)` on `Group::make()` and `Section::make()` explicitly, so these are already correct and unaffected.

2. **`Filament\Forms\Components\Group` namespace** — Unchanged, still `Filament\Forms\Components\Group`. No change needed.

3. **`Filament\Forms\Components\Section` namespace** — In v4, `Section` moved to `Filament\Schemas\Components\Section`. The old `Filament\Forms\Components\Section` namespace is **aliased/kept** by the upgrade script, but the canonical import should be updated:
   - [ ] Update `use Filament\Forms\Components\Section;` → `use Filament\Schemas\Components\Section;`

4. **`Select::reactive()` / `->reactive()`** — The `reactive()` method is **removed in v4**. Use `->live()` instead.
   - [ ] Change every `.reactive()` call to `->live()`:
     - `Select::make('customer_id')->...->reactive()` → `->live()`
     - `Select::make('product_id')->...->reactive()` → `->live()`
     - `TextInput::make('qty')->...->reactive()` → `->live()`
     - `TextInput::make('discount')->...->reactive()` → `->live()`

5. **`Repeater::make()->relationship()` signature** — Unchanged in v4. No change needed.

6. **`ExportAction` import** — `Filament\Tables\Actions\ExportAction` still exists in v4. No change needed.

7. **`disableOptionsWhenSelectedInSiblingRepeaterItems()`** — This method is **renamed** in v4 to `->distinct()`.
   - [ ] Change `->disableOptionsWhenSelectedInSiblingRepeaterItems()` → `->distinct()`

---

### `app/Filament/Resources/ProductResource.php`

1. **`Section` namespace** — same as OrderResource.
   - [ ] Update `use Filament\Forms\Components\Section;` → `use Filament\Schemas\Components\Section;`

2. **`Select::reactive()`** — same removal.
   - [ ] Change every `->reactive()` to `->live()`:
     - `Select::make('brand_id')->...->reactive()`
     - `Select::make('category_id')->...->reactive()`
     - `Select::make('subcategory_id')->...->reactive()`

3. **`fn(callable $get)`** closure parameter type — In v4, `callable` type hint in closures passed to `->disabled()`, `->options()` etc. should be `Get $get` (using the `Filament\Forms\Get` import that's already present). While `callable` still works, the idiomatic v4 form is `Get`:
   - [ ] Change `->disabled(fn(callable $get) => $get('category_id') === null)` → `->disabled(fn(Get $get) => $get('category_id') === null)`

4. **`Group` namespace** — unchanged.

5. **`Section` column span behavior** — The existing explicit `->columnSpan(2)` and `->columnSpan(1)` are correct for v4. However note that in v4 `columnSpan()` now targets `>= lg` devices by default (same as `columns()`). This form already uses explicit numeric spans so the rendered result should be identical.

---

### `app/Filament/Resources/BrandResource.php`
No v4 breaking changes apply. This resource uses only standard columns, forms, and actions with no deprecated methods.
- [ ] No changes required (verify after upgrade script runs).

---

### `app/Filament/Resources/CategoryResource.php`
No v4 breaking changes apply.
- [ ] No changes required (verify after upgrade script runs).

---

### `app/Filament/Resources/SubCategoryResource.php`
No v4 breaking changes apply.
- [ ] No changes required (verify after upgrade script runs).

---

### `app/Filament/Resources/CustomerResource.php`
No v4 breaking changes apply. Uses standard forms, tables, and `ActionGroup` (still present in v4).
- [ ] No changes required (verify after upgrade script runs).

---

### `app/Filament/Resources/UserResource.php`
No v4 breaking changes apply. All form fields and table columns use the stable v4 API.
- [ ] No changes required (verify after upgrade script runs).

---

### `app/Filament/Resources/OrderResource/RelationManagers/OrderDetailRelationManager.php`
No `reactive()` calls, no removed middleware, no namespace changes needed.
- [ ] No changes required (verify after upgrade script runs).

---

### `app/Filament/Resources/CustomerResource/RelationManagers/OrdersRelationManager.php`
No `reactive()` calls, no removed middleware, no namespace changes needed.
- [ ] No changes required (verify after upgrade script runs).

---

### `app/Filament/Resources/OrderResource/Pages/ListOrders.php`

1. **`Tab` component** — `Filament\Resources\Components\Tab` is unchanged in v4.
2. **`getHeaderWidgets()`** — Unchanged.
- [ ] No changes required (verify after upgrade script runs).

---

### `app/Filament/Resources/OrderResource/Widgets/OrderStats.php`

1. **`StatsOverviewWidget` / `Stat`** — Namespaces and API are unchanged in v4.
- [ ] No changes required (verify after upgrade script runs).

---

### `app/Filament/Widgets/StatsWidget.php`

1. **`Filament\Support\Enums\IconPosition`** — Unchanged in v4.
2. **`StatsOverviewWidget\Stat`** — Unchanged.
- [ ] No changes required (verify after upgrade script runs).

---

### `app/Filament/Widgets/OrdersChart.php`
1. **`ChartWidget`** — Unchanged in v4.
- [ ] No changes required (verify after upgrade script runs).

---

### `app/Filament/Widgets/CustomerWidget.php`
1. **`ChartWidget`** — Unchanged in v4.
- [ ] No changes required (verify after upgrade script runs).

---

### `app/Filament/Widgets/BestSelling.php`
1. **`TableWidget`** — Unchanged in v4.
2. **`getTableRecordKey()`** — This method on `TableWidget` is unchanged in v4.
- [ ] No changes required (verify after upgrade script runs).

---

### `app/Filament/Widgets/LastOrders.php`
1. **`TableWidget`** — Unchanged in v4.
- [ ] No changes required (verify after upgrade script runs).

---

### `app/Filament/Exports/OrderExporter.php`

1. **`Filament\Actions\Exports\Exporter`** namespace — Unchanged in v4. Exporter classes remain under `Filament\Actions\Exports`.
2. **Import/export job retries** — In v4, export jobs are retried 3 times with 60s backoff (v3 retried for 24h). This is a behavior change but requires no code edit unless a custom retry count is needed.
- [ ] No code changes required. Optionally, if the old retry behavior must be preserved, add to the exporter:
  ```php
  public int $tries = 0; // unlimited retries like v3
  ```
  Otherwise leave as-is and accept the new 3-retry default.

---

## Section 3 – Artisan Commands to Run Post-`composer update`

Run these in order:

```bash
# 1. Publish Filament config (new in v4 — needed for filesystem disk & file_generation settings)
php artisan vendor:publish --tag=filament-config

# 2. Upgrade Filament assets / caches
php artisan filament:upgrade

# 3. Clear all caches
php artisan optimize:clear

# 4. Re-setup Shield (required — v4 is a near-full rewrite of Shield)
#    When prompted "Shield is already installed. Would you like to reinstall?", answer Yes.
#    Choose No for the tenancy option (this app does not use tenancy).
php artisan shield:setup

# 5. Re-publish the Shield resource (if it was published previously)
php artisan shield:publish

# 6. Re-generate Shield policies for all resources
php artisan shield:generate --all

# 7. Re-run migrations (Shield v4 may add new tables / columns)
php artisan migrate

# 8. Rebuild frontend assets
npm run build
```

---

## Section 4 – Verification Steps

After completing all changes:

1. **Application boots without error**
   ```bash
   php artisan config:clear && php artisan route:list --compact 2>&1 | head -30
   ```
   Expected: routes list prints without any `Class not found` or `method does not exist` errors.

2. **No deprecated method calls remain**
   ```bash
   # Check for reactive() usage that was missed
   grep -rn "->reactive()" app/Filament/
   
   # Check for removed middleware imports
   grep -rn "DisableBladeIconComponents\|DispatchServingFilamentEvent" app/
   
   # Check for old disableOptionsWhenSelectedInSiblingRepeaterItems calls
   grep -rn "disableOptionsWhenSelectedInSiblingRepeaterItems" app/Filament/
   ```
   Expected: zero matches for all three.

3. **Panel loads in browser**
   - Navigate to `/admin` — the login page must render without a 500 error.
   - Log in and confirm the dashboard, resources, and widgets render.

4. **Shield permissions are intact**
   ```bash
   php artisan shield:generate --all
   ```
   Expected: policies generated for all resources without errors.

5. **Exports work** — Navigate to Orders → Download Excel and confirm the export job queues without error.

6. **Run the test suite**
   ```bash
   php artisan test --compact
   ```
   Expected: all tests pass (or match baseline before upgrade).

---

## Notes & Known Risks

| Item | Risk | Mitigation |
|---|---|---|
| `filament-shield` v4 is a near-full rewrite | Shield config file and any customized policy stubs are incompatible with v3 | Delete `config/filament-shield.php` and re-run `shield:setup` as instructed in its own upgrade steps |
| `filament/notifications` removal | Any blade/PHP code that directly imports from `filament/notifications` namespaces will break | Search for `Filament\Notifications` usages outside the standard `AdminPanelProvider` — none found in the reviewed files, but do a global grep: `grep -rn "filament/notifications" app/` |
| `Section` namespace move | If auto-upgrade script doesn't handle it, Section components in forms will not render | Run the upgrade script first; it handles most namespace moves via Rector |
| Tailwind CSS v4 custom theme | If a custom theme was created with `php artisan make:filament-theme`, the theme CSS file needs to be updated to Tailwind v4 syntax | No custom theme was found in this codebase — only the default panel CSS is used, so this does **not** apply here |
| Table filters are deferred by default in v4 | Users must now click "Apply" to trigger filters — behavior change | Add `->deferFilters(false)` to tables in `OrderResource`, `CustomerResource`, and any widget table if the old instant-filter UX must be preserved |
