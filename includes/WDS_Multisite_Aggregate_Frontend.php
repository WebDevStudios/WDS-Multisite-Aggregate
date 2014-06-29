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
		add_filter( 'post_thumbnail_html', array( $this, 'thumbnail_link' ), 10, 2 );
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

		return $link;
	}

	function thumbnail_link( $html, $post_id ) {
		global $wpdb;

		$tags_blog_id = $this->options->get( 'tags_blog_id' );

		if ( !$tags_blog_id ) {
			return $html;
		}

		if ( $wpdb->blogid != $tags_blog_id ) {
			return $html;
		}

		$thumb = get_post_meta( $post_id, 'thumbnail_html', true );

		return $thumb ? $thumb : $html;
	}

}
