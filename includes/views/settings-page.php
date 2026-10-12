<?php
/**
 * Settings page view.
 *
 * @var array<string,mixed> $settings
 * @var array<string,mixed>|null $cache
 * @var array<string,bool> $status
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$session_count = is_array( $cache ) && ! empty( $cache['sessions'] ) ? count( $cache['sessions'] ) : 0;
$sample        = is_array( $cache ) && ! empty( $cache['sessions'][0] ) ? $cache['sessions'][0] : null;
$meta          = is_array( $cache ) && is_array( $cache['meta'] ?? null ) ? $cache['meta'] : array();
$event         = is_array( $meta['event'] ?? null ) ? $meta['event'] : null;
$session_days  = array();
if ( is_array( $cache ) && ! empty( $cache['sessions'] ) ) {
	foreach ( $cache['sessions'] as $row ) {
		if ( ! empty( $row['day_key'] ) ) {
			$session_days[ $row['day_key'] ] = ( $session_days[ $row['day_key'] ] ?? 0 ) + 1;
		}
	}
	ksort( $session_days );
}
$tz_sources = array(
	'setting' => __( 'Timezone override setting', 'cryptocon-brella' ),
	'brella'  => __( 'Brella event', 'cryptocon-brella' ),
);
?>
<div class="wrap cryptocon-brella-settings">
	<h1><?php esc_html_e( 'W3 Brella Integration', 'cryptocon-brella' ); ?></h1>

	<div class="cryptocon-brella-checklist">
		<h2><?php esc_html_e( 'Credentials', 'cryptocon-brella' ); ?></h2>
		<ul>
			<li class="<?php echo $status['api_key'] ? 'is-ok' : 'is-missing'; ?>">
				<?php echo $status['api_key'] ? '✓' : '✗'; ?>
				<?php esc_html_e( 'API key', 'cryptocon-brella' ); ?>
			</li>
			<li class="<?php echo $status['organization_id'] ? 'is-ok' : 'is-missing'; ?>">
				<?php echo $status['organization_id'] ? '✓' : '✗'; ?>
				<?php esc_html_e( 'Organization ID', 'cryptocon-brella' ); ?>
			</li>
			<li class="<?php echo $status['event_id'] ? 'is-ok' : 'is-missing'; ?>">
				<?php echo $status['event_id'] ? '✓' : '✗'; ?>
				<?php esc_html_e( 'Event ID', 'cryptocon-brella' ); ?>
			</li>
		</ul>
	</div>

	<form method="post" action="options.php" id="cryptocon-brella-settings-form">
		<?php settings_fields( 'cryptocon_brella' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="brella_api_key"><?php esc_html_e( 'API key', 'cryptocon-brella' ); ?></label></th>
				<td>
					<input type="password" id="brella_api_key" name="<?php echo esc_attr( CC_BRELLA_SETTINGS_OPTION ); ?>[api_key]" value="" autocomplete="new-password" class="regular-text" />
					<?php if ( $status['api_key'] ) : ?>
						<p class="description"><?php esc_html_e( '•••• configured — leave blank to keep the existing key.', 'cryptocon-brella' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Generate in Brella Admin → Organization → API key.', 'cryptocon-brella' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="brella_organization_id"><?php esc_html_e( 'Organization ID', 'cryptocon-brella' ); ?></label></th>
				<td>
					<input type="text" id="brella_organization_id" name="<?php echo esc_attr( CC_BRELLA_SETTINGS_OPTION ); ?>[organization_id]" value="<?php echo esc_attr( (string) ( $settings['organization_id'] ?? '' ) ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="brella_event_id"><?php esc_html_e( 'Event ID', 'cryptocon-brella' ); ?></label></th>
				<td>
					<input type="text" id="brella_event_id" name="<?php echo esc_attr( CC_BRELLA_SETTINGS_OPTION ); ?>[event_id]" value="<?php echo esc_attr( (string) ( $settings['event_id'] ?? '' ) ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="brella_cache_ttl"><?php esc_html_e( 'Cache TTL (minutes)', 'cryptocon-brella' ); ?></label></th>
				<td>
					<input type="number" min="1" id="brella_cache_ttl" name="<?php echo esc_attr( CC_BRELLA_SETTINGS_OPTION ); ?>[cache_ttl_minutes]" value="<?php echo esc_attr( (string) ( $settings['cache_ttl_minutes'] ?? 30 ) ); ?>" class="small-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Exclude networking slots', 'cryptocon-brella' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( CC_BRELLA_SETTINGS_OPTION ); ?>[exclude_networking]" value="1" <?php checked( ! empty( $settings['exclude_networking'] ) ); ?> />
						<?php esc_html_e( 'Hide empty-title reservable networking timeslots from the agenda.', 'cryptocon-brella' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="brella_timezone"><?php esc_html_e( 'Timezone override', 'cryptocon-brella' ); ?></label></th>
				<td>
					<input type="text" id="brella_timezone" name="<?php echo esc_attr( CC_BRELLA_SETTINGS_OPTION ); ?>[timezone]" value="<?php echo esc_attr( (string) ( $settings['timezone'] ?? '' ) ); ?>" class="regular-text" placeholder="Australia/Adelaide" />
					<p class="description"><?php esc_html_e( 'Optional IANA timezone. Leave empty to use Brella event timezone or WordPress site timezone.', 'cryptocon-brella' ); ?></p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<?php submit_button( __( 'Save settings', 'cryptocon-brella' ), 'primary', 'submit', false ); ?>
			<button type="button" class="button" id="cryptocon-brella-test-connection"><?php esc_html_e( 'Test connection', 'cryptocon-brella' ); ?></button>
		</p>
	</form>

	<div id="cryptocon-brella-test-result" class="cryptocon-brella-test-result" aria-live="polite"></div>

	<hr />

	<h2><?php esc_html_e( 'Schedule cache', 'cryptocon-brella' ); ?></h2>
	<table class="widefat striped cryptocon-brella-status">
		<tbody>
			<tr>
				<th><?php esc_html_e( 'Cached sessions', 'cryptocon-brella' ); ?></th>
				<td><?php echo esc_html( (string) $session_count ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Last sync', 'cryptocon-brella' ); ?></th>
				<td><?php echo esc_html( \CC\Brella\Cache::last_sync_human() ?: '—' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Cache expires', 'cryptocon-brella' ); ?></th>
				<td><?php echo esc_html( \CC\Brella\Cache::expires_human() ?: '—' ); ?></td>
			</tr>
			<?php if ( $cache ) : ?>
				<tr>
					<th><?php esc_html_e( 'Brella event', 'cryptocon-brella' ); ?></th>
					<td>
						<?php
						if ( null === $event ) {
							esc_html_e( 'Not stored yet. Click Refresh now.', 'cryptocon-brella' );
						} elseif ( ! empty( $event['error'] ) ) {
							echo esc_html( sprintf( __( 'Could not read the event from Brella: %s', 'cryptocon-brella' ), $event['error'] ) );
						} else {
							echo esc_html( '' !== $event['name'] ? $event['name'] : __( '(no name sent)', 'cryptocon-brella' ) );
						}
						?>
					</td>
				</tr>
				<?php if ( $event && empty( $event['error'] ) ) : ?>
					<tr>
						<th><?php esc_html_e( 'Event dates from Brella', 'cryptocon-brella' ); ?></th>
						<td>
							<?php
							if ( '' === $event['start_date'] ) {
								esc_html_e( 'Brella sent no start or end date, so day tabs come from session times only.', 'cryptocon-brella' );
							} else {
								echo esc_html( $event['start_date'] . ( '' !== $event['end_date'] ? ' to ' . $event['end_date'] : '' ) );
							}
							?>
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<th><?php esc_html_e( 'Timezone used', 'cryptocon-brella' ); ?></th>
					<td>
						<?php
						$source = (string) ( $meta['timezone_source'] ?? '' );
						echo esc_html( (string) ( $meta['timezone'] ?? '' ) );
						if ( '' !== $source ) {
							echo ' <span class="description">(' . esc_html( $tz_sources[ $source ] ?? sprintf( __( 'WordPress %s', 'cryptocon-brella' ), $source ) ) . ')</span>';
						}
						if ( $event && '' !== ( $event['time_zone_raw'] ?? '' ) ) {
							echo '<br /><span class="description">' . esc_html( sprintf( __( 'Brella sent: "%s"', 'cryptocon-brella' ), $event['time_zone_raw'] ) ) . '</span>';
						}
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Days with sessions', 'cryptocon-brella' ); ?></th>
					<td>
						<?php
						if ( ! $session_days ) {
							echo '&ndash;';
						}
						foreach ( $session_days as $day => $n ) {
							echo esc_html( sprintf( _n( '%1$s: %2$d session', '%1$s: %2$d sessions', $n, 'cryptocon-brella' ), $day, $n ) ) . '<br />';
						}
						?>
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1em">
		<?php wp_nonce_field( 'cryptocon_brella_refresh' ); ?>
		<input type="hidden" name="action" value="cryptocon_brella_refresh" />
		<?php submit_button( __( 'Refresh now', 'cryptocon-brella' ), 'secondary', 'submit', false ); ?>
	</form>

	<hr />

	<h2><?php esc_html_e( 'Agenda grid', 'cryptocon-brella' ); ?></h2>
	<p><?php esc_html_e( 'In Bricks, add the Brella Agenda element (under General) to show this schedule as a track by time grid with day tabs, filters and speaker avatars. It reads the cache above, so no other setup is needed. Outside Bricks, use the [brella_agenda] shortcode.', 'cryptocon-brella' ); ?></p>

	<?php if ( $sample ) : ?>
		<h2><?php esc_html_e( 'Debug: first cached session', 'cryptocon-brella' ); ?></h2>
		<pre class="cryptocon-brella-debug"><?php echo esc_html( wp_json_encode( $sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
	<?php endif; ?>
</div>
