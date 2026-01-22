<?php
/**
 * Handles terminal-level activation for Premium plugins.
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
	// 1. Install/Activate GF CLI add-on first (required for 'wp gf' commands)
	$install_cli_addon = 'wp plugin install gravityformscli --activate';
	shell_exec( $install_cli_addon );

	// 2. Install GF core and register key
	// Command: wp gf install --key=[key] --activate
	$command = sprintf( 'wp gf install --key=%s --activate', escapeshellarg( $key ) );
	shell_exec( $command );
}

/**
 * Generic shell command helper for future cleanup/optimization.
 */
function wpcd_run_cleanup_commands() {
	// Example: Flush rewrite rules and clear caches after deployment
	shell_exec( 'wp rewrite flush' );
	if ( function_exists( 'rocket_clean_domain' ) ) {
		shell_exec( 'wp rocket clean --confirm' );
	}
}
