<?php
/**
 * Manage Magalu Orders API.
 *
 * @package Virtuaria/Integrations/Marketplace/Magalu.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handle Magalu Orders API.
 */
class Virtuaria_Magalu_Order_API extends Virtuaria_Magalu_API {
	/**
	 * Timeout.
	 *
	 * @var int
	 */
	protected const TIMEOUT = 30;

	/**
	 * Initialize functions.
	 *
	 * @param WC_Logger $log instance from log.
	 */
	public function __construct( WC_Logger $log ) {
		$this->log = $log;
	}

	/**
	 * Get all orders approved.
	 *
	 * @return array|false
	 */
	public function get_approved_orders() {
		$request = wp_remote_get(
			"{$this->endpoint}api/OrderQueue/?Status=APPROVED",
			array(
				'headers' => $this->get_formatted_headers(),
				'timeout' => self::TIMEOUT,
			)
		);

		if ( ! $this->request_success(
			$request,
			'Falha ao obter pedidos da fila: ',
			'get_approved_orders'
		) ) {
			return false;
		}

		return json_decode(
			wp_remote_retrieve_body( $request ),
			true
		);
	}

	/**
	 * Remove orders from order queue.
	 *
	 * @param array $packages_id ids from packages.
	 * @return bool
	 */
	public function remove_orders_queue( $packages_id ) {
		$request = wp_remote_request(
			"{$this->endpoint}api/OrderQueue/",
			array(
				'headers' => $this->get_formatted_headers(),
				'body'    => $this->get_body_params( $packages_id ),
				'method'  => 'PUT',
				'timeout' => self::TIMEOUT,
			)
		);

		return $this->request_success(
			$request,
			'Falha ao remover pedido da fila: ',
			'remove_orders_queue',
			$packages_id
		);
	}

	/**
	 * Get order by id.
	 *
	 * @param int $order_id order id.
	 * @return array|false
	 */
	public function get_order( $order_id ) {
		$request = wp_remote_get(
			"{$this->endpoint}api/Order/$order_id",
			array(
				'headers' => $this->get_formatted_headers(),
				'timeout' => self::TIMEOUT,
			)
		);

		if ( ! $this->request_success(
			$request,
			'Falha ao obter pedido: ',
			'get_order',
			$order_id
		) ) {
			Virtuaria_Magalu_DAO::add_log(
				$order_id,
				wp_remote_retrieve_body( $request ),
				'ERROR'
			);
			return false;
		}

		return json_decode(
			wp_remote_retrieve_body( $request ),
			true
		);
	}

	/**
	 * Update order.
	 *
	 * @param array $data     modified order fields.
	 * @param int   $order_id order id to log.
	 * @return bool
	 */
	public function update_order( $data, $order_id ) {
		$request = wp_remote_request(
			"{$this->endpoint}api/Order",
			array(
				'headers' => $this->get_formatted_headers(),
				'body'    => $this->get_body_params( $data ),
				'method'  => 'PUT',
				'timeout' => self::TIMEOUT,
			)
		);

		$successful = $this->request_success(
			$request,
			'Falha ao atualizar pedido: ',
			'update_order',
			$data,
			$order_id
		);
		if ( $successful ) {
			return true;
		}

		Virtuaria_Magalu_DAO::add_log(
			$order_id,
			wp_json_encode( $request ),
			'ERROR'
		);

		return false;
	}

	/**
	 * Generates a shipping label for the given order ID.
	 *
	 * @param int $order_id The ID of the order to generate a shipping label for.
	 * @return bool Returns true if the shipping label was successfully generated, false otherwise.
	 */
	public function generate_shipping_label( $order_id ) {
		$request = wp_remote_get(
			"{$this->endpoint}api/Order/ShippingLabels",
			array(
				'headers' => $this->get_formatted_headers(),
				'body'    => $this->get_body_params(
					array(
						'format' => 'PDF',
						'orders' => array( $order_id ),

					)
				),
				'timeout' => self::TIMEOUT,
			)
		);

		if ( $this->request_success(
			$request,
			'Falha ao gerar etiqueta: ',
			'generate_shipping_label',
			$order_id
		) ) {
			return json_decode(
				wp_remote_retrieve_body( $request ),
				true
			)[0];
		}

		Virtuaria_Magalu_DAO::add_log(
			$order_id,
			wp_remote_retrieve_body( $request ),
			'ERROR'
		);
		return false;
	}
}
