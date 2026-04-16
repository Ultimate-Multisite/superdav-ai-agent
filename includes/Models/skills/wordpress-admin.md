# WordPress Administration

## When to Use
Use this skill when the user asks about general WordPress settings, updates, user management, or site configuration.

## Key WP-CLI Commands

### Settings & Options
- `wp option get <key>` — Read any option value
- `wp option update <key> <value>` — Update an option
- `wp option list --search=<pattern>` — Find options by name pattern

### User Management
- `wp user list --fields=ID,user_login,user_email,roles` — List users
- `wp user get <user> --fields=ID,user_login,user_email,roles` — Get user details
- `wp user create <login> <email> --role=<role>` — Create a user
- `wp user update <user> --role=<role>` — Change user role
- `wp user meta get <user> <key>` — Read user meta

### Updates
- `wp core version` — Current WordPress version
- `wp core check-update` — Check for core updates
- `wp plugin list --fields=name,status,version,update_version` — Check plugin updates
- `wp theme list --fields=name,status,version,update_version` — Check theme updates
- `wp plugin update <plugin>` — Update a plugin
- `wp theme update <theme>` — Update a theme

### Site Info
- `wp option get siteurl` — Site URL
- `wp option get home` — Home URL
- `wp option get blogname` — Site title
- `wp option get active_plugins --format=json` — Active plugins

## REST API Patterns
- `GET /wp/v2/settings` — Read site settings
- `POST /wp/v2/settings` — Update site settings
- `GET /wp/v2/users` — List users
- `GET /wp/v2/plugins` — List plugins (requires auth)

## Verification Steps
After making changes, always verify:
1. Read back the updated value with `wp option get` or the REST API
2. Check for errors in the response
3. Confirm the change had the expected effect
