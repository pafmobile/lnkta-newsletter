<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait LNKTA_Newsletter_Design {
	public function design_assets( $hook ) {
		if ( 'settings_page_lnkta-newsletter' !== $hook ) { return; }
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".lnkta-newsletter-color").wpColorPicker();});' );
	}
	private function format_content( $content ) {
		return is_string( $content ) && '' !== trim( $content ) ? wp_kses_post( wpautop( do_shortcode( $content ) ) ) : '';
	}
	private function has_acf_footer( $post_id ) {
		return function_exists( 'get_field_object' ) && get_field_object( 'newsletter_footer_content', $post_id, false, false );
	}
	private function add_footer_box() {
		$post = get_post();
		if ( $post && $this->has_acf_footer( $post->ID ) ) { return; }
		add_meta_box( 'lnkta-newsletter-footer', 'Conteúdo final da newsletter', array( $this, 'render_footer_box' ), self::POST_TYPE, 'normal', 'default' );
	}
	public function render_footer_box( $post ) {
		echo '<p>Texto apresentado no corpo da newsletter, depois dos artigos e do podcast (quando selecionado), antes do rodapé colorido. O conteúdo inicial continua a ser editado no editor principal da newsletter.</p>';
		wp_nonce_field( 'lnkta_newsletter_footer_' . $post->ID, 'lnkta_footer_nonce' );
		wp_editor( (string) get_post_meta( $post->ID, 'newsletter_footer_content', true ), 'lnkta_newsletter_footer_content', array( 'textarea_name' => 'lnkta_footer_content', 'textarea_rows' => 8, 'media_buttons' => false ) );
	}
	public function save_footer_content( $post_id ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || $this->has_acf_footer( $post_id ) ) { return; }
		if ( ! isset( $_POST['lnkta_footer_nonce'], $_POST['lnkta_footer_content'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lnkta_footer_nonce'] ) ), 'lnkta_newsletter_footer_' . $post_id ) ) { return; }
		update_post_meta( $post_id, 'newsletter_footer_content', wp_kses_post( wp_unslash( $_POST['lnkta_footer_content'] ) ) );
	}
	private function unsubscribe_html( $color, $for_brevo = false ) {
		$notice = $this->setting( 'unsubscribe_notice', 'Recebe esta newsletter porque subscreveu as nossas novidades. Se não pretende continuar a receber, pode {unsubscribe}.' );
		$link = '<a href="{{ unsubscribe }}" style="color:' . esc_attr( $color ) . ';text-decoration:underline;">' . esc_html( $this->setting( 'unsubscribe_label', 'cancelar a subscrição' ) ) . '</a>';
		if ( ! $for_brevo ) {
			$link = '<span aria-disabled="true" title="O link pessoal de cancelamento é criado pelo Brevo ao enviar a newsletter." style="color:' . esc_attr( $color ) . ';text-decoration:underline;">' . esc_html( $this->setting( 'unsubscribe_label', 'cancelar a subscrição' ) ) . '</span>';
		}
		if ( false === strpos( $notice, '{unsubscribe}' ) ) { $notice .= ' {unsubscribe}'; }
		return str_replace( '{unsubscribe}', $link, esc_html( $notice ) );
	}
	private function design_settings() {
		foreach ( array( 'logo_id' => 'Logótipo (SVG, PNG ou JPEG)', 'email_logo_id' => 'Versão PNG para email (opcional)' ) as $key => $label ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td><input type="file" name="' . esc_attr( $key ) . '" accept="' . ( 'logo_id' === $key ? '.svg,.png,.jpg,.jpeg' : '.png' ) . '">';
			$url = wp_get_attachment_url( absint( $this->setting( $key ) ) );
			if ( $url ) { echo '<p><img src="' . esc_url( $url ) . '" alt="Logótipo atual" style="max-width:180px;max-height:100px;background:#eee;padding:8px;"></p><label><input type="checkbox" name="remove_' . esc_attr( $key ) . '" value="1"> Remover</label>'; }
			echo '<p class="description">' . ( 'logo_id' === $key ? 'Até 1 MB. O SVG é validado e limpo apenas neste formulário. Use formas vetoriais e estilos inline.' : 'Se definida, esta versão é usada no HTML e no Brevo para compatibilidade entre clientes de email.' ) . '</p></td></tr>';
		}
		foreach ( array( 'accent_color' => 'Links e destaques: cor', 'header_background' => 'Cabeçalho: cor de fundo', 'header_text' => 'Cabeçalho: cor do texto', 'footer_background' => 'Rodapé: cor de fundo', 'footer_text' => 'Rodapé: cor do texto' ) as $key => $label ) {
			echo '<tr><th><label for="lnkta-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input id="lnkta-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="text" class="regular-text lnkta-newsletter-color" placeholder="#20382e" pattern="#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?" value="' . esc_attr( $this->setting( $key ) ) . '"><p class="description">Cor hexadecimal. Vazio: usa as cores ACF existentes para cabeçalho/rodapé e #3f6d54 para links e destaques.</p></td></tr>';
		}
		echo '<tr><th><label for="lnkta-unsubscribe-notice">Aviso de cancelamento</label></th><td><textarea id="lnkta-unsubscribe-notice" name="unsubscribe_notice" rows="4" class="large-text">' . esc_textarea( $this->setting( 'unsubscribe_notice', 'Recebe esta newsletter porque subscreveu as nossas novidades. Se não pretende continuar a receber, pode {unsubscribe}.' ) ) . '</textarea><p class="description">Use {unsubscribe} onde pretende o link. Se omitido, o link é acrescentado no fim. O endereço é personalizado automaticamente pelo Brevo ao enviar.</p></td></tr>';
		echo '<tr><th><label for="lnkta-unsubscribe-label">Texto do link</label></th><td><input id="lnkta-unsubscribe-label" class="regular-text" name="unsubscribe_label" value="' . esc_attr( $this->setting( 'unsubscribe_label', 'cancelar a subscrição' ) ) . '"></td></tr>';
	}
	private function save_design_settings( $options ) {
		foreach ( array( 'accent_color', 'header_background', 'header_text', 'footer_background', 'footer_text' ) as $key ) {
			$value = isset( $_POST[$key] ) ? trim( wp_unslash( $_POST[$key] ) ) : '';
			if ( '' !== $value && ! sanitize_hex_color( $value ) ) { wp_die( 'Cor inválida. Utilize um valor hexadecimal como #20382e.' ); }
			$options[$key] = $value;
		}
		$options['unsubscribe_notice'] = isset( $_POST['unsubscribe_notice'] ) ? sanitize_textarea_field( wp_unslash( $_POST['unsubscribe_notice'] ) ) : '';
		$options['unsubscribe_label'] = isset( $_POST['unsubscribe_label'] ) ? sanitize_text_field( wp_unslash( $_POST['unsubscribe_label'] ) ) : '';
		foreach ( array( 'logo_id', 'email_logo_id' ) as $key ) {
			if ( ! empty( $_POST['remove_' . $key] ) ) { unset( $options[$key] ); }
			if ( isset( $_FILES[$key]['error'] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES[$key]['error'] ) {
				$result = $this->upload_logo( $_FILES[$key], 'email_logo_id' === $key );
				if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
				$options[$key] = $result;
			}
		}
		return $options;
	}
	private function upload_logo( $file, $png_only = false ) {
		if ( ! current_user_can( 'upload_files' ) || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) { return new WP_Error( 'upload', 'Não foi possível carregar o logótipo.' ); }
		if ( filesize( $file['tmp_name'] ) > 1024 * 1024 ) { return new WP_Error( 'size', 'O logótipo deve ter no máximo 1 MB.' ); }
		$name = sanitize_file_name( $file['name'] ); $ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$mime = ''; $data = file_get_contents( $file['tmp_name'] );
		if ( 'svg' === $ext && ! $png_only ) {
			$data = $this->sanitize_svg( $data ); if ( is_wp_error( $data ) ) { return $data; } $mime = 'image/svg+xml';
		} else {
			$allowed = $png_only ? array( 'png' => 'image/png' ) : array( 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg' );
			$mime = wp_get_image_mime( $file['tmp_name'] );
			if ( ! isset( $allowed[$ext] ) || $allowed[$ext] !== $mime ) { return new WP_Error( 'type', 'Formato inválido. Utilize SVG, PNG ou JPEG (PNG para a versão de email).' ); }
		}
		// MIME permission is scoped to this already validated upload, not the media library globally.
		$allow_svg = function( $mimes ) { $mimes['svg'] = 'image/svg+xml'; return $mimes; };
		if ( 'svg' === $ext ) { add_filter( 'upload_mimes', $allow_svg ); }
		try { $upload = wp_upload_bits( $name, null, $data ); }
		finally { if ( 'svg' === $ext ) { remove_filter( 'upload_mimes', $allow_svg ); } }
		if ( $upload['error'] ) { return new WP_Error( 'upload', $upload['error'] ); }
		$id = wp_insert_attachment( array( 'post_mime_type' => $mime, 'post_title' => sanitize_text_field( pathinfo( $name, PATHINFO_FILENAME ) ), 'post_status' => 'inherit' ), $upload['file'], 0, true );
		if ( is_wp_error( $id ) ) { wp_delete_file( $upload['file'] ); }
		return $id;
	}
	/** Static SVG subset: no scripts, external resources, animation, HTML or entities. */
	private function sanitize_svg( $xml ) {
		if ( ! class_exists( 'DOMDocument' ) ) { return new WP_Error( 'svg_dom', 'A extensão PHP DOM é necessária para validar SVG. Utilize PNG ou peça ao alojamento para ativar DOM.' ); }
		if ( ! is_string( $xml ) || preg_match( '/<!DOCTYPE|<!ENTITY|<\?/i', preg_replace( '/^\s*<\?xml[^?]*\?>/i', '', $xml ) ) ) { return new WP_Error( 'svg', 'SVG inválido: remova entidades, DOCTYPE e instruções de processamento.' ); }
		$previous = libxml_use_internal_errors( true );
		$doc = new DOMDocument(); $loaded = $doc->loadXML( $xml, LIBXML_NONET );
		libxml_clear_errors(); libxml_use_internal_errors( $previous );
		if ( ! $loaded || ! $doc->documentElement || 'svg' !== $doc->documentElement->localName || 'http://www.w3.org/2000/svg' !== $doc->documentElement->namespaceURI ) { return new WP_Error( 'svg', 'O ficheiro não contém um SVG válido.' ); }
		$tags = array( 'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'defs', 'linearGradient', 'radialGradient', 'stop', 'clipPath', 'mask', 'title', 'desc', 'text', 'tspan', 'use' );
		$attrs = array( 'id', 'viewBox', 'width', 'height', 'x', 'y', 'x1', 'x2', 'y1', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'd', 'points', 'transform', 'preserveAspectRatio', 'fill', 'fill-rule', 'fill-opacity', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'opacity', 'clip-path', 'clip-rule', 'mask', 'offset', 'stop-color', 'stop-opacity', 'gradientUnits', 'gradientTransform', 'spreadMethod', 'fx', 'fy', 'fr', 'clipPathUnits', 'maskUnits', 'maskContentUnits', 'font-family', 'font-size', 'font-weight', 'text-anchor', 'dx', 'dy' );
		$paint = array( 'fill', 'stroke', 'stop-color', 'clip-path', 'mask' );
		$clean = function( $node ) use ( &$clean, $tags, $attrs, $paint ) {
			if ( $node->nodeType === XML_ELEMENT_NODE ) {
				if ( ! in_array( $node->localName, $tags, true ) || 'http://www.w3.org/2000/svg' !== $node->namespaceURI ) { $node->parentNode->removeChild( $node ); return; }
				// Preserve common editor-exported inline paint rules as SVG attributes.
				if ( $node->hasAttribute( 'style' ) ) {
					foreach ( explode( ';', $node->getAttribute( 'style' ) ) as $rule ) {
						$parts = explode( ':', $rule, 2 );
						if ( 2 === count( $parts ) && in_array( trim( $parts[0] ), $attrs, true ) ) { $node->setAttribute( trim( $parts[0] ), trim( $parts[1] ) ); }
					}
				}
				for ( $i = $node->attributes->length - 1; $i >= 0; $i-- ) {
					$a = $node->attributes->item( $i ); $v = trim( $a->value );
					$local_link = 'href' === $a->localName && preg_match( '/^#[a-zA-Z_][a-zA-Z0-9_.:-]*$/D', $v );
					$valid = ( ! $a->namespaceURI && in_array( $a->name, $attrs, true ) ) || $local_link;
					if ( preg_match( '/[\\\\<>]|(?:javascript|data|https?|file):|@import|expression/i', $v ) ) { $valid = false; }
					if ( stripos( $v, 'url' ) !== false && ( ! in_array( $a->name, $paint, true ) || ! preg_match( '/^url\(#[a-zA-Z_][a-zA-Z0-9_.:-]*\)$/D', $v ) ) ) { $valid = false; }
					if ( ! $valid ) { $node->removeAttributeNode( $a ); }
				}
			}
			foreach ( iterator_to_array( $node->childNodes ) as $child ) {
				if ( in_array( $child->nodeType, array( XML_ELEMENT_NODE, XML_TEXT_NODE ), true ) ) { $clean( $child ); }
				else { $node->removeChild( $child ); }
			}
		};
		$clean( $doc->documentElement );
		return $doc->saveXML( $doc->documentElement );
	}
}
