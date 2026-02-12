<?php
/**
 * REST API Endpoints v1.9
 * Bundles Forms, Entries, Views, and System Options.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ... (register_rest_route logic from previous versions) ...

function wpcd_get_single_package_data( $request ) {
    global $wpdb;
    $package_id = $request['id'];
    $package    = get_post( $package_id );
    $data = [ 'success' => true, 'data' => [ 'title' => $package->post_title, 'content' => [ 'pages'=>[], 'forms'=>[], 'views'=>[], 'snippets'=>[], 'options'=>[] ]]];

    // 1. Gravity Forms + Entries
    if ( class_exists( 'GFAPI' ) ) {
        $form_ids = get_post_meta( $package_id, '_wpcd_forms', true ) ?: [];
        foreach ( $form_ids as $fid ) {
            $form = GFAPI::get_form( $fid );
            $form['entries'] = GFAPI::get_entries( $fid, [], null, ['page_size'=>50] );
            $data['data']['content']['forms'][] = $form;
        }
    }

    // 2. GravityKit Views
    $view_ids = get_post_meta( $package_id, '_wpcd_views', true ) ?: [];
    foreach ( $view_ids as $vid ) {
        $view = get_post( $vid );
        if ( $view ) $data['data']['content']['views'][] = [ 'title' => $view->post_title, 'meta' => get_post_custom( $vid ) ];
    }

    // 3. System Options
    $opt_keys = get_post_meta( $package_id, '_wpcd_options', true ) ?: [];
    foreach ( $opt_keys as $key ) {
        $val = get_option($key);
        if($val !== false) $data['data']['content']['options'][] = [ 'name' => $key, 'value' => maybe_serialize($val) ];
    }

    // ... (Add Pages/Snippets processing) ...

    if ( ob_get_length() ) ob_clean();
    wp_send_json( $data );
}
