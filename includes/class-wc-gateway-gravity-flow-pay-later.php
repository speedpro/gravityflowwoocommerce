<?php

if ( ! function_exists( 'WC' ) ) {
	return;
}

/**
 * Payment Gateway
 *
 * @package     GravityFlow
 * @subpackage  Classes/Payment_Gateway
 * @copyright   Copyright (c) 2015-2018, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-3.0.php GNU Public License
 * @since       1.0.0-dev
 */
class WC_Gateway_Gravity_Flow_Pay_Later extends WC_Payment_Gateway {

	/**
	 * The time in days an order can be held as pending.
	 *
	 * @since 1.0.0-dev
	 *
	 * @var int
	 */
	public $pending_duration;

	/**
	 * Disable other gateways on the checkout page.
	 *
	 * @since 1.0.0-dev
	 *
	 * @var string
	 */
	public $disable_other_gateways_on_checkout;

	/**
	 * Constructor for the gateway.
	 *
	 * @since 1.0.0-dev
	 */
	public function __construct() {
		$this->id                 = 'gravity_flow_pay_later';
		$this->has_fields         = false;
		$this->method_title       = esc_html__( 'Gravity Flow Gateway', 'gravityflowwoocommerce' );
		$this->method_description = esc_html__( 'Allow customers to make a payment later in the workflow instead of at the checkout.', 'gravityflowwoocommerce' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title                              = $this->settings['title'];
		$this->description                        = $this->settings['description'];
		$this->enabled                            = $this->settings['enabled'];
		$this->pending_duration                   = $this->get_option( 'pending_duration' );
		$this->disable_other_gateways_on_checkout = $this->get_option( 'disable_other_gateways_on_checkout' );

		add_filter( 'woocommerce_default_order_status', array( $this, 'default_order_status' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'orders_actions' ), 10, 2 );
		add_filter( 'woocommerce_order_has_status', array( $this, 'has_status' ), 10, 3 );
		add_filter( 'woocommerce_email_format_string', array( $this, 'email_format_string' ), 10, 2 );
		add_action( 'woocommerce_after_template_part', array( $this, 'filter_email_content' ), 10, 4 );
		add_action( 'woocommerce_order_status_pending', array( $this, 'send_pending_order_emails' ), 10, 2 );
	}

	/**
	 * Change the default order status to on-hold so that pending order emails can be triggered.
	 *
	 * @since 1.0.0-dev
	 *
	 * @param string $default Default order status.
	 *
	 * @return string Default order status.
	 */
	public function default_order_status( $default ) {
		if ( ! is_admin() && WC()->session->get( 'chosen_payment_method' ) === $this->id ) {
			$default = 'on-hold';
		}

		return $default;
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 *
	 * @since 1.0.0-dev
	 */
	public function init_form_fields() {
		$form_fields = array(
			'enabled'     => array(
				'title'   => sprintf('<b>%s</b>', esc_html__( 'Enable/Disable', 'gravityflowwoocommerce' ) ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Enable the Gravity Flow Gateway.', 'gravityflowwoocommerce' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'       => sprintf( '<b>%s</b>', esc_html__( 'Title', 'gravityflowwoocommerce' ) ),
				'type'        => 'text',
				'description' => esc_html__( 'The title which the user sees during checkout.', 'gravityflowwoocommerce' ),
				'default'     => esc_html__( 'Pay Later', 'gravityflowwoocommerce' ),
			),
			'description' => array(
				'title'       => sprintf( '<b>%s</b>', esc_html__( 'Description', 'gravityflowwoocommerce' ) ),
				'type'        => 'textarea',
				'description' => esc_html__( 'This controls the description which the user sees during checkout.', 'gravityflowwoocommerce' ),
				'default'     => esc_html__( 'Place your order now, and pay later.', 'gravityflowwoocommerce' ),
			),
		);

		$held_duration = get_option( 'woocommerce_hold_stock_minutes' );
		if ( $held_duration > 1 && 'no' !== get_option( 'woocommerce_manage_stock' ) ) {
			$form_fields['pending_duration'] = array(
				'title'       => sprintf( '<b>%s</b>', esc_html__( 'Pending Duration', 'gravityflowwoocommerce' ) ),
				'type'        => 'number',
				'description' => esc_html__( 'How many days should the order be held as pending before it is automatically cancelled?', 'gravityflowwoocommerce' ),
				'default'     => 7,
			);
		}

		$form_fields['disable_other_gateways_on_checkout'] = array(
			'title'   => sprintf( '<b>%s</b>', esc_html__( 'Disable Other Gateways', 'gravityflowwoocommerce' ) ),
			'type'    => 'checkbox',
			'label'   => esc_html__( 'Disable other gateways at the checkout.', 'gravityflowwoocommerce' ),
			'description'   => esc_html__( 'Activate this setting to disable all other gateways on the checkout. They will still be available when the customer pays later in the workflow.', 'gravityflowwoocommerce' ),
			'default' => 'no',
		);

		$this->form_fields = $form_fields;
	}

	/**
	 * Process the payment, set the Order to pending and return the result.
	 *
	 * @since 1.0.0-dev
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		$order->update_status( 'pending' );

		update_post_meta( $order_id, '_is_gravity_flow_pay_later', true );

		// Reduce stock levels.
		wc_reduce_stock_levels( $order_id );

		// Remove cart.
		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => apply_filters( 'wc_pay_later_order_received_url', $order->get_checkout_order_received_url(), $order, $this ),
		);
	}

	/**
	 * Filter WooCommerce My Account My Order actions.
	 *
	 * @param array    $actions Order actions.
	 * @param WC_Order $order WooCommerce order.
	 *
	 * @return mixed
	 */
	public function orders_actions( $actions, $order ) {
		if ( ! isset( $actions['pay'] ) ) {
			return $actions;
		}

		if ( $order->get_payment_method() === $this->id ) {
			$entry_ids = get_post_meta( $order->get_id(), '_gravityflow-entry-id' );
			foreach ( $entry_ids as $entry_id ) {
				$entry = GFAPI::get_entry( $entry_id );
				if ( is_wp_error( $entry ) || ! gravity_flow_woocommerce()->is_woocommerce_orders_integration_enabled( $entry['form_id'] ) ) {
					continue;
				}

				$api          = new Gravity_Flow_API( $entry['form_id'] );
				$current_step = $api->get_current_step( $entry );
				if ( $current_step && 'woocommerce_payment' === $current_step->get_type() ) {
					$can_pay = 1;
				}
			}

			if ( ! isset( $can_pay ) ) {
				unset( $actions['pay'] );
			}
		}

		return $actions;
	}

	/**
	 * Modify has_status() so we could hide the "pay for this order" link in emails.
	 *
	 * @param boolean      $result Result.
	 * @param WC_Order     $order WooCommerce order.
	 * @param string|array $status Status to check against.
	 *
	 * @return boolean $result
	 */
	public function has_status( $result, $order, $status ) {
		if ( $result && 'pending' === $status ) {
			if ( $order->get_payment_method() === $this->id ) {
				$result = false;

				$entry_ids = get_post_meta( $order->get_id(), '_gravityflow-entry-id' );
				foreach ( $entry_ids as $entry_id ) {
					$entry = GFAPI::get_entry( $entry_id );
					if ( is_wp_error( $entry ) || ! gravity_flow_woocommerce()->is_woocommerce_orders_integration_enabled( $entry['form_id'] ) ) {
						continue;
					}

					$api          = new Gravity_Flow_API( $entry['form_id'] );
					$current_step = $api->get_current_step( $entry );
					if ( $current_step && 'woocommerce_payment' === $current_step->get_type() ) {
						$result = true;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Replace order status in WooCommerce Emails.
	 *
	 * @param string   $string Email strings.
	 * @param WC_Email $email WooCommerce email object.
	 *
	 * @return mixed
	 */
	public function email_format_string( $string, $email ) {
		if ( 'customer_on_hold_order' === $email->id && $email->object->get_payment_method() === $this->id ) {
			$string = str_replace( '{order_status}', wc_get_order_status_name( $email->object->get_status() ), $string );
		}

		return $string;
	}

	/**
	 * Filter WooCommerce on-hold email template.
	 *
	 * @param string $template_name Template name.
	 * @param string $template_path Template path.
	 * @param string $located  Locate a template and return the path for inclusion.
	 * @param array  $args     Arguments.
	 */
	public function filter_email_content( $template_name, $template_path, $located, $args ) {
		if ( ! isset( $args['email'] ) || ! $args['order'] ) {
			return;
		}

		if ( 'customer_on_hold_order' === $args['email']->id && $args['order']->get_payment_method() === $this->id ) {
			$email_content = ob_get_contents();
			$email_content = str_replace( 'on-hold', 'pending', $email_content );
			ob_end_clean();

			ob_start();
			echo $email_content;
		}
	}

	/**
	 * Trigger pending order emails and invoice email.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order WooCommerce order.
	 */
	public function send_pending_order_emails( $order_id, $order ) {
		if ( $order->get_payment_method() !== $this->id ) {
			return;
		}

		$emails = new WC_Emails();
		$emails->emails['WC_Email_Customer_On_Hold_Order']->trigger( $order_id );
		$emails->emails['WC_Email_New_Order']->trigger( $order_id );
	}
}
