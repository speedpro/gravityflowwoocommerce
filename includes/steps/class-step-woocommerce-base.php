<?php

/**
 * Gravity Flow WooCommerce Base
 *
 * @package     GravityFlow
 * @subpackage  Classes/Step
 * @copyright   Copyright (c) 2015-2018, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-3.0.php GNU Public License
 * @since       1.1
 */

if ( class_exists( 'Gravity_Flow_Step' ) && function_exists( 'WC' ) ) {

	class Gravity_Flow_Step_Woocommerce_Base extends Gravity_Flow_Step {
		/**
		 * Set a custom icon in the step settings.
		 * 32px x 32px
		 *
		 * @since 1.1
		 *
		 * @return string
		 */
		public function get_icon_url() {
			return '<i class="woocommerce" aria-hidden="true"></i>';
		}

		/**
		 * Is this step supported on this server? Override to hide this step in the list of step types if the requirements are not met.
		 *
		 * @since 1.1
		 *
		 * @return bool
		 */
		public function is_supported() {
			$form_id = $this->get_form_id();

			return function_exists( 'WC' ) && gravity_flow_woocommerce()->is_woocommerce_orders_integration_enabled( $form_id );
		}

		/**
		 * Ensure the step is not processed if the WooCommerce is not supported.
		 *
		 * @since 1.1
		 *
		 * @return bool
		 */
		public function is_active() {
			$is_active = parent::is_active();

			if ( $is_active && ! $this->is_supported() ) {
				$is_active = false;
			}

			return $is_active;
		}

		/**
		 * Processes this step.
		 *
		 * @since 1.1
		 *
		 * @return bool Is the step complete?
		 */
		public function process() {
			$this->log_debug( __METHOD__ . '() Starting action: ' . str_replace( 'woocommerce_', '', $this->get_type() ) );

			$order_id = $this->get_order_id();
			$order    = wc_get_order( $order_id );

			if ( ! $order || ! $this->is_valid_order( $order ) ) {
				$this->update_step_status( 'failed' );

				return true;
			}

			$step_status = $this->process_action( $order );

			$this->update_step_status( $step_status );
			$this->refresh_entry();

			return true;
		}

		/**
		 * Get WooCommerce order id.
		 *
		 * @since 1.1
		 *
		 * @return bool|mixed
		 */
		public function get_order_id() {
			$order_id = gform_get_meta( $this->get_entry_id(), 'workflow_woocommerce_order_id' );

			return $order_id;
		}

		/**
		 * Determines if the payment status is valid for the action to be performed by this step.
		 *
		 * @since 1.1
		 *
		 * @param WC_Order $order WooCommerce Order.
		 *
		 * @return bool
		 */
		public function is_valid_order( $order ) {

			$payment_status = $order->get_status();

			if ( ! $this->is_valid_payment_status( $payment_status ) ) {
				$this->log_debug( __METHOD__ . "(): Aborting; payment status ({$payment_status}) not valid for action." );

				return false;
			}

			return true;
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
			return false;
		}

		/**
		 * Processes the action for the current step.
		 *
		 * @since 1.1
		 *
		 * @param WC_Order $order The WooCommerce order.
		 *
		 * @return string
		 */
		public function process_action( $order ) {
			return '';
		}

		/**
		 * Captures the authorized charge.
		 *
		 * @since 1.1
		 *
		 * @param WC_Order $order The WooCommerce order.
		 * @param string   $to_status Update order to another status (processing or completed).
		 *
		 * @return string
		 */
		public function capture_payment( $order, $to_status = 'processing' ) {
			$result = 'failed';

			// Change order status, WooCommerce should charge cards then.
			$note      = $this->get_name() . ': ' . esc_html__( 'Updated WooCommerce order status to processing.', 'gravityflowwoocommerce' );
			$to_status = ( ! in_array( $to_status, array( 'processing', 'completed' ), true ) ) ? 'processing' : $to_status;
			$charge    = $order->update_status( $to_status, $note );

			if ( $charge ) {
				$result = 'captured';
				$this->log_debug( __METHOD__ . '(): Updated WooCommerce order status to processing.' );
				$this->log_debug( __METHOD__ . '(): Charge captured.' );
			} else {
				$this->log_debug( __METHOD__ . '(): Unable to update WooCommerce order status to processing.' );
				$this->log_debug( __METHOD__ . '(): Unable to capture charge.' );
				$note = $this->get_name() . ': ' . esc_html__( 'Failed to update WooCommerce order status.', 'gravityflowwoocommerce' );
			}

			$this->add_note( $note );

			return $result;
		}

		/**
		 * Cancels the WooCommerce order.
		 *
		 * @since 1.1
		 *
		 * @param WC_Order $order The WooCommerce order.
		 *
		 * @return string
		 */
		public function cancel_payment( $order ) {
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

		/**
		 * Refund the WooCommerce order.
		 *
		 * @since 1.1
		 *
		 * @param WC_Order $order The WooCommerce order.
		 *
		 * @return string
		 */
		public function refund_payment( $order ) {
			$result = 'failed';

			// remove the default WooCommerce refund behavior, because we want to refund the payment and restock items.
			remove_action( 'woocommerce_order_status_refunded', 'wc_order_fully_refunded' );
			$note   = $this->get_name() . ': ' . esc_html__( 'Refunded the order.', 'gravityflowwoocommerce' );
			$update = $order->update_status( 'refunded', $note );

			if ( $update ) {
				$this->log_debug( __METHOD__ . '(): Updated WooCommerce order status to refunded.' );

				$max_refund = wc_format_decimal( $order->get_total() - $order->get_total_refunded() );
				if ( $max_refund ) {
					$refund = wc_create_refund(
						array(
							'amount'         => $max_refund,
							'reason'         => $note,
							'order_id'       => $order->get_id(),
							'line_items'     => $order->get_items( array( 'line_item', 'fee', 'shipping' ) ),
							'refund_payment' => true,
							'restock_items'  => true,
						)
					);

					if ( is_wp_error( $refund ) ) {
						$this->log_debug( __METHOD__ . '(): Unable to refund charge; ' . $refund->get_error_message() );
						$note = $this->get_name() . ': ' . esc_html__( 'WooCommerce order has been marked as refund but failed to refund the payment.', 'gravityflowwoocommerce' );
					} else {
						$result = 'refunded';
						$this->log_debug( __METHOD__ . '(): Charge refunded.' );
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

		/**
		 * Determines the current status of the step.
		 *
		 * @since 1.1
		 *
		 * @return string
		 */
		public function status_evaluation() {
			$step_status = $this->get_status();

			return $step_status ? $step_status : 'complete';
		}

		/**
		 * Determines if the current step has been completed.
		 *
		 * @since 1.1
		 *
		 * @return bool
		 */
		public function is_complete() {
			$status = $this->evaluate_status();

			return ! in_array( $status, array( 'pending', 'queued' ) );
		}

		/**
		 * Uses the Gravity Forms Add-On Framework to write a message to the log file for the Gravity Flow Stripe extension.
		 *
		 * @since 1.1
		 *
		 * @param string $message The message to be logged.
		 */
		public function log_debug( $message ) {
			gravity_flow_woocommerce()->log_debug( $message );
		}

		/**
		 * Prevents the step status becoming the workflow status.
		 *
		 * @since 1.1
		 *
		 * @return bool
		 */
		public function can_set_workflow_status() {
			return false;
		}
	}
}
