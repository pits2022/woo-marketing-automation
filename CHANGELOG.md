# Changelog

All notable changes to this project will be documented in this file.

## [1.0.5] - 2026-05-16

- Fix WP-Cron not registered after plugin update without reactivation: move `wp_schedule_event` to `WMA_Cron::init()` for self-healing scheduling on every `plugins_loaded`

## [1.0.3] - 2026-05-07

- 14 fix changelog and release management (#15) (a280229)

