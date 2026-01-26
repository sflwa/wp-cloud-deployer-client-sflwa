<?php
/**
 * DNS and Domain Information Utility.
 * v1.37 - Finalized with "Best Guess" Email Provider Logic.
 *
 * @package WPCloudDeployerClient
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wpcd_render_dns_info() {
    // --- Initial Data Prep ---
    $domain = get_option( 'wpcd_live_domain', '' );
    if ( isset( $_POST['wpcd_save_domain_nonce'] ) && wp_verify_nonce( $_POST['wpcd_save_domain_nonce'], 'wpcd_save_domain_action' ) ) {
        if ( isset( $_POST['wpcd_live_domain'] ) ) {
            $domain = sanitize_text_field( $_POST['wpcd_live_domain'] );
            update_option( 'wpcd_live_domain', $domain );
        }
    }
    if ( empty( $domain ) ) {
        $domain = str_replace( array( 'http://', 'https://' ), '', get_site_url() );
        $domain = parse_url( 'http://' . $domain, PHP_URL_HOST );
    }

    $server_ip = $_SERVER['SERVER_ADDR'];
    $tld = strtolower(pathinfo($domain, PATHINFO_EXTENSION));

    ?>
    <style>
        .wpcd-badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; color: #fff; }
    </style>
    <div class="wrap">
        <h1>Domain & DNS Management</h1>

        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Temporary Development DNS</h2>
            <table class="wp-list-table widefat fixed striped">
                <tr>
                    <td style="width: 20%;"><strong>Dev URL</strong></td>
                    <td><code><?php echo site_url(); ?></code></td>
                </tr>
                <tr>
                    <td><strong>Server IP</strong></td>
                    <td><code><?php echo esc_html( $server_ip ); ?></code></td>
                </tr>
            </table>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
            <h2>Live Production Domain</h2>
            <form method="post" action="" style="margin-bottom: 25px;">
                <?php wp_nonce_field( 'wpcd_save_domain_action', 'wpcd_save_domain_nonce' ); ?>
                <input type="text" name="wpcd_live_domain" class="regular-text" placeholder="example.com" value="<?php echo esc_attr( $domain ); ?>" style="width: 300px; height: 35px; vertical-align: middle;">
                <input type="submit" class="button button-primary button-large" value="Save & Lookup">
            </form>

            <h3>Domain Information</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr><th style="width: 25%;">Record Type</th><th>Value / Result</th></tr>
                </thead>
                <tbody>
                    <?php
                    // --- RDAP REGISTRAR LOOKUP ---
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

                    $data = json_decode($response, true);
                    $registrar = "Not Found";
                    if ( ! empty( $data['entities'] ) ) {
                        foreach ( $data['entities'] as $entity ) {
                            if ( ! empty( $entity['roles'] ) && in_array( 'registrar', $entity['roles'] ) ) {
                                if ( ! empty( $entity['vcardArray'][1] ) ) {
                                    foreach ( $entity['vcardArray'][1] as $vcard ) {
                                        if ( isset( $vcard[0] ) && $vcard[0] === 'fn' ) {
                                            $registrar = $vcard[3];
                                            break 2;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // --- DNS DATA ---
                    $a_records = dns_get_record( $domain, DNS_A );
                    $ip_address = ! empty( $a_records ) ? $a_records[0]['ip'] : 'Not Found';
                    $ns_records = dns_get_record( $domain, DNS_NS );
                    $mx_records = dns_get_record( $domain, DNS_MX );

                    // --- EMAIL PROVIDER LOGIC (BEST GUESS) ---
                    $email_provider = '<span class="wpcd-badge" style="background:#666;">Other / Unknown</span>';
                    if ( empty( $mx_records ) ) {
                        $email_provider = 'None Detected';
                    } else {
                        $mx_targets = strtolower( implode( ' ', array_column( $mx_records, 'target' ) ) );
                        
                        if ( strpos( $mx_targets, 'outlook.com' ) !== false ) {
                            $email_provider = '<span class="wpcd-badge" style="background:#0078d4;">Microsoft 365</span>';
                        } elseif ( strpos( $mx_targets, 'google.com' ) !== false || strpos( $mx_targets, 'googlemail.com' ) !== false || strpos( $mx_targets, 'aspmx' ) !== false ) {
                            $email_provider = '<span class="wpcd-badge" style="background:#ea4335;">Google Workspace</span>';
                        } elseif ( strpos( $mx_targets, 'secureserver.net' ) !== false ) {
                            $email_provider = '<span class="wpcd-badge" style="background:#7db701;">GoDaddy Email</span>';
                        } elseif ( strpos( $mx_targets, 'mimecast.com' ) !== false ) {
                            $email_provider = '<span class="wpcd-badge" style="background:#2271b1;">Mimecast (Security Gateway)</span>';
                        }
                    }
                    ?>

                    <tr>
                        <td><strong>Registrar</strong></td>
                        <td><strong><?php echo esc_html( $registrar ); ?></strong></td>
                    </tr>
                    <tr>
                        <td><strong>A Record (IP)</strong></td>
                        <td><code><?php echo esc_html( $ip_address ); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong>Name Servers</strong></td>
                        <td>
                            <?php 
                            if ( ! empty( $ns_records ) ) {
                                foreach ( $ns_records as $ns ) { echo '<code>' . esc_html( $ns['target'] ) . '</code><br>'; }
                            } else { echo 'Not Found'; }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>MX Records</strong></td>
                        <td>
                            <?php 
                            if ( ! empty( $mx_records ) ) {
                                foreach ( $mx_records as $mx ) { echo '<code>' . esc_html( $mx['target'] ) . '</code> (Pri: ' . $mx['pri'] . ')<br>'; }
                            } else { echo 'None'; }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Email Provider</strong></td>
                        <td><?php echo $email_provider; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
            <h2>Registry Technical Trace</h2>
            <p><strong>Source URL:</strong> <code><?php echo esc_html( $url ); ?></code></p>
            <h3>RAW Response</h3>
            <pre style='background:#222; color:#0f0; padding:15px; overflow:auto; max-height:400px; border-radius: 4px;'><?php 
                echo ( $httpCode === 200 ) ? json_encode($data, JSON_PRETTY_PRINT) : "Error: HTTP $httpCode"; 
            ?></pre>
        </div>
    </div>
    <?php
}
