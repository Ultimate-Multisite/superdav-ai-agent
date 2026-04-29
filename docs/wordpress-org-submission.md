# WordPress.org Plugin Directory Submission

This document covers the complete process for submitting Superdav AI Agent to the
WordPress.org plugin directory and managing subsequent releases via SVN.

**Current status:** Pre-submission. The SVN repository at
`https://plugins.svn.wordpress.org/sd-ai-agent/` does not yet exist — it is
created by WordPress.org only after the plugin passes manual review.

---

## Table of Contents

- [Prerequisites](#prerequisites)
- [Step 1 — Submit for Review](#step-1--submit-for-review)
- [Step 2 — Wait for Approval](#step-2--wait-for-approval)
- [Step 3 — First SVN Deployment](#step-3--first-svn-deployment)
- [Step 4 — Tag the Release](#step-4--tag-the-release)
- [Subsequent Releases](#subsequent-releases)
- [Assets (Banner, Icon, Screenshots)](#assets-banner-icon-screenshots)
- [Automated SVN Deployment](#automated-svn-deployment)
- [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before submitting, confirm all of the following:

| Requirement | Status |
|-------------|--------|
| GPL-2.0-or-later license header in all PHP files | Done (t124) |
| `readme.txt` with all required sections | Done (t124) |
| Screenshots listed in `readme.txt` match `assets/` | Done (t124) |
| Sanitization/escaping audit passed | Done (t124) |
| Plugin slug `sd-ai-agent` is available on WP.org | Verify at `wordpress.org/plugins/sd-ai-agent/` |
| WordPress.org account exists for the submitter | Required |
| `wp plugin check` passes (requires Plugin Check plugin) | Run before submitting |

### Run Plugin Check

Install the [Plugin Check plugin](https://wordpress.org/plugins/plugin-check/) on a
WordPress 6.9 instance, then:

```bash
wp plugin check sd-ai-agent --format=table
```

All errors must be resolved. Warnings should be reviewed — some are acceptable with
justification in the submission notes.

---

## Step 1 — Submit for Review

1. Log in to your WordPress.org account at `https://login.wordpress.org/`
2. Navigate to: **`https://wordpress.org/plugins/developers/add/`**
3. Fill in the form:
   - **Plugin name**: Superdav AI Agent
   - **Plugin description**: (paste the short description from `readme.txt`)
   - **Plugin ZIP**: Upload the ZIP built by `bin/build.sh` (see below)
4. Submit the form

### Build the submission ZIP

```bash
# From the repo root — builds production assets and creates the ZIP
bin/build.sh
# Output: sd-ai-agent-1.2.0.zip
```

The ZIP must contain a single top-level directory named `sd-ai-agent/` with
`sd-ai-agent.php` at its root. `bin/build.sh` handles this automatically.

### What to include in the submission notes

The review team reads these. Be specific:

```
Superdav AI Agent is an agentic AI assistant for WordPress built on the official
WordPress 6.9 AI Client SDK and Abilities API. It requires a connector plugin
(e.g., the OpenAI connector) to function — it does not bundle any AI provider
credentials or make API calls without explicit user configuration.

External API calls: The plugin calls the user's configured AI provider endpoint
(OpenAI, Anthropic, or any OpenAI-compatible URL). The endpoint URL and API key
are entered by the site administrator in Settings > AI Credentials. No data is
sent to any third-party server controlled by the plugin author.

PHP 8.2+ is required (strict types, enums). WordPress 6.9+ is required (AI
Client SDK, Abilities API).
```

---

## Step 2 — Wait for Approval

- Review typically takes **1–4 weeks**
- You will receive an email at your WordPress.org account address
- The review team may request changes — respond promptly via the ticket system
- Do not resubmit the same plugin while a review is pending

### Common rejection reasons to pre-empt

| Issue | Our status |
|-------|-----------|
| Missing license headers | Fixed (t124) |
| Unescaped output | Fixed (t124) |
| Direct database queries without `$wpdb->prepare()` | Audited (t124) |
| Enqueuing scripts without version parameter | Audited (t124) |
| Calling external APIs without disclosure | Disclosed in submission notes |
| Bundling libraries that should be loaded from WP core | N/A — we use WP core APIs |

---

## Step 3 — First SVN Deployment

After approval, WordPress.org sends credentials and the SVN URL becomes active.

### Install SVN

```bash
# Ubuntu/Debian
sudo apt-get install subversion

# macOS (Homebrew)
brew install subversion

# macOS (Xcode tools — already installed on most Macs)
svn --version
```

### Check out the SVN repository

```bash
# Replace YOUR_WP_USERNAME with your WordPress.org username
svn checkout https://plugins.svn.wordpress.org/sd-ai-agent/ \
    ~/svn/sd-ai-agent \
    --username YOUR_WP_USERNAME
```

The checkout creates three directories:
- `trunk/` — the current development version (what users get when they install)
- `tags/` — immutable snapshots for each release
- `assets/` — banner, icon, and screenshot images (not bundled in the plugin ZIP)

### Copy plugin files to trunk

```bash
# Build the production ZIP first
cd /path/to/sd-ai-agent-repo
bin/build.sh

# Extract into the SVN trunk
cd ~/svn/sd-ai-agent
# Clear trunk (keep .svn metadata)
find trunk/ -mindepth 1 -delete

# Extract the built ZIP into trunk
unzip /path/to/sd-ai-agent-1.2.0.zip -d /tmp/wporg-extract/
cp -r /tmp/wporg-extract/sd-ai-agent/. trunk/
rm -rf /tmp/wporg-extract/
```

Alternatively, use `bin/deploy-wporg.sh` (see [Automated SVN Deployment](#automated-svn-deployment)).

### Add new files and commit

```bash
cd ~/svn/sd-ai-agent

# Stage all new files (SVN does not auto-track new files)
svn status | grep '^?' | awk '{print $2}' | xargs svn add

# Remove files that were deleted
svn status | grep '^!' | awk '{print $2}' | xargs svn delete

# Review what will be committed
svn status

# Commit trunk
svn commit -m "Add Superdav AI Agent v1.2.0 to trunk" \
    --username YOUR_WP_USERNAME
```

SVN will prompt for your WordPress.org password. Use your account password (not an
application password — WP.org SVN does not support application passwords).

---

## Step 4 — Tag the Release

Tags are how WordPress.org knows which version to serve for a specific version number.
The `Stable tag` in `readme.txt` must match a tag in `tags/`.

```bash
cd ~/svn/sd-ai-agent

# Copy trunk to a tag (SVN copy is instant — no file transfer)
svn copy trunk/ tags/1.2.0 -m "Tag Superdav AI Agent v1.2.0"

# Verify
svn list tags/
```

After tagging, the plugin is live on WordPress.org at:
`https://wordpress.org/plugins/sd-ai-agent/`

---

## Subsequent Releases

For each new version:

1. Update `Version:` in `sd-ai-agent.php`
2. Update `Stable tag:` in `readme.txt`
3. Add a changelog entry under `== Changelog ==` in `readme.txt`
4. Run `bin/build.sh` to build the ZIP
5. Run `bin/deploy-wporg.sh --version X.Y.Z` (see below) or follow the manual steps above
6. Tag the release: `svn copy trunk/ tags/X.Y.Z -m "Tag vX.Y.Z"`

---

## Assets (Banner, Icon, Screenshots)

WP.org assets live in the SVN `assets/` directory — they are **not** included in the
plugin ZIP. They are served directly from SVN by the WP.org CDN.

### Required files

| File | Size | Purpose |
|------|------|---------|
| `assets/banner-772x250.png` | 772×250 px | Plugin directory banner |
| `assets/banner-1544x500.png` | 1544×500 px | Retina banner (optional but recommended) |
| `assets/icon-128x128.png` | 128×128 px | Plugin icon |
| `assets/icon-256x256.png` | 256×256 px | Retina icon |
| `assets/screenshot-1.png` | Any | Screenshot 1 (matches `== Screenshots ==` in readme.txt) |
| `assets/screenshot-2.png` | Any | Screenshot 2 |
| … | … | … |

Our assets are already prepared in `assets/` in the Git repo. Copy them to SVN:

```bash
cd ~/svn/sd-ai-agent

# Copy assets from Git repo
cp /path/to/sd-ai-agent-repo/assets/banner-772x250.png  assets/
cp /path/to/sd-ai-agent-repo/assets/icon-128x128.png    assets/
cp /path/to/sd-ai-agent-repo/assets/icon-256x256.png    assets/

# Copy screenshots (rename to screenshot-N.png matching readme.txt order)
cp /path/to/sd-ai-agent-repo/assets/screenshots/screenshot-1.png assets/screenshot-1.png
# … repeat for each screenshot

svn add assets/*
svn commit -m "Add plugin assets (banner, icon, screenshots)" \
    --username YOUR_WP_USERNAME
```

Screenshot filenames in SVN must be `screenshot-1.png`, `screenshot-2.png`, etc. —
not the descriptive names used in the Git repo.

---

## Automated SVN Deployment

`bin/deploy-wporg.sh` automates the trunk update and tagging steps.

```bash
# First deployment (after SVN checkout already exists at ~/svn/sd-ai-agent)
bin/deploy-wporg.sh --version 1.2.0 --username YOUR_WP_USERNAME

# Subsequent releases
bin/deploy-wporg.sh --version 1.3.0 --username YOUR_WP_USERNAME
```

The script:
1. Builds the production ZIP via `bin/build.sh`
2. Syncs the built files into `trunk/` using `rsync`
3. Runs `svn add` on new files and `svn delete` on removed files
4. Commits trunk with a standard message
5. Creates the version tag via `svn copy`

See `bin/deploy-wporg.sh --help` for all options.

---

## Troubleshooting

### `svn: E170013: Unable to connect to a repository`

SVN is not installed or the URL is wrong. Verify:
```bash
svn info https://plugins.svn.wordpress.org/sd-ai-agent/
```
If this returns a 404, the plugin has not been approved yet.

### `svn: E215004: Authentication failed`

Your WordPress.org password is incorrect, or you are using an application password
(not supported for SVN). Use your main account password.

### `svn: E155010: The node ... is not under version control`

You added files to `trunk/` without running `svn add`. Run:
```bash
svn status | grep '^?' | awk '{print $2}' | xargs svn add
```

### Plugin not appearing on WP.org after commit

- Check that `Stable tag:` in `readme.txt` matches an existing tag in `tags/`
- WP.org CDN can take up to 15 minutes to reflect changes
- Check the plugin page directly: `https://wordpress.org/plugins/sd-ai-agent/`

### Review rejected — what next?

Read the rejection email carefully. The review team provides specific feedback.
Common fixes:
- Add missing `esc_*()` calls around output
- Add `$wpdb->prepare()` around raw SQL
- Remove or justify any external API calls not disclosed in the submission
- Fix any GPL-incompatible bundled libraries

After fixing, reply to the review ticket (do not resubmit via the form).
