<?php
/**
 * Template settings page.
 *
 * @package Virtuaria/Integrations/Magalu
 */

defined( 'ABSPATH' ) || exit;

do_action( 'virtuaria_magalu_save_settings' );

$options = get_option( 'virtuaria_magalu_settings' );

?>
<h1 class="main-title">Virtuaria Magalu</h1>
<span class="desc">Integre seu catálogo de produtos ao Marketplace Magalu e venda mais.</span>
<form action="" method="post" id="mainform" class="main-setting">
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="virtuaria_magalu_email">E-mail</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text">
							<span>E-mail</span>
						</legend>
						<input class="input-text regular-input"
							type="text"
							name="virtuaria_magalu_email"
							id="virtuaria_magalu_email"
							value="<?php echo isset( $options['mail'] ) ? esc_attr( $options['mail'] ) : ''; ?>" />
						<p class="description">
							Informe seu e-mail utilizado na conta do Magalu. Isto é necessário para processar os dados de sua loja.
						</p>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="virtuaria_magalu_cnpj">CNPJ</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text">
							<span>CNPJ</span>
						</legend>
						<input class="input-text regular-input"
							type="text"
							name="virtuaria_magalu_cnpj"
							id="virtuaria_magalu_cnpj"
							value="<?php echo isset( $options['cnpj'] ) ? esc_attr( $options['cnpj'] ) : ''; ?>" >
						<p class="description">
							Informe seu CNPJ utilizado na conta do Magalu. Isto é necessário para processar os dados de sua loja.
						</p>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="virtuaria_magalu">Magalu</label>
				</th>
				<td class="forminp">
					<a href="https://seller.magalu.com/integradoras" target="_blank">
						🔗 Autorizar Integradora Virtuaria
					</a>
					<p class="description">
						Autorize a "Virtuaria - integração woocommerce" no portal de seller magalu para habilitar a integração com sua loja virtual.
					</p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="virtuaria_magalu_autorization">
						Conexão <span class="woocommerce-help-tip"></span>
					</label>
				</th>
				<td class="forminp forminp-auth">
					<?php
					if ( isset( $options['authorization'] ) ) {
						echo '<span class="connected"><strong>Status: <span class="status">Conectado.</span></strong></span>';
						echo '<a href="#" class="auth button-primary connected">Desconectar <img src="' . esc_url( VIRTUARIA_MAGALU_URL ) . 'admin/images/conectado.svg" alt="Desconectar" /></a>';
					} else {
						echo '<span class="disconnected"><strong>Status: <span class="status">Desconectado.</span></strong></span>';
						echo '<a href="#" class="auth button-primary disconnected">Conectar <img src="' . esc_url( VIRTUARIA_MAGALU_URL ) . 'admin/images/conectar.png" alt="Conectar" /></a>';
					}
					?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="virtuaria_magalu_fee">Ajustar Preços (%)</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text">
							<span>Ajustar Preços(%)</span>
						</legend>
						<input class="input-text regular-input"
							type="number"
							step="0.01"
							name="virtuaria_magalu_fee"
							id="virtuaria_magalu_fee"
							value="<?php echo isset( $options['fee'] ) ? esc_attr( $options['fee'] ) : ''; ?>" >
						<p class="description">
							Define um percentual extra aplicado aos preços de produtos enviados ao magalu.
						</p>
					</fieldset>
				</td>
			</tr>
		</tbody>
	</table>

	<input type="hidden" name="virtuaria_magalu_authorization" id="authorization">
	<button name="save" class="button-primary woocommerce-save-button" type="submit" value="Salvar alterações">Salvar alterações</button>
	<?php wp_nonce_field( 'setup_virtuaria_module', 'setup_nonce' ); ?>
</form>

<hr class="virt-separator"/>

<div class="disclaimer">
	<h3>🔊 Contato e Suporte</h3>
	Caso encontre algum problema técnico ou dificuldades no uso do plugin, entre em contato com nossa equipe de suporte. Estamos disponíveis para ajudar via e-mail ou WhatsApp:<br/>
	E-mail: <a href="mailto:pluginmagalu@virtuaria.com.br">pluginmagalu@virtuaria.com.br</a><br/>
	WhatsApp: +55 79 99931213
</div>

<hr class="virt-separator"/>

<div class="faq">
<h2><i class="dashicons dashicons-menu-alt3"></i> Dúvidas Frequentes</h2>
	<ol class="frequently-questions">
		<li>
			✅ Produtos e Pedidos
			<ul class="products-orders faq">
				<li>- Após realizar a conexão com a Magalu, será possível exportar produtos para o Magalu Marketplace e importar pedidos.</li>
				<li>- Atualizações de produtos normalmente passam por verificação de conformidade com as políticas da Magalu;</li>
				<li>- Somente produtos com SKU podem ser enviados ao Magalu.</li>
			</ul>
		</li>
		<li>
			✅ Sincronização dos Dados 
			<ul class="sync faq">
				<li> - A sincronização de pedidos ocorre automaticamente a cada 8 horas ou manualmente através do botão "Sincronizar pedidos do Magalu";</li>
				<li> - A sincronização dos produtos exportados ocorre toda vez que o produto é alterado;</li>
				<li> - A autorização é obrigatória e realizada no portal do Magalu.</li>
			</ul>
		</li>
	</ol>
</div>
