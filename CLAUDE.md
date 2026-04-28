# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Purpose

Small PHP/jQuery integration layer for a WooCommerce webshop. It handles newsletter signup forms, validates submissions against Cloudflare Turnstile CAPTCHA, subscribes users to a self-hosted [Sendy](https://sendy.co/) instance, and automatically generates a 10% WooCommerce coupon code sent via email.

Deployed to `/var/www/html/sendy/` inside a WordPress installation.

## Architecture

### Request flow (main form)

1. Browser loads `signup.js` which binds to `#signup_form`
2. Cloudflare Turnstile calls `getToken(token)` → `signup.js` submits the form via jQuery `$.post`
3. `signup.php` receives the POST, calls `functions.php` for:
   - `verifyTurnstileToken()` — Cloudflare Turnstile server-side check
   - `allowedRcpt()` — domain allowlist filter
   - Sendy subscribe API call (`$sendy_url/subscribe`)
   - `sendCC()` → `createCC()` + `emailSend()` — WooCommerce coupon generation and delivery
4. Sendy list used: default list in `$list`; switches to the raffle list when `nyeremenyjatek=1` is posted (no coupon sent for raffle entries)

### Popup/embed form

`signup-embed.js` handles `#signup_form_pop` — a simpler variant with no Turnstile and no raffle support. Posts directly to the same `signup.php`.

### Key files

| File | Role |
|------|------|
| `functions.php` | Config constants + all PHP functions; loaded by `signup.php` via `require` |
| `signup.php` | POST endpoint — CAPTCHA → allowlist → Sendy → coupon |
| `signup.js` | jQuery handler for the main form (`#signup_form`), includes Turnstile callback |
| `signup-embed.js` | jQuery handler for the popup form (`#signup_form_pop`) |
| `email.tpl` | HTML email sent on signup; placeholders `___NAME___` and `___CODE___` |
| `hirlevel.tpl` | General newsletter wrapper template; placeholder `[TARTALOM]` |
| `grc.php` | Legacy standalone reCAPTCHA v2 endpoint — no longer wired up |

### WordPress / WooCommerce integration

`functions.php` bootstraps WordPress via `require('/var/www/html/wp-load.php')` so it can use:
- `wp_mail()` for sending emails
- `wp_insert_post()` + `update_post_meta()` for creating `shop_coupon` posts (WooCommerce coupons)

Coupon format: `TJS-SUB-XXXXXXXX-YYYYYYYY` (deterministic from SHA-256 of email + date), 10% off, expires in 30 days, `exclude_sale_items=yes`, single-use.

## Configuration (in `functions.php`)

| Variable | What it is |
|----------|------------|
| `$sendy_url` | Sendy instance base URL |
| `$list` | Default Sendy list ID |
| `$api_key` | Sendy API key |
| `$cf_secretKey` | Cloudflare Turnstile secret key |

There is a legacy reCAPTCHA v2 secret in `grc.php`; it is unused at runtime.

## Deployment

No build step. Files are served directly by the WordPress/Apache stack. Copy changed files to `/var/www/html/sendy/` on the server. PHP errors go to the web server error log (`error_log()` is used throughout for debugging).

## Documentation

After every code change update `README.md` if necessary.
