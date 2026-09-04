# PRC Block Area Modules

> Canonical docs: [docs/plugins/prc-block-area-modules/](../../docs/plugins/prc-block-area-modules/)

Provides an editorially curated system for populating named zones in a block theme with reusable block content. Rather than using static template parts, editors assign a `block_module` post (a freeform block-editor document) to a named `block_area` taxonomy term, optionally scoped by taxonomy (e.g., topic category or region). The correct module is resolved server-side at render time.

## What it does

- Registers the `block_module` custom post type — full block-editor documents that serve as slot content.
- Registers the `block_area` hierarchical taxonomy, used to tag modules to named zones (e.g., `topic-lede`, `featured-banner`).
- Provides the `prc-platform/block-area` block, which resolves and renders the matching `block_module` at request time based on `blockAreaSlug` + optional taxonomy term. Supports direct `ref` override to pin a specific module.
- Supports `inheritTermFromTemplate` so a single block area placed in a taxonomy archive template automatically scopes to the current term.
- Provides the `prc-platform/block-area-context-provider` block (dev/layout use), which wraps block areas and query blocks. It pre-fetches the `_story_item_ids` from all enclosed block modules and injects them as `post__not_in` into sibling query blocks, preventing story repetition on the same page.
- On `pre_get_posts`, automatically excludes `topic-lede` story items from the main query on category archives.
- Caches resolved `_story_item_ids` per block-area/category combination (1 hour TTL, object cache). Cache is busted on `block_module` save.
- Stores an indexed list of `prc-block/story-item` post IDs (`_story_item_ids` meta) on each `block_module` at save time.
- Ships four block variations: `Topic Block Area` (by `category`), `Regions Countries Block Area` (by `regions-countries`), `Collections Block Area` (by `collection`), and `Newsletter List Block Area` (by `prc_newsletter_list`).
- Block area editor UI includes a setup wizard (for first-time configuration) and an `InnerBlocksAsSyncedContent` view of the resolved module with inspector controls for re-pointing the area.
- Non-menu block areas are suppressed on paged requests (`is_paged()`). The Interactivity API store (`prc-platform/block-area`) mirrors this behavior on the frontend.

## Key files

| File | Purpose |
|---|---|
| `prc-block-area-modules.php` | Plugin entry point; defines constants, loads dependencies, fires `run_prc_block_area_modules()` |
| `includes/class-content-type.php` | Registers `block_module` post type, `block_area` taxonomy, `_story_item_ids` post meta + REST field, and story-item ID extraction on save |
| `includes/class-plugin.php` | Wires `Content_Type`, `Block_Area`, and `Block_Area_Context_Provider` into the loader |
| `src/block-area/block.json` | Block metadata for `prc-platform/block-area` (attributes, supports, usesContext) |
| `src/block-area/class-block-area.php` | Server render callback; resolves matching `block_module` via `WP_Query`, outputs Interactivity API wrapper |
| `src/block-area/edit.jsx` | Editor UI — setup wizard on first use, `InnerBlocksAsSyncedContent` once a module is linked |
| `src/block-area/variations.js` | Four block variations scoped by taxonomy (category, regions-countries, collection, prc_newsletter_list) |
| `src/block-area/view.js` | Interactivity API store — hides non-menu block areas on paged requests |
| `src/block-area-context-provider/class-block-area-context-provider.php` | Collects story item IDs from block modules, injects into query block context and main query |
| `src/block-area-context-provider/block.json` | Block metadata for `prc-platform/block-area-context-provider` |
| `src/block-area/hooks/use-block-modules.js` | Hook for fetching `block_module` posts in the editor by block area + taxonomy term |
| `src/block-area/hooks/use-taxonomy-info.js` | Hook for resolving term/area names and IDs from slugs |
| `src/block-area/block-area-wizard/` | Multi-step editor wizard (intro → select/query → create) for initial block area setup |

## Filters / hooks

| Hook | Type | Direction | Description |
|---|---|---|---|
| `init` | Action | Consumed | Registers `block_module` post type, `block_area` taxonomy, post meta, and both blocks |
| `rest_api_init` | Action | Consumed | Registers the `_story_item_ids` REST field on `block_module` |
| `render_block_context` (priority 1) | Filter | Consumed | `construct_block_context` — finds the first `prc-platform/block-area` inside the context provider and pre-fetches its story item IDs |
| `render_block_context` (priority 100) | Filter | Consumed | `execute_block_context` — injects collected story item IDs into `core/post-template` query context as `post__not_in` |
| `pre_get_posts` | Action | Consumed | On category archive main queries (non-paged, non-front-page), excludes `topic-lede` story items from results |
| `prc_platform_on_block_module_update` | Action | Consumed | Two callbacks: (1) re-indexes `_story_item_ids` from block content, (2) busts the object-cache entry for the module's block area + category |
| `the_content` | Filter | Consumed | Applied to resolved `block_module` post content during `prc-platform/block-area` render |

## Data model

```
block_module (post type)
  └── block_area (taxonomy)          – which zone this module fills
  └── category / regions-countries / collection / prc_newsletter_list (taxonomies) – optional scoping term
  └── _story_item_ids (post meta)    – indexed array of prc-block/story-item postIds
```

A `prc-platform/block-area` block resolves to the single `block_module` post that matches:

1. `block_area` term slug == `blockAreaSlug`
2. (optional) scoping taxonomy term == `taxonomyTermSlug` or current queried term when `inheritTermFromTemplate` is true
3. Or directly via `ref` (post ID), bypassing taxonomy lookup entirely

## Usage notes

- **Template usage**: Place `prc-platform/block-area` in a block theme template. Set `blockAreaSlug` to the zone name (e.g., `topic-lede`), `taxonomyName` to `category`, `regions-countries`, `collection`, or `prc_newsletter_list`, and enable `inheritTermFromTemplate` so the block resolves automatically per archive term.
- **Repetition prevention**: Wrap block areas and downstream query blocks inside `prc-platform/block-area-context-provider`. The provider ensures posts already displayed in a module's story items are excluded from sibling query loops.
- **Cache invalidation**: Object cache entries are keyed by `md5(json([blockAreaSlug, categorySlug]))` under the `prc_block_area_module_story_item_ids` group. Cache TTL is 1 hour. Saving a `block_module` deletes its specific cache entry.
- **Paged suppression**: Block areas without `menu` in their slug return no content on paged requests (`?paged=2`, etc.), both server-side and via the Interactivity API store.

## Dependencies

- **Requires**: `prc-platform-core`
- **PHP**: 8.2+
- **WordPress**: 6.7+
- **JS runtime**: `@wordpress/interactivity`, `@prc/components` (`InnerBlocksAsSyncedContent`)

## Build

```bash
npm run build -w @prc/block-area-modules
```
