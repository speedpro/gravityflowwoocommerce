<?php

/**
 * Gravity Flow WooCommerce Update Order Status Step
 *
 * @package     GravityFlow
 * @subpackage  Classes/Step
 * @copyright   Copyright (c) 2015-2018, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-3.0.php GNU Public License
 * @since       1.1
 */

if ( class_exists( 'Gravity_Flow_Step' ) && function_exists( 'WC' ) ) {

	class Gravity_Flow_Step_Woocommerce_Update_Order_Status_Payment extends Gravity_Flow_Step_Woocommerce_Base {
		/**
		 * A unique key for this step type.
		 *
		 * @since 1.1
		 *
		 * @var string
		 */
		public $_step_type = 'woocommerce_update_order_status';

		/**
		 * Returns the label for the step.
		 *
		 * @since 1.1
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'Update Order Status', 'gravityflowwoocommerce' );
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
			$form    = GFAPI::get_form( $form_id );

			return function_exists( 'WC' ) && ( ! empty( GFFormsModel::get_fields_by_type( $form, 'workflow_woocommerce_order_id' ) ) || gravity_flow_woocommerce()->is_woocommerce_orders_integration_enabled( $form_id ) );
		}

		/**
		 * Adds an alert to the step settings area.
		 *
		 * @since 1.1
		 *
		 * @return array
		 */
		public function get_settings() {
			$form = $this->get_form();
			$args = array(
				'input_types' => array( 'workflow_woocommerce_order_id', 'hidden', 'text' ),
			);

			if ( gravity_flow_woocommerce()->is_woocommerce_orders_integration_enabled( $form['id'] ) ) {
				$args['append_choices'] = array(
					array(
						'label' => esc_html__( 'Current Entry: WooCommerce Order ID', 'gravityflowwoocommerce' ),
						'value' => 'workflow_woocommerce_order_id',
					),
				);
			}

			return array(
				'fields' => array(
					array(
						'name'          => 'woocommerce_order_id',
						'label'         => esc_html__( 'Order ID Field', 'gravityflowwoocommerce' ),
						'type'          => 'field_select',
						'tooltip'       => __( 'Select the field which will contain the ID of the WooCommerce ID to be refunded.', 'gravityflowwoocommerce' ),
						'required'      => true,
						'default_value' => 'workflow_woocommerce_order_id',
						'args'          => $args,
					),
					array(
						'name'     => 'order_status',
						'label'    => esc_html__( 'Order Status', 'gravityflowwoocommerce' ),
						'type'     => 'select',
						'tooltip'  => __( 'Select the order status you\'d like to change to.', 'gravityflowwoocommerce' ),
						'required' => true,
						'choices'  => gravity_flow_woocommerce()->wc_order_statuses( true ),
					),
				),
			);
		}

		/**
		 * Returns an array of statuses and their properties.
		 *
		 * @since 1.1
		 *
		 * @return array
		 */
		public function get_status_config() {
			return array(
				array(
					'status'                    => 'updated',
					'status_label'              => __( 'Updated', 'gravityflowwoocommerce' ),
					'destination_setting_label' => __( 'Next Step if Updated', 'gravityflowwoocommerce' ),
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
		 * @since 1.1
		 *
		 * @param string $payment_status The WooCommerce order payment status.
		 *
		 * @return bool
		 */
		public function is_valid_payment_status( $payment_status ) {
			$setting = $this->get_setting( 'order_status' );

			return $payment_status !== $setting;
		}

		/**
		 * Get WooCommerce order id.
		 *
		 * @since 1.1
		 *
		 * @return bool|mixed
		 */
		public function get_order_id() {
			$setting = $this->get_setting( 'woocommerce_order_id' );

			if ( 'workflow_woocommerce_order_id' === $setting || ! $setting ) {
				$order_id = parent::get_order_id();
			} else {
				$entry    = $this->get_entry();
				$order_id = $entry[ $setting ];
			}

			return $order_id;
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
		public function process_action( $order ) {
			$from_status = $order->get_status();
			$setting     = $this->get_setting( 'order_status' );
			$result      = 'failed';
			$note        = $this->get_name() . ': ' . sprintf( esc_html__( 'Marked the order as %s.', 'gravityflowwoocommerce' ), $setting );

			switch ( $setting ) {
				case 'processing':
				case 'completed':
					$result = $this->capture_payment( $order, $setting );
					break;
				case 'refunded':
					// For refund, we take an extra check to make sure the order is already paid.
					if ( ! in_array( $from_status, array( 'processing', 'completed' ), true ) ) {
						$result = $this->refund_payment( $order );
					} else {
						remove_action( 'woocommerce_order_status_refunded', 'wc_order_fully_refunded' );
						$order->update_status( 'refunded', $note );
					}
					break;
				case 'cancelled':
					$result = $this->cancel_payment( $order );
					break;
				default:
					if ( $order->update_status( $setting, $note ) ) {
						$result = 'updated';
					}
			}

			return $result;
		}
	}

	Gravity_Flow_Steps::register( new Gravity_Flow_Step_Woocommerce_Update_Order_Status_Payment() );
}
