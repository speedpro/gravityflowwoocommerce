<?php


// Make sure Gravity Forms is active and already loaded.
if ( class_exists( 'GFForms' ) ) {

	class Gravity_Flow_Woocommerce extends Gravity_Flow_Extension {

		private static $_instance = null;

		public $_version = GRAVITY_FLOW_WOOCOMMERCE_VERSION;

		public $edd_item_name = GRAVITY_FLOW_WOOCOMMERCE_EDD_ITEM_NAME;

		// The Framework will display an appropriate message on the plugins page if necessary
		protected $_min_gravityforms_version = '1.9.10';

		protected $_slug = 'gravityflowwoocommerce';

		protected $_path = 'gravityflowwoocommerce/woocommerce.php';

		protected $_full_path = __FILE__;

		// Title of the plugin to be used on the settings page, form settings and plugins page.
		protected $_title = 'WooCommerce Extension';

		// Short version of the plugin title to be used on menus and other places where a less verbose string is useful.
		protected $_short_title = 'WooCommerce';

		protected $_capabilities = array(
			'gravityflowwoocommerce_uninstall',
			'gravityflowwoocommerce_settings',
			'gravityflowwoocommerce_form_settings',
		);

		protected $_capabilities_app_settings = 'gravityflowwoocommerce_settings';
		protected $_capabilities_uninstall = 'gravityflowwoocommerce_uninstall';
		protected $_capabilities_form_settings = 'gravityflowwoocommerce_form_settings';

		public static function get_instance() {
			if ( self::$_instance == null ) {
				self::$_instance = new Gravity_Flow_Woocommerce();
			}

			return self::$_instance;
		}

		private function __clone() {
		} /* do nothing */

		public function init() {
			parent::init();
		}

		public function init_admin() {
			parent::init_admin();
		}

		/**
		 * Add the extension capabilities to the Gravity Flow group in Members.
		 *
		 * @since 1.1-dev
		 *
		 * @param array $caps The capabilities and their human readable labels.
		 *
		 * @return array
		 */
		public function get_members_capabilities( $caps ) {
			$prefix = $this->get_short_title() . ': ';

			$caps['gravityflowwoocommerce_settings']      = $prefix . __( 'Manage Settings', 'gravityflowwoocommerce' );
			$caps['gravityflowwoocommerce_uninstall']     = $prefix . __( 'Uninstall', 'gravityflowwoocommerce' );
			$caps['gravityflowwoocommerce_form_settings'] = $prefix . __( 'Manage Form Settings', 'gravityflowwoocommerce' );

			return $caps;
		}

		public function form_settings_fields( $form ) {

			return array(
				array(
					'title'       => esc_html__( 'WooCommerce', 'gravityflowwoocommerce' ),
					'description' => $this->get_woocommerce_setting_description(),
					'fields'      => array(
						array(
							'name'       => 'woocommerce_' . $form['id'],
							'label'      => esc_html__( 'Integration Enabled?', 'gravityflowwoocommerce' ),
							'type'       => 'checkbox',
							'horizontal' => true,
							'required'   => 1,
							'choices'    => array(
								array(
									'label' => esc_html__( 'Enable WooCommerce orders integration.', 'gravityflowwoocommerce' ),
									'value' => 1,
									'name'  => 'woocommerce_orders_integration_enabled',
								),
							),
						),
					),
				),
			);
		}

		/**
		 * Define the markup to be displayed for the WooCommerce description.
		 *
		 * @since 1.0.0
		 *
		 * @return string HTML formatted webhooks description.
		 */
		public function get_woocommerce_setting_description() {
			ob_start();
			?>
			<p><?php esc_html_e( 'When enable WooCommerce integration, it will:', 'gravityflowwoocommerce' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Create a new entry when a WooCommerce Order is created.', 'gravityflowwoocommerce' ); ?></li>
				<li><?php esc_html_e( 'Update the entry payment and transaction details based on the WooCommerce Order.', 'gravityflowwoocommerce' ); ?></li>
			</ul>
			<?php
			return ob_get_clean();
		}
	}
}
