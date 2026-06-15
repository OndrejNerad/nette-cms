# Car Equipment Filter Design

**Date:** 2026-06-15

## Problem

The car list filter (`CarPresenter` / `app/Presentation/Car/templates/{cs,en}/list.latte`) currently filters by brand (`znacka`), bodywork (`karoserie`), fuel (`palivo`), category tag (`stitek`), price/year/mileage ranges, and VAT-deductible (`odpocet`).

Each car also has a set of equipment items (air conditioning, leather seats, towbar, navigation, parking sensors, LED lights, etc.) via a many-to-many relation: `equipment_items` (107 distinct items, opaque feed IDs like `N1`, `25`, `S0`) ↔ `car_equipment` (255k pivot rows) ↔ `cars` (5,114 rows). This data is currently only displayed as a text blurb on each car card (`list.latte` ~line 134), not filterable.

107 items is too many for a flat checkbox list, and `equipment_items` has no category/grouping column (would require manually classifying all 107 items).

## Solution Overview

Add a "Vybavení" (equipment) section to the existing sidebar filter form: a search-as-you-type input with selectable chips. Selecting multiple items requires a car to have **all** of them (AND logic). Before typing, the input shows a list of the ~15 most common equipment items as quick picks.

This is purely additive: no DB schema changes, no new JS dependencies, no AJAX. It follows the existing GET-form + `@persistent` query-param pattern used by all other filters.

## Architecture

### 1. Data layer

- **`EquipmentItemsRepository`**: add `findAllOrdered(): array` — all 107 `EquipmentItem` entities ordered by `title`. Used to build the client-side search dataset.

- **`CarEquipmentRepository`** (existing raw-`Connection` wrapper, same pattern as `findForCar`/`syncForCar`):
  - `findCarIdsWithAllEquipment(array $equipmentIds): array` — runs:
    ```sql
    SELECT car_id FROM car_equipment
    WHERE equipment_id IN (...)
    GROUP BY car_id
    HAVING COUNT(DISTINCT equipment_id) = <count of $equipmentIds>
    ```
  - `findPopularEquipmentIds(int $limit): array` — runs:
    ```sql
    SELECT equipment_id FROM car_equipment
    GROUP BY equipment_id
    ORDER BY COUNT(*) DESC
    LIMIT <limit>
    ```
    (`$limit` = 15)

Both queries are cheap at current scale (5,114 cars / 256k pivot rows / 107 distinct equipment IDs) — no caching needed.

### 2. Backend filter wiring

**`CarPresenter`**:
- New persistent property: `/** @persistent */ public array $equipment = [];` — represented in URLs as `?equipment[]=N1&equipment[]=25`, same mechanism Nette already uses for other filter params.
- `loadState()`: drop any IDs not present in `equipment_items` (handles stale URLs after feed changes).
- `renderList()`:
  - add `'equipment' => $this->equipment` to the `$filters` array passed to `findFiltered()`
  - pass to template: `allEquipment` (full 107-item list for search), `popularEquipment` (top-15), `filterEquipment` (currently selected IDs + resolved titles, for rendering pre-selected chips)

**`CarsRepository::findFiltered()`**: new branch, evaluated like the other filter conditions:
```php
if (!empty($filters['equipment'])) {
    $carIds = $this->carEquipmentRepository->findCarIdsWithAllEquipment($filters['equipment']);
    $collection = $collection->findBy(['id' => $carIds ?: [0]]); // [0] forces an empty result set
}
```

### 3. Frontend UI (`list.latte` + `en/list.latte`)

New `.filter-item.equipment-filter` block, placed after the "Palivo" dropdown and before the price/year/km range inputs:

```html
<div class="filter-item equipment-filter">
  <span class="label">Vybavení</span>
  <input type="text" id="equipment-search" placeholder="Hledat vybavení..." autocomplete="off">
  <div class="equipment-dropdown" id="equipment-dropdown"></div>
  <div class="equipment-chips" id="equipment-chips">
    {foreach $filterEquipment as $item}
      <span class="chip" data-id="{$item->id}">
        {$item->title}
        <i class="remove">&times;</i>
        <input type="hidden" name="equipment[]" value="{$item->id}">
      </span>
    {/foreach}
  </div>
</div>
```

Plus an inline data blob for the JS (small — 107 items is a few KB, no AJAX):
```html
<script>
window.EQUIPMENT_DATA = {
  all: {$allEquipmentJson|noescape},     // [{id, title}, ...] sorted by title
  popular: {$popularEquipmentJson|noescape} // [{id, title}, ...] top 15
};
</script>
```

The "Zrušit filtry" reset link gets `equipment => []` added alongside the existing `znacka => null, karoserie => null, ...` resets.

The product card loop (cars actually displayed, ~lines 107-145) is **not changed**.

### 4. JS behavior

New small module under `assets/js/` (bundled by the existing Vite pipeline into `www/assets/js/scripts.js`, same toolchain as the current `script.js`):

- **Focus, empty input** → render `EQUIPMENT_DATA.popular` in the dropdown
- **Typing** → case-insensitive substring match over `EQUIPMENT_DATA.all` (on `title`), excluding already-selected IDs, capped to ~20 results
- **Click a result** → append a chip + hidden `<input name="equipment[]" value="ID">`, clear the search text, keep focus open for adding more
- **Click a chip's `×`** → remove the chip and its hidden input
- **Submit**: unchanged — the existing "Vyhledat" button submits the same GET form; the new hidden inputs ride along with the rest of the filter fields

## Edge Cases

- **No cars match the AND-combination**: results in an empty `$cars` collection. The list template currently has no explicit "no results" message for *any* filter combination — this is a pre-existing gap, not introduced by this feature, and is out of scope here.
- **Equipment ID in URL no longer exists** (e.g. removed from feed): silently dropped in `loadState()`.
- **JS disabled**: pre-selected chips (rendered server-side from `$filterEquipment`) still submit correctly via their hidden inputs; adding *new* items requires JS, consistent with the rest of the filter sidebar's existing JS dependence.

## Out of Scope

- Categorizing the 107 equipment items into groups (avoided entirely by the search+chips approach)
- OR-logic toggle for equipment matching
- Admin UI for managing/renaming equipment items
- "No results" messaging for the filter as a whole
