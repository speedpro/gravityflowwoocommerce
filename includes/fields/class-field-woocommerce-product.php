<?php
/**
 * Gravity Flow WooCommerce Product Field
 *
 * @package   GravityFlow
 * @copyright Copyright (c) 2015-2018, Steven Henty S.L.
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

if ( ! class_exists( 'GF_Field_List' ) ) {
	die();
}

if ( ! class_exists( 'Gravity_Flow_Field_WooCommerce_Product' ) ) {
	/**
	 * Class Gravity_Flow_Field_WooCommerce_Product
	 *
	 * @since 1.1
	 */
	class Gravity_Flow_Field_WooCommerce_Product extends GF_Field_List {

		/**
		 * The field type.
		 *
		 * @since 1.1
		 *
		 * @var string
		 */
		public $type = 'workflow_woocommerce_product';

		/**
		 * Adds the Workflow Fields group to the form editor.
		 *
		 * @since 1.1
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
		 * @since 1.1
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
		 * @since 1.1
		 *
		 * @return array
		 */
		function get_form_editor_field_settings() {
			return array(
				'columns_setting',
				'maxrows_setting',
				'add_icon_url_setting',
				'delete_icon_url_setting',
				'conditional_logic_field_setting',
				'prepopulate_field_setting',
				'error_message_setting',
				'label_setting',
				'label_placement_setting',
				'admin_label_setting',
				'rules_setting',
				'visibility_setting',
				'description_setting',
				'css_class_setting',
			);
		}

		/**
		 * Returns the field title.
		 *
		 * @since 1.1
		 *
		 * @return string
		 */
		public function get_form_editor_field_title() {
			return __( 'Product', 'gravityflowwoocommerce' );
		}

		/**
		 * Returns the form editor script which will set the field default properties.
		 *
		 * @since 1.1
		 *
		 * @return string
		 */
		public function get_form_editor_inline_script_on_page_render() {
			$script = sprintf( "function SetDefaultValues_%s(field) {field.label = '%s';}", $this->type, $this->get_form_editor_field_title() ) . PHP_EOL;

			return $script;
		}

	}

	GF_Fields::register( new Gravity_Flow_Field_WooCommerce_Product() );
}
