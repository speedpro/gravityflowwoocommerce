<?php

/**
 * Gravity Flow WooCommerce Checkout Step
 *
 * @package     GravityFlow
 * @subpackage  Classes/Step
 * @copyright   Copyright (c) 2015-2018, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-3.0.php GNU Public License
 * @since       1.1
 */

if ( class_exists( 'Gravity_Flow_Step' ) && function_exists( 'WC' ) ) {

	class Gravity_Flow_Step_Woocommerce_Checkout extends Gravity_Flow_Step_Woocommerce_Payment {
		/**
		 * A unique key for this step type.
		 *
		 * @since 1.1
		 *
		 * @var string
		 */
		public $_step_type = 'woocommerce_checkout';

		/**
		 * Returns the label for the step.
		 *
		 * @since 1.1
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'Checkout', 'gravityflowwoocommerce' );
		}

		/**
		 * Add settings to the step.
		 *
		 * @since 1.1
		 *
		 * @return array
		 */
		public function get_settings() {
			$settings_api = $this->get_common_settings_api();

			$page_choices = $this->get_page_choices();

			$settings = array(
				'title'  => esc_html__( 'WooCommerce Checkout', 'gravityflowwoocommerce' ),
				'fields' => array(
					$settings_api->get_setting_assignee_type(),
					$settings_api->get_setting_assignees(),
					$settings_api->get_setting_assignee_routing(),
					$settings_api->get_setting_instructions(),
					$settings_api->get_setting_display_fields(),
					$settings_api->get_setting_notification_tabs( array(
						array(
							'label'  => __( 'Assignee email', 'gravityflowwoocommerce' ),
							'id'     => 'tab_assignee_notification',
							'fields' => $settings_api->get_setting_notification( array(
								'checkbox_default_value' => true,
								'default_message'        => __( 'Please checkout here: {workflow_woocommerce_checkout_link}', 'gravityflowwoocommerce' ),
							) ),
						),
					) ),
					array(
						'name'    => 'order_received_redirection',
						'tooltip' => __( 'Select the page to replace the WooCommerce "Order received (thanks)" page. This can be the Workflow Submit Page in the WordPress Admin Dashboard or you can choose a page with either a Gravity Flow inbox shortcode or a Gravity Forms shortcode.', 'gravityflowwoocommerce' ),
						'label'   => __( 'Order Received Redirection', 'gravityflowwoocommerce' ),
						'type'    => 'select',
						'choices' => $page_choices,
					),
				),
			);

			return $settings;
		}

		/**
		 * Is this step supported on this server? Override to hide this step in the list of step types if the requirements are not met.
		 *
		 * @since 1.1
		 *
		 * @return bool
		 */
		public function is_supported() {
			return function_exists( 'WC' ) && ! gravity_flow_woocommerce()->is_woocommerce_orders_integration_enabled( $this->get_form_id() );
		}

		/**
		 * Evaluates the status for the step.
		 *
		 * @since 1.1
		 *
		 * @return string
		 */
		public function evaluate_status() {
			if ( $this->is_queued() ) {
				return 'queued';
			}

			$step_status ='pending';
			$assignee_details = $this->get_assignees();
			if ( ! empty( $assignee_details ) ) {
				$step_status = 'complete';
			}

			if ( ! empty( $assignee_details ) ) {
				foreach ( $assignee_details as $assignee ) {
					$user_status = $assignee->get_status();

					if ( empty( $user_status ) || $user_status == 'pending' ) {
						$step_status = 'pending';
					}
				}
			}

			return $step_status;
		}

		/**
		 * Returns an array of assignees for this step.
		 *
		 * @since 1.1
		 *
		 * @return Gravity_Flow_Assignee[]
		 */
		public function get_assignees() {
			$assignees = Gravity_Flow_Step::get_assignees();

			return $assignees;
		}

		/**
		 * Process the step.
		 *
		 * @since 1.1
		 */
		public function process() {
			$this->assign();
			$this->log_debug( __METHOD__ . '(): Started, waiting for the order id.' );
			$note = $this->get_name() . ': ' . esc_html__( 'Waiting for a WooCommerce order.', 'gravityflowwoocommerce' );

			$this->add_note( $note );
		}

		/**
		 * Returns the choices for the Submit Page setting.
		 *
		 * @since 1.1
		 *
		 * @return array
		 */
		public function get_page_choices() {
			$choices = array(
				array(
					'label' => esc_html__( 'No Redirection' ),
					'value' => '',
				),
				array(
					'label' => esc_html__( 'WordPress Admin Dashboard: Workflow Submit Page', 'gravityflowwoocommerce' ),
					'value' => 'admin',
				),
			);

			$pages = get_pages();
			foreach ( $pages as $page ) {
				$choices[] = array(
					'label' => $page->post_title,
					'value' => $page->ID,
				);
			}

			return $choices;
		}
	}

	Gravity_Flow_Steps::register( new Gravity_Flow_Step_Woocommerce_Checkout() );
}
