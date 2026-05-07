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
- PHP 8.1+
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

**Important note about coupons:** If you use the `[WMA_COUPON_CODE_PERCENT]` placeholder in your email template, its value is **not** configured globally here. Instead, it is controlled by the shortcode attributes (`coupon_percent` and `coupon_expiry`) where you place the form. When a user subscribes, the plugin dynamically generates a unique, one-time-use WooCommerce coupon and injects the code into the email.

### Email Template tab

Global HTML template used by all outgoing emails. 

**Note on HTML format:** For security reasons, the template is strictly sanitized to prevent Cross-Site Scripting (XSS). Do **not** use a full HTML document structure starting with `<!DOCTYPE html>`. Please paste only the body structure/HTML fragments (e.g. `<div>`, `<table>`) of your newsletter design.

Available placeholders:

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

### Form Styling

The `[wma-sendy]` form is designed to inherit your active WordPress theme's styles for inputs and buttons. It does not ship with opinionated colors or borders. 

However, you may want to add custom CSS to style the success and error status messages. You can add the following starter snippet to **Appearance → Customize → Additional CSS**:

```css
/* WMA Sendy Form - Status Messages */
.wma-status {
    border-radius: 4px;
}
.wma-status.wma-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.wma-status.wma-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
```

## Translations

The plugin is translation-ready and includes English and Hungarian locales. 

### Contributing Translations

We use an automated GitHub Actions workflow to compile `.po` files into `.mo` files upon pull request. However, due to GitHub security restrictions, **this automation only works for pull requests coming from branches within the same repository.**

If you are contributing from a **forked repository**, you must compile the `.mo` files locally before submitting your pull request:

1. Edit the `.po` file in the `languages/` directory.
2. Compile it to `.mo` using `msgfmt` (part of the `gettext` package):
   ```bash
   msgfmt -o languages/woo-marketing-automation-hu_HU.mo languages/woo-marketing-automation-hu_HU.po
   ```
3. Commit and push both the `.po` and `.mo` files to your fork.

## Automated Workflows

### Release Process

The `release.yml` workflow is triggered by pushing a tag (e.g., `1.0.3` or `v1.0.3`). It automatically:
1. Bumps the version numbers in the code and translation headers.
2. Recompiles all translation files.
3. Updates `CHANGELOG.md`.
4. Pushes these changes directly to the `main` branch.
5. Creates a GitHub Release with generated release notes.

**Important:** Because this workflow pushes directly to `main`, if you have **Branch Protection Rules** enabled for the `main` branch, you must ensure that the `github-actions[bot]` (or the account associated with the `GITHUB_TOKEN`) is allowed to bypass these rules (e.g., "Allow force pushes" is not needed, but "Restrict pushes" must allow the bot, or "Require pull request reviews before merging" must be bypassed for this specific automated commit).

## Logging

Debug output is written to `wp-content/uploads/wma-logs/wma-debug.log`.

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).
