<?php

/**
 * Gravity Flow WooCommerce Capture Step
 *
 * @package     GravityFlow
 * @subpackage  Classes/Step
 * @copyright   Copyright (c) 2015-2018, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-3.0.php GNU Public License
 * @since       1.0.0
 */

if ( class_exists( 'Gravity_Flow_Step' ) && function_exists( 'WC' ) ) {

	class Gravity_Flow_Step_Woocommerce_Capture_Payment extends Gravity_Flow_Step {
		/**
		 * A unique key for this step type.
		 *
		 * @var string
		 */
		public $_step_type = 'woocommerce_capture_payment';

		/**
		 * Set a custom icon in the step settings.
		 * 32px x 32px
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function get_icon_url() {
			return '<i class="woocommerce" aria-hidden="true"></i>';
		}

		/**
		 * Returns the label for the step.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'Capture Payment', 'gravityflowwoocommerce' );
		}

		/**
		 * Is this step supported on this server? Override to hide this step in the list of step types if the requirements are not met.
		 *
		 * @since 1.0.0
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
		 * @since 1.0.0
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
		 * Adds an alert to the step settings area.
		 *
		 * @since 1.0.0
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
					'status'                    => 'captured',
					'status_label'              => __( 'Captured', 'gravityflowwoocommerce' ),
					'destination_setting_label' => __( 'Next Step if Captured', 'gravityflowwoocommerce' ),
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
		 * Processes this step.
		 *
		 * @since 1.0.0
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
		 * @since 1.0.0
		 *
		 * @return bool|mixed
		 */
		public function get_order_id() {
			$order_id = gform_get_meta( $this->get_entry_id(), 'workflow_woocommerce_order_id' );
			// The entry could be created by WooCommerce Gravity Forms.
			if ( false === $order_id && function_exists( 'wc_gfpa' ) ) {
				$order_id = gform_get_meta( $this->get_entry_id(), 'woocommerce_order_number' );

				if ( false !== $order_id ) {
					add_post_meta( $order_id, '_gravityflow-entry-id', $this->get_entry_id() );
					gform_update_meta( $this->get_entry_id(), 'workflow_woocommerce_order_id', $order_id );
				}
			}

			return $order_id;
		}

		/**
		 * Determines if the payment status is valid for the action to be performed by this step.
		 *
		 * @since 1.0.0
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
		 * @since 1.0.0
		 *
		 * @param string $payment_status The WooCommerce order payment status.
		 *
		 * @return bool
		 */
		public function is_valid_payment_status( $payment_status ) {
			return $payment_status === 'on-hold';
		}

		/**
		 * Processes the action for the current step.
		 *
		 * @since 1.0.0
		 *
		 * @param WC_Order $order The WooCommerce order.
		 *
		 * @return string
		 */
		public function process_action( $order ) {
			return $this->capture_payment( $order );
		}

		/**
		 * Captures the authorized charge.
		 *
		 * @since 1.0.0
		 *
		 * @param WC_Order $order The WooCommerce order.
		 *
		 * @return string
		 */
		public function capture_payment( $order ) {
			$result = 'failed';

			// Change order status, WooCommerce should charge cards then.
			$note   = $this->get_name() . ': ' . esc_html__( 'Updated WooCommerce order status to processing.', 'gravityflowwoocommerce' );
			$charge = $order->update_status( 'processing', $note );

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
		 * Determines the current status of the step.
		 *
		 * @since 1.0.0
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
		 * @since 1.0.0
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
		 * @since 1.0.0
		 *
		 * @param string $message The message to be logged.
		 */
		public function log_debug( $message ) {
			gravity_flow_woocommerce()->log_debug( $message );
		}

		/**
		 * Prevents the step status becoming the workflow status.
		 *
		 * @since 1.0.0
		 *
		 * @return bool
		 */
		public function can_set_workflow_status() {
			return false;
		}
	}

	Gravity_Flow_Steps::register( new Gravity_Flow_Step_Woocommerce_Capture_Payment() );
}
