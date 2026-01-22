<?php
/**
 * DNS and Domain Information Utility for Adam.
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
	$domain = str_replace( array( 'http://', 'https://' ), '', get_site_url() );
	$domain = parse_url( 'http://' . $domain, PHP_URL_HOST );

	?>
	<div class="card" style="max-width: 800px; margin-top: 20px;">
		<h2>DNS Status for: <?php echo esc_html( $domain ); ?></h2>
		<p class="description">Quickly verify where this domain is pointed before going live.</p>
		
		<table class="wp-list-table widefat fixed striped" style="margin-top:20px;">
			<thead>
				<tr>
					<th style="width: 20%;">Record Type</th>
					<th>Value / Result</th>
				</tr>
			</thead>
			<tbody>
				<?php
				// 1. Check A Record (The IP Address)
				$a_records = dns_get_record( $domain, DNS_A );
				$ip_address = ! empty( $a_records ) ? $a_records[0]['ip'] : 'Not Found';
				?>
				<tr>
					<td><strong>A Record (IP)</strong></td>
					<td><code><?php echo esc_html( $ip_address ); ?></code></td>
				</tr>

				<?php
				// 2. Check Nameservers
				$ns_records = dns_get_record( $domain, DNS_NS );
				?>
				<tr>
					<td><strong>Nameservers</strong></td>
					<td>
						<?php 
						if ( ! empty( $ns_records ) ) {
							foreach ( $ns_records as $ns ) {
								echo '<code>' . esc_html( $ns['target'] ) . '</code><br>';
							}
						} else {
							echo '<span style="color:red;">No Nameservers Found</span>';
						}
						?>
					</td>
				</tr>

				<?php
				// 3. MX Records (Email Check)
				$mx_records = dns_get_record( $domain, DNS_MX );
				?>
				<tr>
					<td><strong>MX Records</strong></td>
					<td>
						<?php 
						if ( ! empty( $mx_records ) ) {
							foreach ( $mx_records as $mx ) {
								echo '<code>' . esc_html( $mx['target'] ) . '</code> (Priority: ' . esc_html( $mx['pri'] ) . ')<br>';
							}
						} else {
							echo 'None (Usually means no custom email setup)';
						}
						?>
					</td>
				</tr>
			</tbody>
		</table>

		<div style="margin-top: 20px; padding: 15px; background: #f0f6fb; border-left: 4px solid #11a0d2;">
			<strong>SiteGround Quick-Tip:</strong><br>
			If the IP above is not <code><?php echo esc_html( $_SERVER['SERVER_ADDR'] ); ?></code>, the domain is not yet pointed to this server.
		</div>
	</div>
	<?php
}
