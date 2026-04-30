<?php
defined( 'ABSPATH' ) || exit;

class WMA_Admin {

	public static function init(): void {
		add_action( 'admin_menu',                        [ self::class, 'add_menu' ] );
		add_action( 'admin_post_wma_save_settings',      [ self::class, 'handle_save' ] );
		add_action( 'admin_post_wma_reactivation_action', [ self::class, 'handle_reactivation_action' ] );
		add_action( 'admin_post_wma_test_email',         [ self::class, 'handle_test_email' ] );
		add_action( 'admin_enqueue_scripts',             [ self::class, 'enqueue_scripts' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( WMA_PLUGIN_FILE ), [ self::class, 'add_plugin_action_links' ] );
	}

	public static function add_plugin_action_links( array $links ): array {
		$settings_url = admin_url( 'admin.php?page=wma' );
		$docs_url     = 'https://github.com/pits2022/woo-marketing-automation/blob/main/README.md';

		$custom_links = [
			'settings' => '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'woo-marketing-automation' ) . '</a>',
			'docs'     => '<a href="' . esc_url( $docs_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Documentation', 'woo-marketing-automation' ) . '</a>',
		];

		return array_merge( $custom_links, $links );
	}

	public static function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Marketing Automation', 'woo-marketing-automation' ),
			__( 'Marketing Automation', 'woo-marketing-automation' ),
			'manage_woocommerce',
			'wma',
			[ self::class, 'render_page' ]
		);
	}

	public static function enqueue_scripts( string $hook ): void {
		if ( strpos( $hook, 'page_wma' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'wma-admin',
			WMA_PLUGIN_URL . 'assets/css/wma-admin.css',
			[],
			WMA_VERSION
		);
	}

	// -------------------------------------------------------------------------
	// Page router
	// -------------------------------------------------------------------------

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'woo-marketing-automation' ) );
		}

		$tab    = sanitize_key( $_GET['tab']    ?? 'sendy' );
		$action = sanitize_key( $_GET['action'] ?? '' );
		?>
		<div class="wrap wma-admin">
		<h1><?php esc_html_e( 'Marketing Automation', 'woo-marketing-automation' ); ?></h1>

		<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'woo-marketing-automation' ); ?></p></div>
		<?php endif; ?>

		<?php if ( isset( $_GET['test-email-sent'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Test email sent successfully.', 'woo-marketing-automation' ); ?></p></div>
		<?php endif; ?>

		<?php if ( isset( $_GET['test-email-error'] ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p>
				<?php 
				$error_code = sanitize_text_field( wp_unslash( $_GET['test-email-error'] ) );
				if ( $error_code === '1' ) {
					esc_html_e( 'Please provide a valid email address.', 'woo-marketing-automation' );
				} else {
					esc_html_e( 'Failed to send test email. Please check your server email configuration or the debug log.', 'woo-marketing-automation' );
				}
				?>
			</p>
		</div>
		<?php endif; ?>

		<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
			<?php
			$tabs = [
				'sendy'          => __( 'Sendy', 'woo-marketing-automation' ),
				'customer-lists' => __( 'Customer Lists', 'woo-marketing-automation' ),
				'welcome-email'  => __( 'Welcome Email', 'woo-marketing-automation' ),
				'email-template' => __( 'Email Template', 'woo-marketing-automation' ),
				'reactivation'   => __( 'Reactivation Emails', 'woo-marketing-automation' ),
			];
			foreach ( $tabs as $slug => $label ) {
				$class = 'nav-tab' . ( $tab === $slug ? ' nav-tab-active' : '' );
				printf(
					'<a href="%s" class="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=wma&tab=' . $slug ) ),
					esc_attr( $class ),
					esc_html( $label )
				);
			}
			?>
		</nav>

		<div class="wma-tab-content">
		<?php
		switch ( $tab ) {
			case 'customer-lists':
				self::tab_customer_lists();
				break;
			case 'welcome-email':
				self::tab_welcome_email();
				break;
			case 'email-template':
				self::tab_email_template();
				break;
			case 'reactivation':
				if ( $action === 'edit' || $action === 'new' ) {
					self::tab_reactivation_edit();
				} else {
					self::tab_reactivation_list();
				}
				break;
			default:
				self::tab_sendy();
		}
		?>
		</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab: Sendy
	// -------------------------------------------------------------------------

	private static function tab_sendy(): void {
		$s = WMA_Settings::get( 'sendy' ) ?? [];
		self::form_open( 'sendy' );
		?>
		<table class="form-table">
			<tr>
				<th><label for="wma_sendy_url"><?php esc_html_e( 'Sendy URL', 'woo-marketing-automation' ); ?> *</label></th>
				<td>
					<input type="url" id="wma_sendy_url" name="wma_sendy[url]" value="<?php echo esc_attr( $s['url'] ?? '' ); ?>" class="regular-text" required>
					<p class="description"><?php esc_html_e( 'Base URL of your Sendy installation, e.g. https://sendy.example.com', 'woo-marketing-automation' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="wma_sendy_api_key"><?php esc_html_e( 'Sendy API Key', 'woo-marketing-automation' ); ?> *</label></th>
				<td><input type="text" id="wma_sendy_api_key" name="wma_sendy[api_key]" value="<?php echo esc_attr( $s['api_key'] ?? '' ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="wma_cf_site_key"><?php esc_html_e( 'Cloudflare Turnstile Site Key', 'woo-marketing-automation' ); ?></label></th>
				<td>
					<input type="text" id="wma_cf_site_key" name="wma_sendy[cf_site_key]" value="<?php echo esc_attr( $s['cf_site_key'] ?? '' ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Optional. Renders the CAPTCHA widget in subscription forms.', 'woo-marketing-automation' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="wma_cf_secret_key"><?php esc_html_e( 'Cloudflare Turnstile Secret Key', 'woo-marketing-automation' ); ?></label></th>
				<td>
					<input type="password" id="wma_cf_secret_key" name="wma_sendy[cf_secret_key]" value="<?php echo esc_attr( $s['cf_secret_key'] ?? '' ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Optional. Used for server-side CAPTCHA verification.', 'woo-marketing-automation' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::form_close();
	}

	// -------------------------------------------------------------------------
	// Tab: Customer Lists
	// -------------------------------------------------------------------------

	private static function tab_customer_lists(): void {
		$s = WMA_Settings::get( 'customer_lists' ) ?? [];
		self::form_open( 'customer-lists' );
		?>
		<table class="form-table">
			<tr>
				<th><label for="wma_customers_list"><?php esc_html_e( 'Customers List ID', 'woo-marketing-automation' ); ?> *</label></th>
				<td>
					<input type="text" id="wma_customers_list" name="wma_customer_lists[customers_list]" value="<?php echo esc_attr( $s['customers_list'] ?? '' ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Every customer is added to this Sendy list on order completion.', 'woo-marketing-automation' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="wma_vip_list"><?php esc_html_e( 'VIP Customers List ID', 'woo-marketing-automation' ); ?></label></th>
				<td><input type="text" id="wma_vip_list" name="wma_customer_lists[vip_customers_list]" value="<?php echo esc_attr( $s['vip_customers_list'] ?? '' ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="wma_vip_amount"><?php esc_html_e( 'VIP Minimum Order Amount', 'woo-marketing-automation' ); ?></label></th>
				<td>
					<input type="number" id="wma_vip_amount" name="wma_customer_lists[vip_customers_amount]" value="<?php echo esc_attr( $s['vip_customers_amount'] ?? '' ); ?>" class="small-text" min="0" step="0.01">
					<p class="description"><?php esc_html_e( 'Orders at or above this total qualify for the VIP list.', 'woo-marketing-automation' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="wma_returning_list"><?php esc_html_e( 'Returning Customers List ID', 'woo-marketing-automation' ); ?></label></th>
				<td>
					<input type="text" id="wma_returning_list" name="wma_customer_lists[returning_customers_list]" value="<?php echo esc_attr( $s['returning_customers_list'] ?? '' ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Customers with a previous completed order are added here too.', 'woo-marketing-automation' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::form_close();
	}

	// -------------------------------------------------------------------------
	// Tab: Welcome Email
	// -------------------------------------------------------------------------

	private static function tab_welcome_email(): void {
		$s = WMA_Settings::get( 'welcome_email' ) ?? [];
		self::form_open( 'welcome-email' );
		?>
		<table class="form-table">
			<tr>
				<th><label for="wma_welcome_subject"><?php esc_html_e( 'Email Subject', 'woo-marketing-automation' ); ?></label></th>
				<td><input type="text" id="wma_welcome_subject" name="wma_welcome_email[subject]" value="<?php echo esc_attr( $s['subject'] ?? '' ); ?>" class="large-text"></td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Email Message', 'woo-marketing-automation' ); ?></label></th>
				<td>
					<?php
					wp_editor(
						$s['message'] ?? '',
						'wma_welcome_message',
						[
							'textarea_name' => 'wma_welcome_email[message]',
							'media_buttons' => false,
							'textarea_rows' => 10,
						]
					);
					?>
					<p class="description">
						<?php esc_html_e( 'Injected into [WMA_MESSAGE]. Use [WMA_COUPON_CODE_PERCENT] to display the coupon code sent via the shortcode parameter.', 'woo-marketing-automation' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
		self::form_close();
	}

	// -------------------------------------------------------------------------
	// Tab: Email Template
	// -------------------------------------------------------------------------

	private static function tab_email_template(): void {
		$html = WMA_Settings::get( 'email_template.html' ) ?? '';
		?>
		<div class="postbox wma-test-email-box">
			<h2 class="hndle"><span><?php esc_html_e( 'Test Email Template', 'woo-marketing-automation' ); ?></span></h2>
			<div class="inside">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wma_test_email">
					<?php wp_nonce_field( 'wma_test_email', 'wma_nonce' ); ?>
					<p>
						<label for="wma_test_email_address"><strong><?php esc_html_e( 'Email Address:', 'woo-marketing-automation' ); ?></strong></label><br>
						<input type="email" id="wma_test_email_address" name="test_email_address" required class="regular-text" placeholder="you@example.com">
					</p>
					<p>
						<?php submit_button( __( 'Send Test Email', 'woo-marketing-automation' ), 'secondary', 'submit', false ); ?>
					</p>
					<p class="description"><?php esc_html_e( 'This will send a test email using the currently saved template. Fake coupon codes will be used.', 'woo-marketing-automation' ); ?></p>
				</form>
			</div>
		</div>
		<?php
		self::form_open( 'email-template' );
		?>
		<p><?php esc_html_e( 'Global HTML email template used by all outgoing emails. Available shortcodes:', 'woo-marketing-automation' ); ?></p>
		<ul style="list-style:disc;margin-left:20px;">
			<li><code>[WMA_MESSAGE]</code> &mdash; <?php esc_html_e( 'Per-email message text (always rendered)', 'woo-marketing-automation' ); ?></li>
			<li><code>[WMA_REVIEW_PRODUCTS]</code> &mdash; <?php esc_html_e( 'Ordered products table (when enabled on the reactivation email)', 'woo-marketing-automation' ); ?></li>
			<li><code>[WMA_DISCOUNT_PRODUCTS]</code> &mdash; <?php esc_html_e( 'Top discounted products table (when top_sale_products > 0)', 'woo-marketing-automation' ); ?></li>
			<li><code>[WMA_COUPON_CODE_PERCENT]</code> &mdash; <?php esc_html_e( 'Percentage discount coupon (when coupon_percent > 0)', 'woo-marketing-automation' ); ?></li>
			<li><code>[WMA_COUPON_CODE_FREESHIPMENT]</code> &mdash; <?php esc_html_e( 'Free shipping coupon (when coupon_expiry_freeship > 0)', 'woo-marketing-automation' ); ?></li>
			<li><code>[WMA_UNSUBSCRIBE_URL]</code> &mdash; <?php esc_html_e( 'Unsubscribe link URL', 'woo-marketing-automation' ); ?></li>
		</ul>
		<?php
		wp_editor(
			$html,
			'wma_email_template',
			[
				'textarea_name' => 'wma_email_template[html]',
				'media_buttons' => true,
				'textarea_rows' => 28,
			]
		);
		self::form_close( __( 'Save Template', 'woo-marketing-automation' ) );
	}

	// -------------------------------------------------------------------------
	// Tab: Reactivation Emails — list
	// -------------------------------------------------------------------------

	private static function tab_reactivation_list(): void {
		$emails  = WMA_Settings::get_reactivation_emails();
		$add_url = admin_url( 'admin.php?page=wma&tab=reactivation&action=new' );
		?>
		<div style="margin:15px 0;">
			<a href="<?php echo esc_url( $add_url ); ?>" class="button button-primary">
				<?php esc_html_e( '+ Add Reactivation Email', 'woo-marketing-automation' ); ?>
			</a>
		</div>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'woo-marketing-automation' ); ?></th>
					<th style="width:100px;"><?php esc_html_e( 'Wait Period', 'woo-marketing-automation' ); ?></th>
					<th><?php esc_html_e( 'Content', 'woo-marketing-automation' ); ?></th>
					<th style="width:80px;"><?php esc_html_e( 'Status', 'woo-marketing-automation' ); ?></th>
					<th style="width:180px;"><?php esc_html_e( 'Actions', 'woo-marketing-automation' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $emails ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No reactivation emails configured yet.', 'woo-marketing-automation' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $emails as $email ) :
					$id      = (int) $email['id'];
					$enabled = ! empty( $email['enabled'] );

					$edit_url   = admin_url( "admin.php?page=wma&tab=reactivation&action=edit&email_id={$id}" );
					$toggle_url = wp_nonce_url(
						admin_url( "admin-post.php?action=wma_reactivation_action&reactivation_action=toggle&email_id={$id}" ),
						'wma_reactivation_' . $id
					);
					$delete_url = wp_nonce_url(
						admin_url( "admin-post.php?action=wma_reactivation_action&reactivation_action=delete&email_id={$id}" ),
						'wma_reactivation_' . $id
					);

					$parts = [];
					if ( ! empty( $email['include_review_products'] ) )         $parts[] = '[WMA_REVIEW_PRODUCTS]';
					if ( (int) ( $email['top_sale_products'] ?? 0 ) > 0 )       $parts[] = '[WMA_DISCOUNT_PRODUCTS]';
					if ( (int) ( $email['coupon_percent'] ?? 0 ) > 0 )          $parts[] = '[WMA_COUPON_CODE_PERCENT]';
					if ( (int) ( $email['coupon_expiry_freeship'] ?? 0 ) > 0 )  $parts[] = '[WMA_COUPON_CODE_FREESHIPMENT]';
					if ( empty( $parts ) ) $parts[] = '[WMA_MESSAGE]';
				?>
				<tr>
					<td><strong><?php echo esc_html( $email['name'] ?? '' ); ?></strong></td>
					<td><?php printf( esc_html__( '%d days', 'woo-marketing-automation' ), (int) ( $email['wait_period'] ?? 0 ) ); ?></td>
					<td><small><?php echo esc_html( implode( ', ', $parts ) ); ?></small></td>
					<td>
						<span class="wma-badge <?php echo $enabled ? 'wma-badge--active' : 'wma-badge--inactive'; ?>">
							<?php echo $enabled ? esc_html__( 'Active', 'woo-marketing-automation' ) : esc_html__( 'Inactive', 'woo-marketing-automation' ); ?>
						</span>
					</td>
					<td>
						<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'woo-marketing-automation' ); ?></a>
						&nbsp;|&nbsp;
						<a href="<?php echo esc_url( $toggle_url ); ?>">
							<?php echo $enabled ? esc_html__( 'Disable', 'woo-marketing-automation' ) : esc_html__( 'Enable', 'woo-marketing-automation' ); ?>
						</a>
						&nbsp;|&nbsp;
						<a href="<?php echo esc_url( $delete_url ); ?>"
						   onclick="return confirm('<?php esc_attr_e( 'Delete this reactivation email?', 'woo-marketing-automation' ); ?>')"
						   style="color:#a00;">
							<?php esc_html_e( 'Delete', 'woo-marketing-automation' ); ?>
						</a>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab: Reactivation Emails — add / edit form
	// -------------------------------------------------------------------------

	private static function tab_reactivation_edit(): void {
		$action   = sanitize_key( $_GET['action'] ?? 'new' );
		$email_id = absint( $_GET['email_id'] ?? 0 );
		$email    = $action === 'edit' && $email_id
			? WMA_Settings::get_reactivation_email( $email_id )
			: self::empty_reactivation_email();

		if ( $action === 'edit' && ! $email ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Reactivation email not found.', 'woo-marketing-automation' ) . '</p></div>';
			return;
		}

		$title = $action === 'edit'
			? __( 'Edit Reactivation Email', 'woo-marketing-automation' )
			: __( 'Add Reactivation Email', 'woo-marketing-automation' );
		?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action"              value="wma_reactivation_action">
			<input type="hidden" name="reactivation_action" value="save">
			<input type="hidden" name="email_id"            value="<?php echo esc_attr( $email['id'] ?? 0 ); ?>">
			<?php wp_nonce_field( 'wma_reactivation_save', 'wma_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="wma_r_name"><?php esc_html_e( 'Name', 'woo-marketing-automation' ); ?> *</label></th>
					<td><input type="text" id="wma_r_name" name="wma_reactivation[name]" value="<?php echo esc_attr( $email['name'] ?? '' ); ?>" class="regular-text" required></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Status', 'woo-marketing-automation' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wma_reactivation[enabled]" value="1" <?php checked( ! empty( $email['enabled'] ) ); ?>>
							<?php esc_html_e( 'Active', 'woo-marketing-automation' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="wma_r_wait"><?php esc_html_e( 'Wait Period (days)', 'woo-marketing-automation' ); ?> *</label></th>
					<td>
						<input type="number" id="wma_r_wait" name="wma_reactivation[wait_period]" value="<?php echo esc_attr( $email['wait_period'] ?? 7 ); ?>" class="small-text" min="1" required>
						<p class="description"><?php esc_html_e( 'Days after order completion before sending this email.', 'woo-marketing-automation' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="wma_r_subject"><?php esc_html_e( 'Email Subject', 'woo-marketing-automation' ); ?> *</label></th>
					<td><input type="text" id="wma_r_subject" name="wma_reactivation[email_subject]" value="<?php echo esc_attr( $email['email_subject'] ?? '' ); ?>" class="large-text" required></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'Email Message', 'woo-marketing-automation' ); ?></label></th>
					<td>
						<?php
						wp_editor(
							$email['email_message'] ?? '',
							'wma_reactivation_message',
							[
								'textarea_name' => 'wma_reactivation[email_message]',
								'media_buttons' => false,
								'textarea_rows' => 8,
							]
						);
						?>
						<p class="description"><?php esc_html_e( 'Injected into [WMA_MESSAGE] in the global template.', 'woo-marketing-automation' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( '[WMA_REVIEW_PRODUCTS]', 'woo-marketing-automation' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wma_reactivation[include_review_products]" value="1" <?php checked( ! empty( $email['include_review_products'] ) ); ?>>
							<?php esc_html_e( 'Include ordered products table', 'woo-marketing-automation' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( '[WMA_DISCOUNT_PRODUCTS]', 'woo-marketing-automation' ); ?></th>
					<td>
						<label>
							<?php esc_html_e( 'Number of top discounted products:', 'woo-marketing-automation' ); ?>
							<input type="number" name="wma_reactivation[top_sale_products]" value="<?php echo esc_attr( $email['top_sale_products'] ?? 0 ); ?>" class="small-text" min="0">
						</label>
						<p class="description"><?php esc_html_e( '0 = disabled (shortcode stripped)', 'woo-marketing-automation' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( '[WMA_COUPON_CODE_PERCENT]', 'woo-marketing-automation' ); ?></th>
					<td>
						<label>
							<?php esc_html_e( 'Discount %:', 'woo-marketing-automation' ); ?>
							<input type="number" name="wma_reactivation[coupon_percent]" value="<?php echo esc_attr( $email['coupon_percent'] ?? 0 ); ?>" class="small-text" min="0" max="100">
						</label>
						&nbsp;&nbsp;
						<label>
							<?php esc_html_e( 'Expiry (days):', 'woo-marketing-automation' ); ?>
							<input type="number" name="wma_reactivation[coupon_expiry_percent]" value="<?php echo esc_attr( $email['coupon_expiry_percent'] ?? 30 ); ?>" class="small-text" min="1">
						</label>
						<p class="description"><?php esc_html_e( '0% = disabled.', 'woo-marketing-automation' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( '[WMA_COUPON_CODE_FREESHIPMENT]', 'woo-marketing-automation' ); ?></th>
					<td>
						<label>
							<?php esc_html_e( 'Expiry (days):', 'woo-marketing-automation' ); ?>
							<input type="number" name="wma_reactivation[coupon_expiry_freeship]" value="<?php echo esc_attr( $email['coupon_expiry_freeship'] ?? 0 ); ?>" class="small-text" min="0">
						</label>
						<p class="description"><?php esc_html_e( '0 = disabled.', 'woo-marketing-automation' ); ?></p>
					</td>
				</tr>
			</table>
			<p>
				<?php submit_button( __( 'Save Email', 'woo-marketing-automation' ), 'primary', 'submit', false ); ?>
				&nbsp;
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wma&tab=reactivation' ) ); ?>" class="button">
					<?php esc_html_e( 'Cancel', 'woo-marketing-automation' ); ?>
				</a>
			</p>
		</form>
		<?php
	}

	// -------------------------------------------------------------------------
	// Form save handlers
	// -------------------------------------------------------------------------

	public static function handle_save(): void {
		check_admin_referer( 'wma_save_settings', 'wma_nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'woo-marketing-automation' ) );
		}

		$tab      = sanitize_key( $_POST['tab'] ?? '' );
		$settings = WMA_Settings::get();

		switch ( $tab ) {
			case 'sendy':
				$raw              = $_POST['wma_sendy'] ?? [];
				$settings['sendy'] = [
					'url'           => esc_url_raw( wp_unslash( $raw['url'] ?? '' ) ),
					'api_key'       => sanitize_text_field( wp_unslash( $raw['api_key'] ?? '' ) ),
					'cf_site_key'   => sanitize_text_field( wp_unslash( $raw['cf_site_key'] ?? '' ) ),
					'cf_secret_key' => sanitize_text_field( wp_unslash( $raw['cf_secret_key'] ?? '' ) ),
				];
				break;

			case 'customer-lists':
				$raw = $_POST['wma_customer_lists'] ?? [];
				$settings['customer_lists'] = [
					'customers_list'           => sanitize_text_field( wp_unslash( $raw['customers_list'] ?? '' ) ),
					'vip_customers_list'       => sanitize_text_field( wp_unslash( $raw['vip_customers_list'] ?? '' ) ),
					'vip_customers_amount'     => sanitize_text_field( wp_unslash( $raw['vip_customers_amount'] ?? '' ) ),
					'returning_customers_list' => sanitize_text_field( wp_unslash( $raw['returning_customers_list'] ?? '' ) ),
				];
				break;

			case 'welcome-email':
				$raw = $_POST['wma_welcome_email'] ?? [];
				$settings['welcome_email'] = [
					'subject' => sanitize_text_field( wp_unslash( $raw['subject'] ?? '' ) ),
					'message' => wp_kses_post( wp_unslash( $raw['message'] ?? '' ) ),
				];
				break;

			case 'email-template':
				$raw = $_POST['wma_email_template'] ?? [];
				
				$allowed_html = array_merge( wp_kses_allowed_html( 'post' ), [
					'style' => [ 'type' => true ],
					'html'  => [ 'lang' => true, 'xmlns' => true ],
					'head'  => [],
					'body'  => [ 'style' => true, 'class' => true, 'id' => true, 'bgcolor' => true ],
					'table' => [ 'style' => true, 'class' => true, 'id' => true, 'width' => true, 'cellpadding' => true, 'cellspacing' => true, 'border' => true, 'align' => true, 'bgcolor' => true, 'role' => true ],
					'tr'    => [ 'style' => true, 'class' => true, 'id' => true, 'align' => true, 'valign' => true, 'bgcolor' => true ],
					'td'    => [ 'style' => true, 'class' => true, 'id' => true, 'align' => true, 'valign' => true, 'bgcolor' => true, 'width' => true, 'height' => true, 'colspan' => true, 'rowspan' => true ],
					'th'    => [ 'style' => true, 'class' => true, 'id' => true, 'align' => true, 'valign' => true, 'bgcolor' => true, 'width' => true, 'height' => true, 'colspan' => true, 'rowspan' => true ],
					'div'   => [ 'style' => true, 'class' => true, 'id' => true, 'align' => true ],
					'span'  => [ 'style' => true, 'class' => true, 'id' => true ],
					'a'     => [ 'style' => true, 'class' => true, 'id' => true, 'href' => true, 'target' => true, 'title' => true, 'rel' => true ],
					'img'   => [ 'style' => true, 'class' => true, 'id' => true, 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'border' => true ],
					'meta'  => [ 'charset' => true, 'name' => true, 'content' => true, 'http-equiv' => true ],
				] );

				$settings['email_template'] = [
					'html' => wp_kses( wp_unslash( $raw['html'] ?? '' ), $allowed_html ),
				];
				break;
		}

		WMA_Settings::update( $settings );

		wp_safe_redirect(
			add_query_arg( 'settings-updated', '1', admin_url( 'admin.php?page=wma&tab=' . $tab ) )
		);
		exit;
	}

	public static function handle_reactivation_action(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'woo-marketing-automation' ) );
		}

		$ra       = sanitize_key( $_REQUEST['reactivation_action'] ?? '' );
		$email_id = absint( $_REQUEST['email_id'] ?? 0 );

		switch ( $ra ) {
			case 'toggle':
				check_admin_referer( 'wma_reactivation_' . $email_id );
				WMA_Settings::toggle_reactivation_email( $email_id );
				break;

			case 'delete':
				check_admin_referer( 'wma_reactivation_' . $email_id );
				WMA_Settings::delete_reactivation_email( $email_id );
				break;

			case 'save':
				check_admin_referer( 'wma_reactivation_save', 'wma_nonce' );
				$raw = $_POST['wma_reactivation'] ?? [];
				WMA_Settings::save_reactivation_email( [
					'id'                      => $email_id,
					'name'                    => sanitize_text_field( wp_unslash( $raw['name'] ?? '' ) ),
					'enabled'                 => ! empty( $raw['enabled'] ),
					'wait_period'             => absint( $raw['wait_period'] ?? 7 ),
					'email_subject'           => sanitize_text_field( wp_unslash( $raw['email_subject'] ?? '' ) ),
					'email_message'           => wp_kses_post( wp_unslash( $raw['email_message'] ?? '' ) ),
					'include_review_products' => ! empty( $raw['include_review_products'] ),
					'top_sale_products'       => absint( $raw['top_sale_products'] ?? 0 ),
					'coupon_percent'          => absint( $raw['coupon_percent'] ?? 0 ),
					'coupon_expiry_percent'   => absint( $raw['coupon_expiry_percent'] ?? 30 ),
					'coupon_expiry_freeship'  => absint( $raw['coupon_expiry_freeship'] ?? 0 ),
				] );
				wp_safe_redirect( add_query_arg( 'settings-updated', '1', admin_url( 'admin.php?page=wma&tab=reactivation' ) ) );
				exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wma&tab=reactivation' ) );
		exit;
	}

	public static function handle_test_email(): void {
		check_admin_referer( 'wma_test_email', 'wma_nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'woo-marketing-automation' ) );
		}

		$email = sanitize_email( wp_unslash( $_POST['test_email_address'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( 'test-email-error', '1', admin_url( 'admin.php?page=wma&tab=email-template' ) ) );
			exit;
		}

		$data = [
			'message'                      => '<p>This is a test message. Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
			'list_id'                      => 'TEST-LIST-ID',
			'review_products'              => '<table style="width:100%;border-collapse:collapse;"><thead><tr><th style="padding:8px;text-align:left;border-bottom:2px solid #ddd;">Product</th><th style="padding:8px;text-align:center;border-bottom:2px solid #ddd;">Qty</th></tr></thead><tbody><tr><td style="padding:8px;border-bottom:1px solid #eee;"><a href="#">Test Product 1</a></td><td style="padding:8px;border-bottom:1px solid #eee;text-align:center;">1</td></tr><tr><td style="padding:8px;border-bottom:1px solid #eee;"><a href="#">Test Product 2</a></td><td style="padding:8px;border-bottom:1px solid #eee;text-align:center;">2</td></tr></tbody></table>',
			'discount_products'            => '<div style="margin-bottom:15px;border:1px solid #eee;padding:10px;"><a href="#" style="font-weight:bold;font-size:16px;">Test Sale Product</a><br><del style="color:#999;font-size:14px;">$20.00</del> <ins style="color:#c00;font-size:14px;text-decoration:none;">$15.00</ins></div>',
			'coupon_percent_code'          => 'WMA-TEST-PERCENT',
			'coupon_freeship_code'         => 'WMA-TEST-FREESHIP',
		];

		$sent = WMA_Email::send( $email, 'Test User', 'Test Email: Marketing Automation', $data );

		if ( $sent ) {
			wp_safe_redirect( add_query_arg( 'test-email-sent', '1', admin_url( 'admin.php?page=wma&tab=email-template' ) ) );
		} else {
			wp_safe_redirect( add_query_arg( 'test-email-error', '2', admin_url( 'admin.php?page=wma&tab=email-template' ) ) );
		}
		exit;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private static function form_open( string $tab ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wma_save_settings">
			<input type="hidden" name="tab"    value="<?php echo esc_attr( $tab ); ?>">
			<?php wp_nonce_field( 'wma_save_settings', 'wma_nonce' ); ?>
		<?php
	}

	private static function form_close( string $label = '' ): void {
		if ( ! $label ) {
			$label = __( 'Save Settings', 'woo-marketing-automation' );
		}
		submit_button( $label );
		echo '</form>';
	}

	private static function empty_reactivation_email(): array {
		return [
			'id'                      => 0,
			'name'                    => '',
			'enabled'                 => true,
			'wait_period'             => 7,
			'email_subject'           => '',
			'email_message'           => '',
			'include_review_products' => false,
			'top_sale_products'       => 0,
			'coupon_percent'          => 0,
			'coupon_expiry_percent'   => 30,
			'coupon_expiry_freeship'  => 0,
		];
	}
}
