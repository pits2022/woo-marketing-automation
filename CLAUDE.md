# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Purpose

WordPress plugin that automates post-purchase email sequences and Sendy newsletter signups for WooCommerce stores.

## Architecture

```
woo-marketing-automation/
├── woo-marketing-automation.php   — plugin bootstrap, hooks registration
├── uninstall.php                  — cleanup on uninstall
├── includes/
│   ├── class-wma-activator.php    — activation/deactivation, default settings
│   ├── class-wma-settings.php     — dot-notation settings accessor, reactivation email CRUD
│   ├── class-wma-logger.php       — timestamped log to wp-content/wma-debug.log
│   ├── class-wma-sendy.php        — Sendy subscribe/status API calls
│   ├── class-wma-coupon.php       — WooCommerce coupon creation (percent, free shipping)
│   ├── class-wma-email.php        — HTML email sender, product table builders
│   ├── class-wma-shortcode.php    — [wma-sendy] shortcode + AJAX subscribe handler
│   ├── class-wma-customer-lists.php — order completion hook, Sendy list segmentation
│   ├── class-wma-cron.php         — daily cron, reactivation email dispatch
│   └── class-wma-admin.php        — WooCommerce submenu, 5-tab settings UI
├── assets/
│   ├── css/wma-admin.css          — admin badge styles
│   ├── css/wma-frontend.css       — signup form styles
│   └── js/wma-signup.js           — form AJAX submission with Turnstile support
└── languages/
    ├── woo-marketing-automation-en_US.po
    └── woo-marketing-automation-hu_HU.po
```

### Key flows

**Newsletter signup** (`[wma-sendy]` shortcode):
1. Form rendered by `WMA_Shortcode::render()` with Cloudflare Turnstile if configured
2. `wma-signup.js` submits via AJAX to `wp_ajax_wma_subscribe`
3. `WMA_Shortcode::ajax_subscribe()` validates nonce + CAPTCHA, queues async Sendy subscribe, sends welcome email with optional coupon

**Customer list segmentation** (on order completion):
1. `WMA_Customer_Lists` hooks `woocommerce_order_status_completed`
2. Schedules `wma_process_customer_lists` single event
3. Subscribes to customers list; VIP list if total ≥ threshold; returning list if prior completed order exists

**Reactivation emails** (daily cron `wma_daily_cron`):
1. `WMA_Cron::run_daily()` loops enabled reactivation email configs
2. Queries completed orders from exactly `wait_period` days ago that haven't received this email
3. Skips orders where billing email is not subscribed to the customers list
4. Builds email data (ordered products, on-sale products, coupons) and sends via `WMA_Email::send()`
5. Sets order meta `_wma_email_{id}_sent` to prevent duplicate sends

### Settings storage

All settings in a single WordPress option `wma_settings` (array). Access via `WMA_Settings::get('dot.notation.key')`.

### Email template placeholders

`[WMA_MESSAGE]`, `[WMA_REVIEW_PRODUCTS]`, `[WMA_DISCOUNT_PRODUCTS]`, `[WMA_COUPON_CODE_PERCENT]`, `[WMA_COUPON_CODE_FREESHIPMENT]`, `[WMA_UNSUBSCRIBE_URL]`

## Development notes

- Text domain: `woo-marketing-automation`
- All admin actions protected by `check_admin_referer()` and `current_user_can('manage_woocommerce')`
- Coupon codes are deterministic: SHA-256 of `email + date + wp_salt()`, formatted as `WMA-XXXXXXXX-YYYYYYYY`
- After every code change update `README.md` if necessary
