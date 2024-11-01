<?php
/**
 * Template order list and edit.
 *
 * @package Virtuaria/Integrations/Marketplace/Magalu.
 */

defined( 'ABSPATH' ) || exit;

if ( isset( $data ) && $data ) {
	if ( isset( $data['billing'] ) ) {
		$billing  = json_decode( $data['billing'], true );
	}

	if ( isset( $data['shipping'] ) ) {
		$shipping = json_decode( $data['shipping'], true );
	}

	if ( isset( $data['delivery_date'] ) ) {
		$data['delivery_date'] = str_replace(
			' 00:00:00',
			'',
			$data['delivery_date']
		);
	}
}

?>

<h1>🛒 Magalu Pedidos</h1>
<p class="mag-subtitle">Lista de pedidos do Magazine Luiza importados na loja Virtuaria.</p>
<?php
if ( isset( $message ) ) {
	echo wp_kses_post( $message );
}
if ( isset( $_GET['order_id'] ) ) :
	?>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=magalu_orders' ) ); ?>" class="back-order-list">
		🔙 Volta para lista de pedidos
	</a>
	<div id="order-flux">

		<form method="post" id="form1">
			<h2>🧾 Faturamento</h2>
			<label for="nfNumber">Nº da NF:</label>
			<input type="text" id="nfNumber" name="InvoicedNumber" required value="<?php if ( isset( $billing['InvoicedNumber'] ) ) { echo esc_attr( $billing['InvoicedNumber'] ); } ?>"><br>

			<label for="nfKey">Chave da NF:</label>
			<input type="text" id="nfKey" name="InvoicedKey" required value="<?php if ( isset( $billing['InvoicedKey'] ) ) { echo esc_attr( $billing['InvoicedKey'] ); } ?>"><br>

			<label for="nfSeries">Série da NF:</label>
			<input type="text" id="nfSeries" name="InvoicedLine" required value="<?php if ( isset( $billing['InvoicedLine'] ) ) { echo esc_attr( $billing['InvoicedLine'] ); } ?>"><br>

			<label for="nfDate">Data de Emissão da NF:</label>
			<input type="date" id="nfDate" name="InvoicedIssueDate" required value="<?php if ( isset( $billing['InvoicedIssueDate'] ) ) { echo esc_attr( $billing['InvoicedIssueDate'] ); } ?>"><br>
			<small><b>Atenção:</b> A data de emissão da NF não poderá ser alterada.</small>

			<label for="xmlDanfe">XML da Danfe:</label>
			<textarea name="invoicedDanfeXml" id="xmlDanfe"><?php if ( isset( $billing['invoicedDanfeXml'] ) ) { echo esc_html( $billing['invoicedDanfeXml'] ); } ?></textarea>

			<div class="btn-container">
				<?php
				wp_nonce_field( 'update_nf', 'nf_nonce' );
				if ( ! isset( $shipping['ShippedCarrierDate'] ) ) {
					if ( isset( $billing['InvoicedNumber'] ) ) :
						?>
						<input type="submit" value="Atualizar" class="button-primary update" />
						<?php
					else :
						?>
						<input type="submit" value="Próxima Etapa" class="button-primary" />
						<?php
					endif;
				}
				?>
			</div>
			<input type="hidden" name="operation" value="billing" />
		</form>

		<form method="post" id="form2" class="hidden">
			<h2>🚚 Envio</h2>
			<label for="shippingDate">Data de Envio:</label>
			<input type="date" id="shippingDate" name="ShippedCarrierDate" required value="<?php if ( isset( $shipping['ShippedCarrierDate'] ) ) { echo esc_attr( $shipping['ShippedCarrierDate'] ); } ?>"><br>

			<label for="trackingCode">Código de Rastreio:</label>
			<input type="text" id="trackingCode" name="ShippedTrackingProtocol" required value="<?php if ( isset( $shipping['ShippedTrackingProtocol'] ) ) { echo esc_attr( $shipping['ShippedTrackingProtocol'] ); } ?>"><br>

			<label for="estimatedDeliveryDate">Data Estimada de Entrega:</label>
			<input type="date" id="estimatedDeliveryDate" name="ShippedEstimatedDelivery" required value="<?php if ( isset( $shipping['ShippedEstimatedDelivery'] ) ) { echo esc_attr( $shipping['ShippedEstimatedDelivery'] ); } ?>"><br>

			<label for="ShippedCarrierName">Transportadora:</label>
			<input type="text" id="ShippedCarrierName" name="ShippedCarrierName" required value="<?php if ( isset( $shipping['ShippedCarrierName'] ) ) { echo esc_attr( $shipping['ShippedCarrierName'] ); } ?>"><br>

			<div class="btn-container">
				<?php
				wp_nonce_field( 'update_shipping', 'shipping_nonce' );
				if ( ! isset( $shipping['ShippedCarrierDate'] ) ) :
					?>
					<input type="submit" value="Próxima Etapa" class="button-primary" />
					<?php
				endif;
				?>
			</div>
			<input type="hidden" name="operation" value="shipping" />
		</form>

		<form method="post" id="form3" class="hidden">
			<h2>📬 Entrega</h2>
			<label for="deliveryDate">Data de Entrega:</label>
			<input type="date" id="deliveryDate" name="DeliveredDate" required value="<?php if ( isset( $data['delivery_date'] ) ) { echo esc_attr( $data['delivery_date'] ); } ?>"><br>

			<div class="btn-container">
				<?php
				wp_nonce_field( 'update_delivery', 'delivery_nonce' );
				if ( isset( $shipping['ShippedCarrierDate'] )
					&& ( ! isset( $data['delivery_date'] ) || ! $data['delivery_date'] ) ) :
					?>
					<input type="submit" value="Enviar" class="button-primary" />
					<?php
				endif;
				?>
			</div>
			<input type="hidden" name="operation" value="delivery" />
		</form>
	</div>
	<h2 class="logs">🕒 Histórico do Pedido</h2>
	<table id="magalu-order-logs" class="display" width="100%"></table>
	<?php
else :
	?>
	<table id="magalu-orders" class="display" width="100%"></table>
	<?php
endif;
