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

			$page_choices = $this->get_page_choices();

			$settings = array(
				'title'  => esc_html__( 'WooCommerce Payment', 'gravityflowwoocommerce' ),
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
								'default_message'        => __( 'Please make a payment here: {workflow_woocommerce_pay_link}', 'gravityflowwoocommerce' ),
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

			if ( gravity_flow_woocommerce()->is_woocommerce_orders_integration_enabled( $this->get_form_id() ) ) {
				unset( $settings['fields'][0] );
				unset( $settings['fields'][1] );
				unset( $settings['fields'][2] );
			}

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
		 * Is this step supported on this server? Override to hide this step in the list of step types if the requirements are not met.
		 *
		 * @since 1.1
		 *
		 * @return bool
		 */
		public function is_supported() {
			return function_exists( 'WC' );
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

			$assignee_details = $this->get_assignees();

			$step_status = ( empty( $assignee_details ) ) ? 'pending' : 'complete';

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
				$order        = wc_get_order( $order_id );
				$user_id      = $order->get_user_id();
				$assignee_key = ( ! empty( $user_id ) ) ? 'user_id|' . $user_id : 'email|' . $order->get_billing_email();

				$assignees[] = new Gravity_Flow_Assignee( $assignee_key, $this );
			} elseif ( ! gravity_flow_woocommerce()->is_woocommerce_orders_integration_enabled( $this->get_form_id() ) ) {
				$assignees = parent::get_assignees();
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

			if ( ( false !== $order && 'pending' === $order->get_status() ) || false === $order ) {
				if ( false !== $order ) {
					$this->assign();
					$this->log_debug( __METHOD__ . '(): Started, waiting for payment.' );
					$note = $this->get_name() . ': ' . esc_html__( 'Waiting for payment.', 'gravityflowwoocommerce' );
				} else {
					if ( ! gravity_flow_woocommerce()->is_woocommerce_orders_integration_enabled( $this->get_form_id() ) ) {
						$this->assign();
					}
					$this->log_debug( __METHOD__ . '(): Started, waiting for the order id.' );
					$note = $this->get_name() . ': ' . esc_html__( 'Waiting for a WooCommerce order.', 'gravityflowwoocommerce' );
				}

				$this->add_note( $note );
			} else {
				$note = $this->get_name() . ': ' . esc_html__( 'Payment is not pending. Step completed without sending notification.', 'gravityflowwoocommerce' );
				$this->add_note( $note );

				return true;
			}
		}

		/**
		 * Display the workflow detail box for this step.
		 *
		 * @since 1.1   Update payment URL.
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

				$order  = wc_get_order( $this->get_order_id() );
				$status = ( false !== $order ) ? $order->get_status() : '';

				$assignees = $this->get_assignees();

				$can_submit = false;
				foreach ( $assignees as $assignee ) {
					if ( $assignee->is_current_user() ) {
						$can_submit = true;
						break;
					}
				}

				if ( $can_submit ) {
					if ( false === $order ) {
						$entry_id = $this->get_entry_id();
						$url      = add_query_arg(
							array(
								'workflow_order_entry_id' => $entry_id,
								'workflow_order_hash'     => gravity_flow_woocommerce()->get_workflow_order_hash( $entry_id, $this ),
							),
							wc_get_checkout_url()
						);
						/**
						 * Filter the payment step hash url.
						 *
						 * @since 1.1
						 *
						 * @param string $url URL.
						 * @param int $entry_id Entry id.
						 * @param Gravity_Flow_Step $this Gravity Flow step.
						 */
						$url  = apply_filters( 'gravityflowwoocommerce_payment_step_url', $url, $entry_id, $this );
						$text = esc_html__( 'Create a WooCommerce order', 'gravityflowwoocommerce' );
					} elseif ( $order && $status === 'pending' ) {
						$url  = $order->get_checkout_payment_url();
						$text = esc_html__( 'Pay for this order', 'gravityflowwoocommerce' );
					}

					echo '<br /><div class="gravityflow-action-buttons">';
					echo sprintf( '<a href="%s" target="_blank" class="button button-large button-primary">%s</a><br><br>', $url, $text );
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
		 * Returns the choices for the Submit Page setting.
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

	Gravity_Flow_Steps::register( new Gravity_Flow_Step_Woocommerce_Payment() );
}
