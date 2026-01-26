<?php
/**
 * Linear Build Engine v2.6
 * Integrated: Options Injection, GF Entries, GravityKit Views, and Cache Busting.
 *
 * @package WPCloudDeployerClient
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper: Applies surgical replacements to content if enabled in the UI.
 */
function wpcd_apply_surgical_replacements( $data ) {
    $enable_replace = isset($_POST['replace_enabled']) && $_POST['replace_enabled'] === 'true';
    if ( ! $enable_replace ) return $data;

    $agency_id = get_option('wpcd_default_agency_id', '4833');

    $replacements = array(
        // Surgical context for Agency ID to avoid touching phone numbers
        'agency_id=4833' => 'agency_id=' . $agency_id,
        'agencyID=4833'  => 'agencyID=' . $agency_id,
        // Serialized string context for ITB/Options logic
        's:4:"4833"'     => 's:' . strlen($agency_id) . ':"' . $agency_id . '"',
    );

    // Add generic pairs from the Connection tab
    for ($i = 2; $i <= 3; $i++) {
        $s = get_option("wpcd_search_$i");
        $r = get_option("wpcd_replace_$i");
        if (!empty($s)) $replacements[$s] = $r;
    }

    return wpcd_deep_replace($data, $replacements);
}

/**
 * Deep recursive search and replace for arrays and strings.
 */
function wpcd_deep_replace($data, $replacements) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = wpcd_deep_replace($value, $replacements);
        }
    } elseif (is_string($data)) {
        foreach ($replacements as $search => $replace) {
            $data = str_replace($search, $replace, $data);
        }
    }
    return $data;
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
        <h1>Cloud Architecture Deployer <small style="font-size: 0.5em; vertical-align: middle; opacity: 0.6;">v2.6</small></h1>
        
        <div class="wpcd-step-card">
            <h2>Step 1: Site Core Build</h2>
            <p>Installs the Master theme, core plugins, and activates licenses sequentially.</p>
            <button id="wpcd-run-build" class="button button-primary button-large" style="height: 45px; padding: 0 30px;">Start Build Sequence</button>
            <div id="wpcd-build-log" class="wpcd-terminal"></div>
        </div>

        <div class="wpcd-step-card">
            <h2>Step 2: Package Injection</h2>
            <p>Select a pre-built bundle. Replacements are optional based on the toggle below.</p>
            
            <div style="display: flex; gap: 10px; align-items: center; margin-top: 15px;">
                <select id="wpcd-package-select" style="width: 250px; height: 35px;">
                    <option value="">Connecting to Warehouse...</option>
                </select>

                <label style="background: #f0f6fb; padding: 5px 10px; border-radius: 4px; border: 1px solid #c3e1f9; cursor: pointer;">
                    <input type="checkbox" id="wpcd-enable-replace" value="1"> <strong>Perform Variable Replace</strong>
                </label>

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

        // Fetch Packages with Cache Buster
        $.ajax({
            url: masterUrl + '/wp-json/wpcd/v1/packages?v=' + Date.now(),
            beforeSend: function(xhr) { xhr.setRequestHeader('Authorization', authHeader); },
            success: function(response) {
                var select = $('#wpcd-package-select');
                select.empty().append('<option value="">-- Choose a Package --</option>');
                $.each(response, function(i, pkg) {
                    select.append('<option value="'+pkg.id+'">'+pkg.title+'</option>');
                });
                $('#wpcd-inject-package').prop('disabled', false);
            }
        });

        // STEP 1 build sequence...
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
                    if(pluginRes.success) log(logId, 'Successfully installed ' + plugin.slug, 'ok');
                    else log(logId, 'Failed: ' + plugin.slug, 'err');
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
            const replaceEnabled = $('#wpcd-enable-replace').is(':checked');
            const logId = '#wpcd-inject-log';
            const btn = $(this);
            
            if(!pkgId) return;
            btn.prop('disabled', true).text('Injecting...');
            $(logId).empty().show();

            try {
                const response = await $.post(ajaxurl, { 
                    action: 'wpcd_get_package_manifest', 
                    id: pkgId, 
                    nonce: '<?php echo wp_create_nonce("wpcd_build_nonce"); ?>' 
                });

                if(!response.success) throw response.data;
                const pkgData = response.data;

                // 1. Options Injection (ITB, etc.)
                if (pkgData.content.options) {
                    log(logId, 'Phase 1: Syncing System Options...', 'info');
                    for (let opt of pkgData.content.options) {
                        await $.post(ajaxurl, { 
                            action: 'wpcd_step_inject_option', 
                            opt_data: opt, 
                            replace_enabled: replaceEnabled,
                            nonce: '<?php echo wp_create_nonce("wpcd_build_nonce"); ?>' 
                        });
                    }
                }

                // 2. Plugins
                if (pkgData.plugins) {
                    for (let plugin of pkgData.plugins) {
                        await $.post(ajaxurl, { action: 'wpcd_step_plugin', url: plugin.url });
                    }
                }

                // 3. Pages
                if (pkgData.content.pages) {
                    for (let page of pkgData.content.pages) {
                        log(logId, 'Injecting Page: ' + page.title, 'wait');
                        await $.post(ajaxurl, { 
                            action: 'wpcd_step_inject_page', 
                            page_data: page,
                            replace_enabled: replaceEnabled,
                            nonce: '<?php echo wp_create_nonce("wpcd_build_nonce"); ?>'
                        });
                    }
                }

                // 4. Forms & Entries
                if (pkgData.content.forms) {
                    for (let form of pkgData.content.forms) {
                        log(logId, 'Injecting Form: ' + form.title, 'wait');
                        await $.post(ajaxurl, {
                            action: 'wpcd_step_inject_form',
                            form_data: form,
                            replace_enabled: replaceEnabled,
                            nonce: '<?php echo wp_create_nonce("wpcd_build_nonce"); ?>'
                        });
                    }
                }

                // 5. GravityKit Views
                if (pkgData.content.views) {
                    log(logId, 'Phase 5: Injecting GravityKit Views...', 'info');
                    for (let view of pkgData.content.views) {
                        log(logId, 'Injecting View: ' + view.title, 'wait');
                        await $.post(ajaxurl, {
                            action: 'wpcd_step_inject_view',
                            view_data: view,
                            replace_enabled: replaceEnabled,
                            nonce: '<?php echo wp_create_nonce("wpcd_build_nonce"); ?>'
                        });
                    }
                }

                // 6. Snippets
                if (pkgData.content.snippets) {
                    for (let snippet of pkgData.content.snippets) {
                        log(logId, 'Injecting Snippet: ' + snippet.title, 'wait');
                        await $.post(ajaxurl, {
                            action: 'wpcd_step_inject_snippet',
                            snippet_data: snippet,
                            replace_enabled: replaceEnabled,
                            nonce: '<?php echo wp_create_nonce("wpcd_build_nonce"); ?>'
                        });
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

function wpcd_get_api_headers() {
    $user = get_option( 'wpcd_master_user' );
    $pass = get_option( 'wpcd_master_pass' );
    return array( 'Authorization' => 'Basic ' . base64_encode( $user . ':' . $pass ) );
}

add_action('wp_ajax_wpcd_get_manifest', function() {
    check_ajax_referer('wpcd_build_nonce', 'nonce');
    $master_url = get_option( 'wpcd_master_url' );
    $response = wp_remote_get( untrailingslashit( $master_url ) . '/wp-json/wpcd/v1/defaults?v=' . time(), array( 'headers' => wpcd_get_api_headers(), 'timeout' => 45 ) );
    if ( is_wp_error( $response ) ) wp_send_json_error('Master unreachable.');
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    wp_send_json_success([
        'theme'   => $body['core_theme'] ?? 'astra',
        'plugins' => $body['core_plugins'] ?? [],
        'keys'    => $body['license_keys'] ?? ''
    ]);
});

add_action('wp_ajax_wpcd_get_package_manifest', function() {
    check_ajax_referer('wpcd_build_nonce', 'nonce');
    $id = intval($_POST['id']);
    $master_url = get_option( 'wpcd_master_url' );
    $response = wp_remote_get( untrailingslashit( $master_url ) . "/wp-json/wpcd/v1/package/$id?v=" . time(), array( 'headers' => wpcd_get_api_headers(), 'timeout' => 45 ) );
    if ( is_wp_error( $response ) ) wp_send_json_error('Package fetch failed.');
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    wp_send_json( $data );
});

add_action('wp_ajax_wpcd_step_inject_option', function() {
    check_ajax_referer('wpcd_build_nonce', 'nonce');
    $opt = wpcd_apply_surgical_replacements(wp_unslash($_POST['opt_data']));
    $protected = array('siteurl', 'home', 'admin_email');
    if (in_array($opt['name'], $protected)) wp_send_json_error();
    update_option($opt['name'], maybe_unserialize($opt['value']));
    wp_send_json_success();
});

add_action('wp_ajax_wpcd_step_inject_page', function() {
    check_ajax_referer('wpcd_build_nonce', 'nonce');
    $page = wpcd_apply_surgical_replacements(wp_unslash($_POST['page_data']));
    $new_id = wp_insert_post( array('post_title' => $page['title'], 'post_status' => 'publish', 'post_type' => 'page') );
    update_post_meta( $new_id, '_elementor_data', $page['content'] );
    update_post_meta( $new_id, '_elementor_edit_mode', 'builder' );
    wp_send_json_success();
});

add_action('wp_ajax_wpcd_step_inject_form', function() {
    check_ajax_referer('wpcd_build_nonce', 'nonce');
    if ( ! class_exists( 'GFAPI' ) ) wp_send_json_error('GF missing.');
    $form_data = wpcd_apply_surgical_replacements(wp_unslash($_POST['form_data']));
    $new_form_id = GFAPI::add_form( $form_data );
    if ($new_form_id && !empty($form_data['entries'])) {
        foreach ($form_data['entries'] as $entry) {
            $entry['form_id'] = $new_form_id;
            GFAPI::add_entry($entry);
        }
    }
    wp_send_json_success();
});

add_action('wp_ajax_wpcd_step_inject_view', function() {
    check_ajax_referer('wpcd_build_nonce', 'nonce');
    $view = wpcd_apply_surgical_replacements(wp_unslash($_POST['view_data']));
    $new_view_id = wp_insert_post( array('post_title' => $view['title'], 'post_status' => 'publish', 'post_type' => 'gravityview') );
    if (!empty($view['meta'])) {
        foreach ($view['meta'] as $k => $v) update_post_meta($new_view_id, $k, $v);
    }
    wp_send_json_success();
});

add_action('wp_ajax_wpcd_step_inject_snippet', function() {
    global $wpdb;
    check_ajax_referer('wpcd_build_nonce', 'nonce');
    $s = wpcd_apply_surgical_replacements(wp_unslash($_POST['snippet_data']));
    $wpdb->insert($wpdb->prefix . 'snippets', array('name' => $s['title'], 'code' => $s['code'], 'scope' => $s['scope'], 'active' => 1));
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
    $temp_file = download_url( $url . '?v=' . time(), 120 );
    if ( is_wp_error( $temp_file ) ) return false;
    $upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
    $result   = $upgrader->install( $temp_file );
    @unlink( $temp_file );
    if ( is_wp_error( $result ) ) return false;
    $slug = basename( parse_url( $url, PHP_URL_PATH ), '.zip' );
    foreach ( get_plugins() as $file => $data ) { if ( strpos( $file, $slug . '/' ) === 0 ) { activate_plugin( $file ); return true; } }
    return false;
}
