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
	}
}
