=== WP Cloud Deployer Client by SFLWA ===
Contributors: sflwa
Tags: deployment, automation, astra, elementor, gravity-forms, dns-lookup, rdap
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 2.0.0
License: GPLv2 or later

== Description ==

The "Receiver" component of the SFLWA Cloud Deployment system. This plugin connects to your Master Warehouse to pull core architecture, license keys, and pre-built content packages. 

Version 2.0 introduces a dedicated DNS Intelligence Suite, allowing developers to verify registrar data and email service providers before final deployment.

== Installation ==

1. Upload the `wp-cloud-deployer-client-sflwa` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to 'Cloud Deploy' in the sidebar.
4. Go to the 'Connect' tab and enter your Master Site URL and Application Password.
5. Use the 'Domain/DNS Info' tab to configure your target production domain.

== DNS Intelligence Suite ==

The plugin now features a three-tier DNS utility:
* **Tier 1 (Environment):** Displays local Server IP and Development URL.
* **Tier 2 (Registry/RDAP):** Uses a native cURL engine to query Verisign (.com/.net) and PIR (.org) for authoritative registrar data.
* **Tier 3 (DNS & Email):** Performs real-time lookups for A-Records, Nameservers, and MX records to provide a "Best Guess" for Email Providers like Microsoft 365 or Google Workspace.

== CLI Requirements ==

This plugin automates premium activations using the following terminal commands. Ensure WP-CLI is available on your server:

* **Elementor Pro:** `wp elementor-pro license activate <key>`
* **Astra Pro:** `wp brainstormforce license activate astra-addon <key>`
* **Gravity Forms:** `wp gf install --key=<key> --activate`

== Frequently Asked Questions ==

= Why is the DNS lookup failing? =
The plugin uses native PHP cURL to bypass SiteGround's wrapper restrictions. If a lookup fails, ensure your server can make outbound HTTPS requests to `rdap.verisign.com` or `rdap.publicinterestregistry.org`.

= How do I know if the site is pointed correctly? =
The 'Domain/DNS Info' tab compares the live A-record to your `$_SERVER['SERVER_ADDR']` and provides a "Quick-Tip" if they do not match.

== Changelog ==

= 2.0.0 =
* Offloaded DNS logic to a dedicated tab with a three-div structure.
* Implemented native cURL RDAP engine for high-reliability registry lookups.
* Added "Best Guess" email provider detection based on MX patterns.
* Cleaned up Deployer Engine by removing redundant Step 3 manual inputs.

= 1.0.0 =
* Initial release.
