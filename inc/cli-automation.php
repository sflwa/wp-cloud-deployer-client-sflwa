<?php
/**
 * CLI Automation Service: Handles terminal-level activations and cleanup.
 *
 * @package WPCloudDeployerClient
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Orchestrator: Loops through license strings and triggers specific CLI logic.
 *
 * @param string $license_string Format: slug|key (one per line)
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

			case 'astra-addon':
				wpcd_cli_activate_astra( $key );
				break;
		}
	}

	// Finalize the environment
	wpcd_cli_cleanup();
}

/**
 * Elementor Pro Activation.
 */
function wpcd_cli_activate_elementor( $key ) {
	// Ensure plugin is active before licensing
	shell_exec( 'wp plugin activate elementor-pro' );
	
	$command = sprintf( 'wp elementor-pro license activate %s', escapeshellarg( $key ) );
	shell_exec( $command );
}

/**
 * Astra Pro Activation (Brainstorm Force).
 * Requires force-activation of both theme and addon to register correctly.
 */
function wpcd_cli_activate_astra( $key ) {
	// 1. Ensure the Parent Theme is active
	shell_exec( 'wp theme activate astra' );

	// 2. Ensure the Addon Plugin is active
	shell_exec( 'wp plugin activate astra-addon' );

	// 3. Clear any existing/stuck license data
	shell_exec( 'wp brainstormforce license deactivate astra-addon' );

	// 4. Activate new license
	$command = sprintf( 
		'wp brainstormforce license activate astra-addon %s', 
		escapeshellarg( $key ) 
	);
	shell_exec( $command );
}

/**
 * Gravity Forms Activation.
 */
function wpcd_cli_activate_gravityforms( $key ) {
	// 1. Install/Activate GF CLI add-on (Needed for 'wp gf' commands)
	shell_exec( 'wp plugin install gravityformscli --activate' );

	// 2. Register key and install core
	$command = sprintf( 'wp gf install --key=%s --activate', escapeshellarg( $key ) );
	shell_exec( $command );
}

/**
 * Post-Build Cleanup and Optimization.
 */
function wpcd_cli_cleanup() {
	// 1. Remove Gravity Forms CLI helper (No longer needed)
	shell_exec( 'wp plugin deactivate gravityformscli' );
	shell_exec( 'wp plugin delete gravityformscli' );

	// 2. Flush Rewrite Rules (Prevents 404s on new installs)
	shell_exec( 'wp rewrite flush' );

	// 3. Purge SiteGround Cache if SG Optimizer is active
	if ( class_exists( 'SG_CachePress' ) ) {
		shell_exec( 'wp sg purge' );
	}

	// 4. Clear Elementor CSS Cache
	if ( class_exists( '\Elementor\Plugin' ) ) {
		shell_exec( 'wp elementor flush_css' );
	}
}
