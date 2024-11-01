<?php
/**
 * Handle register product to Magalu.
 *
 * @package Virtuaria/Integrations/Marketplace/Magalu.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handle product.
 */
class Virtuaria_Magalu_Product {
	use Virtuaria_Magalu_Product_Trait;

	/**
	 * Api access.
	 *
	 * @var Virtuaria_Magalu_Product_API
	 */
	private $api;

	/**
	 * Initialize functions.
	 */
	public function __construct() {
		$this->api = new Virtuaria_Magalu_Product_API();
		add_action( 'add_meta_boxes', array( $this, 'sync_magalu_product_box' ) );
		add_action( 'save_post', array( $this, 'save_product_magalu_info' ), 25, 3 );
		add_action( 'save_post', array( $this, 'send_product_to_magalu' ), 35 );
		add_action( 'wp_ajax_multi_add_magalu_products', array( $this, 'send_multi_product_to_magalu' ), 35 );
		add_filter( 'manage_edit-product_columns', array( $this, 'magalu_sync_column' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'magalu_sync_column_content' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'update_product_to_magalu' ) );
		add_action( 'save_post', array( $this, 'update_product_to_magalu' ), 30 );
		add_action( 'woocommerce_product_bulk_edit_end', array( $this, 'inline_edit_magalu_product_sync' ) );
		add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_magalu_product_sync' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_style_scripts' ) );
	}

	/**
	 * Create product in magalu catalog.
	 */
	public function send_multi_product_to_magalu() {
		if ( isset( $_POST['magalu_product_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['magalu_product_nonce'] ) ), 'sync_magalu_product' ) ) {
			$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['post_ids'] ) ) : array();
			if ( $post_ids && is_array( $post_ids ) ) {
				foreach ( $post_ids as $post_id ) {
					$this->send_product_to_magalu( $post_id );
				}
			}
		}
		wp_die();
	}

	/**
	 * Create metabox.
	 */
	public function sync_magalu_product_box() {
		add_meta_box(
			'magalu-product-sync',
			'Integração Magalu',
			array( $this, 'magalu_product_box_content' ),
			'product',
			'side'
		);
	}

	/**
	 * Content from metabox.
	 *
	 * @param WP_Post $post the post.
	 */
	public function magalu_product_box_content( $post ) {
		$synced    = get_post_meta( $post->ID, '_magalu_product_synced', true );
		$warranty  = get_post_meta( $post->ID, '_magalu_product_warranty_time', true );
		$brand     = get_post_meta( $post->ID, '_magalu_product_brand', true );
		$nbmorigin = get_post_meta( $post->ID, '_magalu_product_nbmorigin', true );

		if ( $synced ) {
			echo '<p>O produto já está sendo sincronizado com o Magalu</p>';
		} else {
			?>
			<p>
				<input type="checkbox" name="sync_magalu_product" id="sync-magalu-product" value="yes"/>
				<label for="sync-magalu-product">
					Marque para que este produto seja sincronizado com o Magalu.
				</label>
			</p>
			<?php
			wp_nonce_field( 'sync_magalu_product', 'magalu_product_nonce' );
		}
		?>
		<label for="warranty-time">Tempo de Garantia (meses)</label>
		<input type="number" name="warranty_time" id="warranty-time" value="<?php echo esc_attr( $warranty ); ?>"/>
		<label for="brand">Marca</label>
		<input type="text" name="magalu_brand" id="magalu-brand" value="<?php echo esc_attr( $brand ); ?>"/>
		<input type="checkbox" name="nbmorigin" id="nbmorigin" <?php checked( 'yes', $nbmorigin ); ?> value="yes"/>
		<label for="nbmorigin">Produto importado?</label>
		<?php
	}

	/**
	 * Add new column.
	 *
	 * @param array $columns the columns.
	 */
	public function magalu_sync_column( $columns ) {
		$columns['magalu_sync'] = 'Magalu';
		return $columns;
	}

	/**
	 * Add new column.
	 *
	 * @param string $column the columns.
	 * @param int    $post_id the post id.
	 */
	public function magalu_sync_column_content( $column, $post_id ) {
		if ( 'magalu_sync' === $column ) {
			if ( get_post_meta( $post_id, '_magalu_product_synced', true ) ) {
				echo 'Sincronizado';
			} else {
				echo 'Não Sincronizado';
			}
		}
	}

	/**
	 * Quick edit field.
	 *
	 * @param String $column_name current column name.
	 * @param String $post_type type from current post in edit.
	 */
	public function quick_edit_magalu_product_sync( $column_name, $post_type ) {
		if ( 'magalu_sync' !== $column_name || 'product' !== $post_type ) {
			return;
		}

		echo '<fieldset class="inline-edit-col-left" style="margin-top: 20px">';
		$this->inline_edit_magalu_product_sync();
		echo '</fieldset>';
	}

	/**
	 * Diaply inline information about search_filter.
	 */
	public function inline_edit_magalu_product_sync() {
		?>
			<div class="inline-edit-col" style="clear: left;">
				<input type="checkbox" name="sync_magalu_product" id="sync-magalu-product" value="yes"/>
				<label for="sync-magalu-product" style="display: inline-block;vertical-align:baseline;">
					Marque para que este produto seja sincronizado com o Magalu.
				</p>
			</div>
		<?php
		wp_nonce_field( 'sync_magalu_product', 'magalu_product_nonce' );
	}

	/**
	 * Create product in magalu catalog.
	 *
	 * @param int $post_id the post id.
	 */
	public function send_product_to_magalu( $post_id ) {
		if ( 'product' === get_post_type( $post_id )
			&& ! get_post_meta( $post_id, '_magalu_product_synced', true )
			&& isset( $_POST['sync_magalu_product'] )
			&& isset( $_POST['magalu_product_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['magalu_product_nonce'] ) ), 'sync_magalu_product' ) ) {

			$product = wc_get_product( $post_id );
			if ( $product
				&& ! $product->is_virtual()
				&& ! $product->is_downloadable() ) {
				$success = $this->api->add_product(
					$this->get_product_data( $product )
				);

				if ( $success ) {
					if ( $product->is_type( 'variable' ) ) {
						foreach ( $product->get_children() as $child_id ) {
							$variation = wc_get_product( $child_id );

							if ( $variation
								&& ! $variation->is_virtual()
								&& ! $variation->is_downloadable() ) {
								$child_sended = $this->api->add_sku(
									$this->get_sku_data( $variation )
								);
								if ( $child_sended ) {
									update_post_meta( $child_id, '_magalu_product_synced', true );
								}
							}
						}
					} else {
						$success = $this->api->add_sku(
							$this->get_sku_data( $product )
						);
					}
				}

				if ( $success ) {
					update_post_meta( $post_id, '_magalu_product_synced', true );
				}
			}
		}
	}

	/**
	 * Update product in magalu catalog.
	 *
	 * @param int $post_id the post id.
	 */
	public function update_product_to_magalu( $post_id ) {
		if ( 'product' === get_post_type( $post_id )
			&& get_post_meta( $post_id, '_magalu_product_synced', true ) ) {
			$product = wc_get_product( $post_id );
			if ( $product
				&& ! $product->is_virtual()
				&& ! $product->is_downloadable() ) {
				$success = $this->api->update_product(
					$this->get_product_data( $product )
				);

				if ( $success ) {
					if ( $product->is_type( 'variable' ) ) {
						foreach ( $product->get_children() as $child_id ) {
							$variation = wc_get_product( $child_id );

							if ( $variation
								&& ! $variation->is_virtual()
								&& ! $variation->is_downloadable() ) {
								if ( get_post_meta( $child_id, '_magalu_product_synced', true ) ) {
									$this->api->update_sku(
										$this->get_sku_data( $variation )
									);
								} else {
									$child_sended = $this->api->add_sku(
										$this->get_sku_data( $variation )
									);
									if ( $child_sended ) {
										update_post_meta( $child_id, '_magalu_product_synced', true );
									}
								}
							}
						}
					} else {
						$this->api->update_sku(
							$this->get_sku_data( $product )
						);
					}
				}
			}
		}
	}

	/**
	 * Save magalu info.
	 *
	 * @param int     $post_id post id.
	 * @param wp_post $post    post.
	 * @param bool    $update  true if is update or false otherwise.
	 */
	public function save_product_magalu_info( $post_id, $post, $update ) {
		if ( 'product' === get_post_type( $post_id )
			&& isset( $_POST['magalu_product_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['magalu_product_nonce'] ) ), 'sync_magalu_product' ) ) {

			if ( isset( $_POST['warranty_time'] ) ) {
				update_post_meta(
					$post_id,
					'_magalu_product_warranty_time',
					sanitize_text_field( wp_unslash( $_POST['warranty_time'] ) )
				);
			}

			if ( isset( $_POST['magalu_brand'] ) ) {
				update_post_meta(
					$post_id,
					'_magalu_product_brand',
					sanitize_text_field( wp_unslash( $_POST['magalu_brand'] ) )
				);
			}

			if ( isset( $_POST['_magalu_product_nbmorigin'] ) ) {
				update_post_meta(
					$post_id,
					'_magalu_product_nbmorigin',
					'yes'
				);
			} else {
				delete_post_meta( $post_id, '_magalu_product_nbmorigin' );
			}
		}
	}

	/**
	 * Get sku attributes.
	 *
	 * @param wc_product $product product.
	 * @return array
	 */
	private function get_sku_attributes( $product ) {
		$sku_attributes = array();
		if ( $product->get_parent_id() ) {
			$attributes = wc_get_formatted_variation( $product, true );
			if ( $attributes && is_string( $attributes ) ) {
				$attributes = explode( ', ', $attributes );
				if ( $attributes ) {
					foreach ( $attributes as $attribute ) {
						$attribute = explode( ': ', $attribute );
						if ( $attribute ) {
							$sku_attributes[] = array(
								'name'  => isset( $attribute[0] )
									? $attribute[0]
									: '',
								'value' => isset( $attribute[1] )
									? $attribute[1]
									: '',
							);
						}
					}
				}
			}
		} else {
			$sku_attributes = $this->get_attributes( $product );
		}

		return $sku_attributes;
	}

	/**
	 * Get product data to magalu.
	 *
	 * @param wc_product $product product.
	 * @return array
	 */
	private function get_product_data( $product ) {
		$data = array();

		if ( $product ) {
			$data['idProduct']    = strval( $product->get_id() );
			$data['name']         = $product->get_title();
			$data['code']         = strval( $product->get_id() );
			$data['brand']        = $product->get_meta( '_magalu_product_brand' );
			$data['nbmOrigin']    = $product->get_meta( '_magalu_product_nbmorigin' ) ? 1 : 0;
			$data['warrantyTime'] = $product->get_meta( '_magalu_product_warranty_time' ) ?? 3; // 3 months from warranty default.
			$data['active']       = 'publish' === $product->get_status();

			$data['categories'] = $this->get_categories( $product );

			$data['attributes'] = $this->get_attributes( $product );
		}

		return $data;
	}

	/**
	 * Get product categories.
	 *
	 * @param wc_product $product product.
	 * @return array
	 */
	private function get_categories( $product ) {
		$categories = array();

		if ( $product->get_category_ids() ) {
			foreach ( $product->get_category_ids() as $category_id ) {
				$term = get_term_by(
					'id',
					$category_id,
					'product_cat'
				);

				if ( $term ) {
					$categories[] = array(
						'Id'       => strval( $category_id ),
						'Name'     => $term->name,
						'ParentId' => $term->parent ? strval( $term->parent ) : '',
					);
				}
			}
		}

		return $categories;
	}

	/**
	 * Get product attributes.
	 *
	 * @param wc_product $product product.
	 * @return array
	 */
	private function get_attributes( $product ) {
		$attributes = array();
		if ( $product->get_attributes() ) {
			foreach ( $product->get_attributes() as $taxonomy => $attribute ) {
				if ( is_object( $attribute ) && $attribute->get_options() ) {
					foreach ( $attribute->get_options() as $option ) {
						$attributes[] = array(
							'name'  => ucwords(
								wc_attribute_label( $attribute->get_name() )
							),
							'value' => $option,
						);
					}
				}
			}
		}
		return $attributes;
	}

	/**
	 * Add styles and scripts.
	 *
	 * @param string $page page identifier.
	 */
	public function admin_style_scripts( $page ) {
		if ( isset( $_GET['post_type'] )
			&& 'product' === $_GET['post_type']
			&& 'edit.php' === $page ) {
			wp_enqueue_style(
				'virtuaria-magalu-product-list',
				VIRTUARIA_MAGALU_URL . 'admin/css/product-list.css',
				array(),
				filemtime( VIRTUARIA_MAGALU_PATH . 'admin/css/product-list.css' )
			);
		}

		if ( isset( $_GET['post'] )
			&& 'product' === get_post_type( sanitize_text_field( wp_unslash( $_GET['post'] ) ) )
			&& 'post.php' === $page ) {
			wp_enqueue_style(
				'virtuaria-magalu-product',
				VIRTUARIA_MAGALU_URL . 'admin/css/product.css',
				array(),
				filemtime( VIRTUARIA_MAGALU_PATH . 'admin/css/product.css' )
			);
		}
	}
}

new Virtuaria_Magalu_Product();
