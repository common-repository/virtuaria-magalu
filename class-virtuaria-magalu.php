<?php
/**
 * Plugin Name: Virtuaria - Integração com Magalu
 * Plugin URI: https://virtuaria.com.br
 * Description: Integração de lojas Virtuaria com o marketplace Magazine Luiza.
 * Version: 1.0.1
 * Author: Virtuaria
 * Author URI: htttps://virtuaria.com.br
 * License: GPLv2 or later
 *
 * @package Virtuaria/Integrations/Marketplace/Magalu.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Virtuaria_Magalu' ) ) :
	register_activation_hook(
		__FILE__,
		array( 'Virtuaria_Magalu', 'install_magalu_integration' )
	);

	register_deactivation_hook(
		__FILE__,
		array( 'Virtuaria_Magalu', 'uninstall_magalu_integration' )
	);

	define( 'VIRTUARIA_MAGALU_URL', plugin_dir_url( __FILE__ ) );
	define( 'VIRTUARIA_MAGALU_PATH', plugin_dir_path( __FILE__ ) );

	/**
	 * Handle integration.
	 */
	class Virtuaria_Magalu {
		/**
		 * Class instance.
		 *
		 * @var Virtuaria_Magalu
		 */
		protected static $instance = null;

		/**
		 * Initiale functions.
		 */
		private function __construct() {
			$this->load_dependencys();

			add_action( 'admin_enqueue_scripts', array( $this, 'admin_style_scripts' ) );
			add_filter( 'cron_schedules', array( __CLASS__, 'event_interval' ) );
			add_action( 'admin_menu', array( $this, 'add_menu' ) );
			add_action( 'virtuaria_magalu_save_settings', array( $this, 'save_settings' ) );
		}

		/**
		 * Return an instance of this class.
		 *
		 * @return object A single instance of this class.
		 */
		public static function get_instance() {
			// If the single instance hasn't been set, set it now.
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Load dependencys.
		 */
		private function load_dependencys() {
			require_once 'includes/class-virtuaria-magalu-api.php';
			require_once 'includes/class-virtuaria-magalu-product-api.php';
			require_once 'includes/class-virtuaria-magalu-order-api.php';
			require_once 'includes/class-virtuaria-magalu-dao.php';
			require_once 'includes/traits/trait-virtuaria-magalu-products-o.php';
			require_once 'includes/class-virtuaria-magalu-product.php';
			require_once 'includes/traits/trait-virtuaria-magalu-orders-o.php';
			require_once 'includes/class-virtuaria-magalu-orders.php';
		}

		/**
		 * Add styles and scripts.
		 *
		 * @param string $page page identifier.
		 */
		public function admin_style_scripts( $page ) {
			$dir_url  = plugin_dir_url( __FILE__ ) . 'admin/';
			$dir_path = plugin_dir_path( __FILE__ ) . 'admin/';

			if ( 'edit.php' === $page
				&& isset( $_GET['post_type'] )
				&& 'product' === $_GET['post_type'] ) {
				wp_enqueue_script(
					'quick-bulk-edit',
					$dir_url . 'js/quick-bulk-edit.js',
					array( 'jquery' ),
					filemtime( $dir_path . 'js/quick-bulk-edit.js' ),
					true
				);
			}

			if ( 'toplevel_page_virtuaria_magalu' === $page ) {
				wp_enqueue_style(
					'virtuaria_magalu',
					$dir_url . 'css/setup.css',
					array(),
					filemtime( $dir_path . 'css/setup.css' )
				);

				wp_enqueue_script(
					'virtuaria_magalu',
					$dir_url . 'js/setup.js',
					array( 'jquery' ),
					filemtime( $dir_path . 'js/setup.js' ),
					true
				);
			}
		}

		/**
		 * Hook in installing plugin
		 */
		public static function install_magalu_integration() {
			require_once 'includes/class-virtuaria-magalu-dao.php';
			new Virtuaria_Magalu_DAO();
			do_action( 'virtuaria_magalu_install' );

			add_filter( 'cron_schedules', array( __CLASS__, 'event_interval' ) );
			if ( ! wp_next_scheduled( 'virtuaria_magalu_import_orders' ) ) {
				wp_schedule_event(
					strtotime( '11:00:00' ),
					'every_eight_hours',
					'virtuaria_magalu_import_orders'
				);
			}

			if ( ! wp_next_scheduled( 'virtuaria_magalu_update_orders_status' ) ) {
				wp_schedule_event(
					strtotime( '13:00:00' ),
					'twicedaily',
					'virtuaria_magalu_update_orders_status'
				);
			}
		}

		/**
		 * Hook in installing plugin
		 */
		public static function uninstall_magalu_integration() {
			do_action( 'virtuaria_magalu_uninstall' );
			$timestamp = wp_next_scheduled( 'virtuaria_magalu_import_orders' );
			if ( $timestamp ) {
				wp_unschedule_event(
					$timestamp,
					'virtuaria_magalu_import_orders'
				);
			}

			$timestamp = wp_next_scheduled( 'virtuaria_magalu_update_orders_status' );
			if ( $timestamp ) {
				wp_unschedule_event(
					$timestamp,
					'virtuaria_magalu_update_orders_status'
				);
			}
		}

		/**
		 * Add custom schedules time.
		 *
		 * @param array $schedules the current schedules.
		 * @return array
		 */
		public static function event_interval( $schedules ) {
			if ( ! isset( $schedules['every_eight_hours'] ) ) {
				$schedules['every_eight_hours'] = array(
					'interval' => 8 * HOUR_IN_SECONDS,
					'display'  => 'A cada 8 horas',
				);
			}

			return $schedules;
		}

		/**
		 * Create plugin menu structure.
		 */
		public function add_menu() {
			add_menu_page(
				'Virtuaria Magalu',
				'Virtuaria Magalu',
				'remove_users',
				'virtuaria_magalu',
				array( $this, 'magalu_settings' ),
				VIRTUARIA_MAGALU_URL . 'admin/images/virtuaria.png'
			);

			add_submenu_page(
				'virtuaria_magalu',
				'Magalu Configurações',
				'Configurações',
				'remove_users',
				'virtuaria_magalu',
				array( $this, 'magalu_settings' ),
				1
			);
		}

		/**
		 * Callback menu 'Virtuaria Magalu'.
		 */
		public function magalu_settings() {
			require_once 'templates/magalu-settings.php';
		}

		/**
		 * Save settings to magalu integration.
		 */
		public function save_settings() {
			if ( isset( $_POST['setup_nonce'] )
				&& wp_verify_nonce(
					sanitize_text_field(
						wp_unslash( $_POST['setup_nonce'] )
					),
					'setup_virtuaria_module'
				)
			) {
				$data = array(
					'mail' => isset( $_POST['virtuaria_magalu_email'] )
						? sanitize_text_field( wp_unslash( $_POST['virtuaria_magalu_email'] ) )
						: '',
					'cnpj' => isset( $_POST['virtuaria_magalu_cnpj'] )
						? sanitize_text_field( wp_unslash( $_POST['virtuaria_magalu_cnpj'] ) )
						: '',
					'fee'  => isset( $_POST['virtuaria_magalu_fee'] )
						? sanitize_text_field( wp_unslash( $_POST['virtuaria_magalu_fee'] ) )
						: '',
				);

				if ( isset( $_POST['virtuaria_magalu_authorization'] ) ) {
					if ( 'disconnected' === $_POST['virtuaria_magalu_authorization'] ) {
						$auth = $this->get_store_connection(
							$data['mail'],
							$data['cnpj']
						);

						if ( $auth ) {
							$data['authorization'] = is_string( $auth )
								? json_decode( $auth, true )
								: $auth;
						} else {
							echo '<div id="message" class="error inline"><p><strong>Falha ao conectar.</strong></p></div>';
							return;
						}
					}
				} else {
					$settings = get_option( 'virtuaria_magalu_settings' );
					if ( isset( $settings['authorization'] ) ) {
						$data['authorization'] = $settings['authorization'];
					}
				}

				update_option( 'virtuaria_magalu_settings', $data );
				echo '<div id="message" class="updated inline"><p><strong>Suas configurações foram salvas.</strong></p></div>';
				return;
			}
		}

		/**
		 * Get store connection.
		 *
		 * @param string $mail marketplace mail.
		 * @param string $cnpj marketplace cnpj.
		 */
		private function get_store_connection( $mail, $cnpj ) {
			$result = wp_remote_get(
				"https://magalu.virtuaria.com.br/auth/magalu?cnpj=$cnpj&email=$mail&t=" . time()
			);

			wc_get_logger()->add(
				'virt-magalu',
				'Obtendo conexão - ' . wp_json_encode( $result ),
				WC_Log_Levels::INFO
			);
			if ( is_wp_error( $result )
				|| 200 !== wp_remote_retrieve_response_code( $result ) ) {
				return false;
			}
			$data = json_decode(
				wp_remote_retrieve_body( $result ),
				true
			);

			return isset( $data['data']['token'] )
				? $data['data']['token']
				: false;
		}
	}

	add_action( 'plugins_loaded', array( 'Virtuaria_Magalu', 'get_instance' ) );

endif;
