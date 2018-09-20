<?php

/**
 * Gravity Flow WooCommerce Cancel Step
 *
 * @package     GravityFlow
 * @subpackage  Classes/Step
 * @copyright   Copyright (c) 2015-2018, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-3.0.php GNU Public License
 * @since       1.0.0
 */

if ( class_exists( 'Gravity_Flow_Step' ) && function_exists( 'WC' ) ) {

	class Gravity_Flow_Step_Woocommerce_Cancel_Payment extends Gravity_Flow_Step_Woocommerce_Base {
		/**
		 * A unique key for this step type.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $_step_type = 'woocommerce_cancel_payment';

		/**
		 * Returns the label for the step.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'Cancel Payment', 'gravityflowwoocommerce' );
		}

		/**
		 * Adds an alert to the step settings area.
		 *
		 * @since 1.1
		 *
		 * @return array
		 */
		public function get_settings() {
			return array(
				'description' => sprintf( '<div class="delete-alert alert_yellow"><i class="fa fa-exclamation-triangle gf_invalid"></i> %s</div>', esc_html__( 'Payment gateways automatically cancel (expire) authorized charges which are not captured within certain days. For example, Stripe cancels them after 7 days and PayPal does the same after 29 days.' ) )
			);
		}

		/**
		 * Returns an array of statuses and their properties.
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public function get_status_config() {
			return array(
				array(
					'status'                    => 'cancelled',
					'status_label'              => __( 'Cancelled', 'gravityflowwoocommerce' ),
					'destination_setting_label' => __( 'Next Step if Cancelled', 'gravityflowwoocommerce' ),
					'default_destination'       => 'next',
				),
				array(
					'status'                    => 'failed',
					'status_label'              => __( 'Failed', 'gravityflowwoocommerce' ),
					'destination_setting_label' => __( 'Next step if Failed', 'gravityflowwoocommerce' ),
					'default_destination'       => 'complete',
				),
			);
		}

		/**
		 * Determines if the entry payment status is valid for the current action.
		 *
		 * @since 1.1
		 *
		 * @param string $payment_status The WooCommerce order payment status.
		 *
		 * @return bool
		 */
		public function is_valid_payment_status( $payment_status ) {
			return $payment_status === 'on-hold';
		}

		/**
		 * Cancels the WooCommerce order.
		 *
		 * @since 1.0.0
		 * @since 1.1   Move the action method to the base class
		 *
		 * @param WC_Order $order The WooCommerce order.
		 *
		 * @return string
		 */
		public function process_action( $order ) {
			return $this->cancel_payment( $order );
		}
	}

	Gravity_Flow_Steps::register( new Gravity_Flow_Step_Woocommerce_Cancel_Payment() );
}
