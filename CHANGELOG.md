# Changelog

All notable changes to this project will be documented in this file.

## [1.0.7] - 2026-06-15

- Test email now renders the configured Welcome Email content into `[WMA_MESSAGE]` (instead of a hardcoded placeholder message), with a fallback notice when no Welcome Email is configured
- Fix missing translations on the Test Email section and several other admin strings: 19 strings were absent from the Hungarian catalog and 21 from the English catalog (manually maintained `.po` files were never updated); all added and `.mo` files recompiled

## [1.0.6] - 2026-05-20

- Fix email layout: add product image to WMA_REVIEW_PRODUCTS table (matching WMA_DISCOUNT_PRODUCTS format)
- Rename WMA_REVIEW_PRODUCTS table header to "Megvásárolt termékek" (Purchased products)
- Rename WMA_DISCOUNT_PRODUCTS table header to "Akciós termékek" (Sale products)
- Add margin-bottom spacing between WMA_REVIEW_PRODUCTS and WMA_DISCOUNT_PRODUCTS blocks

## [1.0.5] - 2026-05-16

- Fix WP-Cron not registered after plugin update without reactivation: move `wp_schedule_event` to `WMA_Cron::init()` for self-healing scheduling on every `plugins_loaded`

## [1.0.3] - 2026-05-07

- 14 fix changelog and release management (#15) (a280229)

