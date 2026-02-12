<?php
/**
 * Linear Build Engine v2.7
 * Integrated: Options Injection, GF Entries, GravityKit Views, and Surgical Replace.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Helper: Applies surgical replacements to content if enabled.
 */
function wpcd_apply_surgical_replacements( $data ) {
    if ( !isset($_POST['replace_enabled']) || $_POST['replace_enabled'] !== 'true' ) return $data;

    $agency_id = get_option('wpcd_default_agency_id', '4833');
    $siid      = get_option('wpcd_default_siid', '');

    $replacements = array(
        'agencyID=4833'  => 'agencyID=' . $agency_id,
        'agency_id=4833' => 'agency_id=' . $agency_id,
        'agency=4833'    => 'agency=' . $agency_id,
        // Serialized safety for ITB ssid/customssid
        's:4:"4833"'     => 's:' . strlen($agency_id) . ':"' . $agency_id . '"',
    );

    return wpcd_deep_replace($data, $replacements);
}

/**
 * Recursive Search & Replace for Arrays/Strings.
 */
function wpcd_deep_replace($data, $replacements) {
    if (is_array($data)) {
        foreach ($data as $key => $value) $data[$key] = wpcd_deep_replace($value, $replacements);
    } elseif (is_string($data)) {
        foreach ($replacements as $search => $replace) $data = str_replace($search, $replace, $data);
    }
    return $data;
}

/**
 * Main Deployment UI.
 */
function wpcd_render_deployment_screen() {
    $master_url = get_option( 'wpcd_master_url' );
    if ( ! $master_url ) {
        echo '<div class="notice notice-error"><p>Connect to Master in Connection tab first.</p></div>';
        return;
    }
    ?>
    <div class="wrap">
        <h1>Cloud Architecture Deployer <small>v2.7</small></h1>
        
        <div class="wpcd-step-card" style="background:#fff; padding:20px; border:1px solid #ccd0d4; margin-top:20px;">
            <h2>Step 1: Site Core Build</h2>
            <button id="wpcd-run-build" class="button button-primary">Start Build Sequence</button>
            <div id="wpcd-build-log" style="display:none; background:#1c1c1c; color:#00ff00; padding:15px; margin-top:10px; font-family:monospace;"></div>
        </div>

        <div class="wpcd-step-card" style="background:#fff; padding:20px; border:1px solid #ccd0d4; margin-top:20px;">
            <h2>Step 2: Package Injection</h2>
            <select id="wpcd-package-select" style="width:250px;"><option value="">Connecting...</option></select>
            <label><input type="checkbox" id="wpcd-enable-replace" value="1" checked> Perform Surgical Replace</label>
            <button id="wpcd-inject-package" class="button button-secondary" disabled>Inject Content</button>
            <div id="wpcd-inject-log" style="display:none; background:#1c1c1c; color:#00ff00; padding:15px; margin-top:10px; font-family:monospace;"></div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        const masterUrl = '<?php echo esc_js(untrailingslashit($master_url)); ?>';
        const authHeader = 'Basic <?php echo base64_encode( get_option( "wpcd_master_user" ) . ":" . get_option( "wpcd_master_pass" ) ); ?>';

        function log(id, msg) { $(id).show().append('<div>'+msg+'</div>'); }

        // Fetch Packages
        $.ajax({
            url: masterUrl + '/wp-json/wpcd/v1/packages?v=' + Date.now(),
            beforeSend: function(xhr) { xhr.setRequestHeader('Authorization', authHeader); },
            success: function(res) {
                let s = $('#wpcd-package-select');
                s.empty().append('<option value="">-- Select Package --</option>');
                $.each(res, function(i, p) { s.append('<option value="'+p.id+'">'+p.title+'</option>'); });
                $('#wpcd-inject-package').prop('disabled', false);
            }
        });

        // Injection Trigger
        $('#wpcd-inject-package').on('click', async function() {
            const pkgId = $('#wpcd-package-select').val();
            const replace = $('#wpcd-enable-replace').is(':checked');
            const logId = '#wpcd-inject-log';
            if(!pkgId) return;

            $(logId).empty().show();
            log(logId, 'Fetching manifest...');

            const res = await $.post(ajaxurl, { action: 'wpcd_get_package_manifest', id: pkgId, nonce: '<?php echo wp_create_nonce("wpcd_build_nonce"); ?>' });
            const pkg = res.data;

            // 1. Options
            if(pkg.content.options) {
                log(logId, 'Injecting System Options...');
                for(let o of pkg.content.options) await $.post(ajaxurl, { action: 'wpcd_step_inject_option', opt_data: o, replace_enabled: replace, nonce: '<?php echo wp_create_nonce("wpcd_build_nonce"); ?>' });
            }

            // 2. Forms & Entries
            if(pkg.content.forms) {
                log(logId, 'Injecting Forms & Results...');
                for(let f of pkg.content.forms) await $.post(ajaxurl, { action: 'wpcd_step_inject_form', form_data: f, replace_enabled: replace, nonce: '<?php echo wp_create_nonce("wpcd_build_nonce"); ?>' });
            }

            // 3. GravityKit Views
            if(pkg.content.views) {
                log(logId, 'Injecting GravityKit Views...');
                for(let v of pkg.content.views) await $.post(ajaxurl, { action: 'wpcd_step_inject_view', view_data: v, replace_enabled: replace, nonce: '<?php echo wp_create_nonce("wpcd_build_nonce"); ?>' });
            }

            // 4. Pages & Snippets... (Standard Logic)
            log(logId, 'Injection Complete.');
        });
    });
    </script>
    <?php
}

/** AJAX Handlers **/

// Options
add_action('wp_ajax_wpcd_step_inject_option', function() {
    check_ajax_referer('wpcd_build_nonce', 'nonce');
    $opt = wpcd_apply_surgical_replacements(wp_unslash($_POST['opt_data']));
    update_option($opt['name'], maybe_unserialize($opt['value']));
    wp_send_json_success();
});

// Forms + Entries
add_action('wp_ajax_wpcd_step_inject_form', function() {
    check_ajax_referer('wpcd_build_nonce', 'nonce');
    if ( ! class_exists( 'GFAPI' ) ) wp_send_json_error();
    $data = wpcd_apply_surgical_replacements(wp_unslash($_POST['form_data']));
    $new_id = GFAPI::add_form($data);
    if ($new_id && !empty($data['entries'])) {
        foreach($data['entries'] as $e) { $e['form_id'] = $new_id; GFAPI::add_entry($e); }
    }
    wp_send_json_success();
});

// GravityKit
add_action('wp_ajax_wpcd_step_inject_view', function() {
    check_ajax_referer('wpcd_build_nonce', 'nonce');
    $view = wpcd_apply_surgical_replacements(wp_unslash($_POST['view_data']));
    $new_id = wp_insert_post(['post_title'=>$view['title'], 'post_status'=>'publish', 'post_type'=>'gravityview']);
    if (!empty($view['meta'])) {
        foreach($view['meta'] as $k => $v) update_post_meta($new_id, $k, $v[0]);
    }
    wp_send_json_success();
});
