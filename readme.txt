=== WP Cloud Deployer Client by SFLWA ===
Contributors: sflwa
Tags: deployment, automation, astra, elementor, gravity-forms
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.0.0
License: GPLv2 or later

== Description ==

The "Receiver" component of the SFLWA Cloud Deployment system. This plugin connects to your Master Warehouse to pull core architecture, license keys, and pre-built content packages.

Designed specifically for high-speed SiteGround environments using WP-CLI.

== Installation ==

1. Upload the `wp-cloud-deployer-client-sflwa` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to 'Cloud Deploy' in the sidebar.
4. Go to the 'Connect' tab and enter your Master Site URL and Application Password.

== CLI Requirements ==

This plugin automates premium activations using the following terminal commands. Ensure WP-CLI is available on your server:

* **Elementor Pro:** `wp elementor-pro license activate <key>`
* **Astra Pro:** `wp brainstormforce license activate astra-addon <key>`
* **Gravity Forms:** `wp gf install --key=<key> --activate` (Requires Gravity Forms CLI add-on, which is auto-installed and then cleaned up by this plugin).

== License Key Format ==

In your Master Warehouse "License Warehouse" field, ensure keys are entered one per line using the slug-pipe-key format:

`elementor-pro|your-key`
`astra-addon|your-key`
`gravityforms|your-key`

== Frequently Asked Questions ==

= Why did the ZIP installation fail? =
Ensure the Master site's `/uploads/wpcd-exports/` folder is accessible and that the Application Password has 'edit_posts' capabilities.

= Does this work on shared hosting? =
It requires `shell_exec` permissions to run the CLI activation commands. Most SiteGround plans support this by default.

== Changelog ==

= 1.0.0 =
* Initial release.
* Added Step 1: Core Architecture deployment.
* Added Astra Pro, Elementor Pro, and Gravity Forms CLI automation.
* Added DNS lookup utility.
