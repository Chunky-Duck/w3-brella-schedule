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
		</tbody>
	</table>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1em">
		<?php wp_nonce_field( 'cryptocon_brella_refresh' ); ?>
		<input type="hidden" name="action" value="cryptocon_brella_refresh" />
		<?php submit_button( __( 'Refresh now', 'cryptocon-brella' ), 'secondary', 'submit', false ); ?>
	</form>

	<?php if ( $sample ) : ?>
		<h2><?php esc_html_e( 'Debug: first cached session', 'cryptocon-brella' ); ?></h2>
		<pre class="cryptocon-brella-debug"><?php echo esc_html( wp_json_encode( $sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
	<?php endif; ?>
</div>
