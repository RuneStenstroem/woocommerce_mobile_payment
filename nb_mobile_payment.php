<?php

/*
Plugin Name: Simple Mobile Payment Gateway
Plugin URI: http://www.runestenstroem.dk/2016/02/20/mobilbetaling-i-woocommerce/
Description: This Plugin adds a simple Mobile Payment gateway. It is Very Simular to the standard Bank Transfer gateway.
Version: 1.0
Author: Rune Stenstrøm
Author URI: http://www.runestenstroem.dk
Text Domain: nb_mobile_payment
*/


add_action('plugins_loaded', 'nb_mobile_payment_init', 0);
function nb_mobile_payment_init() {
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	/**
 	 * Localisation
	 */
	add_action('plugins_loaded', 'nb_load_textdomain');
	function nb_load_textdomain() {
		load_plugin_textdomain('nb_mobile_payment', false, dirname( plugin_basename( __FILE__ ) ) . '/lang');
	}
	/**
 	 * Gateway class
 	 */
	class NB_Gateway_MP extends WC_Payment_Gateway {

	/** @var array Array of locales */
	public $locale;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		$this->id                 = 'nbmp';
		/*$this->icon               = apply_filters('woocommerce_nbmp_icon', '');*/
		$this->has_fields         = false;
		$this->method_title       = __( 'Mobile Payment', 'nb_mobile_payment' );
		$this->method_description = __( 'Simple mobile payment gateway', 'nb_mobile_payment' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions', $this->description );

		// nbmp account fields shown on the thanks page and in emails
		$this->account_details = get_option( 'nbmp_accounts',
			array(
				array(
					'account_name'   => $this->get_option( 'account_name' ),
					'name'   => $this->get_option( 'name' ),
					'phone_number' => $this->get_option( 'phone_number' )
				)
			)
		);

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
		add_action( 'woocommerce_thankyou_nbmp', array( $this, 'thankyou_page' ) );

		// Customer Emails
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'nb_mobile_payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Mobile Payment', 'nb_mobile_payment' ),
				'default' => 'no'
			),

			'title' => array(
				'title'       => __( 'Title', 'nb_mobile_payment' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'nb_mobile_payment' ),
				'default'     => __( 'Mobile Payment', 'nb_mobile_payment' ),
				'desc_tip'    => true,
			),

			'description' => array(
				'title'       => __( 'Description', 'nb_mobile_payment' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'nb_mobile_payment' ),
				'default'     => __( 'Make your payment directly into our mobile payment accounts. Please use your Order ID as the payment reference. Your order won\'t be shipped until the funds have cleared in our account.', 'nb_mobile_payment' ),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __( 'Instructions', 'nb_mobile_payment' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page and emails.', 'nb_mobile_payment' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'account_details' => array(
				'type'        => 'account_details'
			),
		);

	}

	/**
	 * Generate account details html.
	 *
	 * @return string
	 */
	public function generate_account_details_html() {

		ob_start();

		$country 	= WC()->countries->get_base_country();

		// Get sortcode label in the $locale array and use appropriate one
		$sortcode = isset( $locale[ $country ]['sortcode']['label'] ) ? $locale[ $country ]['sortcode']['label'] : __( 'Sort Code', 'nb_mobile_payment' );

		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php _e( 'Account Details', 'nb_mobile_payment' ); ?>:</th>
			<td class="forminp" id="nbmp_accounts">
				<table class="widefat wc_input_table sortable" cellspacing="0">
					<thead>
						<tr>
							<th class="sort">&nbsp;</th>
							<th><?php _e( 'Account Name', 'nb_mobile_payment' ); ?></th>
							<th><?php _e( 'Name', 'nb_mobile_payment' ); ?></th>
							<th><?php _e( 'Phone Number', 'nb_mobile_payment' ); ?></th>
						</tr>
					</thead>
					<tbody class="accounts">
						<?php
						$i = -1;
						if ( $this->account_details ) {
							foreach ( $this->account_details as $account ) {
								$i++;

								echo '<tr class="account">
									<td class="sort"></td>
									<td><input type="text" value="' . esc_attr( wp_unslash( $account['account_name'] ) ) . '" name="nbmp_account_name[' . $i . ']" /></td>
									<td><input type="text" value="' . esc_attr( wp_unslash( $account['name'] ) ) . '" name="nbmp_name[' . $i . ']" /></td>
									<td><input type="text" value="' . esc_attr( $account['phone_number'] ) . '" name="nbmp_phone_number[' . $i . ']" /></td>
								</tr>';
							}
						}
						?>
					</tbody>
					<tfoot>
						<tr>
							<th colspan="7"><a href="#" class="add button"><?php _e( '+ Add Account', 'nb_mobile_payment' ); ?></a> <a href="#" class="remove_rows button"><?php _e( 'Remove selected account(s)', 'nb_mobile_payment' ); ?></a></th>
						</tr>
					</tfoot>
				</table>
				<script type="text/javascript">
					jQuery(function() {
						jQuery('#nbmp_accounts').on( 'click', 'a.add', function(){

							var size = jQuery('#nbmp_accounts').find('tbody .account').size();

							jQuery('<tr class="account">\
									<td class="sort"></td>\
									<td><input type="text" name="nbmp_account_name[' + size + ']" /></td>\
									<td><input type="text" name="nbmp_name[' + size + ']" /></td>\
									<td><input type="text" name="nbmp_phone_number[' + size + ']" /></td>\
								</tr>').appendTo('#nbmp_accounts table tbody');

							return false;
						});
					});
				</script>
			</td>
		</tr>
		<?php
		return ob_get_clean();

	}

	/**
	 * Save account details table.
	 */
	public function save_account_details() {

		$accounts = array();

		if ( isset( $_POST['nbmp_account_name'] ) ) {
			$account_names   = array_map( 'wc_clean', $_POST['nbmp_account_name'] );
			$names   = array_map( 'wc_clean', $_POST['nbmp_name'] );
			$phone_numbers = array_map( 'wc_clean', $_POST['nbmp_phone_number'] );

			foreach ( $names as $i => $name ) {
				if ( ! isset( $account_names[ $i ] ) ) {
					continue;
				}

				$accounts[] = array(
					'account_name'   => $account_names[ $i ],
					'name'   => $names[ $i ],
					'phone_number' => $phone_numbers[ $i ],
				);
			}
		}

		update_option( 'nbmp_accounts', $accounts );

	}

	/**
	 * Output for the order received page.
	 *
	 * @param int $order_id
	 */
	public function thankyou_page( $order_id ) {

		if ( $this->instructions ) {
			echo wpautop( wptexturize( wp_kses_post( $this->instructions ) ) );
		}
		$this->mobilepayment_details( $order_id );

	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order
	 * @param bool $sent_to_admin
	 * @param bool $plain_text
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

		if ( ! $sent_to_admin && 'nbmp' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
			$this->mobilepayment_details( $order->id );
		}

	}

	/**
	 * Get bank details and place into a list format.
	 *
	 * @param int $order_id
	 */
	private function mobilepayment_details( $order_id = '' ) {

		if ( empty( $this->account_details ) ) {
			return;
		}

		// Get order and store in $order
		$order 		= wc_get_order( $order_id );

		// Get the order country and country $locale
		$country 	= $order->billing_country;

		$nbmp_accounts = apply_filters( 'nbmp_accounts', $this->account_details );

		if ( ! empty( $nbmp_accounts ) ) {
			echo '<h2>' . __( 'Our mobile payment details', 'nb_mobile_payment' ) . '</h2>' . PHP_EOL;

			foreach ( $nbmp_accounts as $nbmp_account ) {

				$nbmp_account = (object) $nbmp_account;

				if ( $nbmp_account->account_name) {
					echo '<h3>' . wp_unslash( implode( ' - ', array_filter( array( $nbmp_account->account_name ) ) ) ) . '</h3>' . PHP_EOL;
				}

				echo '<ul class="order_details nbmp_details">' . PHP_EOL;

				// nbmp account fields shown on the thanks page and in emails
				$account_fields = apply_filters( 'woocommerce_nbmp_account_fields', array(
					'Name'=> array(
						'label' => __( 'Name', 'nb_mobile_payment' ),
						'value' => $nbmp_account->name
					),
					'account_number'=> array(
						'label' => __( 'Phone Number', 'nb_mobile_payment' ),
						'value' => $nbmp_account->phone_number
					)
				), $order_id );

				foreach ( $account_fields as $field_key => $field ) {
					if ( ! empty( $field['value'] ) ) {
						echo '<li class="' . esc_attr( $field_key ) . '">' . esc_attr( $field['label'] ) . ': <strong>' . wptexturize( $field['value'] ) . '</strong></li>' . PHP_EOL;
					}
				}

				echo '</ul>';
			}
		}

	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		// Mark as on-hold (we're awaiting the payment)
		$order->update_status( 'on-hold', __( 'Waiting for mobile payment', 'nb_mobile_payment' ) );

		// Reduce stock levels
		$order->reduce_order_stock();

		// Remove cart
		WC()->cart->empty_cart();

		// Return thankyou redirect
		return array(
			'result'    => 'success',
			'redirect'  => $this->get_return_url( $order )
		);

	}


	}	

	/**
 	* Add the Gateway to WooCommerce
 	**/
	function woocommerce_add_gateway_name_gateway($methods) {
		$methods[] = 'NB_Gateway_MP';
		return $methods;
	}
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_name_gateway' );
}

