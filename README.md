# woo-marketing-automation

PHP/jQuery integration layer for a WooCommerce webshop.

Handles newsletter signups with Cloudflare Turnstile CAPTCHA, subscribes users to a self-hosted [Sendy](https://sendy.co/) instance, generates a 10% WooCommerce coupon, and sends post-purchase review request emails.

## Architecture

| File | Role |
|------|------|
| `functions.php` | Loads `.env` config + all shared PHP functions; required by all other scripts |
| `signup.php` | POST endpoint: CAPTCHA → allowlist → Sendy subscribe → coupon email |
| `signup.js` | jQuery handler for the main signup form (`#signup_form`), includes Turnstile callback; redirect URL read from form's `data-redirect` attribute |
| `signup-embed.js` | jQuery handler for the popup/embed form (`#signup_form_pop`) |
| `review-email.php` | Cron-triggered script: sends review request emails 7 days after order completion |
| `refill-email.php` | Cron-triggered script: sends refill reminder + free shipping coupon 30 days after order completion |
| `forsale-email.php` | Cron-triggered script: sends top 10 discounted products (by discount %) 60 days after order completion |
| `final-email.php` | Cron-triggered script: sends past order + top discounts + 10% coupon (7-day) 90 days after order completion |
| `email.tpl` | HTML email template for signup coupon; placeholders `___NAME___` and `___CODE___` |
| `hirlevel.tpl` | Newsletter wrapper template; placeholder `[TARTALOM]` |

## Configuration

All secrets and environment-specific values are stored in a `.env` file (not committed to git).

### Setup

```bash
cp .env.example .env
# Edit .env and fill in all values
```

### `.env` keys

| Key | Description |
|-----|-------------|
| `WP_LOAD_PATH` | Absolute path to `wp-load.php` on the server |
| `SENDY_DIR` | Absolute path to the `sendy/` deployment directory |
| `SENDY_URL` | Base URL of the Sendy instance |
| `SENDY_LIST` | Default Sendy list ID (newsletter signups) |
| `SENDY_RAFFLE_LIST` | Sendy list ID for raffle/nyereményjáték signups |
| `SENDY_CUSTOMER_LIST` | Sendy list ID for subscription checks (`review-email.php`, `refill-email.php`, `forsale-email.php`) |
| `SENDY_API_KEY` | Sendy API key |
| `CF_SECRET_KEY` | Cloudflare Turnstile server-side secret key |
| `DEBUG_ORDER_ID` | Order ID for debug mode (`review-email.php`, `refill-email.php`, `forsale-email.php`) |
| `DEBUG_EMAIL` | Recipient address for debug mode emails |

The config is loaded in `functions.php` via:

```php
$config = parse_ini_file(__DIR__ . '/.env');
```

## Deployment

No build step. Copy changed files to `_WEBROOT_/sendy/` on the server, and ensure `.env` exists there with production values.

```bash
rsync -av --exclude='.env' --exclude='.git' ./ user@server:_WEBROOT_/sendy/
```

PHP errors are written to the web server error log via `error_log()`.

## Request flow (main signup form)

1. Browser loads `signup.js`, binds to `#signup_form`
2. Cloudflare Turnstile calls `getToken(token)` → `signup.js` submits via jQuery `$.post`
3. `signup.php` verifies the token, checks the email allowlist, calls the Sendy subscribe API
4. On successful subscribe (and no raffle flag): generates a WooCommerce coupon and sends it via `wp_mail()`

Coupon format: `WMA-SUB-XXXXXXXX-YYYYYYYY` (SHA-256 of email + date), 10% off, 30-day expiry, single-use, excludes sale items.
