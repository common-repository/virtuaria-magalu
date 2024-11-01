<?php
/**
 * Manage Magalu Orders Flux.
 *
 * @package Virtuaria/Integrations/Marketplace/Magalu.
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

/**
 * Handle Magalu Orders.
 */
class Virtuaria_Magalu_Orders {
	use Virtuaria_Magalu_Orders_Trait;

	/**
	 * Dao instance.
	 *
	 * @var Virtuaria_Magalu_DAO
	 */
	private $dao;

	/**
	 * API instance.
	 *
	 * @var Virtuaria_Magalu_Order_API
	 */
	private $api;

	/**
	 * Log instance.
	 *
	 * @var WC_logger
	 */
	private $log;

	/**
	 * Log identifier.
	 *
	 * @var string
	 */
	private $tag;

	/**
	 * Initialization.
	 */
	public function __construct() {
		$this->log = wc_get_logger();
		$this->tag = 'virtuaria-magalu';
		$this->dao = new Virtuaria_Magalu_DAO();
		$this->api = new Virtuaria_Magalu_Order_API(
			$this->log
		);
		add_action( 'admin_menu', array( $this, 'add_orders_menu' ), 15 );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_styles_scripts' ), 20 );
		add_filter( 'woocommerce_email_enabled_cancelled_order', array( $this, 'disable_notifications_magalu_orders' ), 20, 2 );
		add_filter( 'woocommerce_email_enabled_customer_on_hold_order', array( $this, 'disable_notifications_magalu_orders' ), 20, 2 );
		add_filter( 'woocommerce_email_enabled_customer_processing_order', array( $this, 'disable_notifications_magalu_orders' ), 20, 2 );
		add_filter( 'woocommerce_email_enabled_customer_completed_order', array( $this, 'disable_notifications_magalu_orders' ), 20, 2 );
		add_filter( 'woocommerce_email_enabled_customer_invoice', array( $this, 'disable_notifications_magalu_orders' ), 20, 2 );
		add_filter( 'woocommerce_email_enabled_customer_note', array( $this, 'disable_notifications_magalu_orders' ), 20, 2 );
		add_filter( 'woocommerce_email_enabled_customer_refunded_order', array( $this, 'disable_notifications_magalu_orders' ), 20, 2 );
		add_filter( 'woocommerce_email_enabled_failed_order', array( $this, 'disable_notifications_magalu_orders' ), 20, 2 );
		add_filter( 'views_edit-shop_order', array( $this, 'display_sync_magalu_button' ) );
		add_filter( 'woocommerce_before_shop_order_list_table_view_links', array( $this, 'display_sync_magalu_button' ) );
		add_action( 'admin_init', array( $this, 'manual_trigger_sync' ) );
		add_action( 'virtuaria_magalu_import_orders', array( $this, 'import_orders' ) );
		add_action( 'magalu_manual_fetch_orders', array( $this, 'import_orders' ) );
		add_action(
			'add_meta_boxes_' . $this->get_meta_boxes_screen(),
			array( $this, 'shipping_label_box' )
		);
		add_action(
			'woocommerce_process_shop_order_meta',
			array( $this, 'generate_shipping_label_box' )
		);

		add_action(
			'virtuaria_magalu_update_orders_status',
			array( $this, 'update_orders_staus' )
		);
	}

	/**
	 * Add new orders submenu.
	 */
	public function add_orders_menu() {
		add_submenu_page(
			'virtuaria_magalu',
			'Magalu Pedidos',
			'Pedidos',
			'remove_users',
			'magalu_orders',
			array( $this, 'magalu_order_page' )
		);
	}

	/**
	 * Display page content.
	 */
	public function magalu_order_page() {
		$order_id = isset( $_GET['order_id'] )
			? sanitize_text_field( wp_unslash( $_GET['order_id'] ) )
			: null;
		$mag_id   = isset( $_GET['id'] )
			? sanitize_text_field( wp_unslash( $_GET['id'] ) )
			: null;

		if ( $order_id && $mag_id ) {
			$data = $this->dao->get_order( $order_id, $mag_id );
			if ( $data
				&& isset( $_POST['operation'] )
				&& (
					( isset( $_POST['nf_nonce'] )
					&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nf_nonce'] ) ), 'update_nf' ) )
					|| ( isset( $_POST['shipping_nonce'] )
					&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['shipping_nonce'] ) ), 'update_shipping' ) )
					|| ( isset( $_POST['delivery_nonce'] )
					&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['delivery_nonce'] ) ), 'update_delivery' ) )
				)
			) {
				$update = false;

				switch ( $_POST['operation'] ) {
					case 'billing':
						if ( $this->update_billing( $order_id, $mag_id, $data ) ) {
							$update = true;
						}
						break;
					case 'shipping':
						if ( $this->update_shipping( $order_id, $mag_id, $data ) ) {
							$update = true;
						}
						break;
					case 'delivery':
						if ( $this->update_delivery( $order_id, $mag_id, $data ) ) {
							$update = true;
						}
						break;
				}

				if ( $update ) {
					$message = '<div id="message" class="updated inline"><p><strong>Suas configurações foram salvas.</strong></p></div>';
				} else {
					$message = '<div id="message" class="error inline"><p><strong>Falha ao processar operação.</strong></p></div>';
				}
				$data = $this->dao->get_order( $order_id, $mag_id );
			}
		}

		require_once VIRTUARIA_MAGALU_PATH . 'templates/order-page.php';
	}

	/**
	 * Enqueue admin styles and scripts.
	 *
	 * @param String $hook hook page.
	 */
	public function add_admin_styles_scripts( $hook ) {
		$dir_path = plugin_dir_path( __FILE__ ) . '../admin/';
		$dir_url  = plugin_dir_url( __FILE__ ) . '../admin/';

		if ( 'virtuaria-magalu_page_magalu_orders' === $hook ) {
			wp_enqueue_script(
				'datatables.min',
				$dir_url . 'datatables/datatables.min.js',
				array( 'jquery' ),
				filemtime( $dir_path . 'datatables/datatables.min.js' ),
				true
			);

			wp_enqueue_style(
				'datatables.min-css',
				$dir_url . 'datatables/datatables.min.css',
				array(),
				filemtime( $dir_path . 'datatables/datatables.min.css' )
			);

			if ( ! isset( $_GET['order_id'] ) ) {
				wp_enqueue_style(
					'magalu-orders',
					$dir_url . 'css/magalu-orders.css',
					array(),
					filemtime( $dir_path . 'css/magalu-orders.css' )
				);
			} else {
				wp_enqueue_style(
					'magalu-order',
					$dir_url . 'css/magalu-order.css',
					array(),
					filemtime( $dir_path . 'css/magalu-order.css' )
				);
			}

			wp_enqueue_script(
				'magalu-orders',
				$dir_url . 'js/magalu-orders.js',
				array( 'jquery' ),
				filemtime( $dir_path . 'js/magalu-orders.js' ),
				true
			);

			wp_localize_script(
				'magalu-orders',
				'data',
				$this->display_edit_orders_columns(
					$this->dao->get_orders()
				)
			);

			$order_id = isset( $_GET['order_id'] )
				? intval( $_GET['order_id'] )
				: 0;
			wp_localize_script(
				'magalu-orders',
				'logs',
				current_user_can( 'install_themes' )
				? $this->dao->get_logs( intval( $order_id ) )
				: $this->dao->get_logs_by_type( $order_id, 'INFO' )
			);
		}
	}

	/**
	 * Edit orders.
	 *
	 * @param array $orders orders.
	 */
	private function display_edit_orders_columns( $orders ) {
		if ( $orders ) {
			foreach ( $orders as $index => $order ) {
				if ( 'DELIVERED' !== $order['status'] ) {
					$action = 'Editar';
				} else {
					$action = 'Visualizar';
				}
				$orders[ $index ]['actions'] = '<a href="' . admin_url(
					"admin.php?page=magalu_orders&order_id={$order['order_id']}&id={$order['id']}"
				) . '">' . $action . '</a>';
				$orders[ $index ]['order_id'] = '<a target="_blank" href="' . get_edit_post_link( $order['order_id'] )
				. '">' . $order['order_id'] . '</a>';
			}
		}
		return $orders;
	}

	/**
	 * Prevent send mail when fetch magalu order.
	 *
	 * @param boolean  $enabled condition to send.
	 * @param wc_order $order   the order.
	 */
	public function disable_notifications_magalu_orders( $enabled, $order ) {
		if ( $order && get_post_meta( $order->get_id(), '_magalu_order_id', true ) ) {
			$enabled = false;
		}
		return $enabled;
	}

	/**
	 * Display sync magalu button.
	 *
	 * @param array $views the views.
	 */
	public function display_sync_magalu_button( $views ) {
		$base_url = 'edit.php?post_type=shop_order';
		if ( isset( $_SERVER['REQUEST_URI'], $_GET['page'] ) && 'wc-orders' === $_GET['page'] ) {
			$base_url = basename(
				sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			);
		}

		$base_url = esc_url(
			wp_nonce_url(
				admin_url( $base_url . '&acao=sync_magalu' ),
				'fetch_orders',
				'magalu_nonce'
			)
		);

		$base_url .= '&t=' . time();
		?>
		<div class="magalu-actions" style="display:table;width:100%">
		<a
			class="page-title-action magalu"
			style="float:right;font-weight:normal"
			onclick="if (! confirm( 'A importação dos pedidos roda em segundo plano e pode demorar alguns minutos. Não será possível disparar outra importação antes da atual terminar.' ) ) { return false; }"
			href="<?php echo esc_url( $base_url ); ?>">Sincronizar pedidos do magalu</a></div>
		<?php
		return $views;
	}

	/**
	 * Trigger magalu sync.
	 */
	public function manual_trigger_sync() {
		if ( isset( $_GET['acao'] )
			&& isset( $_GET['magalu_nonce'] )
			&& 'sync_magalu' === $_GET['acao']
			&& ! wp_next_scheduled( 'magalu_manual_fetch_orders', array( 'is_manual' => true ) )
			&& ! get_transient( 'doing_magalu_manual_fetch_orders' )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['magalu_nonce'] ) ), 'fetch_orders' ) ) {
			wp_schedule_single_event(
				time(),
				'magalu_manual_fetch_orders',
				array( 'is_manual' => true )
			);

			set_transient(
				'doing_magalu_manual_fetch_orders',
				true,
				10 * MINUTE_IN_SECONDS
			);
		}
	}

	/**
	 * Import magalu orders.
	 *
	 * @param boolean $is_manual is manual.
	 */
	public function import_orders( $is_manual = false ) {
		if ( $is_manual ) {
			$this->log->add(
				$this->tag,
				'Importação manual de pedidos Magalu iniciada',
				WC_Log_Levels::INFO
			);
		} else {
			$this->log->add(
				$this->tag,
				'Iniciando importação automática de pedidos Magalu...',
				WC_Log_Levels::INFO
			);
		}
		$packages = $this->api->get_approved_orders();
		if ( $packages
			&& isset( $packages['OrderQueues'] )
			&& ! empty( $packages['OrderQueues'] ) ) {
			foreach ( $packages['OrderQueues'] as $package ) {
				$mag_order = $this->api->get_order(
					$package['IdOrder']
				);
				$this->log->add(
					$this->tag,
					wp_json_encode( $mag_order ),
					WC_Log_Levels::INFO
				);

				$fail = false;
				if ( $mag_order ) {
					$order_id = $this->create_order( $mag_order );
					if ( $order_id ) {
						$updated = $this->api->update_order(
							array(
								'IdOrder'     => $package['IdOrder'],
								'OrderStatus' => 'PROCESSING',
							),
							$order_id
						);

						$order_queue   = array();
						$order_queue[] = array(
							'Id' => $package['Id'],
						);
						if ( $updated
							&& $this->api->remove_orders_queue( $order_queue ) ) {
							$this->dao->replace_order(
								array(
									'id'            => $package['IdOrder'],
									'order_id'      => $order_id,
									'status'        => 'PROCESSING',
									'approved_date' => $mag_order['ApprovedDate'],
								)
							);
							Virtuaria_Magalu_DAO::add_log(
								$order_id,
								'Pedido importado com sucesso',
								'INFO'
							);
							Virtuaria_Magalu_DAO::add_log(
								$order_id,
								'Pedido importado ' . wp_json_encode( $mag_order ),
								'NOTICE'
							);
						} else {
							$fail = 'Etapa remover da fila.';
						}
					} else {
						$fail = 'Etapa criar pedido.';
					}
				} else {
					$fail = 'Etapa obter pedido.';
				}

				if ( $fail ) {
					Virtuaria_Magalu_DAO::add_log(
						$order_id,
						'Falha ao importar pedido',
						'INFO'
					);
					Virtuaria_Magalu_DAO::add_log(
						$order_id,
						$fail,
						'NOTICE'
					);
				}
			}
		}
		if ( $is_manual ) {
			$this->log->add(
				$this->tag,
				'Importação manual de pedidos Magalu finalizada',
				WC_Log_Levels::INFO
			);
		} else {
			$this->log->add(
				$this->tag,
				'Finalizando importação automática de pedidos Magalu...',
				WC_Log_Levels::INFO
			);
		}
	}

	/**
	 * Converts a string containing a number in cents format to a float number.
	 * This is necessary because Magalu returns numbers in cents format, and
	 * WooCommerce expects a float number.
	 *
	 * @param string $value The string containing the number in cents format.
	 * @return float The number converted to a float.
	 */
	private function convert_to_float( $value ) {
		$value = str_replace( array( '.', ',' ), '', $value );
		return $value / 100;
	}

	/**
	 * Update billing order.
	 *
	 * @param string $order_id order id.
	 * @param string $id       magalu order id.
	 * @param array  $data     order data.
	 * @return boolean
	 */
	private function update_billing( $order_id, $id, $data ) {
		if ( isset( $_POST['nf_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nf_nonce'] ) ), 'update_nf' )
			&& isset(
				$_POST['InvoicedNumber'],
				$_POST['InvoicedKey'],
				$_POST['InvoicedLine'],
				$_POST['InvoicedIssueDate'],
				$_POST['invoicedDanfeXml']
			)
		) {

			$billing = array(
				'IdOrder'           => $id,
				'InvoicedNumber'    => sanitize_text_field( wp_unslash( $_POST['InvoicedNumber'] ) ),
				'InvoicedLine'      => sanitize_text_field( wp_unslash( $_POST['InvoicedLine'] ) ),
				'InvoicedIssueDate' => sanitize_text_field( wp_unslash( $_POST['InvoicedIssueDate'] ) ),
				'InvoicedKey'       => sanitize_text_field( wp_unslash( $_POST['InvoicedKey'] ) ),
				'invoicedDanfeXml'  => esc_xml( $_POST['invoicedDanfeXml'] ),
				'OrderStatus'       => 'PROCESSING' === $data['status'] ? 'INVOICED' : $data['status'],
			);
			Virtuaria_Magalu_DAO::add_log(
				$order_id,
				'Solicitação de Faturamento do Pedido.',
				'INFO'
			);

			Virtuaria_Magalu_DAO::add_log(
				$order_id,
				'Enviando dados: ' . wp_json_encode( $billing ),
				'NOTICE'
			);
			$updated = $this->api->update_order( $billing, $order_id );
			if ( $updated ) {
				if ( $data['billing'] ) {
					$billing['InvoicedIssueDate'] = json_decode( $data['billing'], true )['InvoicedIssueDate'];
				}
				$replace = array(
					'order_id'      => $order_id,
					'id'            => $id,
					'billing'       => wp_json_encode(
						$billing
					),
					'shipping'      => $data['shipping'],
					'status'        => $billing['OrderStatus'],
					'approved_date' => $data['approved_date'],
					'delivery_date' => $data['delivery_date'],
				);

				$this->dao->replace_order(
					$replace
				);

				Virtuaria_Magalu_DAO::add_log(
					$order_id,
					'Dados do Faturamento Atualizados.',
					'INFO'
				);

				$order = wc_get_order( $order_id );
				if ( $order ) {
					$order->add_order_note(
						__( 'Magalu: Pedido em Faturamento.', 'virtuaria-magalu' ),
						0,
						true
					);
					$order->update_meta_data(
						'_magalu_order_status',
						$billing['OrderStatus']
					);
					$order->save();
				}
				return true;
			}
			Virtuaria_Magalu_DAO::add_log(
				$order_id,
				'Falha no Faturamento do Pedido.',
				'INFO'
			);
		}
		return false;
	}

	/**
	 * Update shipping order.
	 *
	 * @param string $order_id order id.
	 * @param string $id       magalu order id.
	 * @param array  $data     order data.
	 * @return boolean
	 */
	private function update_shipping( $order_id, $id, $data ) {
		if ( isset( $_POST['shipping_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['shipping_nonce'] ) ), 'update_shipping' )
			&& isset( $_POST['ShippedCarrierName'] )
			&& isset( $_POST['ShippedCarrierDate'] )
			&& isset( $_POST['ShippedTrackingProtocol'] )
			&& isset( $_POST['ShippedEstimatedDelivery'] ) ) {

			$shipping = array(
				'IdOrder'                  => $id,
				'ShippedCarrierName'       => sanitize_text_field( wp_unslash( $_POST['ShippedCarrierName'] ) ),
				'ShippedCarrierDate'       => sanitize_text_field( wp_unslash( $_POST['ShippedCarrierDate'] ) ),
				'ShippedTrackingProtocol'  => sanitize_text_field( wp_unslash( $_POST['ShippedTrackingProtocol'] ) ),
				'ShippedEstimatedDelivery' => sanitize_text_field( wp_unslash( $_POST['ShippedEstimatedDelivery'] ) ),
				'OrderStatus'              => 'INVOICED' === $data['status'] ? 'SHIPPED' : $data['status'],
			);

			Virtuaria_Magalu_DAO::add_log(
				$order_id,
				'Solicitação de Envio do Pedido.',
				'INFO'
			);
			Virtuaria_Magalu_DAO::add_log(
				$order_id,
				'Enviando dados: ' . wp_json_encode( $shipping ),
				'NOTICE'
			);
			$updated = $this->api->update_order( $shipping, $order_id );
			if ( $updated ) {
				$this->dao->replace_order(
					array(
						'order_id'      => $order_id,
						'id'            => $id,
						'shipping'      => wp_json_encode(
							$shipping
						),
						'billing'       => $data['billing'],
						'status'        => $shipping['OrderStatus'],
						'approved_date' => $data['approved_date'],
						'delivery_date' => $data['delivery_date'],
					)
				);

				Virtuaria_Magalu_DAO::add_log(
					$order_id,
					'Dados do Envio Atualizados.',
					'INFO'
				);

				$order = wc_get_order( $order_id );
				if ( $order ) {
					$order->add_order_note(
						__( 'Magalu: Pedido enviado ao cliente.', 'virtuaria-magalu' ),
						0,
						true
					);
					$order->update_meta_data(
						'_magalu_order_status',
						$shipping['OrderStatus']
					);
					$order->save();
				}
				return true;
			}
			Virtuaria_Magalu_DAO::add_log(
				$order_id,
				'Falha no Envio do Pedido.',
				'INFO'
			);
		}
		return false;
	}

	/**
	 * Update shipping order.
	 *
	 * @param string $order_id order id.
	 * @param string $id       magalu order id.
	 * @param array  $data     order data.
	 * @return boolean
	 */
	private function update_delivery( $order_id, $id, $data ) {
		if ( isset( $_POST['delivery_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['delivery_nonce'] ) ), 'update_delivery' )
			&& isset( $_POST['DeliveredDate'] ) ) {

			$delivery = array(
				'IdOrder'       => $id,
				'DeliveredDate' => sanitize_text_field( wp_unslash( $_POST['DeliveredDate'] ) ),
				'OrderStatus'   => 'SHIPPED' === $data['status'] ? 'DELIVERED' : $data['status'],
			);

			Virtuaria_Magalu_DAO::add_log(
				$order_id,
				'Solicitação de Entrega do Pedido.',
				'INFO'
			);
			Virtuaria_Magalu_DAO::add_log(
				$order_id,
				'Enviando dados: ' . wp_json_encode( $delivery ),
				'NOTICE'
			);
			$updated = $this->api->update_order( $delivery, $order_id );
			if ( $updated ) {
				$this->dao->replace_order(
					array(
						'order_id'      => $order_id,
						'id'            => $id,
						'shipping'      => $data['shipping'],
						'billing'       => $data['billing'],
						'status'        => $delivery['OrderStatus'],
						'approved_date' => $data['approved_date'],
						'delivery_date' => $delivery['DeliveredDate'],
					)
				);

				Virtuaria_Magalu_DAO::add_log(
					$order_id,
					"Pedido Entregue em {$delivery['DeliveredDate']}.",
					'INFO'
				);

				$order = wc_get_order( $order_id );
				if ( $order ) {
					$order->add_order_note(
						__( 'Magalu: Pedido entregue.', 'virtuaria-magalu' ),
						0,
						true
					);
					$order->update_meta_data(
						'_magalu_order_status',
						$delivery['OrderStatus']
					);
					$order->save();
				}
				return true;
			}
			Virtuaria_Magalu_DAO::add_log(
				$order_id,
				'Falha ao Definir Entrega do Pedido.',
				'INFO'
			);
		}
		return false;
	}

	/**
	 * Retrieve the screen ID for meta boxes.
	 *
	 * @return string
	 */
	private function get_meta_boxes_screen() {
		return class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
			&& wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			&& function_exists( 'wc_get_page_screen_id' )
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
	}

	/**
	 * Adds a shipping label box to the order page if the order is invoiced.
	 *
	 * @param mixed $post_or_order The WP_Post object or the order.
	 * @return void
	 */
	public function shipping_label_box( $post_or_order ) {
		$order = $this->get_order_from_mixed( $post_or_order );

		if ( $order
			&& 'INVOICED' === $order->get_meta( '_magalu_order_status' ) ) {
			add_meta_box(
				'shipping-label',
				__( 'Etique de Entrega Magalu', 'virtuaria-magalu' ),
				array( $this, 'shipping_label_box_content' ),
				$this->get_meta_boxes_screen(),
				'side'
			);
		}
	}

	/**
	 * Generates the content for the shipping label box on the order page.
	 *
	 * @param WP_Post|object $post The WP_Post object or the order.
	 * @return void
	 */
	public function shipping_label_box_content( $post ) {
		$order = $this->get_order_from_mixed( $post );
		?>
		<small>Gere a etiqueta de entrega para seu pedido Magalu. Os dados da Etiqueta serão registrados nas notas do pedido.</small>
		<button class="button button-primary magalu-ticket-button">Gerar Etiqueta</button>
		<input type="hidden" name="local_store_order_id" id="local-store-order-id">
		<script>
			jQuery(document).ready(function($) {
				$('.magalu-ticket-button').on('click', function() {
					$('#local-store-order-id').val('<?php echo esc_html( $order->get_id() ); ?>');
				});
			});
		</script>
		<?php
		wp_nonce_field( 'magalu_shipping_label', 'magalu_label_nonce' );
	}

	/**
	 * Retrieves the order from either a WP_Post object or directly from the order.
	 *
	 * @param mixed $post_or_order The WP_Post object or the order.
	 * @return WC_Order The WooCommerce order object
	 */
	private function get_order_from_mixed( $post_or_order ) {
		return $post_or_order instanceof WP_Post
		? wc_get_order( $post_or_order->ID )
		: $post_or_order;
	}

	/**
	 * Generates a shipping label box for a given order.
	 *
	 * This function checks if the required parameters are set in the $_POST array,
	 * verifies the nonce, retrieves the order using the provided order ID,
	 * generates a shipping label using the order's Magalu order ID, and adds an
	 * order note with the generated label information. If the label generation
	 * fails, an error note is added instead.
	 *
	 * @return void
	 */
	public function generate_shipping_label_box() {
		if ( isset( $_POST['local_store_order_id'], $_POST['magalu_label_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['magalu_label_nonce'] ) ), 'magalu_shipping_label' ) ) {
			$order = wc_get_order(
				sanitize_text_field(
					wp_unslash(
						$_POST['local_store_order_id']
					)
				)
			);

			if ( $order ) {
				$result = $this->api->generate_shipping_label(
					$order->get_meta(
						'_magalu_order_id',
					)
				);

				if ( $result ) {
					$order->add_order_note(
						sprintf(
							/* translators: %1$s: magalu order id, %2$s: tracking code, %3$s: trakking url, %4$s: print url */
							__( 'Etiqueta de entrega gerada com sucesso. <br><br>ID do pedido Magalu: %1$s<br>Código de rastreio: %2$s<br><a href="%3$s" target="_blank">Rastrear pedido</a><br><br><a target="_blank" href="%4$s">Imprimir etiqueta</a>', 'virtuaria-magalu' ),
							$result['Orders'][0]['Order'],
							$result['Orders'][0]['TrackingCode'],
							$result['Orders'][0]['TrackingUrl'],
							$result['Url']
						),
						0,
						true
					);
				} else {
					$order->add_order_note(
						__( 'Erro ao gerar etiqueta de entrega. Tente novamente mais tarde.', 'virtuaria-magalu' ),
						0,
						true
					);
				}
			}
		}
	}

	/**
	 * Update orders status.
	 *
	 * This function retrieve the last magalu orders and
	 * checks if the status has changed. If the status is
	 * 'CANCELED', the local order is updated to 'cancelled'.
	 *
	 * @return void
	 */
	public function update_orders_staus() {
		$this->log->info( 'Updating orders status...' );
		$orders = $this->dao->get_last_magalu_orders();

		if ( $orders ) {
			foreach ( $orders as $order ) {
				$current_order = $this->api->get_order( $order['id'] );
				$this->log->debug(
					'Processing order: ' . wp_json_encode( $current_order )
				);
				if ( isset( $current_order['OrderStatus'] ) ) {
					if ( 'CANCELED' === $current_order['OrderStatus'] ) {
						$local_order = wc_get_order( $order['order_id'] );
						if ( $local_order ) {
							$local_order->update_status(
								'cancelled',
								__( 'Magalu: Este pedido foi cancelado.', 'virtuaria-magalu' )
							);
						}
					}
				}
			}
		}

		$this->log->info( 'Updating orders status finished...' );
	}
}

new Virtuaria_Magalu_Orders();
