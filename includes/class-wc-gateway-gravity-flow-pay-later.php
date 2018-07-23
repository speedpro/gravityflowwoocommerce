<?php

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
	 * Constructor for the gateway.
	 *
	 * @since 1.0.0-dev
	 */
	public function __construct() {
		$this->id                 = 'gravity_flow_pay_later';
		$this->has_fields         = false;
		$this->method_title       = __( 'Gravity Flow Pay Later', 'gravityflowwoocommerce' );
		$this->method_description = __( 'Allow customers to make a payment later.', 'gravityflowwoocommerce' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title            = $this->settings['title'];
		$this->description      = $this->settings['description'];
		$this->enabled          = $this->settings['enabled'];
		$this->pending_duration = $this->get_option( 'pending_duration' );

		add_filter( 'woocommerce_default_order_status', array( $this, 'default_order_status' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_filter( 'woocommerce_valid_order_statuses_for_payment', array( $this, 'valid_order_statuses_for_payment' ), 10, 2 );
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
	 * Set valid order statuses for payment.
	 *
	 * @since 1.0.0-dev
	 *
	 * @param array  $statuses Payment statuses.
	 * @param object $order WooCommerce Order.
	 *
	 * @return array Payment statuses.
	 */
	public function valid_order_statuses_for_payment( $statuses, $order ) {
		if ( true === get_post_meta( $order->get_id(), '_is_gravity_flow_pay_later', true ) ) {
			$statuses[] = 'on-hold';
		}

		return $statuses;
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 *
	 * @since 1.0.0-dev
	 */
	public function init_form_fields() {
		$form_fields = array(
			'enabled'     => array(
				'title'   => __( '<b>Enable/Disable:</b>', 'gravityflowwoocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Gravity Flow Pay Later Payment Gateway.', 'gravityflowwoocommerce' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'       => __( '<b>Title:</b>', 'gravityflowwoocommerce' ),
				'type'        => 'text',
				'description' => __( 'The title which the user sees during checkout.', 'gravityflowwoocommerce' ),
				'default'     => __( 'Gravity Flow Pay Later', 'gravityflowwoocommerce' ),
			),
			'description' => array(
				'title'       => __( '<b>Description:</b>', 'gravityflowwoocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'gravityflowwoocommerce' ),
				'default'     => __( 'Place your order now, and make a payment later.', 'gravityflowwoocommerce' ),
			),
		);

		$held_duration = get_option( 'woocommerce_hold_stock_minutes' );
		if ( $held_duration > 1 && 'no' !== get_option( 'woocommerce_manage_stock' ) ) {
			$form_fields['pending_duration'] = array(
				'title'       => __( '<b>Pending Duration:</b>', 'gravityflowwoocommerce' ),
				'type'        => 'number',
				'description' => __( 'Hold an order as pending for x days. If after the duration it still hasn\'t been paid, the order will be cancelled.', 'gravityflowwoocommerce' ),
				'default'     => 7,
			);
		}

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
}
