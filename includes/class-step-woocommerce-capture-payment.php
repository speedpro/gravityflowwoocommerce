<?php

/**
 * Gravity Flow WooCommerce Capture Step
 *
 * @package     GravityFlow
 * @subpackage  Classes/Step
 * @copyright   Copyright (c) 2015-2018, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-3.0.php GNU Public License
 * @since       1.0.0-dev
 */

if ( class_exists( 'Gravity_Flow_Step' ) ) {

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
		 * @since 1.0.0-dev
		 *
		 * @return string
		 */
		public function get_icon_url() {
			return '<i class="woocommerce" aria-hidden="true"></i>';
		}

		/**
		 * Returns the label for the step.
		 *
		 * @since 1.0.0-dev
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'Capture Payment', 'gravityflowwoocommerce' );
		}

		/**
		 * Is this step supported on this server? Override to hide this step in the list of step types if the requirements are not met.
		 *
		 * @since 1.0.0-dev
		 *
		 * @return bool
		 */
		public function is_supported() {
			$form_id  = $this->get_form_id();
			$form     = GFAPI::get_form( $form_id );
			$settings = rgar( $form, 'gravityflowwoocommerce' );

			return ( isset( $settings['woocommerce_orders_integration_enabled'] ) ) && '1' === $settings['woocommerce_orders_integration_enabled'];
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
			do_action( 'gravityflowwoocommerce_capture_payment_step_started', $this->get_entry(), $this->get_form(), $this );

			$order_id = gform_get_meta( $this->get_entry_id(), 'workflow_woocommerce_order_id' );
			$order    = wc_get_order( $order_id );
			if ( 'on-hold' === $order->get_status() ) {
				$note = $this->get_name() . ': ' . esc_html__( 'Processed.', 'gravityflowwoocommerce' );
				$this->add_note( $note );

				// Change order status, WooCommerce should charge cards then.
				$note   = $this->get_name() . ': ' . esc_html__( 'Updated the order status to processing.', 'gravityflowwoocommerce' );
				$result = $order->update_status( 'processing', $note );
				if ( ! $result ) {
					$note = $this->get_name() . ': ' . esc_html__( 'Failed to update the order status. Step completed without capturing payment.', 'gravityflowwoocommerce' );
				}
				$this->add_note( $note );
			} else {
				$note = $this->get_name() . ': ' . esc_html__( 'Payment is not on hold. Step completed without capturing payment.', 'gravityflowwoocommerce' );
				$this->add_note( $note );
			}

			return true;
		}
	}
}
