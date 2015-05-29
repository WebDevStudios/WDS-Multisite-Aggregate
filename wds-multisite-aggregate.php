<?php
/*
Plugin Name: WDS Multisite Aggregate
Plugin URI: https://github.com/WebDevStudios/WDS-Multisite-Aggregate
Description: Creates a blog where all the most recent posts on a WordPress network may be found. Based on WordPress MU Sitewide Tags Pages plugin by Donncha O Caoimh. WP-CLI: `wp multisite_aggregate --help`.
Version: 1.0.2
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

/**
 * Get it started
 *
 * @since  1.0.0
 */
class WDS_Multisite_Aggregate {

	protected $imported        = array();
	protected $total_imported  = 0;
	protected $doing_save_post = false;
	protected $debug           = false;

	public function __construct() {
		// Options setter/getter and handles updating options on save
		$this->options = new WDS_Multisite_Aggregate_Options();
		$this->options->hooks();
		// Handles Admin display
		$this->admin = new WDS_Multisite_Aggregate_Admin( $this->options );
		$this->admin->hooks();
		// Handles removing posts from removed blogs
		$this->remove = new WDS_Multisite_Aggregate_Remove( $this->options );
		$this->remove->hooks();
		// Handles frontend modification for aggregate site
		$this->frontend = new WDS_Multisite_Aggregate_Frontend( $this->options );
		$this->frontend->hooks();

		if ( isset( $_REQUEST['aggregate_debug'] ) ) {
			$this->debug = new WDS_Multisite_Aggregate_Debug();
			$this->debug->hooks();
		}
	}

	function hooks() {
		add_action( 'save_post', array( $this, 'do_post_sync' ), 10, 2 );
		add_action( 'wds_multisite_aggregate_post_sync', array( $this, 'save_meta_fields' ), 10, 3 );
		add_action( 'wp_update_comment_count', array( $this, 'do_comment_sync' ) );

		add_action( 'trash_post', array( $this, 'sync_post_delete' ) );
		add_action( 'delete_post', array( $this, 'sync_post_delete' ) );

		if ( ! empty( $_GET['action'] ) && 'populate_posts_from_blog' == $_GET['action'] ) {
			define( 'WDS_Multisite_Aggregate', true );
			add_action( 'init', array( $this, 'populate_posts_from_blog' ), 8 );
		}
		if ( ! empty( $_GET['page'] ) && 'wds-multisite-aggregate' == $_GET['page'] ) {
			add_action( 'admin_init', array( $this, 'context_hooks' ) );
		}

	}

	public function context_hooks() {
		if ( isset( $_GET['total_imported'] ) ) {
			add_action( 'all_admin_notices', array( $this, 'user_notice' ) );
		}

		$valid_nonce = isset( $_REQUEST['_wpnonce'] ) ? wp_verify_nonce( $_REQUEST['_wpnonce'], 'wds-multisite-aggregate' ) : false;

		if ( ! $valid_nonce ) {
			return;
		}

		if ( isset( $_GET['action'] ) && 'populate_from_blogs' == $_GET['action'] ) {
			return $this->populate_from_blogs();
		}

		if ( ! empty( $_POST ) ) {
			$this->options->update_options();
		}
	}

	function populate_from_blogs() {
		global $wpdb;

		$this->total_imported = $this->options->make_integer_from_request( 'total_imported' );
		$post_count           = $this->options->make_integer_from_request( 'post_count' );

		// Check query string
		$blogs_to_import      = $this->options->comma_delimited_to_array_from_request( 'blogs_to_import' );
		// No query string? Check options
		$blogs_to_import      = ! empty( $blogs_to_import ) ? $blogs_to_import : $this->get_blogs_to_import();

		$tags_blog_id = $this->options->get( 'tags_blog_id' );
		if ( ! $tags_blog_id || empty( $blogs_to_import ) ) {
			return false;
		}

		$blog_to_populate = array_shift( $blogs_to_import );

		if ( $blog_to_populate != $tags_blog_id ) {
			$this->imported = array();

			$details = get_blog_details( $blog_to_populate );
			$url = add_query_arg( array(
				'post_count' => $post_count,
				'action'     => 'populate_posts_from_blog',
				'key'        => md5( serialize( $details ) )
			), $details->siteurl );

			$post_count  = 0;
			$_post_count = 0;
			$result      = wp_remote_get( $url );
			if ( is_wp_error( $result ) ) {
				wp_die( $result->get_error_message() );
			}
			$response = wp_remote_retrieve_body( $result );

			if ( $response ) {
				$json = json_decode( $response );
				$data = $json->success ? $json->data : false;
				if ( $data ) {
					$this->total_imported = $this->total_imported + count( $data->posts_imported );
					$this->imported = (array) $data->posts_imported;
					$_post_count = (int) $data->posts_done;
				}
			}

			if ( $_post_count ) {
				$post_count = $_post_count;
			}
		}

		if ( $post_count || ! empty( $blogs_to_import ) ) {

			$url = network_admin_url( 'settings.php' );
			$args = array(
				'page'           => 'wds-multisite-aggregate',
				'action'         => 'populate_from_blogs',
				'post_count'     => $post_count,
				'total_imported' => (int) $this->total_imported,
				'next_blog'      => true,
			);
			if ( ! empty( $blogs_to_import ) ) {
				$args['blogs_to_import'] = implode( ',', $blogs_to_import );
			}
			$url = add_query_arg( $args, wp_nonce_url( $url , 'wds-multisite-aggregate' ) );

			$count = $this->strong_red( count( $this->imported ) );
			$finished_blog = $this->strong_red( sprintf( __( 'Blog %d', 'wds-multisite-aggregate' ), (int) $blog_to_populate ), false );
			$next_blog = $this->strong_red( sprintf( __( 'Blog %d', 'wds-multisite-aggregate' ), array_shift( $blogs_to_import ) ), false );

			$msg = $this->heading( sprintf( __( 'Imported %s posts from %s', 'wds-multisite-aggregate' ), $count, $finished_blog ) );
			$desc = $this->notice_description( sprintf( __( 'Please wait while posts from %s are imported.', 'wds-multisite-aggregate' ), $next_blog ) );

			wp_die( $msg . $desc . $this->js_redirect( $url, 1 ) );

		}

		$this->save_user_notice();
		wp_redirect( $this->admin->url() );
		exit;

	}

	/**
	 * run populate function in local blog context because get_permalink does not produce the correct permalinks while switched
	 */
	function populate_posts_from_blog() {
		global $wpdb;
		$valid_key = isset( $_REQUEST['key'] ) ? $_REQUEST['key'] == md5( serialize( get_blog_details( $wpdb->blogid ) ) ) : false;
		if ( ! $valid_key ) {
			return $this->error( 'not a valid key.' );
		}
		$this->_populate_posts_from_blog( array(
			'post_count' => isset( $_GET['post_count'] ) ? (int) $_GET['post_count'] : 0,
		) );
	}

	/**
	 * run populate function in local blog context because get_permalink does not produce the correct permalinks while switched
	 */
	function _populate_posts_from_blog( $args = array() ) {
		global $wpdb;

		$tags_blog_id = $this->options->get( 'tags_blog_id' );
		$tags_blog_enabled = $this->options->get( 'tags_blog_enabled' );

		if ( ! $tags_blog_enabled || ! $tags_blog_id || $tags_blog_id == $wpdb->blogid ) {
			return $this->error( 'Aggregate blog not enabled OR there is no aggregate blog ID OR the current site IS the aggregate blog.' );
		}

		$posts_done = 0;
		$post_count = isset( $args['post_count'] ) ? (int) $args['post_count'] : 0; // post count
		while ( $posts_done < 300 ) {
			$args = array(
				'fields'         => 'ids',
				'offset'         => $post_count + $posts_done,
				'posts_per_page' => 50,
				'post_status'    => 'publish',
			);
			$posts = get_posts( $args );

			if ( empty( $posts ) ) {
				return $this->success( array(
					'posts_done'     => 0,
					'posts_imported' => $this->imported,
				) );
			}

			foreach ( $posts as $post ) {
				if ( 1 != $post && 2 != $post ) {
					$maybe_error = $this->do_post_sync( $post, get_post( $post ) );

					if ( $maybe_error ) {
						return $this->error( $maybe_error );
					}
				}
			}
			$posts_done += 50;
		}

		return $this->success( array(
			'posts_done'     => $posts_done,
			'posts_imported' => $this->imported,
		) );
		exit( $posts_done );
	}

	function do_post_sync( $post_id, $post ) {
		$this->post_id = $post_id;
		$this->post    = $post;

		if ( $this->doing_save_post ) {
			$this->doing_save_post = false;
			return;
		}

		if ( $error = $this->check_for_site_problems() ) {
			return $this->error_if_wpcli( $error );
		}

		// wp_insert_category()
		include_once( ABSPATH . 'wp-admin/includes/admin.php' );

		$allowed_post_types = apply_filters( 'sitewide_tags_allowed_post_types', array( 'post' => true ) );
		if ( ! isset( $allowed_post_types[ $post->post_type ] ) || ! $allowed_post_types[ $post->post_type ] ) {
			return;
		}

		$post_categories = wp_get_object_terms( $post_id, 'category' );

		if ( $post_categories && ! is_wp_error( $post_categories ) && is_array( $post_categories ) ) {
			$post->post_category = wp_list_pluck( $post_categories, 'term_id' );
		} else {
			$post->post_category = wp_get_post_categories( $post_id );
		}

		$post->tags_input = implode( ', ', wp_get_post_tags( $post_id, array( 'fields' => 'names', ) ) );

		global $wpdb;
		$post_blog_id = $wpdb->blogid;
		$post->guid = "{$post_blog_id}.{$post_id}";

		$this->meta_to_sync = array();
		$meta_keys = apply_filters( 'sitewide_tags_meta_keys', $this->options->get( 'tags_blog_postmeta', array() ) );
		if ( is_array( $meta_keys ) && ! empty( $meta_keys ) ) {
			foreach ( $meta_keys as $key ) {
				$this->meta_to_sync[ $key ] = get_post_meta( $post->ID, $key, true );
			}
		}
		unset( $meta_keys );

		$this->meta_to_sync['permalink'] = get_permalink( $post_id );
		$this->meta_to_sync['blogid'] = $post_blog_id; // org_blog_id

		if ( $this->options->get( 'tags_blog_thumbs' ) && ( $thumb_id = get_post_thumbnail_id( $post->ID ) ) ) {

			$thumb_sizes = apply_filters( 'sitewide_tags_thumb_size', array(
				'thumbnail' ) );

			// back-compat
			if ( is_string( $thumb_sizes ) ) {
				$this->meta_to_sync['thumbnail_html'] = wp_get_attachment_image( $thumb_id, $thumb_sizes );
			} else {
				// back-compat
				$this->meta_to_sync['thumbnail_html'] = wp_get_attachment_image( $thumb_id, 'thumbnail' );
			}

			// new hawtness
			foreach ( (array) $thumb_sizes as $thumb_size ) {
				$this->meta_to_sync[ "thumbnail_html_$thumb_size" ] = wp_get_attachment_image( $thumb_id, $thumb_size );
			}
		}

		// custom taxonomies
		$taxonomies = apply_filters( 'sitewide_tags_custom_taxonomies', array() );
		if ( ! empty( $taxonomies ) && 'publish' == $post->post_status ) {
			$registered_tax = array_diff( get_taxonomies(), array( 'post_tag', 'category', 'link_category', 'nav_menu' ) );
			$custom_tax = array_intersect( $taxonomies, $registered_tax );
			$tax_input = array();
			foreach ( $custom_tax as $tax ) {
				$terms = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'names' ) );
				if ( empty( $terms ) ) {
					continue;
				}
				if ( is_taxonomy_hierarchical( $tax ) ) {
					$tax_input[ $tax ] = $terms;
				} else {
					$tax_input[ $tax ] = implode( ',', $terms );
				}
			}
			if ( ! empty( $tax_input ) ) {
				$post->tax_input = $tax_input;
			}
		}
		$tags_blog_id = $this->options->get( 'tags_blog_id' );

		switch_to_blog( $tags_blog_id );

		$category_ids = array();
		if ( is_array( $post_categories ) && ! empty( $post_categories ) && 'publish' == $post->post_status ) {
			foreach ( $post_categories as $t => $category ) {
				$term = get_term_by( 'slug', $category->slug, 'category' );
				$term = apply_filters( 'sitewide_tags_get_term', $term, $category, $this );
				if ( $this->debug ) {
					echo '<xmp>$category_to_migrate: '. print_r( $category, true ) .'</xmp>';
					echo '<xmp>$term_on_aggregate: '. print_r( $term, true ) .'</xmp>';
				}
				if ( $term && 0 == $term->parent ) {
					$category_ids[] = $term->term_id;
					continue;
				}

				// Here is where we insert the category if necessary
				$category_id = wp_insert_category( array(
					'cat_name'             => $category->name,
					'category_description' => $category->name,
					'category_nicename'    => $category->slug,
					'category_parent'      => '',
				), true );

				if (
					is_wp_error( $category_id )
					&& false !== stripos( $category_id->get_error_message(), 'already exists' )
					&& is_numeric( $category_id->get_error_data() )
				) {
					$category_ids[] = $category_id->get_error_data();
				} elseif ( is_numeric( $category_id ) ) {
					$category_ids[] = $category_id;
				}
			}
		}

		if ( $this->debug ) {
			wp_die( '<xmp>$category_ids_to_add_to_post: '. print_r( $category_ids, true ) .'</xmp>' );
		}

		$global_post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE guid IN (%s,%s)", $post->guid, esc_url( $post->guid ) ) );
		if ( 'publish' != $post->post_status && is_object( $global_post ) && isset( $global_post->ID ) ) {
			wp_delete_post( $global_post->ID );
		} else {
			if ( isset( $global_post->ID ) && $global_post->ID != '' ) {
				$post->ID = $global_post->ID; // editing an old post

				foreach ( array_keys( $this->meta_to_sync ) as $key ) {
					delete_post_meta( $global_post->ID, $key );
				}
			} else {
				unset( $post->ID ); // new post
			}
		}
		if ( 'publish' == $post->post_status ) {
			$post->ping_status = 'closed';
			$post->comment_status = 'closed';

			// Use the category IDs in the post
			$post->post_category = $category_ids;
			$this->doing_save_post = true;
			$post_id = wp_insert_post( $post, true );

			if ( ! is_wp_error( $post_id ) ) {
				// do meta sync action
				do_action( 'wds_multisite_aggregate_post_sync', $post_id, $post, $this->meta_to_sync );
				$this->imported[] = $post;
			}
		}

		restore_current_blog();
	}

	function check_for_site_problems() {
		global $wpdb;

		if ( ! $this->options->get( 'tags_blog_enabled' ) ) {
			return __( 'Multisite Aggregate not enabled.', 'wds-multisite-aggregate' );
		}

		$tags_blog_id = $this->options->get( 'tags_blog_id' );
		if ( ! $tags_blog_id || $tags_blog_id == $wpdb->blogid ) {
			return __( 'No Multisite Aggregate blog ID, or current blog is Multisite Aggregate blog.', 'wds-multisite-aggregate' );
		}

		$blogs_to_import = $this->get_blogs_to_import();
		if ( ! in_array( (int) $wpdb->blogid, $blogs_to_import ) ) {
			return __( 'Blog ID is not saved to the "Blogs to Aggregate" setting.', 'wds-multisite-aggregate' );
		}

		$blog_status = get_blog_status( $wpdb->blogid, 'public' );

		if ( 1 != $blog_status && ( 0 != $blog_status || 1 == $this->options->get( 'tags_blog_public' ) || 0 == $this->options->get( 'tags_blog_pub_check' ) ) ) {
			return __( "This blog's status is not set to public", 'wds-multisite-aggregate' );
		}

		return false;
	}

	public function save_meta_fields( $post_id, $post, $meta_to_sync ) {
		$updated = array();
		foreach ( (array) $meta_to_sync as $key => $value ) {
			if ( $value ) {
				$updated[ $key ] = add_post_meta( $post_id, $key, $value );
			}
		}
		// return $this->error( compact( 'post_id', 'updated', 'post' ) );
	}

	function do_comment_sync( $post_id ) {
		global $wpdb;

		$tags_blog_id = $this->options->get( 'tags_blog_id' );

		if ( ! $tags_blog_id || $tags_blog_id == $wpdb->blogid ) {
			return;
		}

		$post_blog_id = $wpdb->blogid;

		$new = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved = '1'", $post_id ) );

		switch_to_blog( $tags_blog_id );

		$guid = "{$post_blog_id}.{$post_id}";

		$global_post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE guid IN (%s,%s)", $guid, esc_url( $guid ) ) );

		if ( null !== $global_post_id ) {
			$wpdb->update( $wpdb->posts, array( 'comment_count' => $new ), array( 'ID' => $global_post_id ) );
			clean_post_cache( $global_post_id );
		}

		restore_current_blog();
	}

	function sync_post_delete( $post_id ) {
		global $wpdb;
		/*
		 * what should we do if a post will be deleted and the tags blog feature is disabled?
		 * need an check if we have a post on the tags blog and if so - delete this
		 */
		$tags_blog_id = $this->options->get( 'tags_blog_id' );

		if ( ! $tags_blog_id || $wpdb->blogid == $tags_blog_id ) {
			return;
		}

		$post_blog_id = $wpdb->blogid;
		switch_to_blog( $tags_blog_id );

		$guid = "{$post_blog_id}.{$post_id}";

		$global_post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE guid IN (%s,%s)", $guid, esc_url( $guid ) )  );

		if ( null !== $global_post_id ) {
			wp_delete_post( $global_post_id );
		}

		restore_current_blog();
	}

	protected function get_blogs_to_import() {
		if ( $this->options->get( 'populate_all_blogs' ) ) {
			global $wpdb;
			return $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs ORDER BY blog_id DESC" );
		}
		// 'all blogs' not checked? check the blogs_to_import option
		return $this->options->get( 'blogs_to_import', array() );
	}

	public function save_user_notice() {
		if ( $this->total_imported ) {
			$class = 'updated';

			$count = $this->strong_red( (int) $this->total_imported );
			$message = $this->heading( sprintf( __( 'Finished importing and/or updating %s posts! %s', 'wds-multisite-aggregate' ), $count, '&nbsp;'. $this->anchor( $this->get_aggregate_site_url(), __( 'Check them out?', 'wds-multisite-aggregate' ) ) ), 3 );

		} else {
			$class = 'error';
			$message = $this->heading( __( 'There are no posts to be aggregated.', 'wds-multisite-aggregate' ), 3 );
		}
		add_site_option( 'wds_multisite_aggregate_message', compact( 'class', 'message' ) );
	}

	public function print_user_notice() {
		if ( $msg = get_site_option( 'wds_multisite_aggregate_message' ) ) {
			printf( '<div id="message" class="%s">%s</div>', $msg['class'], $msg['message'] );
			delete_site_option( 'wds_multisite_aggregate_message' );
		}
	}

	protected function js_redirect( $url, $time_in_seconds = .5 ) {
		return sprintf( '
		<script type="text/javascript">
			window.setTimeout( function() {
				window.location.href = "%s";
			}, %d );
		</script>
		', $url, $time_in_seconds * 1000 );
	}

	protected function notice_description( $text, $large = true ) {
		$style = $large ? 'style="font-size: 120%;"' : '';
		return '<p class="description" '. $style .'>'. $text .'</p>';
	}

	protected function strong_red( $text, $red = true ) {
		$style = $red ? ' style="color:red;"' : '';
		return sprintf( '<strong%s>%s</strong>', $style, $text );
	}

	protected function anchor( $url, $text ) {
		return sprintf( '<a href="%s">%s</a>', esc_url( $url ), $text );
	}

	protected function heading( $text, $level = 1 ) {
		return sprintf( '<h%2$d>%1$s</h%2$d>', $text, absint( $level ) );
	}

	protected function get_aggregate_site_url() {
		return get_site_url( $this->options->get( 'tags_blog_id' ) );
	}

	protected function error( $data ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return array( 'success' => false, 'data' => $data );
		}
		wp_send_json_error( $data );
	}

	protected function success( $data ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return array( 'success' => true, 'data' => $data );
		}
		wp_send_json_success( $data );
	}

	protected function error_if_wpcli( $data ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return array( 'success' => false, 'data' => $data );
		}
	}

	/**
	 * Magic getter for our object.
	 *
	 * @param string $field
	 *
	 * @throws Exception Throws an exception if the field is invalid.
	 *
	 * @return mixed
	 */
	public function __get( $field ) {
		switch ( $field ) {
			case 'options':
			case 'admin':
			case 'remove':
			case 'frontend':
			case 'debug':
				return $this->{$field};
			default:
				return new WP_Error( 'wds_multisite_aggregate', __( 'Inaccessible Parameter', 'wds-multisite-aggregate' ) );
		}
	}

}

$WDS_Multisite_Aggregate = new WDS_Multisite_Aggregate();
$WDS_Multisite_Aggregate->hooks();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	include_once( dirname( __FILE__ ) .'/includes/WDS_Multisite_Aggregate_CLI.php' );
}
