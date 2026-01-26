<?php
/**
 * DNS and Domain Information Utility for Adam.
 * v1.35 - Registrar extraction from vcardArray.
 *
 * @package WPCloudDeployerClient
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the DNS Info Tab.
 */
function wpcd_render_dns_info() {
    $domain = get_option( 'wpcd_live_domain', '' );

    if ( isset( $_POST['wpcd_save_domain_nonce'] ) && wp_verify_nonce( $_POST['wpcd_save_domain_nonce'], 'wpcd_save_domain_action' ) ) {
        if ( isset( $_POST['wpcd_live_domain'] ) ) {
            $domain = sanitize_text_field( $_POST['wpcd_live_domain'] );
            update_option( 'wpcd_live_domain', $domain );
            echo '<div class="notice notice-success is-dismissible"><p>Domain saved and lookup refreshed.</p></div>';
        }
    }

    if ( empty( $domain ) ) {
        $domain = str_replace( array( 'http://', 'https://' ), '', get_site_url() );
        $domain = parse_url( 'http://' . $domain, PHP_URL_HOST );
    }

    ?>
    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2>Live Production Domain Settings</h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'wpcd_save_domain_action', 'wpcd_save_domain_nonce' ); ?>
            <input type="text" name="wpcd_live_domain" class="regular-text" placeholder="example.com" value="<?php echo esc_attr( $domain ); ?>" style="width: 300px; height: 35px; vertical-align: middle;">
            <input type="submit" class="button button-primary button-large" value="Save & Lookup">
        </form>
    </div>

    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2>DNS Status for: <?php echo esc_html( $domain ); ?></h2>
        <table class="wp-list-table widefat fixed striped" style="margin-top:20px;">
            <thead>
                <tr><th style="width: 20%;">Record Type</th><th>Value / Result</th></tr>
            </thead>
            <tbody>
                <?php
                $a_records = dns_get_record( $domain, DNS_A );
                $ip_address = ! empty( $a_records ) ? $a_records[0]['ip'] : 'Not Found';
                ?>
                <tr>
                    <td><strong>A Record (IP)</strong></td>
                    <td><code><?php echo esc_html( $ip_address ); ?></code></td>
                </tr>
                <tr>
                    <td><strong>Nameservers</strong></td>
                    <td>
                        <?php 
                        $ns_records = dns_get_record( $domain, DNS_NS );
                        if ( ! empty( $ns_records ) ) {
                            foreach ( $ns_records as $ns ) { echo '<code>' . esc_html( $ns['target'] ) . '</code><br>'; }
                        } else { echo '<span style="color:red;">No Nameservers Found</span>'; }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>MX Records</strong></td>
                    <td>
                        <?php 
                        $mx_records = dns_get_record( $domain, DNS_MX );
                        if ( ! empty( $mx_records ) ) {
                            foreach ( $mx_records as $mx ) { echo '<code>' . esc_html( $mx['target'] ) . '</code><br>'; }
                        } else { echo 'None'; }
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h2>Registry Information (RDAP)</h2>
        <div style="margin-top: 20px;">
            <?php
            $domain_parts = explode('.', $domain);
            $tld = strtolower(end($domain_parts));

            if ($tld === 'com') {
                $url = "https://rdap.verisign.com/com/v1/domain/" . $domain;
            } elseif ($tld === 'net') {
                $url = "https://rdap.verisign.com/net/v1/domain/" . $domain;
            } elseif ($tld === 'org') {
                $url = "https://rdap.publicinterestregistry.org/rdap/domain/" . $domain;
            } else {
                $url = "https://rdap.org/domain/" . $domain;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'User-Agent: PHP-RDAP-Client/1.0']);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                
                // --- Registrar Extraction Logic ---
                $registrar_name = "Not Found";
                if ( ! empty( $data['entities'] ) ) {
                    foreach ( $data['entities'] as $entity ) {
                        // Check if this entity is the registrar
                        if ( ! empty( $entity['roles'] ) && in_array( 'registrar', $entity['roles'] ) ) {
                            // Dig into the vcardArray
                            if ( ! empty( $entity['vcardArray'][1] ) && is_array( $entity['vcardArray'][1] ) ) {
                                foreach ( $entity['vcardArray'][1] as $vcard_entry ) {
                                    // Look for the "fn" (Formatted Name) entry
                                    if ( isset( $vcard_entry[0] ) && $vcard_entry[0] === 'fn' ) {
                                        $registrar_name = $vcard_entry[3]; // Index 3 holds the string
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }
                ?>
                <div style="padding: 15px; background: #f8f9fa; border: 1px solid #ccd0d4; margin-bottom: 20px;">
                    <strong>Detected Registrar:</strong> <span style="font-size: 1.2em; color: #2271b1;"><?php echo esc_html( $registrar_name ); ?></span><br>
                    <strong>Source URL:</strong> <code><?php echo esc_html( $url ); ?></code>
                </div>

                <h3>Raw JSON Response</h3>
                <pre style='background:#222; color:#0f0; padding:15px; overflow:auto; max-height:400px; border-radius: 4px;'><?php echo json_encode($data, JSON_PRETTY_PRINT); ?></pre>
                <?php
            } else {
                echo "<div class='notice notice-error'><p>Error: Server returned HTTP Code $httpCode for " . esc_html($url) . "</p></div>";
            }
            ?>
        </div>
    </div>
    <?php
}
