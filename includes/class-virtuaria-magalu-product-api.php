<?php
/**
 * Handle access to Magalu Products API.
 *
 * @package Virtuaria/Integrations/Marketplace/Magalu.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handle API.
 */
class Virtuaria_Magalu_Product_API extends Virtuaria_Magalu_API {
	/**
	 * Timeout.
	 *
	 * @var int
	 */
	protected const TIMEOUT = 30;

	/**
	 * Initialize functions.
	 */
	public function __construct() {
		$this->log = wc_get_logger();
	}

	/**
	 * New product.
	 *
	 * @param array $data the product data.
	 */
	public function add_product( $data ) {
		$request = wp_remote_post(
			$this->endpoint . 'api/Product',
			array(
				'headers' => $this->get_formatted_headers(),
				'body'    => $this->get_body_params( $data ),
				'timeout' => self::TIMEOUT,
			)
		);

		return $this->request_success(
			$request,
			'Falha ao enviar produto: ',
			'add_product',
			$data
		);
	}

	/**
	 * New SKU.
	 *
	 * @param array $data the product data.
	 */
	public function add_sku( $data ) {
		$request = wp_remote_post(
			$this->endpoint . 'api/Sku',
			array(
				'headers' => $this->get_formatted_headers(),
				'body'    => $this->get_body_params( $data ),
				'timeout' => self::TIMEOUT,
			)
		);

		return $this->request_success(
			$request,
			'Falha ao enviar sku: ',
			'add_sku',
			$data
		);
	}

	/**
	 * Update product.
	 *
	 * @param array $data the product data.
	 */
	public function update_product( $data ) {
		$request = wp_remote_request(
			$this->endpoint . 'api/Product',
			array(
				'headers' => $this->get_formatted_headers(),
				'body'    => $this->get_body_params( $data ),
				'method'  => 'PUT',
				'timeout' => self::TIMEOUT,
			)
		);

		return $this->request_success(
			$request,
			'Falha ao atualizar produto: ',
			'update_product',
			$data
		);
	}

	/**
	 * New SKU.
	 *
	 * @param array $data the product data.
	 */
	public function update_sku( $data ) {
		$request = wp_remote_request(
			"{$this->endpoint}api/Sku",
			array(
				'headers' => $this->get_formatted_headers(),
				'body'    => $this->get_body_params( $data ),
				'method'  => 'PUT',
				'timeout' => self::TIMEOUT,
			)
		);

		return $this->request_success(
			$request,
			'Falha ao atualizar sku: ',
			'update_sku',
			$data
		);
	}
}
