<?php

/**
 * Handles filtering aggregate site's posts on the front-end
 *
 * @since  1.0.0
 */
class WDS_Multisite_Aggregate_Frontend {

	public function __construct( WDS_Multisite_Aggregate_Options $options ) {
		$this->options = $options;
	}

	public function hooks() {
		add_filter( 'post_link', array( $this, 'post_link' ), 10, 2 );
		add_filter( 'page_link', array( $this, 'post_link' ), 10, 2 );
		add_filter( 'post_thumbnail_html', array( $this, 'thumbnail_link' ), 10, 4 );
	}

	function post_link( $link, $post ) {
		global $wpdb;

		$tags_blog_id = $this->options->get( 'tags_blog_id' );

		if ( !$tags_blog_id ) {
			return $link;
		}

		if ( $wpdb->blogid != $tags_blog_id ) {
			return $link;
		}

		$url = is_numeric( $post )
			 ? get_post_meta( $post, 'permalink', true )
			 : get_post_meta( $post->ID, 'permalink', true );

		if ( $url ) {
			return $url;
		}

		$url = $this->retrieve_url_from_guid( $post );

		if ( $url ) {
			return $url;
		}

		return $link;
	}

	function thumbnail_link( $html, $post_id, $post_thumbnail_id, $size ) {

		if ( get_post_thumbnail_id( $post_id ) ) {
			return $html;
		}

		global $wpdb;

		$tags_blog_id = $this->options->get( 'tags_blog_id' );

		if ( !$tags_blog_id ) {
			return $html;
		}

		if ( $wpdb->blogid != $tags_blog_id ) {
			return $html;
		}

		$thumb = get_post_meta( $post_id, "thumbnail_html_$size", true );
		// back-compat
		$thumb = $thumb ? $thumb : get_post_meta( $post_id, 'thumbnail_html', true );

		return $thumb ? $thumb : $html;
	}

	public function retrieve_url_from_guid( $_post ) {
		global $post;

		if ( is_numeric( $_post ) ) {
			$_post = isset( $post->ID ) && $post->ID == $_post ? $post : get_post( $_post );
		}
		if ( ! isset( $_post->guid ) ) {
			return false;
		}

		$guid = str_ireplace( array( 'http://', 'https://' ), '', $_post->guid );
		if ( ! is_numeric( $guid ) ) {
			return false;
		}

		if ( false === stripos( $guid, '.' ) ) {
			return false;
		}

		$parts = explode( '.', $guid );
		foreach ( $parts as $part ) {
			if ( ! is_numeric( $part ) ) {
				return false;
			}
		}
		$url = get_site_url( $parts[0], '/?p='. $parts[1] );
		return $url ? esc_url( $url ) : false;
	}

}
