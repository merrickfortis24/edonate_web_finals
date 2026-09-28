# Facility Management rollout and verification

## Cause of the save error

The local MariaDB log showed error 1364, `Field 'facility_id' doesn't have a default value`, in `FacilityController::store`. Schema inspection found `facilities.facility_id` was `int(11) NOT NULL` with neither an identity key nor `AUTO_INCREMENT`. Laravel correctly omitted the generated ID on insert. The core migration skipped the imported table, leaving its old definition unchanged.

The additive `2026_09_28_160000_repair_facility_identity` migration repairs that MySQL identity while preserving the integer type, signedness, current IDs and related records. It stops without changing records if IDs are missing, non-positive or duplicated. Rollback deliberately does not remove or renumber facility data.

Run only this migration for the facility ID repair, then rebuild the assets:

```powershell
cd C:\Users\Lenovo\edonate_web_finals\edonate_web
php artisan migrate --path=database/migrations/2026_09_28_160000_repair_facility_identity.php
php artisan optimize:clear
npm run build
```

For Hostinger, run the commands in the deployed Laravel directory as part of the deployment migration step. This change has not been deployed.

## Manual address and pin

- Facility creation and editing use manually entered address, barangay, city/municipality and province fields. No location service fills in or guesses address details.
- The existing Leaflet map and CARTO tile layer provide the pin picker. After allowing optional maps in Privacy choices, click to place the pin or drag it to adjust its exact position. The saved coordinates stay in the existing facility latitude/longitude columns; there are no visible coordinate inputs.
- Saving requires a valid selected pin and address review. Editing loads the stored pin; if an older record has no valid coordinates, choose a position before saving.
- Facility inventory markers use those saved coordinates on the existing Blood Availability Map. Activating or deactivating a facility keeps the stored pin and follows the existing active-only map behavior.
- No Google Places service, Google location key, autocomplete script, lookup route or location-refresh job is used. Google/Firebase sign-in is a separate existing feature. CARTO tiles use the app's existing map configuration and require the existing map consent; no new paid location API or key is added.

## Manual verification

1. Sign in as an administrator and open Facility Management. In Privacy choices, allow optional maps. Create a facility and enter its address, barangay, city and province.
2. Click the map to place a pin. Drag it to another position and confirm the map pin moves. Confirm no latitude or longitude fields are visible; submit and verify the facility appears with the saved position.
3. Try saving before choosing a pin. Confirm a clear pin-selection error appears and no facility is created.
4. Edit the facility. Verify the map opens at its saved pin, move the pin, update an address field, save, and confirm the new address and map location are retained.
5. Open Blood Availability Map → Facility Inventory and confirm the facility marker appears at the saved position. Check that the facility table remains usable when map tiles fail.
6. Deactivate/reactivate a facility and check the Active/Inactive icon and text, counts, inventory details, and active-only map.
7. Repeat on desktop and mobile. Check that only the table scrolls horizontally and the pin map remains usable.

## Automated verification

```powershell
php artisan test --filter=Phase10FacilityInventoryTest
php artisan test
$env:EDONATE_EXPORT_A11Y_FIXTURES='1'
php artisan test --filter=AccessibilityFixturesTest
npx playwright test
```

The browser tests use local tile request interception; they verify the Leaflet pin interaction, submitted coordinates, saved facility marker and the map table fallback without calling a location API.

## Files changed for this feature

- `app/Http/Controllers/Admin/FacilityController.php`: facility validation, coordinate persistence, and safe save errors.
- `app/Models/Facility.php`, `app/Services/FacilityBloodInventoryService.php`: keep the existing coordinates as the map source of truth.
- `database/migrations/2026_09_28_160000_repair_facility_identity.php`: guarded repair of imported facility IDs.
- `routes/web.php`: removed Google location endpoints; retained the authenticated facility management routes.
- `resources/views/admin/facilities.blade.php`, `resources/js/admin-facilities.js`, `resources/css/admin.css`: manual address form, responsive Leaflet pin picker, field validation and status indicators.
- `resources/views/admin/blood_availability_mapping.blade.php`: show facility markers with the existing Leaflet map alongside donor availability.
- `config/services.php`, `.env.example`: removed Google Places and browser-map key configuration.
- `resources/views/components/privacy-controls.blade.php`, `resources/views/donor/privacy.blade.php`, `resources/views/legal/cookies.blade.php`: describe CARTO maps without facility search disclosure.
- `tests/Feature/Phase10FacilityInventoryTest.php`, `tests/browser/facility-management.spec.mjs`: create/edit, pin validation, map display and responsive checks.
- `vite.config.js`, `public_html/build/manifest.json`, `public_html/build/assets/*`: compiled facility interface assets.

Pre-existing dashboard/account-list changes are preserved and are not part of this facility revision. The browser audit's inactive tab contrast adjustment is also retained.
