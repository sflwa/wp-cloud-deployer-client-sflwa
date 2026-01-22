<?php
/**
 * Connection Settings for the Master Site.
 */

function wpcd_render_connection_settings() {
    if ( isset( $_POST['wpcd_save_connection'] ) && check_admin_referer( 'wpcd_connection_action', 'wpcd_conn_nonce' ) ) {
        update_option( 'wpcd_master_url', esc_url_raw( $_POST['wpcd_master_url'] ) );
        update_option( 'wpcd_master_user', sanitize_text_field( $_POST['wpcd_master_user'] ) );
        update_option( 'wpcd_master_pass', sanitize_text_field( $_POST['wpcd_master_pass'] ) ); // App Password
        echo '<div class="updated"><p>Connection settings saved!</p></div>';
    }

    $url  = get_option( 'wpcd_master_url', '' );
    $user = get_option( 'wpcd_master_user', '' );
    $pass = get_option( 'wpcd_master_pass', '' );

    ?>
    <div class="card" style="max-width: 600px; margin-top: 20px;">
        <h3>Connection Credentials</h3>
        <p>Enter your Master site details to link this site to your library.</p>
        <form method="post">
            <?php wp_nonce_field( 'wpcd_connection_action', 'wpcd_conn_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th>Master Site URL</th>
                    <td><input type="url" name="wpcd_master_url" value="<?php echo esc_url($url); ?>" class="regular-text" placeholder="https://masterlibrary.com"></td>
                </tr>
                <tr>
                    <th>Master Username</th>
                    <td><input type="text" name="wpcd_master_user" value="<?php echo esc_attr($user); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>Application Password</th>
                    <td><input type="password" name="wpcd_master_pass" value="<?php echo esc_attr($pass); ?>" class="regular-text"></td>
                </tr>
            </table>
            <input type="submit" name="wpcd_save_connection" class="button button-primary" value="Save & Connect">
        </form>
    </div>
    <?php
}
