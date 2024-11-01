<?php
/**
 * Handle Magalu database.
 *
 * @package Virtuaria/Integrations/Marketplace/Magalu.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dao.
 */
class Virtuaria_Magalu_DAO {
	/**
	 * Table version.
	 *
	 * @var float
	 */
	private const TABLE_VERSION = 1.0;

	/**
	 * Initialize functions.
	 */
	public function __construct() {
		add_action( 'virtuaria_magalu_install', array( $this, 'initialize_structure' ) );
	}

	/**
	 * Create or update table.
	 */
	public function initialize_structure() {
		global $wpdb;

		$installed_ver = get_option( 'virtuaria_magalu_db_version' );

		if ( floatVal( $installed_ver ) !== self::TABLE_VERSION ) {

			$sql = "CREATE TABLE {$wpdb->prefix}virtuaria_magalu_orders (
				id VARCHAR(100) NOT NULL,
				billing TEXT,
				shipping TEXT,
				delivery_date DATETIME,
				order_id INTEGER NOT NULL,
				status VARCHAR(15),
				approved_date DATETIME,
				PRIMARY KEY  (id, order_id)
			);
			CREATE TABLE {$wpdb->prefix}virtuaria_magalu_orders_log (
				id INTEGER NOT NULL AUTO_INCREMENT,
				order_id INTEGER NOT NULL,
				message TEXT NOT NULL,
				type VARCHAR(10),
				event_date DATETIME DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id)
			);";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			wc_get_logger()->info(
				'Update DB structure: ' . wp_json_encode( dbDelta( $sql ) )
			);

			update_option( 'virtuaria_magalu_db_version', self::TABLE_VERSION );
		}
	}

	/**
	 * Get all orders.
	 *
	 * @return array|false Database query results.
	 */
	public function get_orders() {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}virtuaria_magalu_orders",
			ARRAY_A
		);

		if ( $results ) {
			return $results;
		}
		return false;
	}

	/**
	 * Get order.
	 *
	 * @param int $order_id  order id.
	 * @param int $magalu_id magalu order id.
	 * @return array|false Database query results.
	 */
	public function get_order( $order_id, $magalu_id ) {
		global $wpdb;

		$results = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}virtuaria_magalu_orders WHERE order_id = %d AND id = %d",
				$order_id,
				$magalu_id
			),
			ARRAY_A
		);

		if ( $results ) {
			return $results;
		}
		return false;
	}

	/**
	 * Replace order.
	 *
	 * @param array $data data to replace.
	 * @return array|false Database query results.
	 */
	public function replace_order( $data ) {
		global $wpdb;

		$format = array();
		foreach ( $data as $val ) {
			if ( is_numeric( $val ) ) {
				$format[] = '%d';
			} else {
				$format[] = '%s';
			}
		}
		$results = $wpdb->replace(
			"{$wpdb->prefix}virtuaria_magalu_orders",
			$data,
			$format
		);

		if ( $results ) {
			return $results;
		}
		return false;
	}

	/**
	 * Add new message to log.
	 *
	 * @param int    $order_id order id.
	 * @param string $message  message to log.
	 * @param string $type event type default 'INFO'.
	 * @return boolean
	 */
	public static function add_log( $order_id, $message, $type = 'INFO' ) {
		global $wpdb;

		if ( 'INFO' === $type ) {
			$message = mb_strtoupper( wp_get_current_user()->user_login ) . ' - ' . $message;
		}

		$result = $wpdb->insert(
			"{$wpdb->prefix}virtuaria_magalu_orders_log",
			array(
				'order_id' => $order_id,
				'message'  => $message,
				'type'     => $type,
			),
			array(
				'%d',
				'%s',
				'%s',
			)
		);

		return false !== $result;
	}

	/**
	 * Get all log orders.
	 *
	 * @param int $order_id order id.
	 * @return array|false
	 */
	public function get_logs( $order_id ) {
		global $wpdb;

		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}virtuaria_magalu_orders_log WHERE order_id = %d ORDER BY id DESC",
				$order_id
			),
			ARRAY_A
		);

		return $result;
	}

	/**
	 * Get log orders by type.
	 *
	 * @param in     $order_id order id.
	 * @param string $type     log type.
	 * @return array|false
	 */
	public function get_logs_by_type( $order_id, $type ) {
		global $wpdb;

		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}virtuaria_magalu_orders_log WHERE order_id = %d AND type = %s ORDER BY id DESC",
				$order_id,
				$type
			),
			ARRAY_A
		);

		return $result;
	}


	/**
	 * Get last 4 days magalu orders.
	 *
	 * @return array|false
	 */
	public function get_last_magalu_orders() {
		global $wpdb;

		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, order_id FROM {$wpdb->prefix}virtuaria_magalu_orders WHERE approved_date >= %s ORDER BY id DESC",
				wp_date( 'Y-m-d', strtotime( '-4 day' ) )
			),
			ARRAY_A
		);

		return $result;
	}
}
