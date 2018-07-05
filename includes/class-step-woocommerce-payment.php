<?php

if ( class_exists( 'Gravity_Flow_Step' ) ) {

	class Gravity_Flow_Step_Woocommerce_Payment extends Gravity_Flow_Step {
		public $_step_type = 'woocommerce_payment';

		public function get_icon_url() {
			return '<i class="fa fa-credit-card" aria-hidden="true"></i>';
		}

		public function get_label() {
			return esc_html__( 'WooCommerce Payment', 'gravityflowincomingwebhook' );
		}

		/**
		 * Is this step supported on this server? Override to hide this step in the list of step types if the requirements are not met.
		 *
		 * @return bool
		 */
		public function is_supported() {
			$form_id  = $this->get_form_id();
			$form     = GFAPI::get_form( $form_id );
			$settings = rgar( $form, 'gravityflowwoocommerce' );

			return ( isset( $settings['woocommerce_orders_integration_enabled'] ) ) && '1' === $settings['woocommerce_orders_integration_enabled'];
		}

		public function get_settings() {
			$settings_api = $this->get_common_settings_api();

			$settings = array(
				'title'  => esc_html__( 'WooCommerce Payment', 'gravityflowwoocommerce' ),
				'fields' => array(
					$settings_api->get_setting_instructions(),
					$settings_api->get_setting_notification_tabs( array(
						array(
							'label'  => __( 'Assignee email', 'gravityflowwoocommerce' ),
							'id'     => 'tab_assignee_notification',
							'fields' => $settings_api->get_setting_notification( array(
								'checkbox_default_value' => true,
								'default_message'        => __( 'Please make a payment here: {workflow_woocommerce_pay_link}', 'gravityflowwoocommerce' ),
							) ),
						),
					) ),
				),
			);

			return $settings;
		}

		public function evaluate_status() {
			if ( $this->is_queued() ) {
				return 'queued';
			}

			$assignee_details = $this->get_assignees();

			$step_status = 'complete';

			foreach ( $assignee_details as $assignee ) {
				$user_status = $assignee->get_status();

				if ( empty( $user_status ) || $user_status == 'pending' ) {
					$step_status = 'pending';
				}
			}

			return $step_status;
		}

		public function get_assignees() {
			$assignees = array();

			$order_id     = gform_get_meta( $this->get_entry_id(), 'workflow_woocommerce_order_id' );
			$order        = wc_get_order( $order_id );
			$assignee_key = 'billing_email|' . $order->get_billing_email();

			$assignees[] = new Gravity_Flow_Assignee( $assignee_key, $this );

			return $assignees;
		}

		public function process() {
			/**
			 * Fires when the workflow is first assigned to the billing email.
			 *
			 * @since 2.0.2
			 *
			 * @param array $entry The current entry.
			 * @param array $form The current form.
			 * @param array $step The current step.
			 */
			do_action( 'gravityflowwoocommercepayment_step_started', $this->get_entry(), $this->get_form(), $this );
			$this->assign();
		}
	}
}
