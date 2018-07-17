<?php

/**
 * Gravity Flow WooCommerce Payment Step
 *
 * @package     GravityFlow
 * @subpackage  Classes/Step
 * @copyright   Copyright (c) 2015-2018, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-3.0.php GNU Public License
 * @since       1.0.0-dev
 */

if ( class_exists( 'Gravity_Flow_Step' ) ) {

	class Gravity_Flow_Step_Woocommerce_Payment extends Gravity_Flow_Step {
		/**
		 * A unique key for this step type.
		 *
		 * @var string
		 */
		public $_step_type = 'woocommerce_payment';

		/**
		 * Set a custom icon in the step settings.
		 * 32px x 32px
		 *
		 * @since 1.0.0-dev
		 *
		 * @return string
		 */
		public function get_icon_url() {
			return '<i class="fa fa-credit-card" aria-hidden="true"></i>';
		}

		/**
		 * Returns the label for the step.
		 *
		 * @since 1.0.0-dev
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'WooCommerce Payment', 'gravityflowwoocommerce' );
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
		 * Add settings to the step.
		 *
		 * @since 1.0.0-dev
		 *
		 * @return array
		 */
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

		/**
		 * Evaluates the status for the step.
		 *
		 * @since 1.0.0-dev
		 *
		 * @return string
		 */
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

		/**
		 * Returns an array of assignees for this step.
		 *
		 * @since 1.0.0-dev
		 *
		 * @return Gravity_Flow_Assignee[]
		 */
		public function get_assignees() {
			$assignees = array();

			$order_id     = gform_get_meta( $this->get_entry_id(), 'workflow_woocommerce_order_id' );
			$order        = wc_get_order( $order_id );
			$assignee_key = array(
				'type' => 'email',
				'id'   => $order->get_billing_email(),
			);

			$assignees[] = new Gravity_Flow_Assignee( $assignee_key, $this );

			return $assignees;
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
			do_action( 'gravityflowwoocommerce_payment_step_started', $this->get_entry(), $this->get_form(), $this );

			// do this only when the order is still pending.
			$order_id = gform_get_meta( $this->get_entry_id(), 'workflow_woocommerce_order_id' );
			$order    = wc_get_order( $order_id );

			if ( 'pending' === $order->get_status() ) {
				$this->assign();

				$note = $this->get_name() . ': ' . esc_html__( 'Processed.', 'gravityflowwoocommerce' );
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
		 * @since 1.0.0-dev
		 *
		 * @param array $form The current form.
		 * @param array $args The page arguments.
		 */
		public function workflow_detail_box( $form, $args ) {
			?>
			<div>
				<?php
				$order_id = gform_get_meta( $this->get_entry_id(), 'workflow_woocommerce_order_id' );
				$order    = wc_get_order( $order_id );
				$status   = $order->get_status();

				$can_submit = $status === 'pending';

				if ( $can_submit ) {
					$url  = $order->get_checkout_payment_url();
					$text = esc_html__( 'Pay for this order', 'gravityflowwoocommerce' );
					echo '<br /><div class="gravityflow-action-buttons">';
					echo sprintf( '<a href="%s" target="_blank" class="button button-large button-primary">%s</a><br><br>', $url, $text );
					echo '</div>';
				}
				?>
			</div>
			<?php
		}

		/**
		 * Displays content inside the Workflow metabox on the Gravity Forms Entry Detail page.
		 *
		 * @since 1.0.0-dev
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
		 * Replace merge tags.
		 *
		 * @since 1.0.0-dev
		 *
		 * @param string                $text The text containing merge tags to be processed.
		 * @param Gravity_Flow_Assignee $assignee Assignee array.
		 *
		 * @return mixed
		 */
		public function replace_variables( $text, $assignee ) {
			$text = parent::replace_variables( $text, $assignee );

			$order_id = gform_get_meta( $this->get_entry_id(), 'workflow_woocommerce_order_id' );
			$order    = wc_get_order( $order_id );
			$pay_url  = $order->get_checkout_payment_url();

			preg_match_all( '/{workflow_woocommerce_pay_url(:(.*?))?}/', $text, $matches, PREG_SET_ORDER );
			if ( is_array( $matches ) ) {
				foreach ( $matches as $match ) {
					$full_tag = $match[0];

					$text = str_replace( $full_tag, $pay_url, $text );
				}
			}

			preg_match_all( '/{workflow_woocommerce_pay_link(:(.*?))?}/', $text, $matches, PREG_SET_ORDER );
			if ( is_array( $matches ) ) {
				foreach ( $matches as $match ) {
					$full_tag       = $match[0];
					$options_string = isset( $match[2] ) ? $match[2] : '';
					$options        = shortcode_parse_atts( $options_string );

					$args = shortcode_atts(
						array(
							'text' => esc_html__( 'Pay for this order', 'gravityflowwoocommerce' ),
						), $options
					);

					$pay_link = sprintf( '<a href="%s">%s</a>', $pay_url, esc_html( $args['text'] ) );
					$text     = str_replace( $full_tag, $pay_link, $text );
				}
			}

			return $text;
		}
	}
}
