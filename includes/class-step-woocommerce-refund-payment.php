<?php

/**
 * Gravity Flow WooCommerce Refund Step
 *
 * @package     GravityFlow
 * @subpackage  Classes/Step
 * @copyright   Copyright (c) 2015-2018, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-3.0.php GNU Public License
 * @since       1.0.0-dev
 */

if ( class_exists( 'Gravity_Flow_Step' ) && function_exists( 'WC' ) ) {

	class Gravity_Flow_Step_Woocommerce_Refund_Payment extends Gravity_Flow_Step_Woocommerce_Capture_Payment {
		/**
		 * A unique key for this step type.
		 *
		 * @var string
		 */
		public $_step_type = 'woocommerce_refund_payment';

		/**
		 * Returns the label for the step.
		 *
		 * @since 1.0.0-dev
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'Refund Payment', 'gravityflowwoocommerce' );
		}

		/**
		 * Adds an alert to the step settings area.
		 *
		 * @return array
		 */
		public function get_settings() {
			return array();
		}

		/**
		 * Returns an array of statuses and their properties.
		 *
		 * @return array
		 */
		public function get_status_config() {
			return array(
				array(
					'status'                    => 'refunded',
					'status_label'              => __( 'Refunded', 'gravityflowwoocommerce' ),
					'destination_setting_label' => __( 'Next Step if Refunded', 'gravityflowwoocommerce' ),
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
		 * @param string $payment_status The WooCommerce order payment status.
		 *
		 * @return bool
		 */
		public function is_valid_payment_status( $payment_status ) {
			return $payment_status === 'processing' || 'completed';
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

			// remove the default WooCommerce refund behavior, because we want to refund the payment and restock items.
			remove_action( 'woocommerce_order_status_refunded', 'wc_order_fully_refunded' );
			$note   = $this->get_name() . ': ' . esc_html__( 'Refunded the order.', 'gravityflowwoocommerce' );
			$update = $order->update_status( 'refunded', $note );

			if ( $update ) {
				$this->log_debug( __METHOD__ . '(): Updated WooCommerce order status to refunded.' );

				$max_refund = wc_format_decimal( $order->get_total() - $order->get_total_refunded() );
				if ( $max_refund ) {
					try {
						wc_create_refund(
							array(
								'amount'         => $max_refund,
								'reason'         => $note,
								'order_id'       => $order->get_id(),
								'line_items'     => $order->get_items( array( 'line_item', 'fee', 'shipping' ) ),
								'refund_payment' => true,
								'restock_items'  => true,
							)
						);

						$result = 'refunded';
						$this->log_debug( __METHOD__ . '(): Charge refunded.' );
					} catch ( Exception $e ) {
						$result = 'failed';
						$this->log_debug( __METHOD__ . '(): Refund failed.' );
						$note = $this->get_name() . ': ' . esc_html__( 'WooCommerce order has been marked as refund but failed to refund the payment.', 'gravityflowwoocommerce' );
					}
				}
			} else {
				$this->log_debug( __METHOD__ . '(): Unable to update WooCommerce order status to refunded.' );
				$this->log_debug( __METHOD__ . '(): Unable to refund charge.' );
				$note = $this->get_name() . ': ' . esc_html__( 'Failed to refund the order.', 'gravityflowwoocommerce' );
			}

			$this->add_note( $note );

			return $result;
		}
	}

	Gravity_Flow_Steps::register( new Gravity_Flow_Step_Woocommerce_Refund_Payment() );
}
