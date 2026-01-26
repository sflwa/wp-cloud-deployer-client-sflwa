<?php
/**
 * Connection Settings for the Master Site.
 * Updated: Added Global Deployment Variables.
 */

function wpcd_render_connection_settings() {
    // 1. Handle Saving All Options
    if ( isset( $_POST['wpcd_save_connection'] ) && check_admin_referer( 'wpcd_connection_action', 'wpcd_conn_nonce' ) ) {
        update_option( 'wpcd_master_url', esc_url_raw( $_POST['wpcd_master_url'] ) );
        update_option( 'wpcd_master_user', sanitize_text_field( $_POST['wpcd_master_user'] ) );
        update_option( 'wpcd_master_pass', sanitize_text_field( $_POST['wpcd_master_pass'] ) ); 
        
        // Save the new Global Variables
        update_option( 'wpcd_default_agency_id', sanitize_text_field( $_POST['wpcd_default_agency_id'] ) );
        update_option( 'wpcd_search_2', sanitize_text_field( $_POST['wpcd_search_2'] ) );
        update_option( 'wpcd_replace_2', sanitize_text_field( $_POST['wpcd_replace_2'] ) );
        update_option( 'wpcd_search_3', sanitize_text_field( $_POST['wpcd_search_3'] ) );
        update_option( 'wpcd_replace_3', sanitize_text_field( $_POST['wpcd_replace_3'] ) );

        echo '<div class="updated"><p>Connection settings and deployment variables saved!</p></div>';
    }

    // 2. Fetch Existing Values
    $url  = get_option( 'wpcd_master_url', '' );
    $user = get_option( 'wpcd_master_user', '' );
    $pass = get_option( 'wpcd_master_pass', '' );
    
    $agency_id = get_option( 'wpcd_default_agency_id', '4833' );
    $search_2  = get_option( 'wpcd_search_2', '' );
    $replace_2 = get_option( 'wpcd_replace_2', '' );
    $search_3  = get_option( 'wpcd_search_3', '' );
    $replace_3 = get_option( 'wpcd_replace_3', '' );

    ?>
    <div class="wrap">
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h3>Connection Credentials</h3>
            <p>Enter your Master site details to link this site to your library.</p>
            <form method="post">
                <?php wp_nonce_field( 'wpcd_connection_action', 'wpcd_conn_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Master Site URL</th>
                        <td><input type="url" name="wpcd_master_url" value="<?php echo esc_url($url); ?>" class="regular-text" placeholder="https://masterlibrary.com"></td>
                    </tr>
                    <tr>
                        <th scope="row">Master Username</th>
                        <td><input type="text" name="wpcd_master_user" value="<?php echo esc_attr($user); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Application Password</th>
                        <td><input type="password" name="wpcd_master_pass" value="<?php echo esc_attr($pass); ?>" class="regular-text"></td>
                    </tr>
                </table>

                <hr style="margin: 20px 0;">

                <h3>Global Deployment Variables</h3>
                <p class="description">Define the strings to be surgically replaced during package injection.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Default Agency ID</th>
                        <td>
                            <input type="text" name="wpcd_default_agency_id" value="<?php echo esc_attr($agency_id); ?>" class="regular-text" style="width: 150px;">
                            <p class="description">This replaces all instances of <code>4833</code> found in <code>agency_id=</code> or <code>agencyID=</code> context.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Replacement Pair 2</th>
                        <td>
                            <input type="text" name="wpcd_search_2" placeholder="Search String" value="<?php echo esc_attr($search_2); ?>" style="width: 180px;">
                            <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px;"></span>
                            <input type="text" name="wpcd_replace_2" placeholder="Replace With" value="<?php echo esc_attr($replace_2); ?>" style="width: 180px;">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Replacement Pair 3</th>
                        <td>
                            <input type="text" name="wpcd_search_3" placeholder="Search String" value="<?php echo esc_attr($search_3); ?>" style="width: 180px;">
                            <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px;"></span>
                            <input type="text" name="wpcd_replace_3" placeholder="Replace With" value="<?php echo esc_attr($replace_3); ?>" style="width: 180px;">
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="wpcd_save_connection" class="button button-primary button-large" value="Save All Settings">
                </p>
            </form>
        </div>
    </div>
    <?php
}
