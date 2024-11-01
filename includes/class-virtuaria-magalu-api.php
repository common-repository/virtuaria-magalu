<?php
/**
 * Handle access to Magalu API.
 *
 * @package Virtuaria/Integrations/Marketplace/Magalu.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handle API.
 */
abstract class Virtuaria_Magalu_API {
	/**
	 * Log instance
	 *
	 * @var WC_Logger.
	 */
	protected $log;

	/**
	 * Log identifier
	 *
	 * @var string.
	 */
	protected $tag = 'virtuaria-magalu';

	/**
	 * Endpoint
	 *
	 * @var string.
	 */
	protected $endpoint = 'https://in.integracommerce.com.br/';

	/**
	 * Count try exec functions.
	 *
	 * @var array
	 */
	protected $retry_function;

	/**
	 * Get formatted body request.
	 *
	 * @param array $data product data.
	 * @return string
	 */
	protected function get_body_params( $data ) {
		$body = array();

		if ( $data ) {
			foreach ( $data as $index => $val ) {
				$body[ $index ] = $val;
			}
		}

		return wp_json_encode( $body );
	}

	/**
	 * Check sucess from request.
	 *
	 * @param mixed  $request   request.
	 * @param string $msg_error message error to log.
	 * @param string ...$params list from retry function.
	 * @return boolean true in success otherwise false
	 */
	protected function request_success( $request, $msg_error, ...$params ) {
		$has_error = false;
		if ( is_wp_error( $request ) ) {
			$this->log->add(
				$this->tag,
				$msg_error . $request->get_error_message(),
				WC_Log_Levels::ERROR
			);
			$has_error = true;
		}

		$resp_code = wp_remote_retrieve_response_code( $request );
		$body      = json_decode(
			wp_remote_retrieve_body( $request ),
			true
		);
		$duplicate = false;

		if ( isset( $body['Errors'][0]['Message'] )
			&& 'Já existe um produto com o IdProductErp informado.' === $body['Errors'][0]['Message'] ) {
			$duplicate = true;
		}

		if ( ! $duplicate && ! in_array( $resp_code, array( 200, 201, 204 ), true ) ) {
			$this->log->add(
				$this->tag,
				$msg_error . 'REQUEST ' . wp_json_encode( $params ) . ' RESPONSE: ' . wp_json_encode( $request ),
				WC_Log_Levels::ERROR
			);

			$has_error = true;
			if ( 429 === $resp_code ) {
				sleep( 3 );
			}

			if ( 401 === $resp_code ) {
				$this->refresh_token();
				if ( ( ! isset( $this->retry_function[ $params[0] ] )
					|| ! $this->retry_function[ $params[0] ] )
					&& isset( $params[0] ) ) {

					$this->retry_function[ $params[0] ] = true;
					$this->{$params[0]}(
						isset( $params[1] ) ? $params[1] : null,
						isset( $params[2] ) ? $params[2] : null
					);
				}
			}
		}

		if ( isset( $params[0] ) ) {
			$this->retry_function[ $params[0] ] = false;
		}

		return ! $has_error;
	}

	/**
	 * Get formatted request headers.
	 */
	protected function get_formatted_headers() {
		$settings = get_option( 'virtuaria_magalu_settings' );
		$token    = isset( $settings['authorization']['access_token'] )
			? $settings['authorization']['access_token']
			: '';
		return array(
			'Content-Type'  => 'application/json',
			'Authorization' => "Bearer $token",
		);
	}

	/**
	 * Get new token.
	 */
	protected function refresh_token() {
		$settings = get_option( 'virtuaria_magalu_settings' );
		$token    = isset( $settings['authorization']['refresh_token'] )
			? $settings['authorization']['refresh_token']
			: '';

		$request = wp_remote_post(
			'https://magalu.virtuaria.com.br/wp-json/v1/magalu/refresh_token',
			array(
				'headers' => array(
					'content-Length' => 0,
					'token'          => $token,
				),
				'timeout' => 30,
			)
		);

		$this->log->add(
			$this->tag,
			'Response auth: ' . wp_json_encode( $request ),
			WC_Log_Levels::INFO
		);

		if ( ! is_wp_error( $request )
				&& 200 === wp_remote_retrieve_response_code( $request ) ) {
			$settings['authorization'] = json_decode(
				wp_remote_retrieve_body( $request ),
				true
			);
			update_option( 'virtuaria_magalu_settings', $settings );
			$this->log->add(
				$this->tag,
				'Token renovado!',
				WC_Log_Levels::INFO
			);
		}
	}
}
