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
		 * Process the step.
		 *
		 * @since 1.0.0-dev
		 *
		 * @return bool
		 */
		public function process() {
			/**
			 * Fires when the workflow is first assigned to the billing email.
			 *
			 * @since 1.0.0
			 *
			 * @param array $entry The current entry.
			 * @param array $form The current form.
			 * @param array $step The current step.
			 */
			do_action( 'gravityflowwoocommerce_refund_payment_step_started', $this->get_entry(), $this->get_form(), $this );

			$order_id = gform_get_meta( $this->get_entry_id(), 'workflow_woocommerce_order_id' );
			$order    = wc_get_order( $order_id );
			if ( 'processing' === $order->get_status() || 'completed' === $order->get_status() ) {
				$note = $this->get_name() . ': ' . esc_html__( 'Processed.', 'gravityflowwoocommerce' );
				$this->add_note( $note );

				// remove the default WooCommerce refund behavior, because we want to refund the payment and restock items.
				remove_action( 'woocommerce_order_status_refunded', 'wc_order_fully_refunded' );
				$note   = $this->get_name() . ': ' . esc_html__( 'Refunded the order.', 'gravityflowwoocommerce' );
				$result = $order->update_status( 'refunded', $note );
				if ( ! $result ) {
					$note = $this->get_name() . ': ' . esc_html__( 'Failed to refund the order. Step completed without refunding payment.', 'gravityflowwoocommerce' );
				} else {
					$max_refund = wc_format_decimal( $order->get_total() - $order->get_total_refunded() );

					if ( $max_refund ) {
						wc_create_refund(
							array(
								'amount'         => $max_refund,
								'reason'         => $note,
								'order_id'       => $order_id,
								'line_items'     => $order->get_items( array( 'line_item', 'fee', 'shipping' ) ),
								'refund_payment' => true,
								'restock_items'  => true,
							)
						);
					}
				}

				$this->add_note( $note );
			} else {
				$note = $this->get_name() . ': ' . esc_html__( 'Payment is not made. Step completed without refunding payment.', 'gravityflowwoocommerce' );
				$this->add_note( $note );
			}

			return true;
		}
	}

	Gravity_Flow_Steps::register( new Gravity_Flow_Step_Woocommerce_Refund_Payment() );
}
