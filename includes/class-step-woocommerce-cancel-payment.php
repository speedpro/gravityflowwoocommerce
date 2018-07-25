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

if ( class_exists( 'Gravity_Flow_Step' ) ) {

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
			do_action( 'gravityflowwoocommerce_cancel_payment_step_started', $this->get_entry(), $this->get_form(), $this );

			$order_id = gform_get_meta( $this->get_entry_id(), 'workflow_woocommerce_order_id' );
			$order    = wc_get_order( $order_id );
			if ( 'on-hold' === $order->get_status() ) {
				$note = $this->get_name() . ': ' . esc_html__( 'Processed.', 'gravityflowwoocommerce' );
				$this->add_note( $note );

				// Cancel the order, so no charge will be made.
				$note   = $this->get_name() . ': ' . esc_html__( 'Cancelled the order.', 'gravityflowwoocommerce' );
				$result = $order->update_status( 'cancelled', $note );
				if ( ! $result ) {
					$note = $this->get_name() . ': ' . esc_html__( 'Failed to cancel the order. Step completed without cancelling payment.', 'gravityflowwoocommerce' );
				}
				$this->add_note( $note );
			} else {
				$note = $this->get_name() . ': ' . esc_html__( 'Payment is not on hold. Step completed without cancelling payment.', 'gravityflowwoocommerce' );
				$this->add_note( $note );
			}

			return true;
		}
	}
}

Gravity_Flow_Steps::register( new Gravity_Flow_Step_Woocommerce_Cancel_Payment() );