<?php
/**
 * Gravity Flow WooCommerce Order ID Field
 *
 * @package   GravityFlow
 * @copyright Copyright (c) 2015-2018, Steven Henty S.L.
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

if ( ! class_exists( 'GF_Field_Text' ) ) {
	die();
}

if ( ! class_exists( 'Gravity_Flow_Field_WooCommerce_Order_ID' ) ) {
	/**
	 * Class Gravity_Flow_Field_WooCommerce_Order_ID
	 *
	 * @since 1.0.0-dev
	 */
	class Gravity_Flow_Field_WooCommerce_Order_ID extends GF_Field_Text {

		/**
		 * The field type.
		 *
		 * @since 1.0.0-dev
		 *
		 * @var string
		 */
		public $type = 'workflow_woocommerce_order_id';

		/**
		 * Adds the Workflow Fields group to the form editor.
		 *
		 * @since 1.0.0-dev
		 *
		 * @param array $field_groups The properties for the field groups.
		 *
		 * @return array
		 */
		public function add_button( $field_groups ) {
			$field_groups = Gravity_Flow_Fields::maybe_add_workflow_field_group( $field_groups );

			return parent::add_button( $field_groups );
		}

		/**
		 * Returns the field button properties for the form editor.
		 *
		 * @since 1.0.0-dev
		 *
		 * @return array
		 */
		public function get_form_editor_button() {
			return array(
				'group' => 'workflow_fields',
				'text'  => $this->get_form_editor_field_title(),
			);
		}

		/**
		 * Returns the class names of the settings which should be available on the field in the form editor.
		 *
		 * @since 1.0.0-dev
		 *
		 * @return array
		 */
		function get_form_editor_field_settings() {
			return array(
				'conditional_logic_field_setting',
				'prepopulate_field_setting',
				'error_message_setting',
				'label_setting',
				'label_placement_setting',
				'admin_label_setting',
				'size_setting',
				'rules_setting',
				'placeholder_setting',
				'default_value_setting',
				'visibility_setting',
				'duplicate_setting',
				'description_setting',
				'css_class_setting',
			);
		}

		/**
		 * Returns the field title.
		 *
		 * @since 1.0.0-dev
		 *
		 * @return string
		 */
		public function get_form_editor_field_title() {
			return __( 'Order ID', 'gravityflowwoocommerce' );
		}

		/**
		 * Returns the form editor script which will set the field default properties.
		 *
		 * @since 1.0.0-dev
		 *
		 * @return string
		 */
		public function get_form_editor_inline_script_on_page_render() {
			$script = sprintf( "function SetDefaultValues_%s(field) {field.label = '%s';}", $this->type, $this->get_form_editor_field_title() ) . PHP_EOL;

			return $script;
		}

		/**
		 * Validate that the entry exists for the specified WooCommerce Order id.
		 *
		 * @since 1.0.0-dev
		 *
		 * @param string|array $value The field value from get_value_submission().
		 * @param array        $form  The Form Object currently being processed.
		 */
		public function validate( $value, $form ) {
			$order = wc_get_order( $value );

			if ( ! $order ) {
				$this->failed_validation  = true;
				$this->validation_message = empty( $this->errorMessage ) ? esc_html__( 'Order not found for this WooCommerce Order ID.', 'gravityflowwoocommerce' ) : $this->errorMessage;
			}
		}

		/**
		 * Formats the entry value for display on the entry detail page and for the {all_fields} merge tag.
		 *
		 * @since 1.0.0-dev
		 *
		 * @param string     $value    The field value.
		 * @param string     $currency The entry currency code.
		 * @param bool|false $use_text When processing choice based fields should the choice text be returned instead of the value.
		 * @param string     $format   The format requested for the location the merge is being used. Possible values: html, text or url.
		 * @param string     $media    The location where the value will be displayed. Possible values: screen or email.
		 *
		 * @return string
		 */
		public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
			return $this->get_order_details( $value, $format );
		}

		/**
		 * Format the entry value for when the field merge tag is processed. Not called for the {all_fields} merge tag.
		 *
		 * @since 1.0.0-dev
		 *
		 * @param string|array $value      The field value. Depending on the location the merge tag is being used the following functions may have already been applied to the value: esc_html, nl2br, and urlencode.
		 * @param string       $input_id   The field or input ID from the merge tag currently being processed.
		 * @param array        $entry      The Entry Object currently being processed.
		 * @param array        $form       The Form Object currently being processed.
		 * @param string       $modifier   The merge tag modifier. e.g. value
		 * @param string|array $raw_value  The raw field value from before any formatting was applied to $value.
		 * @param bool         $url_encode Indicates if the urlencode function may have been applied to the $value.
		 * @param bool         $esc_html   Indicates if the esc_html function may have been applied to the $value.
		 * @param string       $format     The format requested for the location the merge is being used. Possible values: html, text or url.
		 * @param bool         $nl2br      Indicates if the nl2br function may have been applied to the $value.
		 *
		 * @return string
		 */
		public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
			return $this->get_order_details( $value, $format );
		}

		/**
		 * Returns the WooCommerce Order id and, if appropriate, the associated order details.
		 *
		 * @since 1.0.0-dev
		 *
		 * @param string $value  The field value, the WooCommerce Order id.
		 * @param string $format The format requested for the location the value is being used. Possible values: html, text or url.
		 *
		 * @return string
		 */
		public function get_order_details( $value, $format ) {
			if ( empty( $value ) || in_array( 'value', $this->get_modifiers() ) ) {
				return $value;
			}

			$order_id = $value;
			$order    = wc_get_order( $order_id );

			if ( ! empty( $order ) ) {
				$is_html = $format === 'html';

				if ( $is_html && current_user_can( 'edit_shop_orders' ) ) {
					$query_args = array(
						'post'   => $order_id,
						'action' => 'edit',
					);
					$order_id   = sprintf( '<a href="%s" target="_blank">%s</a>', add_query_arg( $query_args, admin_url( 'post.php' ) ), $order_id );
				}

				$details = sprintf(
					"%s: %s\n%s: %s\n%s: %s\n%s: %s",
					esc_html__( 'Order ID', 'gravityflowwoocommerce' ),
					$order_id,
					esc_html__( 'Submitted', 'gravityflowwoocommerce' ),
					esc_html( GFCommon::format_date( $order->get_date_created(), true, 'Y/m/d' ) ),
					esc_html__( 'Payment Status', 'gravityflowwoocommerce' ),
					esc_html( $order->get_status() ),
					esc_html__( 'Payment Amount', 'gravityflowwoocommerce' ),
					esc_html( GFCommon::to_money( $order->get_total(), $order->get_currency() ) )
				);

				if ( $is_html ) {
					$value .= '<hr>' . nl2br( $details );
				} else {
					$value .= "\n" . str_repeat( '-', 70 ) . "\n" . $details;
				}
			}

			return $value;
		}

	}

	GF_Fields::register( new Gravity_Flow_Field_WooCommerce_Order_ID() );
}
