<?php
/**
 * Gravity Flow WooCommerce
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Extension
 * @copyright   Copyright (c) 2015-2018, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-3.0.php GNU Public License
 * @since       1.0.0
 */

// Make sure Gravity Forms is active and already loaded.
if ( class_exists( 'GFForms' ) ) {

	class Gravity_Flow_Woocommerce extends Gravity_Flow_Extension {

		private static $_instance = null;

		public $_version = GRAVITY_FLOW_WOOCOMMERCE_VERSION;

		public $edd_item_name = GRAVITY_FLOW_WOOCOMMERCE_EDD_ITEM_NAME;

		// The Framework will display an appropriate message on the plugins page if necessary
		protected $_min_gravityforms_version = '1.9.10';

		protected $_slug = 'gravityflowwoocommerce';

		protected $_path = 'gravityflowwoocommerce/woocommerce.php';

		protected $_full_path = __FILE__;

		// Title of the plugin to be used on the settings page, form settings and plugins page.
		protected $_title = 'WooCommerce Extension';

		// Short version of the plugin title to be used on menus and other places where a less verbose string is useful.
		protected $_short_title = 'WooCommerce';

		protected $_capabilities = array(
			'gravityflowwoocommerce_uninstall',
			'gravityflowwoocommerce_settings',
			'gravityflowwoocommerce_form_settings',
		);

		protected $_capabilities_app_settings = 'gravityflowwoocommerce_settings';
		protected $_capabilities_uninstall = 'gravityflowwoocommerce_uninstall';
		protected $_capabilities_form_settings = 'gravityflowwoocommerce_form_settings';

		public static function get_instance() {
			if ( self::$_instance == null ) {
				self::$_instance = new Gravity_Flow_Woocommerce();
			}

			return self::$_instance;
		}

		private function __clone() {
		} /* do nothing */

		public function init() {
			parent::init();

			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'add_entry' ), 10, 2 );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'payment_gateways' ) );
			add_action( 'woocommerce_available_payment_gateways', array( $this, 'maybe_disable_gateway' ) );
			add_action( 'woocommerce_order_status_changed', array( $this, 'update_entry' ), 10, 4 );
		}

		public function init_admin() {
			parent::init_admin();
		}

		/**
		 * Add the extension capabilities to the Gravity Flow group in Members.
		 *
		 * @since 1.1-dev
		 *
		 * @param array $caps The capabilities and their human readable labels.
		 *
		 * @return array
		 */
		public function get_members_capabilities( $caps ) {
			$prefix = $this->get_short_title() . ': ';

			$caps['gravityflowwoocommerce_settings']      = $prefix . __( 'Manage Settings', 'gravityflowwoocommerce' );
			$caps['gravityflowwoocommerce_uninstall']     = $prefix . __( 'Uninstall', 'gravityflowwoocommerce' );
			$caps['gravityflowwoocommerce_form_settings'] = $prefix . __( 'Manage Form Settings', 'gravityflowwoocommerce' );

			return $caps;
		}

		/**
		 * Set form settings sections.
		 *
		 * @param array $form Form object.
		 *
		 * @return array
		 */
		public function form_settings_fields( $form ) {
			$fields = array(
				array(
					'name'       => 'woocommerce_orders_integration_enabled',
					'label'      => esc_html__( 'Integration Enabled?', 'gravityflowwoocommerce' ),
					'type'       => 'checkbox',
					'horizontal' => true,
					'onchange'   => "jQuery(this).closest('form').submit();",
					'choices'    => array(
						array(
							'label' => esc_html__( 'Enable WooCommerce orders integration.', 'gravityflowwoocommerce' ),
							'value' => 1,
							'name'  => 'woocommerce_orders_integration_enabled',
						),
					),
				),
			);

			// register the mapping field.
			$mapping_field = array(
				'name'                => 'mappings',
				'label'               => esc_html__( 'Field Mapping', 'gravityflowwoocommerce' ),
				'type'                => 'generic_map',
				'enable_custom_key'   => false,
				'enable_custom_value' => true,
				'key_field_title'     => esc_html__( 'Field', 'gravityflowwoocommerce' ),
				'value_field_title'   => esc_html__( 'WooCommerce Order Property', 'gravityflowwoocommerce' ),
				'key_choices'         => $this->field_mappings( $form['id'] ),
				'value_choices'       => $this->value_mappings(),
				'tooltip'             => '<h6>' . esc_html__( 'Mapping', 'gravityflowwoocommerce' ) . '</h6>' . esc_html__( 'Map the fields of this form to the WooCommerce Order properties. Values from an WooCommerce Order will be saved in the entry in this form.', 'gravityflowwoocommerce' ),
				'dependency'          => array(
					'field'  => 'woocommerce_orders_integration_enabled',
					'values' => array( '1' ),
				),
			);
			$fields[]      = $mapping_field;

			return array(
				array(
					'title'       => esc_html__( 'WooCommerce', 'gravityflowwoocommerce' ),
					'description' => $this->get_woocommerce_setting_description(),
					'fields'      => $fields,
				),
			);
		}

		/**
		 * Define the markup to be displayed for the WooCommerce setting description.
		 *
		 * @since 1.0.0
		 *
		 * @return string HTML formatted WooCommerce setting description.
		 */
		public function get_woocommerce_setting_description() {
			ob_start();
			?>
			<p><?php esc_html_e( 'When enable WooCommerce integration, it will:', 'gravityflowwoocommerce' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Create a new entry when a WooCommerce Order is created.', 'gravityflowwoocommerce' ); ?></li>
				<li><?php esc_html_e( 'Update the entry payment and transaction details based on the WooCommerce Order.', 'gravityflowwoocommerce' ); ?></li>
			</ul>
			<?php
			return ob_get_clean();
		}

		/**
		 * Add the "Pay Later" gateway.
		 *
		 * @param array $methods WooCommerce payment gateways.
		 *
		 * @return array Updated payment gateways.
		 */
		public function payment_gateways( $methods ) {
			$methods[] = 'WC_Gateway_Gravity_Flow_Pay_Later';

			return $methods;
		}

		/**
		 * Show this gateway only if we're on the checkout page (is_checkout), but not on the order-pay page (is_checkout_pay_page).
		 *
		 * @param array $gateways Available gateways.
		 *
		 * @return array
		 */
		public function maybe_disable_gateway( $gateways ) {
			if ( is_checkout_pay_page() ) {
				if ( isset( $gateways['gravity_flow_pay_later'] ) ) {
					unset( $gateways['gravity_flow_pay_later'] );
				}
			}

			return $gateways;
		}

		/**
		 * Adds WooCommerce order id to the entry meta.
		 *
		 * @since 1.0.0
		 *
		 * @param array $entry_meta Entry meta.
		 * @param int   $form_id Form ID.
		 *
		 * @return array
		 */
		public function get_entry_meta( $entry_meta, $form_id ) {
			if ( $this->is_woocommerce_orders_integration_enabled( $form_id ) || rgpost( 'woocommerce_orders_integration_enabled' ) ) {
				$entry_meta['workflow_woocommerce_order_id'] = array(
					'label'             => esc_html__( 'WooCommerce Order ID', 'gravityflowwoocommerce' ),
					'is_numeric'        => true,
					'is_default_column' => false,
					'filter'            => array(
						'operators' => array( 'is', 'isnot', '>', '<', 'contains' ),
					),
				);
			}

			return $entry_meta;
		}

		/**
		 * Helper to check if WooCommerce Orders integration is enabled.
		 *
		 * @since  1.0.0
		 *
		 * @param int $form_id Form ID.
		 *
		 * @return int True if integration is enabled. False otherwise.
		 */
		public function is_woocommerce_orders_integration_enabled( $form_id ) {
			$form     = GFAPI::get_form( $form_id );
			$settings = $this->get_form_settings( $form );

			return ( isset( $settings['woocommerce_orders_integration_enabled'] ) ) && '1' === $settings['woocommerce_orders_integration_enabled'];
		}

		/**
		 * Prepare field map.
		 *
		 * @param int $form_id Form ID.
		 *
		 * @return array
		 */
		public function field_mappings( $form_id ) {
			$fields = $this->get_field_map_choices( $form_id );

			// unset workflow_woocommerce_order_id entry meta since it is set mandatory.
			foreach ( $fields as $key => $field ) {
				if ( 'workflow_woocommerce_order_id' === $field['value'] ) {
					unset( $fields[ $key ] );
				}
			}

			return $fields;
		}

		/**
		 * Prepare value map.
		 *
		 * @return array
		 */
		public function value_mappings() {
			return array(
				array(
					'value' => '',
					'label' => esc_html__( 'Select a Field', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'id',
					'label' => esc_html__( 'Order ID', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'order_number',
					'label' => esc_html__( 'Order Number', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'date_created',
					'label' => esc_html__( 'Order Date', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'subtotal',
					'label' => esc_html__( 'Cart Subtotal', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'currency_symbol',
					'label' => esc_html__( 'Currency Symbol', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'view_order_url',
					'label' => esc_html__( 'View Order URL', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'user_id',
					'label' => esc_html__( 'User ID', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'billing_first_name',
					'label' => esc_html__( 'Billing First Name', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'billing_last_name',
					'label' => esc_html__( 'Billing Last Name', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'billing_company',
					'label' => esc_html__( 'Billing Company', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'billing_address',
					'label' => esc_html__( 'Billing Address', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'billing_email',
					'label' => esc_html__( 'Billing Email', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'billing_phone',
					'label' => esc_html__( 'Billing Phone', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'billing_address_1',
					'label' => esc_html__( 'Billing Address Line 1', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'billing_address_2',
					'label' => esc_html__( 'Billing Address Line 2', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'billing_city',
					'label' => esc_html__( 'Billing City', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'billing_postcode',
					'label' => esc_html__( 'Billing Postcode', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'billing_country',
					'label' => esc_html__( 'Billing Country Code', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'billing_country_name',
					'label' => esc_html__( 'Billing Country', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'billing_state',
					'label' => esc_html__( 'Billing State Code', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'billing_state_name',
					'label' => esc_html__( 'Billing State', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'shipping_first_name',
					'label' => esc_html__( 'Shipping First Name', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'shipping_last_name',
					'label' => esc_html__( 'Shipping Last Name', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'shipping_company',
					'label' => esc_html__( 'Shipping Company', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'shipping_address',
					'label' => esc_html__( 'Shipping Address', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'shipping_address_1',
					'label' => esc_html__( 'Shipping Address Line 1', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'shipping_address_2',
					'label' => esc_html__( 'Shipping Address Line 2', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'shipping_city',
					'label' => esc_html__( 'Shipping City', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'shipping_postcode',
					'label' => esc_html__( 'Shipping Postcode', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'shipping_country',
					'label' => esc_html__( 'Shipping Country Code', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'shipping_country_name',
					'label' => esc_html__( 'Shipping Country', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'shipping_state',
					'label' => esc_html__( 'Shipping State Code', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'shipping_state_name',
					'label' => esc_html__( 'Shipping State', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'shipping_method',
					'label' => esc_html__( 'Shipping Method', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'cart_total_discount',
					'label' => esc_html__( 'Cart Discount', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'cart_tax',
					'label' => esc_html__( 'Tax', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'shipping_total',
					'label' => esc_html__( 'Shipping Total', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'shipping_tax',
					'label' => esc_html__( 'Shipping Tax', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'prices_include_tax',
					'label' => esc_html__( 'Are prices inclusive of tax?', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'customer_note',
					'label' => esc_html__( 'Customer Note', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'coupons',
					'label' => esc_html__( 'Coupon Codes Used', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'item_count',
					'label' => esc_html__( 'Total Number of Items', 'gravityflowwoocommerce' ),
				),
			);
		}

		/**
		 * Generating new entry from a WooCommerce Order.
		 *
		 * @param array $form Form Object.
		 * @param int   $order_id WooCommerce Order id.
		 *
		 * @return array $new_entry
		 */
		public function do_mapping( $form, $order_id ) {
			$new_entry = array();
			$settings  = $this->get_form_settings( $form );
			$mappings  = $settings['mappings'];
			$order     = wc_get_order( $order_id );

			// Set mandatory fields.
			$new_entry['currency']       = $order->get_currency();
			$new_entry['payment_status'] = $order->get_status();
			$new_entry['payment_amount'] = $order->get_total();
			$new_entry['payment_method'] = $order->get_payment_method();
			// A WooCommerce order can contain both products and subscriptions. Set to payments for now.
			$new_entry['transaction_type'] = 1;
			if ( $order->is_paid() ) {
				$new_entry['transaction_id'] = $order->get_transaction_id();
				$new_entry['payment_date']   = $order->get_date_paid();
			}
			if ( 'completed' === $new_entry['payment_status'] ) {
				$new_entry['is_fulfilled'] = 1;
			}

			if ( is_array( $mappings ) ) {
				foreach ( $mappings as $mapping ) {
					if ( rgblank( $mapping['key'] ) ) {
						continue;
					}

					$new_entry = $this->add_mapping_to_entry( $mapping, $order, $new_entry, $form );
				}
			}

			$new_entry['workflow_woocommerce_order_id'] = $order_id;

			return apply_filters( 'gravityflowwoocommerce_new_entry', $new_entry, $order, $form );
		}

		/**
		 * Add the mapped value to the new entry.
		 *
		 * @param array  $mapping The properties for the mapping being processed.
		 * @param object $order WooCommerce Order.
		 * @param array  $new_entry The entry to be added or updated.
		 * @param array  $form The form being processed by this step.
		 *
		 * @return array
		 */
		public function add_mapping_to_entry( $mapping, $order, $new_entry, $form ) {
			$target_field_id     = (string) trim( $mapping['key'] );
			$order_property_name = (string) $mapping['value'];

			if ( 'gf_custom' === $order_property_name ) {
				$new_entry[ $target_field_id ] = GFCommon::replace_variables( $mapping['custom_value'], $form, $order, false, false, false, 'text' );
			} else {
				$is_full_target = (string) intval( $target_field_id ) === $target_field_id;
				$target_field   = GFFormsModel::get_field( $form, $target_field_id );
				$input_type     = $target_field->get_input_type();

				if ( $is_full_target && 'address' === $input_type && in_array( $order_property_name, array( 'billing_address', 'shipping_address' ), true ) ) {
					$new_entry[ $target_field_id . '.1' ] = $this->get_source_property_value( $order, str_replace( 'address', 'address_1', $order_property_name ) );
					$new_entry[ $target_field_id . '.2' ] = $this->get_source_property_value( $order, str_replace( 'address', 'address_2', $order_property_name ) );
					$new_entry[ $target_field_id . '.3' ] = $this->get_source_property_value( $order, str_replace( 'address', 'city', $order_property_name ) );
					$new_entry[ $target_field_id . '.4' ] = $this->get_source_property_value( $order, str_replace( 'address', 'state', $order_property_name ) );
					$new_entry[ $target_field_id . '.5' ] = $this->get_source_property_value( $order, str_replace( 'address', 'postcode', $order_property_name ) );
					$new_entry[ $target_field_id . '.6' ] = $this->get_source_property_value( $order, str_replace( 'address', 'country', $order_property_name ) );
				} else {
					$new_entry[ $target_field_id ] = $this->get_source_property_value( $order, $order_property_name );
				}
			}

			return $new_entry;
		}

		/**
		 * Get the WooCommerce Order property value.
		 *
		 * @param object $order WooCommerce Order.
		 * @param string $property_name WooCommerce Order property name.
		 *
		 * @return string
		 */
		public function get_source_property_value( $order, $property_name ) {
			if ( is_callable( array( $order, "get_{$property_name}" ) ) ) {
				$property_value = $order->{"get_{$property_name}"}();
			} else {
				$property_value = '';
				// some exceptions.
				switch ( $property_name ) {
					case 'currency_symbol':
						$property_value = get_woocommerce_currency_symbol( $order->currency );
						break;
					case 'billing_address':
						$property_value = $order->get_formatted_billing_address();
						break;
					case 'billing_country_name':
						if ( ! empty( $order->billing_country ) ) {
							$property_value = WC()->countries->countries[ $order->billing_country ];
						}
						break;
					case 'billing_state_name':
						if ( ! empty( $order->billing_state ) && isset( WC()->countries->states[ $order->billing_country ][ $order->billing_state ] ) ) {
							$property_value = WC()->countries->states[ $order->billing_country ][ $order->billing_state ];
						}
						break;
					case 'shipping_address':
						$property_value = $order->get_formatted_shipping_address();
						break;
					case 'shipping_country_name':
						if ( ! empty( $order->shipping_country ) ) {
							$property_value = WC()->countries->countries[ $order->shipping_country ];
						}
						break;
					case 'shipping_state_name':
						if ( ! empty( $order->shipping_state ) && isset( WC()->countries->states[ $order->shipping_country ][ $order->shipping_state ] ) ) {
							$property_value = WC()->countries->states[ $order->shipping_country ][ $order->shipping_state ];
						}
						break;
					case 'coupons':
						$coupons = $order->get_used_coupons();
						if ( count( $coupons ) ) {
							$property_value = implode( ', ', $coupons );
						}
						break;
				}
			}

			return $property_value;
		}

		/**
		 * Add new entry when a WooCommerce order created.
		 *
		 * @param int   $order_id WooCommerce Order ID.
		 * @param array $data WooCommerce Order meta data.
		 */
		public function add_entry( $order_id, $data ) {
			// get forms with WooCommerce integration.
			$form_ids = RGFormsModel::get_form_ids();
			foreach ( $form_ids as $form_id ) {
				if ( ! $this->is_woocommerce_orders_integration_enabled( $form_id ) ) {
					unset( $form_ids[ $form_id ] );
				}
			}

			foreach ( $form_ids as $form_id ) {
				$form = GFAPI::get_form( $form_id );
				// create new entry.
				$new_entry = $this->do_mapping( $form, $order_id );

				if ( ! empty( $new_entry ) ) {
					$new_entry['form_id'] = $form_id;
					$entry_id             = GFAPI::add_entry( $new_entry );
					if ( is_wp_error( $entry_id ) ) {
						$this->log_debug( __METHOD__ . '(): failed to add entry' );
					} else {
						$this->log_debug( __METHOD__ . '(): successfully created new entry #' . $entry_id );

						// save entry ID to WC order.
						update_post_meta( $order_id, '_gform-entry-id', $entry_id );
					}
				}
			}
		}

		/**
		 * Update the entry when WooCommerce order status changed.
		 *
		 * @param int      $order_id WooCommerce Order ID.
		 * @param string   $from_status WooCommerce old order status.
		 * @param string   $to_status WooCommerce new order status.
		 * @param WC_Order $order WooCommerce Order object.
		 */
		public function update_entry( $order_id, $from_status, $to_status, $order ) {
			$entry_id = get_post_meta( $order_id, '_gform-entry-id', true );
			if ( ! $entry_id ) {
				return;
			}

			$entry        = GFAPI::get_entry( $entry_id );
			$api          = new Gravity_Flow_API( $entry['form_id'] );
			$current_step = $api->get_current_step( $entry );

			// When order changed from "pending" to "processing" or "complete",
			// check if we need to release the entry from a WooCommerce Payment status.
			if ( 'woocommerce_payment' === $current_step->get_type() && 'pending' === $from_status && ( 'processing' === $to_status || 'complete' === $to_status ) ) {
				/**
				 * Allows the processing to be overridden entirely.
				 *
				 * @param array    $entry Entry object.
				 * @param int      $order_id WooCommerce Order ID.
				 * @param string   $from_status WooCommerce old order status.
				 * @param string   $to_status WooCommerce new order status.
				 * @param WC_Order $order WooCommerce Order object.
				 */
				do_action( 'gravityflowwoocommerce_payment_pre_update_entry', $entry, $order_id, $from_status, $to_status, $order );

				// update entry properties.
				$entry['payment_status'] = $to_status;
				$entry['payment_method'] = $order->get_payment_method();
				$entry['transaction_id'] = $order->get_transaction_id();
				$entry['payment_date']   = $order->get_date_paid();
				if ( 'completed' === $entry['payment_status'] ) {
					$entry['is_fulfilled'] = 1;
				}
				GFAPI::update_entry( $entry );

				$assignee_key = array(
					'type' => 'email',
					'id'   => $order->get_billing_email(),
				);
				$assignee     = $current_step->get_assignee( $assignee_key );
				$assignee->update_status( 'complete' );

				$api->process_workflow( $entry_id );

				// refresh entry.
				$entry = $current_step->refresh_entry();

				// add note.
				$note = $current_step->get_name() . ': ' . esc_html__( 'Completed.', 'gravityflowwoocommerce' );
				$current_step->add_note( $note );

				/**
				 * Allows the entry to be modified after processing.
				 *
				 * @param array    $entry Entry object.
				 * @param int      $order_id WooCommerce Order ID.
				 * @param string   $from_status WooCommerce old order status.
				 * @param string   $to_status WooCommerce new order status.
				 * @param WC_Order $order WooCommerce Order object.
				 */
				do_action( 'gravityflowwoocommerce_payment_post_update_entry', $entry, $order_id, $from_status, $to_status, $order );
			}
		}
	}
}
