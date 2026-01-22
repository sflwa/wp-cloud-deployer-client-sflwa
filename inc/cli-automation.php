<?php
/**
 * Handles terminal-level activation for Premium plugins and post-deployment cleanup.
 *
 * @package WPCloudDeployerClient
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes CLI commands for specific plugins based on the license key string.
 *
 * @param string $license_string Raw slug|key from Master.
 */
function wpcd_execute_cli_activation( $license_string ) {
	if ( empty( $license_string ) ) {
		return;
	}

	$lines = explode( "\n", $license_string );

	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( empty( $line ) || strpos( $line, '|' ) === false ) {
			continue;
		}

		list( $slug, $key ) = explode( '|', $line );

		switch ( $slug ) {
			case 'elementor-pro':
				wpcd_cli_activate_elementor( $key );
				break;

			case 'gravityforms':
				wpcd_cli_activate_gravityforms( $key );
				break;
		}
	}

	// Run cleanup once all keys are processed
	wpcd_cli_cleanup();
}

/**
 * Elementor Pro Activation via CLI.
 */
function wpcd_cli_activate_elementor( $key ) {
	// Command: wp elementor-pro license activate [key]
	$command = sprintf( 'wp elementor-pro license activate %s', escapeshellarg( $key ) );
	shell_exec( $command );
}

/**
 * Gravity Forms Activation via CLI.
 */
function wpcd_cli_activate_gravityforms( $key ) {
	// 1. Install/Activate GF CLI add-on (Required for 'wp gf' commands)
	shell_exec( 'wp plugin install gravityformscli --activate' );

	// 2. Register GF core key and activate
	$command = sprintf( 'wp gf install --key=%s --activate', escapeshellarg( $key ) );
	shell_exec( $command );
}

/**
 * Cleanup Service: Deletes helper plugins and flushes caches.
 */
function wpcd_cli_cleanup() {
	// 1. Delete Gravity Forms CLI helper (No longer needed after activation)
	shell_exec( 'wp plugin deactivate gravityformscli' );
	shell_exec( 'wp plugin delete gravityformscli' );

	// 2. Flush Rewrite Rules
	shell_exec( 'wp rewrite flush' );

	// 3. SiteGround Specific: Flush Object Cache if available
	if ( class_exists( 'SG_CachePress' ) ) {
		shell_exec( 'wp sg purge' );
	}

	// 4. Clear Elementor CSS Cache to prevent styling issues on import
	if ( class_exists( '\Elementor\Plugin' ) ) {
		shell_exec( 'wp elementor flush_css' );
	}
}
