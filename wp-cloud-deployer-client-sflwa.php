<?php
/**
 * Plugin Name:       WP Cloud Deployer Client by SFLWA
 * Description:       The "Receiver" plugin to pull and configure content from your Master library.
 * Version:           1.0.0
 * Author:            SFLWA
 * License:           GPL-2.0-or-later
 * Text Domain:       wpcd-client
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constants
define( 'WPCD_CLIENT_VERSION', '1.0.0' );
define( 'WPCD_CLIENT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPCD_CLIENT_URL', plugin_dir_url( __FILE__ ) );

// Core Includes
require_once WPCD_CLIENT_PATH . 'inc/settings-connection.php';
require_once WPCD_CLIENT_PATH . 'inc/deployer-engine.php';
require_once WPCD_CLIENT_PATH . 'inc/cli-automation.php';
require_once WPCD_CLIENT_PATH . 'inc/dns-lookup.php';

// Branding the sidebar menu based on Master's name (coming soon)
add_action( 'admin_menu', function() {
    add_menu_page(
        'Cloud Deploy',
        'Cloud Deploy',
        'manage_options',
        'wpcd-client',
        'wpcd_client_dashboard',
        'dashicons-cloud-download',
        25
    );
});

function wpcd_client_dashboard() {
    ?>
    <div class="wrap">
        <h1>Cloud Deployer Dashboard</h1>
        <p>Welcome, Adam. Use the tabs below to connect to your library and start your site build.</p>
        <h2 class="nav-tab-wrapper">
            <a href="?page=wpcd-client&tab=connect" class="nav-tab <?php echo !isset($_GET['tab']) || $_GET['tab'] == 'connect' ? 'nav-tab-active' : ''; ?>">Connect to Master</a>
            <a href="?page=wpcd-client&tab=deploy" class="nav-tab <?php echo isset($_GET['tab']) && $_GET['tab'] == 'deploy' ? 'nav-tab-active' : ''; ?>">Deploy Content</a>
            <a href="?page=wpcd-client&tab=dns" class="nav-tab <?php echo isset($_GET['tab']) && $_GET['tab'] == 'dns' ? 'nav-tab-active' : ''; ?>">DNS Info</a>
        </h2>
        <?php
        $tab = isset($_GET['tab']) ? $_GET['tab'] : 'connect';
        if ($tab == 'connect') wpcd_render_connection_settings();
        if ($tab == 'deploy') wpcd_render_deployment_screen();
        if ($tab == 'dns') wpcd_render_dns_info();
        ?>
    </div>
    <?php
}
