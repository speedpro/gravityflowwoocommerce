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

	class Gravity_Flow_Step_Woocommerce_Cancel_Order extends Gravity_Flow_Step_Woocommerce_Capture_Payment {
		/**
		 * A unique key for this step type.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $_step_type = 'woocommerce_cancel_order';

		/**
		 * Returns the label for the step.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'Cancel Order', 'gravityflowwoocommerce' );
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
		 * @since 1.1.0
		 *
		 * @param string $payment_status The WooCommerce order payment status.
		 *
		 * @return bool
		 */
		public function is_valid_payment_status( $payment_status ) {
			return $payment_status === 'pending' || 'on-hold';
		}

		/**
		 * Cancels the WooCommerce order.
		 *
		 * @since 1.0.0
		 *
		 * @param WC_Order $order The WooCommerce order.
		 *
		 * @return string
		 */
		public function process_action( $order ) {
			$result = 'failed';

			// Cancel the order, so no charge will be made.
			$note   = $this->get_name() . ': ' . esc_html__( 'Cancelled the order.', 'gravityflowwoocommerce' );
			$update = $order->update_status( 'cancelled', $note );

			if ( $update ) {
				$result = 'cancelled';
				$this->log_debug( __METHOD__ . '(): Updated WooCommerce order status to cancelled.' );
				$this->log_debug( __METHOD__ . '(): Charge authorization cancelled.' );
			} else {
				$this->log_debug( __METHOD__ . '(): Unable to update WooCommerce order status to cancelled.' );
				$this->log_debug( __METHOD__ . '(): Unable to cancel charge authorization.' );
				$note = $this->get_name() . ': ' . esc_html__( 'Failed to update WooCommerce order status.', 'gravityflowwoocommerce' );
			}

			$this->add_note( $note );

			return $result;
		}
	}

	Gravity_Flow_Steps::register( new Gravity_Flow_Step_Woocommerce_Cancel_Order() );
}
