<?php
/**
 * Linear Build Engine v1.7
 * Baseline v1.5 + Direct SQL Snippet Injection.
 * * Strict Policy: No refactoring/shortening applied.
 *
 * @package WPCloudDeployerClient
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper for API Auth Headers.
 */
function wpcd_get_api_headers() {
    $user = get_option( 'wpcd_master_user' );
    $pass = get_option( 'wpcd_master_pass' );
    return array( 'Authorization' => 'Basic ' . base64_encode( $user . ':' . $pass ) );
}

/**
 * Render the Deployment Screen.
 */
function wpcd_render_deployment_screen() {
    $master_url = get_option( 'wpcd_master_url' );
    if ( ! $master_url ) {
        echo '<div class="notice notice-error"><p>Please connect to a Master site in the Connection tab first.</p></div>';
        return;
    }
    ?>
    <style>
        .wpcd-terminal { 
            background: #1c1c1c; color: #00ff00; padding: 20px; border-radius: 8px; 
            font-family: 'Courier New', monospace; font-size: 13px; min-height: 200px; 
            max-height: 600px; overflow-y: auto; border: 1px solid #333; margin-top: 20px; display:none;
            box-shadow: inset 0 0 15px #000;
        }
        .wpcd-line { margin-bottom: 8px; line-height: 1.5; border-bottom: 1px solid #2a2a2a; padding-bottom: 4px; }
        .status-wait { color: #72aee6; }
        .status-ok { color: #00ff00; }
        .status-err { color: #ff4444; font-weight: bold; }
        .status-info { color: #bbbbbb; }
        .wpcd-step-card { max-width: 850px; padding: 25px; margin-top: 20px; border-radius: 10px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
    </style>

    <div class="wrap">
        <h1>Cloud Architecture Deployer <small style="font-size: 0.5em; vertical-align: middle; opacity: 0.6;">v1.6</small></h1>
        
        <div class="wpcd-step-card">
            <h2>Step 1: Site Core Build</h2>
            <p>Installs the Master theme, core plugins, and activates licenses sequentially.</p>
            <button id="wpcd-run-build" class="button button-primary button-large" style="height: 45px; padding: 0 30px;">Start Build Sequence</button>
            <div id="wpcd-build-log" class="wpcd-terminal"></div>
        </div>

        <div class="wpcd-step-card">
            <h2>Step 2: Package Injection</h2>
            <p>Select a pre-built bundle from the Master Warehouse to inject into this site.</p>
            
            <div style="display: flex; gap: 10px; align-items: center; margin-top: 15px;">
                <select id="wpcd-package-select" style="width: 300px; height: 35px;">
                    <option value="">Connecting to Warehouse...</option>
                </select>
                <button id="wpcd-inject-package" class="button button-secondary button-large" disabled>Inject Content</button>
            </div>
            
            <div id="wpcd-inject-log" class="wpcd-terminal"></div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        const masterUrl = '<?php echo esc_js(untrailingslashit($master_url)); ?>';
        const authHeader = 'Basic <?php echo base64_encode( get_option( "wpcd_master_user" ) . ":" . get_option( "wpcd_master_pass" ) ); ?>';

        function log(container, msg, type='ok') {
            let symbol = (type === 'wait') ? '⚙ ' : (type === 'err' ? '✗ ' : '✓ ');
            $(container).show().append('<div class="wpcd-line status-'+type+'">'+symbol+msg+'</div>');
            $(container).scrollTop($(container)[0].scrollHeight);
        }

        // Fetch Packages
        $.ajax({
            url: masterUrl + '/wp-json/wpcd/v1/packages',
            beforeSend: function(xhr) { xhr.setRequestHeader('Authorization', authHeader); },
            success: function(response) {
                var select = $('#wpcd-package-select');
                select.empty().append('<option value="">-- Choose a Package --</option>');
                $.each(response, function(i, pkg) {
                    select.append('<option value="'+pkg.id+'">'+pkg.title+'</option>');
                });
                $('#wpcd-inject-package').prop('disabled', false);
            },
            error: function() {
                $('#wpcd-package-select').empty().append('<option value="">Error connecting to Warehouse</option>');
            }
        });

        // STEP 1 Build Sequence
        $('#wpcd-run-build').on('click', async function() {
            const btn = $(this);
            btn.prop('disabled', true).text('Building System...');
            const logId = '#wpcd-build-log';
            $(logId).empty().show();

            log(logId, 'Connecting to Master Warehouse...', 'wait');

            try {
                const res = await $.post(ajaxurl, { 
                    action: 'wpcd_get_manifest', 
                    nonce: '<?php echo wp_create_nonce("wpcd_build_nonce"); ?>' 
                });

                if(!res.success) throw res.data;
                const manifest = res.data;

                log(logId, 'Phase 1: Setting up Theme (' + manifest.theme + ')...', 'wait');
                await $.post(ajaxurl, { action: 'wpcd_step_theme', theme: manifest.theme });
                log(logId, 'Theme active.', 'ok');

                log(logId, 'Phase 2: Sideloading Architecture Plugins...', 'info');
                for (let plugin of manifest.plugins) {
                    log(logId, 'Sideloading: ' + plugin.slug + '...', 'wait');
                    const pluginRes = await $.post(ajaxurl, { action: 'wpcd_step_plugin', url: plugin.url });
                    if(pluginRes.success) {
                        log(logId, 'Successfully installed ' + plugin.slug, 'ok');
                    } else {
                        log(logId, 'Failed: ' + plugin.slug, 'err');
                    }
                }

                log(logId, 'Phase 3: Triggering CLI License Handshakes...', 'wait');
                await $.post(ajaxurl, { action: 'wpcd_step_license', keys: manifest.keys });
                log(logId, 'Licensing processed.', 'ok');

                log(logId, 'BUILD SEQUENCE COMPLETE.', 'ok');
                btn.text('Build Successful');

            } catch (err) {
                log(logId, 'FATAL ERROR: ' + err, 'err');
                btn.prop('disabled', false).text('Retry Sequence');
            }
        });

        // STEP 2: Package Injection
        $('#wpcd-inject-package').on('click', async function() {
            const pkgId = $('#wpcd-package-select').val();
            const logId = '#wpcd-inject-log';
            const btn = $(this);
            
            if(!pkgId) return;

            btn.prop('disabled', true).text('Injecting...');
            $(logId).empty().show();

            log(logId, 'Requesting package manifest from Master...', 'wait');

            try {
                const response = await $.post(ajaxurl, { 
                    action: 'wpcd_get_package_manifest', 
                    id: pkgId, 
                    nonce: '<?php echo wp_create_nonce("wpcd_build_nonce"); ?>' 
                });

                if(!response || response === null) { throw 'Master Site returned a blank response.'; }
                if(!response.success) { throw response.data || 'Could not fetch package data.'; }

                const pkgData = response.data;

                // 1. Plugins
                if (pkgData.plugins && pkgData.plugins.length > 0) {
                    log(logId, 'Phase 1: Installing Package Plugins...', 'info');
                    for (let plugin of pkgData.plugins) {
                        log(logId, 'Installing: ' + plugin.slug, 'wait');
                        await $.post(ajaxurl, { action: 'wpcd_step_plugin', url: plugin.url });
                        log(logId, plugin.slug + ' is now active.', 'ok');
                    }
                }

                // 2. Pages
                if (pkgData.content.pages && pkgData.content.pages.length > 0) {
                    log(logId, 'Phase 2: Injecting ' + pkgData.content.pages.length + ' pages...', 'info');
                    for (let page of pkgData.content.pages) {
                        log(logId, 'Processing Page: ' + page.title + '...', 'wait');
                        const injectRes = await $.post(ajaxurl, { 
                            action: 'wpcd_step_inject_page', 
                            page_data: page,
                            nonce: '<?php echo wp_create_nonce("wpcd_build_nonce"); ?>'
                        });
                        if(injectRes.success) log(logId, '✓ Page Created: ' + page.title, 'ok');
                        else log(logId, '✗ Failed: ' + page.title, 'err');
                    }
                }

                // 3. Forms
                if (pkgData.content.forms && pkgData.content.forms.length > 0) {
                    log(logId, 'Phase 3: Injecting ' + pkgData.content.forms.length + ' Gravity Forms...', 'info');
                    for (let form of pkgData.content.forms) {
                        log(logId, 'Processing Form: ' + form.title + '...', 'wait');
                        const formRes = await $.post(ajaxurl, {
                            action: 'wpcd_step_inject_form',
                            form_data: form,
                            nonce: '<?php echo wp_create_nonce("wpcd_build_nonce"); ?>'
                        });
                        if(formRes.success) log(logId, '✓ Form Created: ' + form.title, 'ok');
                        else log(logId, '✗ Failed: ' + form.title, 'err');
                    }
                }

                // 4. Snippets (SQL-Based v1.6)
                if (pkgData.content.snippets && pkgData.content.snippets.length > 0) {
                    log(logId, 'Phase 4: Injecting ' + pkgData.content.snippets.length + ' Code Snippets...', 'info');
                    for (let snippet of pkgData.content.snippets) {
                        log(logId, 'Processing Snippet: ' + snippet.title + '...', 'wait');
                        const snippetRes = await $.post(ajaxurl, {
                            action: 'wpcd_step_inject_snippet',
                            snippet_data: snippet,
                            nonce: '<?php echo wp_create_nonce("wpcd_build_nonce"); ?>'
                        });
                        if(snippetRes.success) log(logId, '✓ Snippet Created: ' + snippet.title, 'ok');
                        else log(logId, '✗ Failed: ' + snippet.title, 'err');
                    }
                }

                log(logId, 'INJECTION COMPLETE.', 'ok');
                btn.text('Injection Successful');

            } catch (err) {
                log(logId, 'Error: ' + err, 'err');
                btn.prop('disabled', false).text('Try Again');
            }
        });
    });
    </script>
    <?php
}

/**
 * AJAX Handlers
 */
add_action('wp_ajax_wpcd_get_manifest', function() {
    check_ajax_referer('wpcd_build_nonce', 'nonce');
    $master_url = get_option( 'wpcd_master_url' );
    $response = wp_remote_get( untrailingslashit( $master_url ) . '/wp-json/wpcd/v1/defaults', array( 'headers' => wpcd_get_api_headers(), 'timeout' => 45 ) );
    if ( is_wp_error( $response ) ) wp_send_json_error('Master unreachable.');
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    wp_send_json_success([
        'theme'   => $body['core_theme'] ?? 'astra',
        'plugins' => $body['core_plugins'] ?? [],
        'keys'    => $body['license_keys'] ?? ''
    ]);
});

// v1.7 Handler: Fetch manifest with clean buffer protection
add_action('wp_ajax_wpcd_get_package_manifest', function() {
    check_ajax_referer('wpcd_build_nonce', 'nonce');
    $id = intval($_POST['id']);
    $master_url = get_option( 'wpcd_master_url' );
    $response = wp_remote_get( untrailingslashit( $master_url ) . "/wp-json/wpcd/v1/package/$id", array( 'headers' => wpcd_get_api_headers(), 'timeout' => 45 ) );
    
    if ( is_wp_error( $response ) ) wp_send_json_error('Package fetch failed.');

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( ob_get_length() ) ob_clean();
    wp_send_json( $data );
});

add_action('wp_ajax_wpcd_step_inject_page', function() {
    check_ajax_referer('wpcd_build_nonce', 'nonce');
    $page = $_POST['page_data'];
    $new_id = wp_insert_post( array('post_title' => $page['title'], 'post_status' => 'publish', 'post_type' => 'page') );
    if ( is_wp_error( $new_id ) ) wp_send_json_error();
    update_post_meta( $new_id, '_elementor_data', $page['content'] );
    update_post_meta( $new_id, '_elementor_edit_mode', 'builder' );
    update_post_meta( $new_id, '_elementor_template_type', 'wp-page' );
    if ( ! empty( $page['settings'] ) ) { update_post_meta( $new_id, '_elementor_page_settings', $page['settings'] ); }
    wp_send_json_success();
});

add_action('wp_ajax_wpcd_step_inject_form', function() {
    check_ajax_referer('wpcd_build_nonce', 'nonce');
    $form_data = $_POST['form_data'];
    if ( ! class_exists( 'GFAPI' ) ) { wp_send_json_error('Gravity Forms is not active.'); }
    $form_id = GFAPI::add_form( $form_data );
    if ( is_wp_error( $form_id ) ) { wp_send_json_error( $form_id->get_error_message() ); }
    wp_send_json_success();
});

// v1.6 Handler: Direct SQL Injection for Code Snippets
add_action('wp_ajax_wpcd_step_inject_snippet', function() {
    global $wpdb;
    check_ajax_referer('wpcd_build_nonce', 'nonce');
    $s = $_POST['snippet_data'];
    $table_name = $wpdb->prefix . 'snippets';

    // Insert directly into the plugin's SQL table
    $result = $wpdb->insert(
        $table_name,
        array(
            'name'   => $s['title'],
            'code'   => $s['code'],
            'scope'  => $s['scope'],
            'active' => 1
        ),
        array( '%s', '%s', '%s', '%d' )
    );

    if ( false === $result ) {
        wp_send_json_error( $wpdb->last_error );
    }

    wp_send_json_success();
});

add_action('wp_ajax_wpcd_step_theme', function() {
    $theme = sanitize_text_field($_POST['theme']);
    shell_exec( sprintf( 'wp theme install %s --activate', escapeshellarg( $theme ) ) );
    wp_send_json_success();
});

add_action('wp_ajax_wpcd_step_plugin', function() {
    $url = esc_url_raw($_POST['url']);
    if ( wpcd_sideload_plugin( $url ) ) wp_send_json_success();
    else wp_send_json_error();
});

add_action('wp_ajax_wpcd_step_license', function() {
    if ( function_exists( 'wpcd_execute_cli_activation' ) ) { wpcd_execute_cli_activation( $_POST['keys'] ); }
    wp_send_json_success();
});

function wpcd_sideload_plugin( $url ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    if ( ! defined( 'FS_METHOD' ) ) define( 'FS_METHOD', 'direct' );
    $temp_file = download_url( $url, 120 );
    if ( is_wp_error( $temp_file ) ) return false;
    $upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
    $result   = $upgrader->install( $temp_file );
    @unlink( $temp_file );
    if ( is_wp_error( $result ) ) return false;
    $slug = basename( parse_url( $url, PHP_URL_PATH ), '.zip' );
    foreach ( get_plugins() as $file => $data ) { if ( strpos( $file, $slug . '/' ) === 0 ) { activate_plugin( $file ); return true; } }
    return false;
}
