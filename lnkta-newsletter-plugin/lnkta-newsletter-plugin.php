<?php
/**
 * Plugin Name: LNKTA Newsletter Plugin
 * Description: Pré-visualização, exportação HTML e criação de rascunhos Brevo com os campos ACF existentes.
 * Version: 2.5.0
 * Update URI: https://updates.lnkta.invalid/lnkta-newsletter-plugin/
 * Author: LNKTA
 * Requires at least: 5.8
 * Requires PHP: 7.2
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Reuses the existing newsletter CPT and ACF fields. */
require_once __DIR__ . '/includes/trait-newsletter-design.php';
require_once __DIR__ . '/includes/trait-newsletter-images.php';
final class LNKTA_Newsletter_Plugin {
	use LNKTA_Newsletter_Design;
	use LNKTA_Newsletter_Images;
	const POST_TYPE = 'newsletter';
	const ACTION = 'petfeelings_newsletter_export';
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'design_assets' ) );
		add_action( 'save_post_newsletter', array( $this, 'save_footer_content' ) );
		add_action( 'admin_menu', array( $this, 'settings_menu' ) );
		add_action( 'admin_post_lnkta_brevo_test', array( $this, 'test_brevo_connection' ) );
		add_action( 'admin_post_lnkta_newsletter_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_lnkta_brevo_draft', array( $this, 'create_draft' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_export_box' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_export' ) );
	}
	private function value( $name, $id ) {
		if ( function_exists( 'get_field' ) ) { $value = get_field( $name, $id ); if ( null !== $value && false !== $value && '' !== $value ) { return $value; } }
		return get_post_meta( $id, $name, true );
	}
	public function add_export_box() { $this->add_footer_box(); add_meta_box( 'petfeelings-newsletter-export', 'Pré-visualização e HTML', array( $this, 'render_export_box' ), self::POST_TYPE, 'side', 'high' ); }
	public function render_export_box( $post ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) { return; }
		$url = add_query_arg( array( 'action' => self::ACTION, 'newsletter_id' => absint( $post->ID ), 'view' => 'preview' ), admin_url( 'admin-post.php' ) );
		$url = wp_nonce_url( $url, self::ACTION . '_' . $post->ID );
		$html_url = add_query_arg( 'view', 'html', html_entity_decode( $url ) );
		echo '<p>Usa exclusivamente os campos ACF já configurados nesta newsletter.</p>';
		echo '<p><a class="button button-primary" target="_blank" href="' . esc_url( $url ) . '">Pré-visualizar newsletter</a></p>';
		echo '<p><a class="button" target="_blank" href="' . esc_url( $html_url ) . '">Descarregar código HTML</a></p>';
		$this->brevo_box( $post );
	}
	/** Supports ACF Post Object, Relationship and ID return formats. */
	private function post_ids( $value ) {
		if ( is_object( $value ) && isset( $value->ID ) ) { return array( absint( $value->ID ) ); }
		if ( is_numeric( $value ) ) { return array( absint( $value ) ); }
		if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) { $id = url_to_postid( $value ); return $id ? array( $id ) : array(); }
		if ( ! is_array( $value ) ) { return array(); }
		if ( isset( $value['ID'] ) ) { return array( absint( $value['ID'] ) ); }
		if ( isset( $value['url'] ) ) { return $this->post_ids( $value['url'] ); }
		$ids = array();
		foreach ( $value as $item ) { $ids = array_merge( $ids, $this->post_ids( $item ) ); }
		return array_values( array_filter( array_unique( $ids ) ) );
	}
	private function display_date( $value ) {
		$value = trim( (string) ( is_array( $value ) ? reset( $value ) : $value ) ); if ( ! $value ) { return ''; }
		foreach ( array( 'Ymd', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d' ) as $format ) { $date = DateTime::createFromFormat( '!' . $format, $value ); if ( $date && $date->format( $format ) === $value ) { return $date->format( 'd/m/Y' ); } }
		return sanitize_text_field( $value );
	}
	/** Accept standard ACF colour-picker values and fall back to a safe colour. */
	private function color( $value, $fallback ) {
		$color = sanitize_hex_color( (string) $value );
		return $color ? $color : $fallback;
	}
	private function article_html( $id, $featured ) {
		if ( ! $id || ! get_post( $id ) ) { return ''; }
		$title = get_the_title( $id ); $link = get_permalink( $id ); $excerpt = get_the_excerpt( $id ); $image = $this->email_image_url( get_post_thumbnail_id( $id ), $featured ? 'large' : 'medium_large' );
		$accent = $this->color( $this->setting( 'accent_color' ), '#3f6d54' );
		$html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:0 0 28px;">';
		if ( $image ) { $html .= '<tr><td><a href="' . esc_url( $link ) . '"><img src="' . esc_url( $image ) . '" alt="' . esc_attr( $title ) . '" width="' . ( $featured ? '600' : '250' ) . '" style="display:block;width:100%;height:auto;border:0;"></a></td></tr>'; }
		$html .= '<tr><td style="padding:16px 0 0;font-family:Arial,sans-serif;"><h2 style="margin:0 0 9px;font-size:' . ( $featured ? '26' : '20' ) . 'px;line-height:1.25;color:#20382e;">' . esc_html( $title ) . '</h2>';
		if ( $excerpt ) { $html .= '<p style="margin:0 0 12px;font-size:15px;line-height:1.5;color:#4b584f;">' . esc_html( wp_trim_words( wp_strip_all_tags( $excerpt ), $featured ? 40 : 24 ) ) . '</p>'; }
		return $html . '<a href="' . esc_url( $link ) . '" style="font-size:13px;font-weight:bold;color:' . esc_attr( $accent ) . ';text-decoration:none;">LER MAIS →</a></td></tr></table>';
	}
	private function newsletter_html( $newsletter_id, $for_brevo = false ) {
		$number = sanitize_text_field( (string) $this->value( 'newsletter_number', $newsletter_id ) );
		$year = sanitize_text_field( (string) $this->value( 'newsletter_year', $newsletter_id ) );
		$legacy_bg = $this->color( $this->value( 'newsletter_background_color', $newsletter_id ), '#20382e' );
		$legacy_fg = $this->color( $this->value( 'newsletter_foreground_color', $newsletter_id ), '#ffffff' );
		$header_bg = $this->color( $this->setting( 'header_background' ), $legacy_bg );
		$header_fg = $this->color( $this->setting( 'header_text' ), $legacy_fg );
		$footer_bg = $this->color( $this->setting( 'footer_background' ), $legacy_bg );
		$footer_fg = $this->color( $this->setting( 'footer_text' ), $legacy_fg );
		$accent = $this->color( $this->setting( 'accent_color' ), '#3f6d54' );
		$posts = array();
		for ( $number_field = 1; $number_field <= 5; $number_field++ ) {
			foreach ( $this->post_ids( $this->value( 'newsletter_post' . $number_field, $newsletter_id ) ) as $id ) {
				if ( ! in_array( $id, $posts, true ) ) { $posts[] = $id; }
			}
		}
		$podcast_ids = $this->post_ids( $this->value( 'newsletter_podcast', $newsletter_id ) );
		$podcast = ! empty( $podcast_ids ) ? reset( $podcast_ids ) : 0;
		$intro_content = $this->format_content( get_post_field( 'post_content', $newsletter_id ) );
		$footer_content = $this->format_content( $this->value( 'newsletter_footer_content', $newsletter_id ) );
		$label = implode( ' · ', array_filter( array( $number ? 'NEWSLETTER N.º ' . $number : '', $year ) ) );
		$brand = $this->setting( 'brand', get_bloginfo( 'name' ) );
		$logo_id = absint( $this->setting( 'email_logo_id', $this->setting( 'logo_id' ) ) );
		$logo_url = $logo_id ? wp_get_attachment_url( $logo_id ) : '';
		$logo = $logo_url ? '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $brand ) . '" width="180" style="display:block;width:180px;max-width:100%;height:auto;border:0;">' : '<span style="font-size:26px;font-weight:700;letter-spacing:1px;">' . esc_html( $brand ) . '</span>';
		$html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>' . esc_html( get_the_title( $newsletter_id ) ) . '</title></head><body style="margin:0;padding:0;background:#f5f4ef;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f4ef;"><tr><td align="center" style="padding:24px 10px;"><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;"><tr><td style="padding:28px 32px;background:' . esc_attr( $header_bg ) . ';color:' . esc_attr( $header_fg ) . ';font-family:Arial,sans-serif;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="left" valign="middle" width="60%"><a href="' . esc_url( home_url( '/' ) ) . '" style="color:' . esc_attr( $header_fg ) . ';text-decoration:none;">' . $logo . '</a></td><td align="right" valign="middle" style="font:12px/1.5 Arial,sans-serif;color:' . esc_attr( $header_fg ) . ';">' . esc_html( $label ) . '</td></tr></table></td></tr><tr><td style="padding:32px;">' . ( $intro_content ? '<div class="lnkta-newsletter-intro" style="margin:0 0 28px;font-family:Arial,sans-serif;font-size:16px;line-height:1.55;color:#4b584f;">' . $intro_content . '</div>' : '' );
		foreach ( $posts as $index => $id ) { if ( 1 === $index ) { $html .= '<div style="padding-top:6px;border-top:1px solid #d9dfd9;"><h3 style="font:700 17px Arial,sans-serif;color:#20382e;margin:24px 0 18px;">O QUE TEMOS PARA SI</h3></div>'; } $html .= $this->article_html( $id, 0 === $index ); }
		if ( $podcast && get_post( $podcast ) ) { $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#e7efe8;"><tr><td style="padding:22px;font-family:Arial,sans-serif;"><div style="font-size:12px;font-weight:bold;color:' . esc_attr( $accent ) . ';">CONVERSAS EM VOZ ALTA</div><h2 style="margin:8px 0;font-size:21px;line-height:1.25;color:#20382e;">' . esc_html( get_the_title( $podcast ) ) . '</h2><a href="' . esc_url( get_permalink( $podcast ) ) . '" style="font-size:13px;font-weight:bold;color:' . esc_attr( $accent ) . ';text-decoration:none;">OUVIR / VER EPISÓDIO →</a></td></tr></table>'; }
		if ( $footer_content ) { $html .= '<div class="lnkta-newsletter-footer-content" style="margin:28px 0 0;font:16px/1.55 Arial,sans-serif;color:#4b584f;">' . $footer_content . '</div>'; }
		$html .= '</td></tr><tr><td style="padding:22px 32px;background:' . esc_attr( $footer_bg ) . ';color:' . esc_attr( $footer_fg ) . ';text-align:left;font:15px/1.55 Arial,sans-serif;">';
		$html .= '<div style="text-align:center;font:12px/1.5 Arial,sans-serif;"><a href="' . esc_url( home_url( '/' ) ) . '" style="color:' . esc_attr( $footer_fg ) . ';text-decoration:none;">' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</a>';
		if ( $this->setting( 'address' ) ) { $html .= '<p>' . esc_html( $this->setting( 'address' ) ) . '</p>'; }
		$html .= '<p class="lnkta-newsletter-unsubscribe">' . $this->unsubscribe_html( $footer_fg, $for_brevo ) . '</p></div>';
		return $html . '</td></tr></table></td></tr></table></body></html>';
	}

	private function setting( $name, $default = '' ) {
		$options = get_option( 'lnkta_newsletter_settings', array() );
		return isset( $options[$name] ) && '' !== $options[$name] ? $options[$name] : $default;
	}
	private function api_key() {
		return trim( (string) ( defined( 'LNKTA_BREVO_API_KEY' ) ? LNKTA_BREVO_API_KEY : $this->setting( 'api_key' ) ) );
	}
	public function settings_menu() {
		add_options_page( 'LNKTA Newsletter Plugin', 'LNKTA Newsletter', 'manage_options', 'lnkta-newsletter', array( $this, 'settings_page' ) );
	}
	private function api( $path, $method = 'GET', $body = null ) {
		if ( ! $this->api_key() ) { return new WP_Error( 'missing_key', 'Introduza a chave API Brevo nas definições.' ); }
		$args = array( 'method' => $method, 'timeout' => 25, 'redirection' => 0, 'headers' => array( 'api-key' => $this->api_key(), 'Accept' => 'application/json', 'Content-Type' => 'application/json' ) );
		if ( null !== $body ) { $args['body'] = wp_json_encode( $body ); }
		$response = wp_remote_request( 'https://api.brevo.com/v3/' . $path, $args );
		if ( is_wp_error( $response ) ) { return new WP_Error( 'network', 'Não foi possível contactar Brevo: ' . $this->safe_brevo_message( $response->get_error_message() ) ); }
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$message = 'Brevo recusou o pedido (HTTP ' . absint( $code ) . ').';
			$detail = is_array( $data ) && isset( $data['message'] ) && is_string( $data['message'] ) ? $this->safe_brevo_message( $data['message'] ) : '';
			if ( $detail ) { $message .= ' ' . $detail; }
			if ( preg_match( '/(?:unrecogni[sz]ed|unauthori[sz]ed|unknown|blocked|not authori[sz]ed).*\bip\b|\bip\b.*(?:unrecogni[sz]ed|unauthori[sz]ed|unknown|blocked|not authori[sz]ed)/i', $detail ) ) {
				$message .= ' Autorize o IP de saída deste alojamento em Brevo → Segurança → IPs autorizados (API).';
			} elseif ( 401 === $code ) {
				$message .= ' Verifique a chave API v3 e os IPs autorizados. HTTP 401 não confirma, por si só, que a chave está errada.';
			} elseif ( 403 === $code ) { $message .= ' Verifique permissões da chave, estado da conta e restrições de IP.'; }
			elseif ( 429 === $code ) { $message .= ' Limite de pedidos atingido. Tente mais tarde.'; }
			return new WP_Error( $code >= 400 && $code < 500 ? 'rejected' : 'brevo', $message );
		}
		if ( ! is_array( $data ) ) { return new WP_Error( 'invalid_response', 'Brevo respondeu com dados inválidos. Verifique se existe um proxy ou filtro no alojamento.' ); }
		return $data;
	}
	private function safe_brevo_message( $message ) {
		$message = (string) $message;
		$key = $this->api_key();
		if ( $key ) { $message = str_replace( $key, '[chave ocultada]', $message ); }
		$message = preg_replace( '/(?:xkeysib|xsmtpsib)-[a-zA-Z0-9_-]+/i', '[chave ocultada]', $message );
		return substr( sanitize_text_field( $message ), 0, 1000 );
	}
	private function connection_test_box() {
		$last = get_option( 'lnkta_newsletter_brevo_test', array() );
		if ( ! empty( $last['message'] ) ) {
			echo '<div class="notice ' . ( ! empty( $last['success'] ) ? 'notice-success' : 'notice-error' ) . '"><p><strong>Último teste Brevo (' . esc_html( isset( $last['time'] ) ? $last['time'] : '' ) . ' UTC):</strong> ' . esc_html( $this->safe_brevo_message( $last['message'] ) ) . '</p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="lnkta_brevo_test">';
		wp_nonce_field( 'lnkta_brevo_test' );
		echo '<p><button class="button" type="submit">Testar ligação Brevo</button></p><p class="description">Usa a chave já guardada e testa apenas a leitura das listas. Guarde alterações à chave antes de testar. Não cria campanhas nem envia emails.</p></form>';
	}
	public function test_brevo_connection() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.', '', array( 'response' => 403 ) ); }
		check_admin_referer( 'lnkta_brevo_test' );
		$result = $this->api( 'contacts/lists?limit=1&offset=0' );
		$success = ! is_wp_error( $result ) && isset( $result['lists'] ) && is_array( $result['lists'] );
		if ( is_wp_error( $result ) ) { $message = $result->get_error_message(); }
		elseif ( ! $success ) { $message = 'Resposta inesperada: Brevo não devolveu uma lista válida.'; }
		else {
			$count = isset( $result['count'] ) ? absint( $result['count'] ) : count( $result['lists'] );
			$message = 'Ligação válida. Listas encontradas: ' . $count . '.';
			if ( 0 === $count ) { $message .= ' Crie uma lista de contactos no Brevo e volte a carregar as definições.'; }
		}
		update_option( 'lnkta_newsletter_brevo_test', array( 'success' => $success, 'message' => $this->safe_brevo_message( $message ), 'time' => gmdate( 'Y-m-d H:i:s' ) ), false );
		wp_safe_redirect( admin_url( 'options-general.php?page=lnkta-newsletter' ) ); exit;
	}

	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		echo '<div class="wrap"><h1>LNKTA Newsletter Plugin</h1><p>Cria apenas rascunhos. O envio e os testes são feitos no Brevo.</p>';
		$this->connection_test_box();
		if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success"><p>Definições guardadas.</p></div>'; }
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="lnkta_newsletter_settings">';
		wp_nonce_field( 'lnkta_newsletter_settings' );
		echo '<table class="form-table"><tr><th>Chave API Brevo</th><td><input type="password" name="api_key" value="" autocomplete="new-password" class="regular-text"><p class="description">' . ( $this->api_key() ? 'Chave configurada. Deixe vazio para manter.' : 'Introduza uma chave API v3 (não a chave SMTP).' ) . ' Guardada no servidor; nunca é incluída no HTML público. Pode usar LNKTA_BREVO_API_KEY em wp-config.php.</p><label><input type="checkbox" name="remove_key" value="1"> Apagar chave guardada</label></td></tr>';
		foreach ( array( 'brand' => 'Marca visível na newsletter', 'sender_name' => 'Nome do remetente', 'sender_email' => 'Email do remetente verificado', 'reply_to' => 'Responder para (opcional)', 'address' => 'Morada / identificação no rodapé' ) as $key => $label ) {
			echo '<tr><th><label for="lnkta-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="lnkta-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $this->setting( $key, 'brand' === $key ? get_bloginfo( 'name' ) : '' ) ) . '" type="' . ( in_array( $key, array( 'sender_email', 'reply_to' ), true ) ? 'email' : 'text' ) . '"></td></tr>';
		}
		$this->design_settings();
		$lists = array(); $error = '';
		if ( $this->api_key() ) {
			for ( $offset = 0; $offset < 10000; $offset += 50 ) {
				$result = $this->api( 'contacts/lists?limit=50&offset=' . $offset );
				if ( is_wp_error( $result ) ) { $error = $result->get_error_message(); break; }
				$batch = isset( $result['lists'] ) ? $result['lists'] : array(); $lists = array_merge( $lists, $batch );
				if ( count( $batch ) < 50 ) { break; }
			}
		}
		echo '<tr><th>Lista Brevo</th><td><select name="list_id"><option value="0">Selecione uma lista</option>';
		$found = false;
		foreach ( $lists as $list ) {
			$id = absint( $list['id'] ); if ( $id === absint( $this->setting( 'list_id' ) ) ) { $found = true; }
			echo '<option value="' . $id . '" ' . selected( $this->setting( 'list_id' ), $id, false ) . '>' . esc_html( $list['name'] ) . ' (#' . $id . ')</option>';
		}
		if ( ! $found && $this->setting( 'list_id' ) ) { echo '<option selected value="' . absint( $this->setting( 'list_id' ) ) . '">Lista guardada #' . absint( $this->setting( 'list_id' ) ) . '</option>'; }
		echo '</select><p class="description">Guarde primeiro a chave API para carregar as listas. Use apenas contactos subscritos.</p>' . ( $error ? '<p>' . esc_html( $error ) . '</p>' : '' ) . '</td></tr></table>';
		submit_button(); echo '</form></div>';
	}
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.', '', array( 'response' => 403 ) ); }
		check_admin_referer( 'lnkta_newsletter_settings' );
		$options = get_option( 'lnkta_newsletter_settings', array() );
		foreach ( array( 'brand', 'sender_name', 'address' ) as $key ) { $options[$key] = isset( $_POST[$key] ) ? sanitize_text_field( wp_unslash( $_POST[$key] ) ) : ''; }
		foreach ( array( 'sender_email', 'reply_to' ) as $key ) { $options[$key] = isset( $_POST[$key] ) ? sanitize_email( wp_unslash( $_POST[$key] ) ) : ''; }
		$options = $this->save_design_settings( $options );
		$options['list_id'] = isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0;
		if ( ! empty( $_POST['remove_key'] ) ) { unset( $options['api_key'] ); }
		elseif ( ! empty( $_POST['api_key'] ) ) { $options['api_key'] = sanitize_text_field( wp_unslash( $_POST['api_key'] ) ); }
		update_option( 'lnkta_newsletter_settings', $options, false );
		wp_safe_redirect( admin_url( 'options-general.php?page=lnkta-newsletter&saved=1' ) ); exit;
	}
	private function brevo_box( $post ) {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$campaign = absint( get_post_meta( $post->ID, '_lnkta_brevo_campaign', true ) );
		echo '<hr><p><strong>LNKTA · Brevo</strong></p><p>Guarde/atualize a newsletter antes de criar o rascunho. São usados os dados guardados.</p>';
		if ( $campaign ) { echo '<p>Última campanha criada: #' . $campaign . '.</p><p><a class="button" href="https://app.brevo.com/campaign/listing" target="_blank" rel="noopener noreferrer">Abrir campanhas Brevo</a></p>'; }
		echo '<p><label>Assunto <input type="text" id="lnkta-subject" class="widefat" value="' . esc_attr( get_the_title( $post ) ) . '"></label></p><button type="button" class="button button-primary" id="lnkta-brevo">Create Brevo draft</button><p id="lnkta-brevo-result" role="status" aria-live="polite"></p><p><a href="' . esc_url( admin_url( 'options-general.php?page=lnkta-newsletter' ) ) . '">Definições LNKTA Newsletter</a></p>';
		$config = wp_json_encode( array( 'id' => $post->ID, 'nonce' => wp_create_nonce( 'lnkta_brevo_' . $post->ID ), 'url' => admin_url( 'admin-ajax.php' ) ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		echo '<script>(function(){const c=' . $config . ';const b=document.getElementById("lnkta-brevo"),r=document.getElementById("lnkta-brevo-result");let requestId="";b.addEventListener("click",async function(){if(b.disabled)return;b.disabled=true;r.textContent="A criar rascunho…";if(!requestId)requestId=typeof crypto.randomUUID==="function"?crypto.randomUUID():Date.now().toString(36)+"-"+Math.random().toString(36).slice(2)+"-"+Math.random().toString(36).slice(2);try{const body=new URLSearchParams({action:"lnkta_brevo_draft",newsletter_id:c.id,_ajax_nonce:c.nonce,request_id:requestId,subject:document.getElementById("lnkta-subject").value});const response=await fetch(c.url,{method:"POST",credentials:"same-origin",body});const data=await response.json();r.textContent=data.data&&data.data.message?data.data.message:"Resposta inesperada. Verifique as campanhas no Brevo.";if(data.success){requestId="";b.textContent="Create another Brevo draft";const a=document.createElement("a");a.href="https://app.brevo.com/campaign/listing";a.target="_blank";a.rel="noopener noreferrer";a.textContent=" Abrir campanhas Brevo";r.appendChild(a);}}catch(e){r.textContent="Ligação interrompida. Pode repetir este pedido; se já foi criado, será devolvido o mesmo rascunho. Verifique as campanhas no Brevo antes de iniciar outro pedido.";}finally{b.disabled=false;}});})();</script>';
	}
	public function create_draft() {
		$id = isset( $_POST['newsletter_id'] ) ? absint( $_POST['newsletter_id'] ) : 0;
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $id ) || self::POST_TYPE !== get_post_type( $id ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
		check_ajax_referer( 'lnkta_brevo_' . $id );

		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		if ( ! $subject || ! is_email( $this->setting( 'sender_email' ) ) || ! $this->setting( 'sender_name' ) || ! absint( $this->setting( 'list_id' ) ) ) { wp_send_json_error( array( 'message' => 'Preencha o assunto e configure remetente e lista nas definições.' ), 400 ); }
		$request = isset( $_POST['request_id'] ) ? sanitize_text_field( wp_unslash( $_POST['request_id'] ) ) : '';
		if ( ! preg_match( '/^[a-zA-Z0-9_-]{16,80}$/D', $request ) ) { wp_send_json_error( array( 'message' => 'Pedido inválido. Recarregue o editor da newsletter.' ), 400 ); }
		$request_key = '_lnkta_brevo_request_' . hash( 'sha256', $request );
		$previous = get_post_meta( $id, $request_key, true );
		if ( is_array( $previous ) && ! empty( $previous['campaign'] ) ) { wp_send_json_success( array( 'message' => 'Rascunho já criado para este pedido: campanha #' . absint( $previous['campaign'] ) . '.', 'campaign_id' => absint( $previous['campaign'] ) ) ); }
		if ( is_array( $previous ) && ! empty( $previous['pending'] ) ) { wp_send_json_error( array( 'message' => 'Este pedido ainda está em curso ou teve um resultado incerto. Verifique o Brevo. Para criar outro rascunho intencionalmente, recarregue esta página.' ), 409 ); }
		$lock = 'lnkta_brevo_lock_' . $id;
		$started = (int) get_option( $lock, 0 );
		if ( $started && $started < time() - 120 ) { delete_option( $lock ); }
		if ( ! add_option( $lock, time(), '', false ) ) { wp_send_json_error( array( 'message' => 'Já existe um pedido em curso. Aguarde alguns segundos e tente novamente.' ), 409 ); }
		// Check again inside the atomic lock, so simultaneous retries cannot create duplicates.
		$previous = get_post_meta( $id, $request_key, true );
		if ( is_array( $previous ) && ! empty( $previous['campaign'] ) ) { delete_option( $lock ); wp_send_json_success( array( 'message' => 'Rascunho já criado: campanha #' . absint( $previous['campaign'] ) . '.', 'campaign_id' => absint( $previous['campaign'] ) ) ); }
		if ( is_array( $previous ) && ! empty( $previous['pending'] ) ) { delete_option( $lock ); wp_send_json_error( array( 'message' => 'Resultado deste pedido pendente. Verifique o Brevo antes de iniciar outro pedido.' ), 409 ); }
		update_post_meta( $id, $request_key, array( 'pending' => true, 'time' => time() ) );
		$html = $this->newsletter_html( $id, true );
		$payload = array( 'name' => 'Newsletter ' . $id . ' - ' . gmdate( 'Y-m-d H:i:s' ) . ' - ' . $subject, 'subject' => $subject, 'sender' => array( 'name' => $this->setting( 'sender_name' ), 'email' => $this->setting( 'sender_email' ) ), 'recipients' => array( 'listIds' => array( absint( $this->setting( 'list_id' ) ) ) ), 'htmlContent' => $html );
		if ( is_email( $this->setting( 'reply_to' ) ) ) { $payload['replyTo'] = $this->setting( 'reply_to' ); }
		$result = $this->api( 'emailCampaigns', 'POST', $payload );
		if ( is_wp_error( $result ) ) {
			delete_option( $lock );
			if ( in_array( $result->get_error_code(), array( 'missing_key', 'rejected' ), true ) ) { delete_post_meta( $id, $request_key ); }
			wp_send_json_error( array( 'message' => $result->get_error_message() . ' Se o resultado for incerto, verifique as campanhas no Brevo antes de repetir.' ), 400 );
		}
		if ( empty( $result['id'] ) ) { delete_option( $lock ); wp_send_json_error( array( 'message' => 'Resposta inesperada. Verifique as campanhas no Brevo antes de repetir.' ), 502 ); }
		$campaign = absint( $result['id'] );
		update_post_meta( $id, $request_key, array( 'campaign' => $campaign, 'time' => time() ) );
		update_post_meta( $id, '_lnkta_brevo_campaign', $campaign ); delete_option( $lock );
		wp_send_json_success( array( 'message' => 'Rascunho criado: campanha #' . $campaign . '. Pode criar outro rascunho com o mesmo botão.', 'campaign_id' => $campaign ) );
	}

	public function handle_export() {
		$id = isset( $_GET['newsletter_id'] ) ? absint( $_GET['newsletter_id'] ) : 0;
		if ( ! $id || self::POST_TYPE !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) { wp_die( 'Não tem permissão para aceder a esta newsletter.', '', array( 'response' => 403 ) ); }
		check_admin_referer( self::ACTION . '_' . $id );
		$download = isset( $_GET['view'] ) && 'html' === $_GET['view'];
		$html = $this->newsletter_html( $id, $download );
		if ( isset( $_GET['view'] ) && 'html' === $_GET['view'] ) { header( 'Content-Type: text/plain; charset=UTF-8' ); header( 'Content-Disposition: attachment; filename="lnkta-newsletter-' . $id . '.html"' ); echo $html; exit; }
		header( 'Content-Type: text/html; charset=UTF-8' ); echo $html; exit;
	}
}
new LNKTA_Newsletter_Plugin();

require_once __DIR__ . '/updater.php';
