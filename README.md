# WooCommerce Marketing Automation

A WordPress plugin that automates post-purchase email sequences and Sendy newsletter signups for WooCommerce stores.

## Features

- **Customer list segmentation** — automatically adds customers to Sendy lists (all customers, VIP by order amount, returning customers) when an order is completed
- **Welcome email** — sends a welcome email with an optional percentage-off coupon when someone subscribes via the signup form
- **Reactivation email sequences** — configurable series of post-purchase emails triggered by order completion date; supports ordered product tables, on-sale product tables, percentage-off coupons, and free shipping coupons
- **Global email template** — one HTML template used by all outgoing emails, editable from the admin UI
- **Signup shortcode** — `[wma-sendy]` renders a subscription form with optional Cloudflare Turnstile CAPTCHA
- **Multilingual** — ships with English and Hungarian translations

## Requirements

- WordPress 6.9+
- WooCommerce 10.7.0+
- PHP 8.5+
- A [Sendy](https://sendy.co/) installation

## Installation

1. Upload the `woo-marketing-automation` folder to `/wp-content/plugins/`
2. Activate the plugin through **Plugins** in WordPress admin
3. Go to **WooCommerce → Marketing Automation** and configure your Sendy URL and API key

## Configuration

### Sendy tab

| Field | Description |
|-------|-------------|
| Sendy URL | Base URL of your Sendy installation |
| Sendy API Key | Your Sendy API key |
| Cloudflare Turnstile Site Key | Optional; enables CAPTCHA on subscription forms |
| Cloudflare Turnstile Secret Key | Optional; used for server-side CAPTCHA verification |

### Customer Lists tab

| Field | Description |
|-------|-------------|
| Customers List ID | Every customer is added here on order completion |
| VIP Customers List ID | Added when order total ≥ VIP minimum amount |
| VIP Minimum Order Amount | Threshold for VIP classification |
| Returning Customers List ID | Added when the customer has a previous completed order |

### Welcome Email tab

Subject and message for the email sent after a successful subscription via the `[wma-sendy]` shortcode.

### Email Template tab

Global HTML template used by all outgoing emails. Available placeholders:

| Shortcode | Rendered when |
|-----------|---------------|
| `[WMA_MESSAGE]` | Always |
| `[WMA_REVIEW_PRODUCTS]` | "Include ordered products table" is checked on the reactivation email |
| `[WMA_DISCOUNT_PRODUCTS]` | Top sale products count > 0 |
| `[WMA_COUPON_CODE_PERCENT]` | Coupon percent > 0 |
| `[WMA_COUPON_CODE_FREESHIPMENT]` | Free shipping expiry days > 0 |
| `[WMA_UNSUBSCRIBE_URL]` | Always |

### Reactivation Emails tab

Add, edit, enable/disable, and delete reactivation email entries. Each email defines:

- **Wait period** — days after order completion before sending
- **Email subject and message**
- **Ordered products table** — include the items from the triggering order
- **Top discounted products** — include the N most-discounted currently on-sale products
- **Percentage-off coupon** — discount % and expiry in days
- **Free shipping coupon** — expiry in days (0 = disabled)

Reactivation emails are sent once per order per email type; a WooCommerce order meta flag prevents duplicates.

## Shortcode

```
[wma-sendy list="YOUR_LIST_ID"]
[wma-sendy list="YOUR_LIST_ID" redirect="https://example.com/thank-you"]
[wma-sendy list="YOUR_LIST_ID" coupon_percent="10" coupon_expiry="30"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `list` | _(required)_ | Sendy list ID to subscribe to |
| `id` | `wma-form` | HTML element ID prefix (must be unique per page) |
| `redirect` | _(none)_ | URL to redirect to after successful subscription |
| `coupon_percent` | `0` | Percentage discount sent in the welcome email (0 = disabled) |
| `coupon_expiry` | `30` | Coupon validity in days |

## Logging

Debug output is written to `wp-content/wma-debug.log`.

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).
