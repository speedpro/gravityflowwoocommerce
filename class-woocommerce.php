<?php
/**
 * Gravity Flow WooCommerce
 *
 * @package     GravityFlow
 * @subpackage  Classes/Extension
 * @copyright   Copyright (c) 2015-2018, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.0
 */

// Make sure Gravity Forms is active and already loaded.
if ( class_exists( 'GFForms' ) ) {

	class Gravity_Flow_Woocommerce extends Gravity_Flow_Extension {

		private static $_instance = null;

		public $_version = GRAVITY_FLOW_WOOCOMMERCE_VERSION;

		public $edd_item_name = GRAVITY_FLOW_WOOCOMMERCE_EDD_ITEM_NAME;

		public $edd_item_id = GRAVITY_FLOW_WOOCOMMERCE_EDD_ITEM_ID;

		// The Framework will display an appropriate message on the plugins page if necessary
		protected $_min_gravityforms_version = '1.9.10';

		protected $_slug = 'gravityflowwoocommerce';

		protected $_path = 'gravityflowwoocommerce/woocommerce.php';

		protected $_full_path = __FILE__;

		// Title of the plugin to be used on the settings page, form settings and plugins page.
		protected $_title = 'Gravity Flow WooCommerce Extension';

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

			// Set the priority to 11, so we can be compatible with WooCommerce Gravity Forms add-on.
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'add_entry' ), 11 );
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_entry_hash' ), 11 );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'payment_gateways' ) );
			add_action( 'woocommerce_available_payment_gateways', array( $this, 'maybe_disable_gateway' ) );
			// Set the priority to 11, so we can be compatible with WooCommerce Gravity Forms add-on.
			add_action( 'woocommerce_order_status_changed', array( $this, 'update_entry' ), 11, 4 );
			add_action( 'woocommerce_order_status_changed', array( $this, 'release_payment_step' ), 11, 4 );
			add_filter( 'woocommerce_cancel_unpaid_order', array( $this, 'cancel_unpaid_order' ), 10, 2 );
			add_filter( 'gravityflow_feed_condition_entry_properties', array( $this, 'maybe_update_payment_statuses' ), 10, 2 );
			add_filter( 'gform_field_filters', array( $this, 'filter_gform_field_filters' ), 10, 2 );
			// Save workflow hash to session.
			add_action( 'wp_loaded', array( $this, 'set_workflow_order_hash_session' ), 20 );
			add_filter( 'woocommerce_get_checkout_order_received_url', array( $this, 'woocommerce_get_checkout_order_received_url' ), 10, 2 );
			add_filter( 'wp_redirect', array( $this, 'add_to_cart_redirect' ) );
		}

		public function init_admin() {
			parent::init_admin();
		}

		/**
		 * The minimum requirements required to use this extension.
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public function minimum_requirements() {
			return array(
				'add-ons' => array(
					'gravityflow' => array(
						'version' => '2.1.2',
					),
				),
				'plugins' => array(
					'woocommerce/woocommerce.php' => 'WooCommerce',
				),
			);
		}

		/**
		 * Add the extension capabilities to the Gravity Flow group in Members.
		 *
		 * @since 1.0.0
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
		 * @since 1.0.0
		 *
		 * @param array $form Form object.
		 *
		 * @return array
		 */
		public function form_settings_fields( $form ) {
			$fields = array(
				array(
					'name'       => 'woocommerce_orders_integration_enabled',
					'label'      => esc_html__( 'WooCommerce Integration', 'gravityflowwoocommerce' ),
					'type'       => 'checkbox',
					'horizontal' => true,
					'onchange'   => "jQuery(this).closest('form').submit();",
					'choices'    => array(
						array(
							'label' => esc_html__( 'Enable', 'gravityflowwoocommerce' ),
							'value' => 1,
							'name'  => 'woocommerce_orders_integration_enabled',
						),
					),
					'tooltip'    => '<h6>' . esc_html__( 'WooCommerce Integration', 'gravityflowwoocommerce' ) . '</h6>' . esc_html__( 'When enabled, a new entry will be created in this form when a WooCommerce Order is created. The entry payment and transaction details will also be updated based on the WooCommerce Order. If the order changes, the entry will be updated.', 'gravityflowwoocommerce' ),
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

			$fields[] = array(
				'name'                => 'payment_statuses_mode',
				'label'               => esc_html__( 'Create Entries on Specific Statuses', 'gravityflowwoocommerce' ),
				'tooltip'             => '<h6>' . esc_html__( 'Create Entries on Specific Statuses', 'gravityflowwoocommerce' ) . '</h6>' . esc_html__( 'New entries will only be created when a WooCommerce order is in one of the selected statuses. Each WooCommerce order can be added to this form once.', 'gravityflowwoocommerce' ),
				'type'                => 'payment_statuses',
				'dependency'          => array(
					'field'  => 'woocommerce_orders_integration_enabled',
					'values' => array( '1' ),
				),
				'validation_callback' => array( $this, 'validate_selected_payment_statuses' )
			);

			return array(
				array(
					'title'  => esc_html__( 'WooCommerce', 'gravityflowwoocommerce' ),
					'fields' => $fields,
				),
			);
		}

		/**
		 * Renders the payment statuses setting.
		 *
		 * @since 1.0.1
		 *
		 */
		public function settings_payment_statuses() {
			$mode_field = array(
				'name'          => 'payment_statuses_mode',
				'label'         => '',
				'type'          => 'select',
				'default_value' => 'all_fields',
				'onchange'      => 'jQuery(this).siblings(".gravityflow_payment_statuses_selected_container").toggle(this.value != "all_payment_statuses");',
				'choices'       => array(
					array(
						'label' => __( 'All payment statuses', 'gravityflow' ),
						'value' => 'all_payment_statuses',
					),
					array(
						'label' => __( 'Selected payment statuses', 'gravityflow' ),
						'value' => 'selected_payment_statuses',
					),
				),
			);

			$mode_value = $this->get_setting( 'payment_statuses_mode', 'all_payment_statuses' );

			$payment_status_choices = array();
			$payment_statuses       = wc_get_order_statuses();
			foreach ( $payment_statuses as $key => $value ) {
				$key                      = str_replace( 'wc-', '', $key );
				$payment_status_choices[] = array(
					'label'         => $value,
					'name'          => 'payment_status_' . $key,
					'default_value' => 1,
				);
			}
			$payment_statuses_field = array(
				'name'    => 'payment_statuses_selected',
				'label'   => esc_html__( 'Create Entries on Specific Statuses', 'gravityflowwoocommerce' ),
				'type'    => 'checkbox',
				'choices' => $payment_status_choices,
				'tooltip' => '<h6>' . esc_html__( 'Create Entries on Specific Statuses', 'gravityflowwoocommerce' ) . '</h6>' . esc_html__( 'New entries will only be created when a WooCommerce order is in one of the selected statuses. Each WooCommerce order can be added to this form once.', 'gravityflowwoocommerce' ),
			);

			$this->settings_select( $mode_field );
			$style = $mode_value === 'all_payment_statuses' ? 'style="display:none;"' : '';
			echo '<div class="gravityflow_payment_statuses_selected_container" ' . $style . '>';
			$this->settings_checkbox( $payment_statuses_field );
			echo '</div>';
		}

		/**
		 * Set validation error on empty selected payment statuses.
		 *
		 * @since 1.0.1
		 *
		 * @param array $field The setting field.
		 */
		public function validate_selected_payment_statuses( $field ) {
			$mode_value = $this->get_setting( 'payment_statuses_mode', 'all_payment_statuses' );

			if ( $mode_value === 'selected_payment_statuses' ) {
				$payment_statuses = wc_get_order_statuses();
				$selected         = 0;
				foreach ( $payment_statuses as $key => $value ) {
					$key = str_replace( 'wc-', '', $key );
					if ( $this->get_setting( 'payment_status_' . $key ) === '1' ) {
						$selected ++;
					}
				}

				if ( $selected === 0 ) {
					$this->set_field_error( $field, esc_html__( 'You need to selected at least one payment status.', 'gravityflowwoocommerce' ) );
					return;
				}
			}
		}

		public function styles() {
			$min    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';
			$styles = array();

			$styles[] = array(
				'handle'  => 'gravityflow_woocommerce_css',
				'src'     => $this->get_base_url() . "/css/woocommerce{$min}.css",
				'version' => $this->_version,
				'enqueue' => array(
					array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflow&id=_notempty_' ),
					array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflowwoocommerce&id=_notempty_' ),
					array( 'query' => 'page=gravityflow-inbox&view=entry&id=_notempty_' ),
				),
			);

			return array_merge( parent::styles(), $styles );
		}

		/**
		 * Add the "Pay Later" gateway.
		 *
		 * @since 1.0.0
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
		 * Show this gateway only if we're on the checkout page (is_checkout), but not on the order-pay page (is_checkout_pay_page),
		 * also follow the pay later gateway setting to remove other gateways.
		 *
		 * @since 1.0.0
		 *
		 * @param array $gateways Available gateways.
		 *
		 * @return array
		 */
		public function maybe_disable_gateway( $gateways ) {
			if ( isset( $gateways['gravity_flow_pay_later'] ) ) {
				if ( is_checkout_pay_page() ) {
					unset( $gateways['gravity_flow_pay_later'] );
				} elseif ( is_checkout() ) {
					$gateway_settings = get_option( 'woocommerce_gravity_flow_pay_later_settings' );
					if ( rgar( $gateway_settings, 'disable_other_gateways_on_checkout' ) === 'yes' ) {
						foreach ( $gateways as $name => $gateway ) {
							if ( 'gravity_flow_pay_later' !== $name ) {
								unset( $gateways[ $name ] );
							}
						}
					}
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
		 * @since 1.0.0
		 *
		 * @param int $form_id Form ID.
		 *
		 * @return int True if integration is enabled. False otherwise.
		 */
		public function is_woocommerce_orders_integration_enabled( $form_id ) {
			$form     = GFAPI::get_form( $form_id );
			$settings = $this->get_form_settings( $form );

			return rgar( $settings, 'woocommerce_orders_integration_enabled' ) === '1';
		}

		/**
		 * Helper to check if a payment status is enabled.
		 *
		 * @since 1.0.1
		 *
		 * @param int $form_id Form ID.
		 * @param int $order_id WooCommerce order ID.
		 *
		 * @return int True if integration is enabled. False otherwise.
		 */
		public function is_woocommerce_payment_status_enabled( $form_id, $order_id ) {
			$form     = GFAPI::get_form( $form_id );
			$settings = $this->get_form_settings( $form );

			$mode_value = rgar( $settings, 'payment_statuses_mode', 'all_payment_statuses' );
			if ( $mode_value === 'all_payment_statuses' ) {
				$result = true;
			} else {
				$order  = wc_get_order( $order_id );
				$result = rgar( $settings, "payment_status_{$order->get_status()}" ) === '1' || ! isset( $settings[ "payment_status_{$order->get_status()}" ] );
			}

			return apply_filters( 'gravityflowwoocommerce_is_payment_status_enabled', $result, $form_id, $order_id );
		}

		/**
		 * Prepare field map.
		 *
		 * @since 1.0.0
		 *
		 * @param int $form_id Form ID.
		 *
		 * @return array
		 */
		public function field_mappings( $form_id ) {
			$form    = GFAPI::get_form( $form_id );
			$exclude = array( 'list' );

			// exclude list and most array fields.
			foreach ( $form['fields'] as $field ) {
				$inputs     = $field->get_entry_inputs();
				$input_type = $field->get_input_type();

				if ( is_array( $inputs ) && ( $input_type !== 'address' && $input_type !== 'name' ) ) {
					$exclude[] = $field->type;
				}
			}
			$fields = $this->get_field_map_choices( $form_id, null, $exclude );

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
		 * @since 1.0.0
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
					'value' => 'status',
					'label' => esc_html__( 'Order Status', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'date_created',
					'label' => esc_html__( 'Order Date', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'total',
					'label' => esc_html__( 'Cart Total', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'subtotal',
					'label' => esc_html__( 'Cart Subtotal', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'payment_method',
					'label' => esc_html__( 'Payment Method', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'transaction_id',
					'label' => esc_html__( 'Transaction ID', 'gravityflowwoocommerce' ),
				),
				array(
					'value' => 'currency',
					'label' => esc_html__( 'Currency Currency', 'gravityflowwoocommerce' ),
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
		 * @since 1.0.0
		 *
		 * @param array $form Form Object.
		 * @param int   $order_id WooCommerce Order id.
		 *
		 * @return array $new_entry
		 */
		public function do_mapping( $form, $order_id ) {
			$new_entry = array();
			$settings  = $this->get_form_settings( $form );
			$mappings  = rgar( $settings, 'mappings' );
			$order     = wc_get_order( $order_id );

			// Set mandatory fields.
			$new_entry['currency']       = $order->get_currency();
			$new_entry['payment_status'] = $order->get_status();
			$new_entry['payment_method'] = $order->get_payment_method();
			if ( ! self::has_price_field( $form ) ) {
				$new_entry['payment_amount'] = $order->get_total();
			}
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
		 * @since 1.0.0
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
				$new_entry[ $target_field_id ] = GFCommon::replace_variables( $mapping['custom_value'], $form, $new_entry, false, false, false, 'text' );
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
		 * @since 1.0.0
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
					case 'cart_total_discount':
						$discount       = $order->get_total_discount();
						$property_value = 0;
						if ( $discount ) {
							$property_value -= $discount;
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
		 * @since 1.0.0
		 * @since 1.1   Check if forms have entries created by the WooCommerce Gravity Forms extension.
		 *
		 * @param int   $order_id WooCommerce Order ID.
		 * @param array $forms_has_entry Form ids which already have entries created in them.
		 */
		public function add_entry( $order_id, $forms_has_entry = array() ) {
			$this->log_debug( __METHOD__ . '() starting' );
			// get forms with WooCommerce integration.
			$form_ids = RGFormsModel::get_form_ids();
			foreach ( $form_ids as $key => $form_id ) {
				if ( ! $this->is_woocommerce_orders_integration_enabled( $form_id ) || ! $this->is_woocommerce_payment_status_enabled( $form_id, $order_id ) || in_array( $form_id, $forms_has_entry, true ) || $this->has_wcgf_entries( $form_id, $order_id ) ) {
					unset( $form_ids[ $key ] );
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
						add_post_meta( $order_id, '_gravityflow-entry-id', $entry_id );
					}
				}
			}
		}

		/**
		 * Update the entry when WooCommerce order status changed.
		 *
		 * @since 1.0.0
		 *
		 * @param int      $order_id WooCommerce Order ID.
		 * @param string   $from_status WooCommerce old order status.
		 * @param string   $to_status WooCommerce new order status.
		 * @param WC_Order $order WooCommerce Order object.
		 */
		public function update_entry( $order_id, $from_status, $to_status, $order ) {
			$this->log_debug( __METHOD__ . '() starting' );

			$entry_ids = get_post_meta( $order_id, '_gravityflow-entry-id' );
			if ( ! $entry_ids ) {
				// Call add_entry() because the order may skip in some forms for the payment status not matched.
				$this->add_entry( $order_id );

				return;
			}

			$forms_has_entry = array();
			foreach ( $entry_ids as $entry_id ) {
				$entry = GFAPI::get_entry( $entry_id );
				// Don't update entry if the WooCommerce integration is disabled.
				if ( is_wp_error( $entry ) || ! $this->is_woocommerce_orders_integration_enabled( $entry['form_id'] ) ) {
					continue;
				}

				$forms_has_entry[] = $entry['form_id'];

				$api          = new Gravity_Flow_API( $entry['form_id'] );
				$current_step = $api->get_current_step( $entry );

				/**
				 * Allows the processing to be overridden entirely.
				 *
				 * @since 1.0.0
				 *
				 * @param array    $entry Entry object.
				 * @param int      $order_id WooCommerce Order ID.
				 * @param string   $from_status WooCommerce old order status.
				 * @param string   $to_status WooCommerce new order status.
				 * @param WC_Order $order WooCommerce Order object.
				 */
				do_action( 'gravityflowwoocommerce_pre_update_entry', $entry, $order_id, $from_status, $to_status, $order );

				$result = $this->update_entry_payment_data( $entry, $order, $from_status, $to_status );

				/**
				 * Set complete status for the payment step.
				 *
				 * @since 1.1
				 *
				 * @param array|string $complete_status Default complete status.
				 */
				$complete_status = apply_filters( 'gravityflowwoocommerce_payment_step_complete_status', array( 'processing', 'completed', 'failed' ) );
				if ( is_string( $complete_status ) ) {
					$complete_status = array( $complete_status );
				}
				// A new payment release the entry from the WooCommerce Payment step.
				// Update assignee status programmatically.
				if ( $current_step && 'woocommerce_payment' === $current_step->get_type()
					&& ( in_array( $to_status, $complete_status, true ) ) ) {
					if ( true === $result ) {
						$user_id      = $order->get_user_id();
						$assignee_key = ( ! empty( $user_id ) ) ? 'user_id|' . $user_id : 'email|' . $order->get_billing_email();
						$assignee     = $current_step->get_assignee( $assignee_key );
						$assignee->update_status( 'complete' );

						$api->process_workflow( $entry_id );

						// refresh entry.
						$entry = $current_step->refresh_entry();
					}
				}

				/**
				 * Allows the entry to be modified after processing.
				 *
				 * @since 1.0.0
				 *
				 * @param array    $entry Entry object.
				 * @param int      $order_id WooCommerce Order ID.
				 * @param string   $from_status WooCommerce old order status.
				 * @param string   $to_status WooCommerce new order status.
				 * @param WC_Order $order WooCommerce Order object.
				 */
				do_action( 'gravityflowwoocommerce_post_update_entry', $entry, $order_id, $from_status, $to_status, $order );
			}

			$this->add_entry( $order_id, $forms_has_entry );
		}

		/**
		 * Update entry payment data.
		 *
		 * @since 1.0.0
		 *
		 * @param array    $entry Entry object.
		 * @param WC_Order $order WooCommerce Order object.
		 * @param string   $from_status Previous payment status.
		 * @param string   $to_status Final payment status.
		 *
		 * @return true|WP_Error
		 */
		public function update_entry_payment_data( $entry, $order, $from_status, $to_status ) {
			// don't update entry payment data if the entry was created by other plugins, e.g. the WooCommerce Gravity Forms add-on.
			if ( rgar( $entry, 'woocommerce_order_number' ) ) {
				$this->log_debug( __METHOD__ . '(): Entry #' . $entry['id'] . ' was created by the WooCommerce Gravity Forms add-on, don\'t update the payment status.' );
				return true;
			}

			$entry['payment_status'] = $to_status;
			$entry['payment_method'] = $order->get_payment_method();

			$form = GFAPI::get_form( $entry['form_id'] );
			if ( ! self::has_price_field( $form ) ) {
				$entry['payment_amount'] = $order->get_total();
			}

			$transaction_id = $order->get_transaction_id();
			if ( ! empty( $transaction_id ) ) {
				$entry['transaction_id'] = $transaction_id;
			}

			$date_paid = $order->get_date_paid();
			if ( ! empty( $date_paid ) ) {
				$entry['payment_date'] = $order->get_date_paid();
			}

			if ( 'completed' === $entry['payment_status'] ) {
				$entry['is_fulfilled'] = 1;
			}

			$result = GFAPI::update_entry( $entry );
			$this->log_debug( __METHOD__ . '(): update entry #' . $entry['id'] . ' payment status. Result - ' . print_r( $result, true ) );
			if ( true === $result ) {
				$note = sprintf( esc_html__( 'Entry payment status updated from %s to %s.', 'gravityflowwoocommerce' ), $from_status, $to_status );
			} else {
				$note = esc_html__( 'Failed to update entry.', 'gravityflowwoocommerce' );
			}
			gravity_flow()->add_note( $entry['id'], $note, 'gravityflow' );

			return $result;
		}

		/**
		 * Cancel an unpaid order if it expired.
		 *
		 * @since 1.0.0
		 *
		 * @param bool     $result True or false.
		 * @param WC_Order $order WooCommerce Order object.
		 *
		 * @return bool True if order has expired, false otherwise.
		 */
		public function cancel_unpaid_order( $result, $order ) {
			$gateway_settings = get_option( 'woocommerce_gravity_flow_pay_later_settings' );
			if ( empty( $gateway_settings ) ) {
				$gateway_settings['pending_duration'] = 7;
			}

			if ( ( 'gravity_flow_pay_later' === $order->get_payment_method() ) && ( time() <= ( strtotime( $order->get_date_created() ) + $gateway_settings['pending_duration'] * 86400 ) ) ) {
				$result = false;
			}

			return $result;
		}

		/**
		 * Helper function to check if the form has pricing fields.
		 *
		 * @since 1.0.0
		 *
		 * @param array $form Form object.
		 *
		 * @return bool
		 */
		private static function has_price_field( $form ) {
			if ( is_array( $form['fields'] ) ) {
				foreach ( $form['fields'] as $field ) {
					if ( GFCommon::is_product_field( $field->type ) ) {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Replace payment status values in feed condition if WooCommerce Integration enabled.
		 *
		 * @since 1.1
		 *
		 * @param array $properties Entry properties.
		 * @param int   $form_id Form ID.
		 *
		 * @return array Entry properties.
		 */
		public function maybe_update_payment_statuses( $properties, $form_id ) {
			if ( gravity_flow_woocommerce()->is_woocommerce_orders_integration_enabled( $form_id ) ) {
				$wc_order_statuses = $this->wc_order_statuses();

				$properties['payment_status']['filter']['choices'] = $wc_order_statuses;
			}

			return $properties;
		}

		/**
		 * Filter payment status to use WooCommerce order status when the integration is enabled.
		 *
		 * @since 1.1
		 *
		 * @param array $field_filters The form field, entry properties, and entry meta filter settings.
		 * @param array $form          The form object the filter settings have been prepared for.
		 *
		 * @return array $field_filters
		 */
		public function filter_gform_field_filters( $field_filters, $form ) {
			if ( gravity_flow_woocommerce()->is_woocommerce_orders_integration_enabled( $form['id'] ) ) {
				$wc_order_statuses = $this->wc_order_statuses();

				foreach ( $field_filters as $k => $field_filter ) {
					if ( $field_filter['key'] === 'payment_status' ) {
						$field_filters[ $k ]['values'] = $wc_order_statuses;
						return $field_filters;
					}
				}
			}
			return $field_filters;
		}

		/**
		 * Get WooCommerce order statuses as an array.
		 *
		 * @since 1.1
		 *
		 * @return array
		 */
		public function wc_order_statuses() {
			$woocommerce_order_statuses = wc_get_order_statuses();
			$wc_order_statuses          = array();
			foreach ( $woocommerce_order_statuses as $value => $text ) {
				$wc_order_statuses[] = array(
					'text'  => $text,
					'value' => str_replace( 'wc-', '', $value ),
				);
			}

			return $wc_order_statuses;
		}

		/**
		 * Helper function to check if a form already has entries from the WooCommerce Gravity Forms extension.
		 *
		 * @since 1.1
		 *
		 * @param int $form_id Form id.
		 * @param int $order_id Order id.
		 *
		 * @return boolean
		 */
		public function has_wcgf_entries( $form_id, $order_id ) {
			if ( function_exists( 'wc_gfpa' ) ) {
				$search_criteria['field_filters'][] = array(
					'key'   => 'woocommerce_order_number',
					'value' => $order_id,
				);
				$entries                            = GFAPI::get_entries( $form_id, $search_criteria );
				if ( ! is_wp_error( $entries ) && count( $entries ) > 0 ) {
					foreach ( $entries as $entry ) {
						if ( ! rgar( $entry, 'workflow_woocommerce_order_id' ) ) {
							// add entry meta and order meta, so we can update the entry payment status or release steps later.
							add_post_meta( $order_id, '_gravityflow-entry-id', $entry['id'] );
							gform_update_meta( $entry['id'], 'workflow_woocommerce_order_id', $order_id );

							$this->log_debug( __METHOD__ . '(): Entry #' . $entry['id'] . ' was created by the WooCommerce Gravity Forms add-on.' );
						}
					}

					return true;
				}
			}

			return false;
		}

		/**
		 * Perform scripts when this extension upgrades.
		 *
		 * @since 1.1
		 *
		 * @param string $previous_version Previous version.
		 */
		public function upgrade( $previous_version ) {
			if ( ! empty( $previous_version ) && version_compare( '1.1.0', $previous_version, '>' ) ) {
				$this->upgrade_steps_1_1();
			}
		}

		/**
		 * Upgrade steps.
		 *
		 * @since 1.1
		 */
		public function upgrade_steps_1_1() {
			$forms = GFAPI::get_forms();
			foreach ( $forms as $form ) {
				$feeds = gravity_flow()->get_feeds( $form['id'] );
				foreach ( $feeds as $feed ) {
					if ( $feed['meta']['step_type'] === 'woocommerce_cancel_payment' ) {
						$feed['meta']['step_type'] = 'woocommerce_cancel_order';
						gravity_flow()->update_feed_meta( $feed['id'], $feed['meta'] );
					} elseif ( $feed['meta']['step_type'] === 'woocommerce_refund_payment' ) {
						$feed['meta']['step_type'] = 'woocommerce_refund_order';
						gravity_flow()->update_feed_meta( $feed['id'], $feed['meta'] );
					}
				}
			}
		}

		/**
		 * Returns a hash based on the current entry ID and the step timestamp.
		 *
		 * @since 1.1
		 *
		 * @param int               $order_entry_id Parent entry id.
		 * @param Gravity_Flow_Step $step Step object.
		 *
		 * @return string
		 */
		public function get_workflow_order_hash( $order_entry_id, $step ) {
			return wp_hash( 'workflow_order_entry_id:' . $order_entry_id . $step->get_step_timestamp() );
		}

		/**
		 * Set workflow hash in WooCommerce session
		 *
		 * @since 1.1
		 */
		public function set_workflow_order_hash_session() {
			if ( ! isset( $_GET['workflow_order_hash'] ) || ! isset( $_GET['workflow_order_entry_id'] ) ) {
				return;
			}

			$this->log_debug( __METHOD__ . '() starting' );

			$parent_entry_id = absint( rgget( 'workflow_order_entry_id' ) );
			$hash            = rgget( 'workflow_order_hash' );
			$parent_entry    = GFAPI::get_entry( $parent_entry_id );
			$api             = new Gravity_Flow_API( $parent_entry['form_id'] );

			$current_step = $api->get_current_step( $parent_entry );
			if ( empty( $current_step ) || ! $current_step instanceof Gravity_Flow_Step_Woocommerce_Payment ) {
				$this->log_debug( __METHOD__ . '(): Entry #' . $parent_entry_id . '\'s current step isn\'t a WooCommerce Payment step' );
				return;
			}

			$verify_hash = $this->get_workflow_order_hash( $parent_entry_id, $current_step );
			if ( ! hash_equals( $hash, $verify_hash ) ) {
				$this->log_debug( __METHOD__ . '(): Workflow hash check failed' );
				return;
			}

			WC()->session->set( 'workflow_order_hash', $hash );
			WC()->session->set( 'workflow_order_entry_id', $parent_entry_id );
			$this->log_debug( __METHOD__ . '(): Set workflow hash in WooCommerce session with Entry #' . $parent_entry_id );
		}

		/**
		 * Save entry hash when a WooCommerce order created.
		 *
		 * @since 1.1
		 *
		 * @param int $order_id WooCommerce Order ID.
		 */
		public function save_entry_hash( $order_id ) {
			// WC session only available on the frontend, need to start it manually here.
			$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
			$wc_session    = new $session_class();

			if ( version_compare( WC_VERSION, '3.3', '>=' ) ) {
				$wc_session->init();
			}

			$hash            = $wc_session->get( 'workflow_order_hash' );
			$parent_entry_id = $wc_session->get( 'workflow_order_entry_id' );
			if ( empty( $hash ) || empty( $parent_entry_id ) ) {
				return;
			}

			update_post_meta( $order_id, '_workflow_order_hash', $hash );
			update_post_meta( $order_id, '_workflow_order_entry_id', $parent_entry_id );
			gform_update_meta( $parent_entry_id, 'workflow_woocommerce_order_id', $order_id );

			$parent_entry = GFAPI::get_entry( $parent_entry_id );
			$api          = new Gravity_Flow_API( $parent_entry['form_id'] );

			$current_step = $api->get_current_step( $parent_entry );
			if ( $current_step instanceof Gravity_Flow_Step_Woocommerce_Payment ) {
				$order = wc_get_order( $order_id );
				$this->log_debug( __METHOD__ . "(): WooCommerce order #{$order_id} has been created. Order status: {$order->get_status()}" );
				$note = $current_step->get_name() . ': ' . sprintf( esc_html__( 'WooCommerce order #%s has been created. Order status: %s.', 'gravityflowwoocommerce' ), $order_id, $order->get_status() );
				$current_step->add_note( $note );
			}
		}

		/**
		 * Release the entry from a payment step when an order is created with the workflow hash.
		 *
		 * @since 1.1
		 *
		 * @param int      $order_id WooCommerce Order ID.
		 * @param string   $from_status WooCommerce old order status.
		 * @param string   $to_status WooCommerce new order status.
		 * @param WC_Order $order WooCommerce Order object.
		 */
		public function release_payment_step( $order_id, $from_status, $to_status, $order ) {
			$hash            = get_post_meta( $order_id, '_workflow_order_hash', true );
			$parent_entry_id = get_post_meta( $order_id, '_workflow_order_entry_id', true );
			if ( empty( $hash ) || empty( $parent_entry_id ) ) {
				return;
			}

			// means it's a front end action by the user,
			// and they have finished the payment process,
			if ( WC()->session && $to_status !== 'pending' ) {
				WC()->session->set( 'workflow_order_hash', null );
				WC()->session->set( 'workflow_order_entry_id', null );
			}

			if ( $to_status !== 'processing' && $to_status !== 'completed' ) {
				return;
			}

			$parent_entry = GFAPI::get_entry( $parent_entry_id );
			$api          = new Gravity_Flow_API( $parent_entry['form_id'] );

			$current_step = $api->get_current_step( $parent_entry );
			if ( $current_step instanceof Gravity_Flow_Step_Woocommerce_Payment ) {
				$this->log_debug( __METHOD__ . '() starting' );

				$verify_hash = $this->get_workflow_order_hash( $parent_entry_id, $current_step );
				if ( ! hash_equals( $hash, $verify_hash ) ) {
					$this->log_debug( __METHOD__ . '(): Workflow hash check failed' );
					return;
				}

				// Ideally, the order is completed by the assignee themselves.
				$current_user_is_assignee  = false;
				$current_user_assignee_key = $current_step->get_current_assignee_key();
				if ( $current_user_assignee_key ) {
					$assignee                 = $current_step->get_assignee( $current_user_assignee_key );
					$current_user_is_assignee = $assignee->is_current_user();
					if ( $current_user_is_assignee ) {
						$assignee->update_status( 'complete' );
					}
				}
				// But it could be possible the order is completed by the admin,
				// for example, the payment was set to on-hold and then marked as completed by the admin.
				if ( ! $current_user_is_assignee || ! $current_user_assignee_key ) {
					$assignees = $current_step->get_assignees();
					foreach ( $assignees as $assignee ) {
						$assignee->update_status( 'complete' );
					}
				}

				$order = wc_get_order( $order_id );
				$this->log_debug( __METHOD__ . "(): WooCommerce order #{$order_id} has been paid. Order status: {$order->get_status()}" );
				$note = $current_step->get_name() . ': ' . sprintf( esc_html__( 'WooCommerce order #%s has been paid. Order status: %s.', 'gravityflowwoocommerce' ), $order_id, $order->get_status() );
				$current_step->add_note( $note );

				$page_id = $current_step->order_received_redirection;
				if ( ! empty( $page_id ) ) {
					$url = $this->get_order_received_redirection_url( $page_id, $parent_entry_id );
					gform_update_meta( $parent_entry_id, '_workflow_order_received_redirection_url', $url );
				}

				$api->process_workflow( $parent_entry_id );
				$current_step->refresh_entry();
			}
		}

		/**
		 * Redirect to the workflow entry detail after the payment made.
		 *
		 * @since 1.1
		 *
		 * @param string   $url URL.
		 * @param WC_Order $order WooCommerce order object.
		 *
		 * @return string
		 */
		public function woocommerce_get_checkout_order_received_url( $url, $order ) {
			$entry_id = get_post_meta( $order->get_id(), '_workflow_order_entry_id', true );

			if ( $entry_id ) {
				$entry        = GFAPI::get_entry( $entry_id );
				$api          = new Gravity_Flow_API( $entry['form_id'] );
				$current_step = $api->get_current_step( $entry );
				$page_id      = $current_step->order_received_redirection;

				if ( ! empty( $page_id ) ) {
					$url = $this->get_order_received_redirection_url( $page_id, $entry_id );
				} else {
					$_url = gform_get_meta( $entry_id, '_workflow_order_received_redirection_url' );
					if ( ! empty( $_url ) ) {
						$url = $_url;
					}
				}
			}

			return $url;
		}

		/**
		 * Prevent a product added more than once when revisiting the URL.
		 *
		 * @since 1.1
		 *
		 * @param string $location URL.
		 *
		 * @return string
		 */
		public function add_to_cart_redirect( $location ) {
			if ( empty( $_REQUEST['add-to-cart'] ) || ! is_numeric( $_REQUEST['add-to-cart'] ) ) {
				return $location;
			}

			$product_id      = intval( $_GET['add-to-cart'] );
			$product_cart_id = WC()->cart->generate_cart_id( $product_id );
			$in_cart         = WC()->cart->find_product_in_cart( $product_cart_id );

			if ( $in_cart ) {
				$location = remove_query_arg( 'add-to-cart', $location );
			}

			return $location;
		}

		/**
		 * Get order received redirection url.
		 *
		 * @since 1.1
		 *
		 * @param string $page_id Page ID.
		 * @param string $entry_id Entry ID.
		 *
		 * @return string
		 */
		public function get_order_received_redirection_url( $page_id, $entry_id ) {
			$entry      = GFAPI::get_entry( $entry_id );
			$query_args = array(
				'page' => 'gravityflow-inbox',
				'view' => 'entry',
				'id'   => $entry['form_id'],
				'lid'  => $entry_id,
			);

			$url = Gravity_Flow_Common::get_workflow_url( $query_args, $page_id, null, gravity_flow()->get_access_token() );

			return $url;
		}
	}
}
