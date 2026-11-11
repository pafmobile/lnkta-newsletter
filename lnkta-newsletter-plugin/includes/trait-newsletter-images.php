<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait LNKTA_Newsletter_Images {
	/** Use existing local media only; never fetch remote URLs from post content. */
	private function email_image_url( $attachment_id, $size ) {
		if ( ! $attachment_id ) { return ''; }
		$uploads = wp_upload_dir();
		$base = realpath( $uploads['basedir'] );
		if ( ! $base ) { return wp_get_attachment_url( $attachment_id ); }
		$base = trailingslashit( wp_normalize_path( $base ) );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$attached = get_attached_file( $attachment_id );
		$candidates = array();
		if ( $attached && is_array( $metadata ) && ! empty( $metadata['sizes'][$size]['file'] ) ) { $candidates[] = dirname( $attached ) . '/' . wp_basename( $metadata['sizes'][$size]['file'] ); }
		if ( function_exists( 'wp_get_original_image_path' ) ) { $candidates[] = wp_get_original_image_path( $attachment_id ); }
		if ( $attached && is_array( $metadata ) && ! empty( $metadata['original_image'] ) ) { $candidates[] = dirname( $attached ) . '/' . wp_basename( $metadata['original_image'] ); }
		$candidates[] = $attached;
		$valid = array();
		foreach ( array_unique( array_filter( $candidates ) ) as $file ) {
			$real = realpath( $file );
			if ( ! $real || ! is_file( $real ) ) { continue; }
			$real = wp_normalize_path( $real );
			if ( 0 === strpos( $real, $base ) ) { $valid[] = $real; }
		}
		// Prefer JPEG/PNG/GIF sources, including the original retained by format optimizers.
		foreach ( $valid as $file ) {
			if ( in_array( wp_get_image_mime( $file ), array( 'image/jpeg', 'image/png', 'image/gif' ), true ) ) {
				return trailingslashit( $uploads['baseurl'] ) . implode( '/', array_map( 'rawurlencode', explode( '/', substr( $file, strlen( $base ) ) ) ) );
			}
		}
		if ( ! $valid ) { return ''; }
		// AVIF/WebP-only libraries get a cached JPEG; source attachments stay unchanged.
		$source = reset( $valid );
		$cache_dir = trailingslashit( $uploads['basedir'] ) . 'lnkta-newsletters';
		$name = 'email-' . absint( $attachment_id ) . '-' . substr( md5( $source . '|' . filemtime( $source ) . '|' . filesize( $source ) ), 0, 16 ) . '.jpg';
		$target = trailingslashit( $cache_dir ) . $name;
		if ( ! is_file( $target ) && wp_mkdir_p( $cache_dir ) ) {
			$editor = wp_get_image_editor( $source );
			if ( ! is_wp_error( $editor ) ) {
				$editor->resize( 1200, 1200, false );
				$editor->set_quality( 88 );
				$editor->save( $target, 'image/jpeg' );
			}
		}
		if ( is_file( $target ) ) { return trailingslashit( $uploads['baseurl'] ) . 'lnkta-newsletters/' . $name; }
		// Preserve a working modern image if this host cannot convert it to JPEG.
		return trailingslashit( $uploads['baseurl'] ) . implode( '/', array_map( 'rawurlencode', explode( '/', substr( $source, strlen( $base ) ) ) ) );
	}
}
