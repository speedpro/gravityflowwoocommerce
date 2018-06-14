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
			'gravityflowwoocommerce_edit_profiles',
		);

		protected $_capabilities_app_settings = 'gravityflowwoocommerce_settings';
		protected $_capabilities_uninstall = 'gravityflowwoocommerce_uninstall';

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
			$caps['gravityflowwoocommerce_edit_profiles'] = $prefix . __( 'Edit Users', 'gravityflowwoocommerce' );

			return $caps;
		}
	}
}
