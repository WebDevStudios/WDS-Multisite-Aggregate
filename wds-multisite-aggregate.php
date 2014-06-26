<?php
/*
Plugin Name: WDS Multisite Aggregate
Plugin URI: http://ocaoimh.ie/wordpress-mu-sitewide-tags/
Description: Creates a blog where all the most recent posts on a WordPress network may be found. Based on WordPress MU Sitewide Tags Pages plugin by Donncha O Caoimh.
Version: 0.4.2
Author: WebDevStudios
Author URI: http://webdevstudios.com
*/
/*  Copyright 2008 Donncha O Caoimh (http://ocaoimh.ie/)
    With contributions by Ron Rennick(http://wpmututorials.com/), Thomas Schneider(http://www.im-web-gefunden.de/) and others.

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


/**
 * Autoloads files with classes when needed
 * @since  1.0.0
 * @param  string $class_name Name of the class being requested
 */
function wds_ma_autoload_classes( $class_name ) {
	if ( class_exists( $class_name, false ) ) {
		return;
	}

	$file = dirname( __FILE__ ) .'/includes/'. $class_name .'.php';
	if ( file_exists( $file ) ) {
		@include_once( $file );
	}
}
spl_autoload_register( 'wds_ma_autoload_classes' );

class WDS_Multisite_Aggregate {

	public function __construct() {
		// Handles Admin display
		$this->admin = new WDS_Multisite_Aggregate_Admin();
		$this->admin->hooks();
		// Options setter/getter and handles updating options on save
		$this->options = new WDS_Multisite_Aggregate_Options();
		$this->options->hooks();
		// Handles removing posts from removed blogs
		$this->remove = new WDS_Multisite_Aggregate_Remove( $this->options );
		$this->remove->hooks();
		// Handles frontend modification for aggregate site
		$this->frontend = new WDS_Multisite_Aggregate_Frontend( $this->options );
		$this->frontend->hooks();
	}

	function hooks() {
		add_action( 'save_post', array( $this, 'do_post_sync' ), 10, 2 );
		add_action( 'trash_post', array( $this, 'sync_post_delete' ) );
		add_action( 'delete_post', array( $this, 'sync_post_delete' ) );

		if ( !empty( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'sitewidetags-populate' ) {
			add_action( 'init', array( $this, 'populate_posts' ) );
		}

		add_filter( 'sitewide_tags_allowed_post_types', array( $this, 'pages_filter' ) );
	}

	/**
	 * run populate function in local blog context because get_permalink does not produce the correct permalinks while switched
	 */
	function populate_posts() {
		global $wpdb;

		$valid_key = isset( $_REQUEST['key'] ) ? $_REQUEST['key'] == md5( serialize( get_blog_details( $wpdb->blogid ) ) ) : false;
		if ( !$valid_key ) {
			return false;
		}

		$tags_blog_id = $this->options->get( 'tags_blog_id' );
		$tags_blog_enabled = $this->options->get( 'tags_blog_enabled' );

		if ( !$tags_blog_enabled || !$tags_blog_id || $tags_blog_id == $wpdb->blogid ) {
			exit( '0' );
		}

		$posts_done = 0;
		$p = isset( $_GET[ 'p' ] ) ? (int)$_GET[ 'p' ] : 0; // post count
		while ( $posts_done < 300 ) {
			$posts = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' LIMIT %d, 50", $p + $posts_done ) );

			if ( empty( $posts ) )
				exit( '0' );

			foreach ( $posts as $post ) {
				if ( $post != 1 && $post != 2 ) {
					$this->do_post_sync( $post, get_post( $post ) );
				}

			}
			$posts_done += 50;
		}
		exit( $posts_done );
	}

	function do_post_sync( $post_id, $post ) {
		global $wpdb;

		if( !$this->options->get( 'tags_blog_enabled' ) )
			return;

		// wp_insert_category()
		include_once(ABSPATH . 'wp-admin/includes/admin.php');

		$tags_blog_id = $this->options->get( 'tags_blog_id' );
		if( !$tags_blog_id || $wpdb->blogid == $tags_blog_id )
			return;

		$allowed_post_types = apply_filters( 'sitewide_tags_allowed_post_types', array( 'post' => true ) );
		if ( !$allowed_post_types[$post->post_type] )
			return;

		$post_blog_id = $wpdb->blogid;
		$blog_status = get_blog_status($post_blog_id, "public");
		if ( $blog_status != 1 && ( $blog_status != 0 || $this->options->get( 'tags_blog_public') == 1 || $this->options->get( 'tags_blog_pub_check') == 0 ) )
			return;

		$post->post_category = wp_get_post_categories( $post_id );
		$cats = array();
		foreach( $post->post_category as $c ) {
			$cat = get_category( $c );
			$cats[] = array( 'name' => esc_html( $cat->name ), 'slug' => esc_html( $cat->slug ) );
		}

		$post->tags_input = implode( ', ', wp_get_post_tags( $post_id, array('fields' => 'names') ) );

		$post->guid = $post_blog_id . '.' . $post_id;

		$global_meta = array();
		$global_meta['permalink'] = get_permalink( $post_id );
		$global_meta['blogid'] = $org_blog_id = $wpdb->blogid; // org_blog_id

		$meta_keys = apply_filters( 'sitewide_tags_meta_keys', $this->options->get( 'tags_blog_postmeta', array() ) );
		if( is_array( $meta_keys ) && !empty( $meta_keys ) ) {
			foreach( $meta_keys as $key )
				$global_meta[$key] = get_post_meta( $post->ID, $key, true );
		}
		unset( $meta_keys );

		if( $this->options->get( 'tags_blog_thumbs' ) && ( $thumb_id = get_post_meta( $post->ID, '_thumbnail_id', true ) ) ) {
			$thumb_size = apply_filters( 'sitewide_tags_thumb_size', 'thumbnail' );
			$global_meta['thumbnail_html'] = wp_get_attachment_image( $thumb_id, $thumb_size );
		}

		// custom taxonomies
		$taxonomies = apply_filters( 'sitewide_tags_custom_taxonomies', array() );
		if( !empty( $taxonomies ) && $post->post_status == 'publish' ) {
			$registered_tax = array_diff( get_taxonomies(), array( 'post_tag', 'category', 'link_category', 'nav_menu' ) );
			$custom_tax = array_intersect( $taxonomies, $registered_tax );
			$tax_input = array();
			foreach( $custom_tax as $tax ) {
				$terms = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'names' ) );
				if( empty( $terms ) )
					continue;
				if( is_taxonomy_hierarchical( $tax ) )
					$tax_input[$tax] = $terms;
				else
					$tax_input[$tax] = implode( ',', $terms );
			}
			if( !empty( $tax_input ) )
					$post->tax_input = $tax_input;
		}

		switch_to_blog( $tags_blog_id );
		if( is_array( $cats ) && !empty( $cats ) && $post->post_status == 'publish' ) {
			foreach( $cats as $t => $d ) {
				$term = get_term_by( 'slug', $d['slug'], 'category' );
				if( $term && $term->parent == 0 ) {
					$category_id[] = $term->term_id;
					continue;
				}
				/* Here is where we insert the category if necessary */
				wp_insert_category( array('cat_name' => $d['name'], 'category_description' => $d['name'], 'category_nicename' => $d['slug'], 'category_parent' => '') );

				/* Now get the category ID to be used for the post */
				$category_id[] = $wpdb->get_var( "SELECT term_id FROM " . $wpdb->get_blog_prefix( $tags_blog_id ) . "terms WHERE slug = '" . $d['slug'] . "'" );
			}
		}

		$global_post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE guid IN (%s,%s)", $post->guid, esc_url( $post->guid ) ) );
		if( $post->post_status != 'publish' && is_object( $global_post ) ) {
			wp_delete_post( $global_post->ID );
		} else {
			if( $global_post->ID != '' ) {
				$post->ID = $global_post->ID; // editing an old post

				foreach( array_keys( $global_meta ) as $key )
					delete_post_meta( $global_post->ID, $key );
			} else {
				unset( $post->ID ); // new post
			}
		}
		if( $post->post_status == 'publish' ) {
			$post->ping_status = 'closed';
			$post->comment_status = 'closed';

			/* Use the category ID in the post */
		        $post->post_category = $category_id;

			$p = wp_insert_post( $post );
			foreach( $global_meta as $key => $value )
				if( $value )
					add_post_meta( $p, $key, $value );
		}
		restore_current_blog();
	}

	function sync_post_delete( $post_id ) {
		/*
		 * what should we do if a post will be deleted and the tags blog feature is disabled?
		 * need an check if we have a post on the tags blog and if so - delete this
		 */
		global $wpdb;
		$tags_blog_id = $this->options->get( 'tags_blog_id' );
		if( null === $tags_blog_id )
			return;

		if( $wpdb->blogid == $tags_blog_id )
			return;

		$post_blog_id = $wpdb->blogid;
		switch_to_blog( $tags_blog_id );
		$guid = "{$post_blog_id}.{$post_id}";
		$global_post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE guid IN (%s,%s)", $guid, esc_url( $guid ) )  );
		if( null !== $global_post_id )
			wp_delete_post( $global_post_id );

		restore_current_blog();
	}

	function pages_filter( $post_types ) {
		if ( $this->options->get( 'tags_blog_pages' ) ) {
			$post_types = array_merge( $post_types, array( 'page' => true ) );
		}
		return $post_types;
	}

}

$WDS_Multisite_Aggregate = new WDS_Multisite_Aggregate();
$WDS_Multisite_Aggregate->hooks();
