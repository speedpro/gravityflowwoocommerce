<?php

/**
 * Gravity Flow WooCommerce Payment Step
 *
 * @package     GravityFlow
 * @subpackage  Classes/Step
 * @copyright   Copyright (c) 2015-2018, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-3.0.php GNU Public License
 * @since       1.0.0
 */

if ( class_exists( 'Gravity_Flow_Step' ) && function_exists( 'WC' ) ) {

	class Gravity_Flow_Step_Woocommerce_Payment extends Gravity_Flow_Step_Woocommerce_Capture_Payment {
		/**
		 * A unique key for this step type.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $_step_type = 'woocommerce_payment';

		/**
		 * Returns the label for the step.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'Payment', 'gravityflowwoocommerce' );
		}

		/**
		 * Add settings to the step.
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public function get_settings() {
			$settings_api = $this->get_common_settings_api();

			$settings = array(
				'title'  => esc_html__( 'WooCommerce Payment', 'gravityflowwoocommerce' ),
				'fields' => array(
					$settings_api->get_setting_instructions(),
					$settings_api->get_setting_display_fields(),
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
					'status'                    => 'complete',
					'status_label'              => __( 'Complete', 'gravityflowwoocommerce' ),
					'destination_setting_label' => __( 'Next Step', 'gravityflowwoocommerce' ),
					'default_destination'       => 'next',
				),
			);
		}

		/**
		 * Evaluates the status for the step.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function evaluate_status() {
			if ( $this->is_queued() ) {
				return 'queued';
			}

			$order_id    = $this->get_order_id();
			$step_status = ( ! $order_id ) ? 'pending' : 'complete';

			$assignee_details = $this->get_assignees();
			foreach ( $assignee_details as $assignee ) {
				$user_status = $assignee->get_status();

				if ( empty( $user_status ) || $user_status == 'pending' ) {
					$step_status = 'pending';
				}
			}

			return $step_status;
		}

		/**
		 * Returns an array of assignees for this step.
		 *
		 * @since 1.0.0
		 *
		 * @return Gravity_Flow_Assignee[]
		 */
		public function get_assignees() {
			$assignees = array();

			$order_id = $this->get_order_id();
			if ( $order_id ) {
				$order  = wc_get_order( $order_id );
				$status = $order->get_status();

				if ( $status === 'pending' || $status === 'on-hold' ) {
					$user_id      = $order->get_user_id();
					$assignee_key = ( ! empty( $user_id ) ) ? 'user_id|' . $user_id : 'email|' . $order->get_billing_email();

					$assignees[] = new Gravity_Flow_Assignee( $assignee_key, $this );
				}
			}

			return $assignees;
		}

		/**
		 * Process the step.
		 *
		 * @since 1.0.0
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
			do_action( 'gravityflowwoocommerce_payment_step_started', $this->get_entry(), $this->get_form(), $this );

			// do this only when the order is still pending.
			$order = wc_get_order( $this->get_order_id() );

			if ( ( false !== $order && ( 'pending' === $order->get_status() || 'on-hold' === $order->get_status() ) ) || false === $order ) {
				if ( false !== $order ) {
					$this->assign();
					$this->log_debug( __METHOD__ . '(): Started, waiting for payment.' );
				} else {
					$this->log_debug( __METHOD__ . '(): Started, waiting for the order id.' );
				}

				$note = $this->get_name() . ': ' . esc_html__( 'Waiting for payment.', 'gravityflowwoocommerce' );
				$this->add_note( $note );
			} else {
				$note = $this->get_name() . ': ' . esc_html__( 'Payment is not pending or on-hold. Step completed without sending notification.', 'gravityflowwoocommerce' );
				$this->add_note( $note );

				return true;
			}
		}

		/**
		 * Display the workflow detail box for this step.
		 *
		 * @since 1.1 Adds new action - "Update Order Status"
		 * @since 1.0.0
		 *
		 * @param array $form The current form.
		 * @param array $args The page arguments.
		 */
		public function workflow_detail_box( $form, $args ) {
			?>
			<div>
				<?php
				$this->maybe_display_assignee_status_list( $args, $form );

				$order           = wc_get_order( $this->get_order_id() );
				$status          = ( false !== $order ) ? $order->get_status() : '';
				$complete_status = apply_filters( 'gravityflowwoocommerce_payment_step_complete_status', 'processing' );

				$can_submit = $status !== $complete_status;

				if ( $can_submit ) {
					wp_nonce_field( 'gravityflow_woocommerce_payment_' . $this->get_id() );

					echo '<br /><div>';

					$url  = $order->get_checkout_payment_url();
					$text = esc_html__( 'Pay for this order', 'gravityflowwoocommerce' );
					echo sprintf( '<a href="%s" target="_blank" class="button">%s</a><br><br>', $url, $text );

					if ( current_user_can( 'edit_shop_orders' ) ) {
						echo '<hr style="margin-top:10px;"/>';
						echo sprintf( '<h4>%s</h4>', esc_html__( 'Update Order Status', 'gravityflowwoocommerce' ) );

						$statuses = gravity_flow_woocommerce()->wc_order_statuses();
						// Cannot switch to pending, cancelled and refunded status.
						$disabled_statuses = array( 'pending', 'cancelled', 'refunded', 'on-hold' );
						if ( ! in_array( $status, $disabled_statuses, true ) ) {
							$disabled_statuses[] = $status;
						}
						$dropdown  = '<select name="gravityflow_woocommerce_new_status_step_' . $this->get_id() . '" id="gravityflow-woocommerce-payment-statuses">';
						$dropdown .= sprintf( '<option value="">%s</option>', esc_html__( 'Choose a status', 'gravityflowwoocommerce' ) );
						foreach ( $statuses as $status ) {
							if ( in_array( $status['value'], $disabled_statuses, true ) ) {
								continue;
							}

							$dropdown .= '<option value="' . $status['value'] . '">';
							$dropdown .= $status['text'];
							$dropdown .= '</option>';
						}
						$dropdown .= '</select>';
						$button    = '<button name="submit" value="updated" type="submit" class="button">' . esc_html__( 'Update', 'gravityflowwoocommerce' ) . '</button>';

						echo sprintf( '%s %s<br/><br/>', $dropdown, $button );
					}

					echo '</div>';
				}
				?>
			</div>
			<?php
		}

		/**
		 * If applicable display the assignee status list.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args The page arguments.
		 * @param array $form The current form.
		 */
		public function maybe_display_assignee_status_list( $args, $form ) {
			$display_step_status = (bool) $args['step_status'];

			/**
			 * Allows the assignee status list to be hidden.
			 *
			 * @since 1.0.0
			 *
			 * @param array $form
			 * @param array $entry
			 * @param Gravity_Flow_Step $current_step
			 */
			$display_assignee_status_list = apply_filters( 'gravityflow_assignee_status_list_woocommerce', $display_step_status, $form, $this );
			if ( ! $display_assignee_status_list ) {
				return;
			}

			echo sprintf( '<h4 style="margin-bottom:10px;">%s (%s)</h4>', $this->get_name(), $this->get_status_string() );

			echo '<ul>';

			$assignees = $this->get_assignees();

			$this->log_debug( __METHOD__ . '(): assignee details: ' . print_r( $assignees, true ) );

			foreach ( $assignees as $assignee ) {
				$assignee_status = $assignee->get_status();

				$this->log_debug( __METHOD__ . '(): showing status for: ' . $assignee->get_key() );
				$this->log_debug( __METHOD__ . '(): assignee status: ' . $assignee_status );

				if ( ! empty( $assignee_status ) ) {
					$assignee_id = $assignee->get_id();

					$status_label = $this->get_status_label( $assignee_status );
					$type         = is_email( $assignee_id ) ? esc_html__( 'Email', 'gravityflowwoocommerce' ) : esc_html__( 'User', 'gravityflowwoocommerce' );
					$value        = is_email( $assignee_id ) ? $assignee_id : $assignee->get_display_name();

					echo sprintf( '<li>%s: %s (%s)</li>', $type, $value, $status_label );
				}
			}

			echo '</ul>';
		}

		/**
		 * Get the status string, including icon (if complete).
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function get_status_string() {
			$input_step_status = $this->get_status();
			$status_str        = __( 'Pending', 'gravityflowwoocommerce' );

			if ( $input_step_status == 'complete' ) {
				$approve_icon = '<i class="fa fa-check" style="color:green"></i>';
				$status_str   = $approve_icon . __( 'Complete', 'gravityflowwoocommerce' );
			} elseif ( $input_step_status == 'queued' ) {
				$status_str = __( 'Queued', 'gravityflowwoocommerce' );
			}

			return $status_str;
		}

		/**
		 * Displays content inside the Workflow metabox on the Gravity Forms Entry Detail page.
		 *
		 * @since 1.0.0
		 *
		 * @param array $form The current form.
		 */
		public function entry_detail_status_box( $form ) {
			$status = $this->evaluate_status();
			?>
			<h4 style="padding:10px;"><?php echo $this->get_name() . ': ' . $status ?></h4>

			<div style="padding:10px;">
				<ul>
					<?php

					$assignees = $this->get_assignees();

					foreach ( $assignees as $assignee ) {
						$assignee_status_label = $assignee->get_status_label();
						$assignee_status_li    = sprintf( '<li>%s</li>', $assignee_status_label );

						echo $assignee_status_li;
					}

					?>
				</ul>
			</div>
			<?php
		}

		/**
		 * Handles POSTed values from the workflow detail page.
		 *
		 * @since 1.1
		 *
		 * @param array $form  The current form.
		 * @param array $entry The current entry.
		 *
		 * @return string|bool Return a success feedback message safe for page output.
		 */
		public function maybe_process_status_update( $form, $entry ) {
			$feedback        = false;
			$step_status_key = 'gravityflow_woocommerce_new_status_step_' . $this->get_id();
			$order           = wc_get_order( $this->get_order_id() );

			if ( isset( $_POST[ $step_status_key ] ) && isset( $_POST['_wpnonce'] ) && check_admin_referer( 'gravityflow_woocommerce_payment_' . $this->get_id() ) ) {
				$new_status = rgpost( $step_status_key );
				$note       = $this->get_name() . ': ' . sprintf( esc_html__( 'Updated WooCommerce order status to %s.', 'gravityflowwoocommerce' ), $new_status );
				$result     = $order->update_status( $new_status, $note );

				if ( $result ) {
					$feedback = sprintf( esc_html__( 'Updated WooCommerce order status to %s.', 'gravityflowwoocommerce' ), $new_status );
					$this->log_debug( __METHOD__ . "(): Updated WooCommerce order status to $new_status." );
				} else {
					$this->log_debug( __METHOD__ . "(): Unable to update WooCommerce order status to $new_status." );
					$note     = $this->get_name() . ': ' . esc_html__( 'Failed to update WooCommerce order status.', 'gravityflowwoocommerce' );
					$feedback = esc_html__( 'Failed to update WooCommerce order status.', 'gravityflowwoocommerce' );
				}

				// If the entry is not at the complete status,
				// set $feedback to false so we won't release them.
				$complete_status = apply_filters( 'gravityflowwoocommerce_payment_step_complete_status', 'processing' );
				if ( $complete_status !== $new_status ) {
					$feedback = false;
				}

				$this->add_note( $note );
			}

			return $feedback;
		}
	}

	Gravity_Flow_Steps::register( new Gravity_Flow_Step_Woocommerce_Payment() );
}
