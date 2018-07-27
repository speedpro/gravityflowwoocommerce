<?php

/**
 * Gravity Flow WooCommerce Cancel Step
 *
 * @package     GravityFlow
 * @subpackage  Classes/Step
 * @copyright   Copyright (c) 2015-2018, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-3.0.php GNU Public License
 * @since       1.0.0-dev
 */

if ( class_exists( 'Gravity_Flow_Step' ) && function_exists( 'WC' ) ) {

	class Gravity_Flow_Step_Woocommerce_Cancel_Payment extends Gravity_Flow_Step_Woocommerce_Capture_Payment {
		/**
		 * A unique key for this step type.
		 *
		 * @var string
		 */
		public $_step_type = 'woocommerce_cancel_payment';

		/**
		 * Returns the label for the step.
		 *
		 * @since 1.0.0-dev
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'Cancel Payment', 'gravityflowwoocommerce' );
		}

		/**
		 * Returns an array of statuses and their properties.
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
		 * Cancels the WooCommerce order.
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
				$note = $this->get_name() . ': ' . esc_html__( 'Failed to update WooCommerce order status. Step completed without cancelling payment.', 'gravityflowwoocommerce' );
			}

			$this->add_note( $note );

			return $result;
		}
	}

	Gravity_Flow_Steps::register( new Gravity_Flow_Step_Woocommerce_Cancel_Payment() );
}
