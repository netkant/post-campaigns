# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Post Campaigns is a WordPress plugin that integrates with MailWizz to send newsletter campaigns from WordPress posts. It targets the `press_release` custom post type and uses ACF (Advanced Custom Fields) for the admin UI.

## Architecture

### File Structure
- `post-campaigns.php` - Main plugin file, entry point, campaign creation and cron scheduling
- `acf/acf-fields.php` - ACF field group definitions and permission checking
- `includes/admin.php` - Admin UI: custom columns, bulk actions, stats display
- `templates/default-mail-template.php` - HTML email template

### Key Patterns

**Procedural WordPress Style**: No classes or namespaces. Uses WordPress hooks (actions/filters) throughout.

**Data Flow**:
1. User enables "Send campaign" toggle and sets send time on a `press_release` post
2. `acf/save_post` hook schedules a WP-Cron event at the specified time
3. Cron triggers `post_campaigns_send_newsletter_handler()` which:
   - Loads the email template with output buffering
   - Calls MailWizz API to create the campaign
   - Stores the returned `campaign_uid` in ACF field

**ACF Fields** (on `press_release` posts):
- `send_campaign` (boolean) - Toggle to enable sending
- `newsletter_sendtime` (datetime) - Scheduled send time
- `newsletter_campaign_id` (text) - Stored MailWizz campaign UID after creation

### Extensibility Hooks

Filters:
- `post_campaigns_allowed` - Control who can see/use the newsletter fields
- `post_campaigns_template` - Override the email template path
- `post_campaigns_newsletter_campaign_stats_list` - Customize stats displayed in admin

## Required Configuration

These constants must be defined in `wp-config.php`:
```php
define('MAILWIZZ_BASE_URL', '...');     // MailWizz API base URL
define('MAILWIZZ_API_KEY', '...');      // API authentication key
define('MAILWIZZ_LIST_ID', '...');      // Target subscriber list UID
define('MAILWIZZ_FROM_NAME', '...');    // Sender name
define('MAILWIZZ_FROM_EMAIL', '...');   // Sender email
define('MAILWIZZ_REPLY_TO', '...');     // Reply-to email
```

## Dependencies

- **Advanced Custom Fields (ACF)** - Required. Fields won't register without it.
- **WordPress** - Uses WP HTTP API, WP-Cron, post meta

## Development Notes

- No build process, package manager, or tests
- Campaign API response includes debug output via `print_r()` in `post_campaigns_create_campaign()` (line 78-80)
- Stats are cached in post meta (no expiration currently - cleared manually via admin action)
- Failed API calls reschedule 5 minutes later via WP-Cron
