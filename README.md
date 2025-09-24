# Post Campaigns

**Post Campaigns** is a WordPress plugin that integrates with [MailWizz](https://www.mailwizz.com/) to allow you to send newsletter campaigns directly from your WordPress posts. It provides a seamless way to create and schedule MailWizz campaigns using the WordPress admin interface, leveraging the Advanced Custom Fields (ACF) plugin for custom fields.

## Features

- **Send MailWizz campaigns from WordPress posts**: Easily create and send newsletters based on your post content.
- **Schedule campaigns**: Choose when your campaign should be sent.
- **Track campaign status**: View delivery statistics directly in the post overview.
- **Customizable**: Extend and filter campaign data and stats via WordPress hooks.

## Requirements

- **Advanced Custom Fields (ACF)** plugin must be installed and active.
- A [MailWizz](https://www.mailwizz.com/) account and API access.
- The following constants must be defined in your `wp-config.php` file:

### Required `wp-config.php` Settings

Add the following lines to your `wp-config.php`, replacing the values with your own:

- define('MAILWIZZ_BASE_URL', 'URL');
- define('MAILWIZZ_API_KEY', 'API_KEY');
- define('NETKANT_NEWSLETTER_LIST_ID', 'LIST_ID');
- define('NETKANT_NEWSLETTER_FROM_NAME', 'FROM_NAME');
- define('NETKANT_NEWSLETTER_FROM_EMAIL', 'FROM_EMAIL');
- define('NETKANT_NEWSLETTER_REPLY_TO', 'REPLY_TO_EMAIL');