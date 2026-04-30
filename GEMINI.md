# WooCommerce Marketing Automation

A WordPress plugin that automates post-purchase email sequences and Sendy newsletter signups for WooCommerce stores.

## Project Overview

- **Purpose:** Automate marketing efforts by connecting WooCommerce with Sendy and sending personalized post-purchase emails.
- **Main Technologies:** PHP 8.5+, WordPress 6.9+, WooCommerce 10.7.0+.
- **Architecture:** 
    - OOP structure with static `init()` methods for class/hook registration.
    - Centralized settings storage in a single WordPress option (`wma_settings`).
    - Cron-based scheduling for reactivation emails.
    - AJAX-based signup form with Cloudflare Turnstile support.

## Building and Running

This is a standard WordPress plugin and does not require a build step (no npm/composer dependencies are currently used in the main repo).

### Installation & Setup

1.  **Deploy:** Upload the plugin folder to `/wp-content/plugins/`.
2.  **Activate:** Enable via WordPress Admin -> Plugins.
3.  **Requirements:** Ensure WooCommerce is active and configured.
4.  **Configuration:** Navigate to **WooCommerce -> Marketing Automation** to set up Sendy API and email settings.

### Testing

-   **Manual Testing:** Trigger order completion to test list segmentation and email queuing.
-   **Cron Testing:** Use a plugin like WP Crontrol to manually trigger the `wma_daily_cron` or `wma_process_customer_lists` events.
-   **Logging:** Check `wp-content/uploads/wma-logs/wma-debug.log` for execution logs (uses `WMA_Logger`).

## Development Conventions

### Coding Style & Standards

-   **Namespace/Prefix:** All classes use the `WMA_` prefix and are located in the `includes/` directory.
-   **Static Initialization:** Classes typically expose a `public static function init(): void` to register hooks.
-   **Strict Typing:** Use PHP 8.5+ type hinting for properties, arguments, and return types.

### Key Utilities

-   **Settings:** Always use `WMA_Settings::get('key.path')` to retrieve configuration.
-   **Logging:** Use `WMA_Logger::log( $message, $level )` for debugging. Levels: `INFO`, `WARNING`, `ERROR`.
-   **Security:**
    -   Protect admin actions with `check_admin_referer( 'wma_save_settings', 'wma_nonce' )`.
    -   Restrict access using `current_user_can( 'manage_woocommerce' )`.
    -   Sanitize all inputs using `sanitize_text_field`, `sanitize_textarea_field`, etc.

### Internal Workflows

-   **Text Domain:** `woo-marketing-automation` (used for all internationalization).
-   **Coupon Generation:** Use `WMA_Coupon::create()` for deterministic, SHA-256-based coupon codes.
-   **Email Templates:** Global HTML template is stored in settings and uses bracketed placeholders (e.g., `[WMA_MESSAGE]`).
-   **Order Meta:** Tracking sent emails via `_wma_email_{id}_sent` meta key on WooCommerce orders.

## Project Structure

-   `assets/`: CSS and JS for both admin and frontend.
-   `includes/`: Core PHP logic, separated by responsibility (activator, admin, cron, email, etc.).
-   `languages/`: Translation files (.po/.mo).
-   `woo-marketing-automation.php`: Main entry point and bootstrap.
-   `uninstall.php`: Cleanup logic for removal.
