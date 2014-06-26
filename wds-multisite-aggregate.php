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

class WDS_Multisite_Aggregate {

	public function __construct() {
		spl_autoload_register( array( $this, 'autoload_classes' ) );

		$this->admin = new WDS_Multisite_Aggregate_Admin();
		$this->admin->hooks();
		$this->options = new WDS_Multisite_Aggregate_Options();
	}

	public function autoload_classes( $classname ) {
		if ( class_exists( $class_name, false ) )
			return;
		// for PHP versions < 5.3
		$dir = dirname( __FILE__ );

		$file = "$dir/includes/$class_name.php";
		if ( file_exists( $file ) ) {
			@include_once( $file );
		}
	}

	function hooks() {
		add_action('save_post', array( $this, 'do_post_sync' ), 10, 2);
		add_action( 'trash_post', array( $this, 'sync_post_delete' ) );
		add_action( 'delete_post', array( $this, 'sync_post_delete' ) );

		if ( !empty( $_GET[ 'page' ] ) && $_GET[ 'page' ] == 'wds-multisite-aggregate' ) {
			add_action( 'admin_init', array( $this, 'update_options' ) );
		}

		if ( !empty( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'sitewidetags-populate' ) {
			add_action( 'init', array( $this, 'populate_posts' ) );
		}

		/* complete blog actions ($blog_id != 0) */
		add_action('delete_blog', array( $this, 'remove_sync_posts' ), 10, 1);
		add_action('archive_blog', array( $this, 'remove_sync_posts' ), 10, 1);
		add_action('deactivate_blog', array( $this, 'remove_sync_posts' ), 10, 1);
		add_action('make_spam_blog', array( $this, 'remove_sync_posts' ), 10, 1);
		add_action('mature_blog', array( $this, 'remove_sync_posts' ), 10, 1);
		/* single post actions ($blog_id == 0) */
		add_action("transition_post_status", array( $this, 'remove_sync_posts' ));

		add_action('update_option_blog_public', array( $this, 'public_blog_update' ), 10, 2);

		add_filter( 'post_link', array( $this, 'post_link' ), 10, 2 );
		add_filter( 'page_link', array( $this, 'post_link' ), 10, 2 );

		add_filter( 'sitewide_tags_allowed_post_types', array( $this, 'pages_filter' ) );
		add_filter('post_thumbnail_html', array( $this, 'thumbnail_link' ), 10, 2);
	}

	function update_options() {
		global $wpdb, $current_site, $current_user, $wp_version;

		$valid_nonce = isset($_REQUEST['_wpnonce']) ? wp_verify_nonce($_REQUEST['_wpnonce'], 'wds-multisite-aggregate') : false;
		if ( !$valid_nonce )
			return false;

		if ( $_GET[ 'action' ] == 'populateblogs' ) {
			$id = isset( $_GET[ 'populate_blog' ] ) ? (int)$_GET[ 'populate_blog' ] : 0;
			$c = isset( $_GET[ 'c' ] ) ? (int)$_GET[ 'c' ] : 0; // blog count
			$p = isset( $_GET[ 'p' ] ) ? (int)$_GET[ 'p' ] : 0; // post count
			$all = isset( $_GET[ 'all' ] ) ? (int)$_GET[ 'all' ] : 0; // all blogs

			if  ( $id == 0 && isset( $_GET[ 'populate_all_blogs' ] ) ) // all blogs.
				$all = 1;

			$tags_blog_id = $this->options->get( 'tags_blog_id' );
			if( !$tags_blog_id )
				return false;

			if( $all )
				$blogs = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs ORDER BY blog_id DESC LIMIT %d,5", $c ) );
			else
				$blogs = array( $id );

			foreach( $blogs as $blog ) {
				if( $blog != $tags_blog_id ) {
					$details = get_blog_details( $blog );
					$url = add_query_arg( array( 'p' => $p, 'action' => 'sitewidetags-populate', 'key' => md5( serialize( $details ) ) ), $details->siteurl );
					$p = 0;
					$post_count = 0;

					$result = wp_remote_get( $url );
					if( isset( $result['body'] ) )
						$post_count = (int)$result['body'];

					if( $post_count ) {
						$p = $post_count;
						break;
					}
				}
				$c++;
			}
			if( !empty( $blogs ) && ( $all || $p ) ) {
				if ( version_compare( $wp_version, '3.0.9', '<=' ) && version_compare( $wp_version, '3.0', '>=' ) )
					$url = admin_url( 'ms-admin.php' );
				else
					$url = network_admin_url( 'settings.php' );

				wp_redirect( wp_nonce_url( $url , 'wds-multisite-aggregate' ) . "&page=sitewidetags&action=populateblogs&c=$c&p=$p&all=$all" );
				die();
			}
			wp_die( 'Finished importing posts into tags blogs!' );
		}

		if( !$_POST[ 'tags_blog_enabled' ] ) {
			if( $this->options->get( 'tags_blog_enabled' ) != $_POST[ 'tags_blog_enabled' ] )
				$this->options->get( 'tags_blog_enabled', 0, true );
			wp_redirect( add_query_arg( array( 'updated' => '1' ) ) );
			exit;
		}
		$this->options->get( 'tags_blog_enabled', 1 );

		if( ( isset( $_POST[ 'tags_blog' ] ) || isset( $_POST[ 'tags_blog_main_blog' ] ) ) && isset( $_POST[ 'tags_blog_public' ] ) ) {
			if( $_POST[ 'tags_blog_main_blog' ] == 1 ) {
				if( $current_site->blog_id )
					$id = $current_site->blog_id;
				else
					$id = $wpdb->get_var( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = '{$current_site->domain}' AND path = '{$current_site->path}'" );
				if( $id ) {
					$this->options->get( 'tags_blog_id', $id );
					$this->options->get( 'tags_blog_main_blog', 1 );
				} else {
					$this->options->get( 'tags_blog_main_blog', 0 );
				}
			} else {
				$this->options->get( 'tags_blog_main_blog', 0 );
				$tags_blog = $_POST[ 'tags_blog' ];
				$this->options->get( 'tags_blog', $tags_blog );
				if( constant( 'VHOST' ) == 'yes' ) {
					$domain = $tags_blog . '.' . $current_site->domain;
					$path = $current_site->path;
				} else {
					$domain = $current_site->domain;
					$path = trailingslashit( $current_site->path . $tags_blog );
				}
				$tags_blog_id = $wpdb->get_var( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = '$domain' AND path = '$path'" );
				if( $tags_blog_id ) {
					update_sitewide_tags_option( 'tags_blog_id', $tags_blog_id );
				} else {
					$wpdb->hide_errors();
					$id = wpmu_create_blog( $domain, $path, __( 'Global Posts','wds-multisite-aggregate' ), $current_user->id , array( "public" => $_POST[ 'tags_blog_public' ] ), $current_site->id);
					update_sitewide_tags_option( 'tags_blog_id', $id );
					$wpdb->show_errors();
				}
			}
			$tags_blog_public = (int)$_POST[ 'tags_blog_public' ];
			update_sitewide_tags_option( 'tags_blog_public', $tags_blog_public );
			update_blog_option( $tags_blog_id, 'blog_public', $tags_blog_public );
			update_blog_status( $tags_blog_id, 'public', $tags_blog_public);
		}

		if( isset( $_POST[ 'tags_max_posts' ] ) )
			update_sitewide_tags_option( 'tags_max_posts', (int)$_POST[ 'tags_max_posts' ] );

		if( $this->options->get( 'tags_blog_pages' ) != $_POST[ 'tags_blog_pages' ] )
			update_sitewide_tags_option( 'tags_blog_pages', (int)$_POST[ 'tags_blog_pages' ] );

		if( $this->options->get( 'tags_blog_thumbs' ) != $_POST[ 'tags_blog_thumbs' ] )
			update_sitewide_tags_option( 'tags_blog_thumbs', (int)$_POST[ 'tags_blog_thumbs' ] );

		if( $this->options->get( 'tags_blog_pub_check' ) != $_POST[ 'tags_blog_pub_check' ] ) {
			if( $tags_blog_public == 0 )
				update_sitewide_tags_option( 'tags_blog_pub_check', (int)$_POST[ 'tags_blog_pub_check' ] );
			else
				update_sitewide_tags_option( 'tags_blog_pub_check', 0 );
		}

		if( $_POST['tags_blog_postmeta'] != '' ) {
			$meta_keys = split( "\n", stripslashes( $_POST[ 'tags_blog_postmeta' ] ) );
			foreach( (array) $meta_keys as $key ) {
				$keys[] = trim( $key );
			}
			update_sitewide_tags_option( "tags_blog_postmeta", $keys );
		} else {
			update_sitewide_tags_option( "tags_blog_postmeta", '' );
		}

		// force write if changes saved
		update_sitewide_tags_option( true );
		wp_redirect( add_query_arg( array( 'updated' => '1' ) ) );
		exit;
	}

	/**
	 * run populate function in local blog context because get_permalink does not produce the correct permalinks while switched
	 */
	function populate_posts() {
		global $wpdb;

		$valid_key = isset( $_REQUEST['key'] ) ? $_REQUEST['key'] == md5( serialize( get_blog_details( $wpdb->blogid ) ) ) : false;
		if ( !$valid_key )
			return false;

		$tags_blog_id = $this->options->get( 'tags_blog_id' );
		$tags_blog_enabled = $this->options->get( 'tags_blog_enabled' );

		if( !$tags_blog_enabled || !$tags_blog_id || $tags_blog_id == $wpdb->blogid )
			exit( '0' );

		$posts_done = 0;
		$p = isset( $_GET[ 'p' ] ) ? (int)$_GET[ 'p' ] : 0; // post count
		while ( $posts_done < 300 ) {
			$posts = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' LIMIT %d, 50", $p + $posts_done ) );

			if ( empty( $posts ) )
				exit( '0' );

			foreach ( $posts as $post ) {
				if ( $post != 1 && $post != 2 )
					$this->do_post_sync( $post, get_post( $post ) );

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

	/**
	 * remove all posts from a given blog ($blog_id != 0)
	 * - used if a blog is deleted or marked as deactivat, spam, archive, mature
	 * - also runs if a blog is switched to a none public blog (called by
	 *   public_blog_update), more details on public_blog_update
	 * removes some posts if the limit is reached ($blog_id == 0)
	 * - triggered by other actions but without an given blog_id
	 * - number of posts to delete in $max_to_del
	 *
	 * @param $blog_id
	 */
	function remove_sync_posts($blog_id = 0) {
		global $wpdb;
		$tags_blog_id = $this->options->get( 'tags_blog_id' );
		$max_to_del = 10;

		if( !$tags_blog_id )
			return;

		/* actions on the tags blog */
		if ( ($blog_id == 0) && ($wpdb->blogid == $tags_blog_id) )
			return;
		if ( $tags_blog_id == $blog_id )
			return;

		switch_to_blog( $tags_blog_id );

		if ( $blog_id != 0 ) {
			$posts = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE guid LIKE '" . $blog_id . ".%' OR guid LIKE '" . esc_url( $blog_id ) . ".%'" );
			if( is_array( $posts ) && !empty( $posts ) ) {
				foreach( $posts as $p_id ) {
					wp_delete_post( $p_id );
				}
			}
		} else {
			/* delete all posts over the max limit */
			if( mt_rand( 0, 10 ) ) {
				$allowed_post_types = apply_filters( 'sitewide_tags_allowed_post_types', array( 'post' => true ) );
				if( is_array( $allowed_post_types ) && !empty( $allowed_post_types ) ) {
					$post_types = array();
					foreach( $allowed_post_types as $k => $v ) {
						if( $v ) {
							$post_types[] = $k;
						}
					}
					if( is_array( $post_types ) && !empty( $post_types ) ) {
						if( count( $post_types ) > 1 )
							$where = "IN ('" . join( "','", $post_types ) . "') ";
						else
							$where = "= '" . $post_types[0] . "' ";
					} else {
						$where = "= 'post' ";
					}
					$posts = $wpdb->get_results( "SELECT ID, guid FROM {$wpdb->posts} WHERE post_status='publish' AND post_type {$where} ORDER BY ID DESC limit " . $this->options->get( 'tags_max_posts', 5000 ) . ", " . $max_to_del );
					if( is_array( $posts ) && !empty( $posts ) ) {
						foreach( $posts as $p ) {
							if( preg_match('|^.*\.([0-9]+)$|', $p->guid, $matches) && intval( $matches[1] ) > 0 )
								wp_delete_post( $p->ID );
						}
					}
				}
			}
		}
		restore_current_blog();
	}

	/**
	 * called as an action if the public state for a blog is switched
	 * - if a blog becomes not public - all posts in the tags blog will be removed
	 * - bug on 1.5.1: update_option_blog_public is only triggered if the public state
	 *   is changed from the backend - from edit blog as siteadmin the action isn't
	 *   running and the state in the blogs backend isn't changed
	 *
	 * @param int $old - old public state
	 * @param int $new - new state, public == 1, not public == 0
	 */
	function public_blog_update($old, $new) {
		global $wpdb;
		$tags_blog_id = $this->options->get( 'tags_blog_id' );

		if( !$tags_blog_id )
			return;

		/* the tags blog */
		if ( $tags_blog_id == $wpdb->blogid )
			return;

		if ($new == 0 ) {
			$this->remove_sync_posts($wpdb->blogid);
		}
	}

	function post_link( $link, $post ) {
		global $wpdb;
		$tags_blog_id = $this->options->get( 'tags_blog_id' );
		if( !$tags_blog_id )
			return $link;

		if( $wpdb->blogid == $tags_blog_id ) {
			if( is_numeric( $post ) )
				$url = get_post_meta( $post, 'permalink', true );
			else
				$url = get_post_meta( $post->ID, "permalink", true );

			if( $url )
				return $url;
		}

		return $link;
	}

	function pages_filter( $post_types ) {
		if( $this->options->get( 'tags_blog_pages' ) )
			$post_types = array_merge( $post_types, array( 'page' => true ) );
		return $post_types;
	}

	function thumbnail_link( $html, $post_id ) {
		global $wpdb;
		$tags_blog_id = $this->options->get( 'tags_blog_id' );
		if( !$tags_blog_id )
			return $html;

		if( $wpdb->blogid == $tags_blog_id ) {
			$thumb = get_post_meta( $post_id, 'thumbnail_html', true );
			if( $thumb )
				return $thumb;
		}
		return $html;
	}

}

$WDS_Multisite_Aggregate = new WDS_Multisite_Aggregate();
$WDS_Multisite_Aggregate->hooks();

