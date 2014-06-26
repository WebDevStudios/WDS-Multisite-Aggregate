<?php

class WDS_Multisite_Aggregate_Options {

	function get( $key, $default = false ) {
		static $tags_options = '1';
		if( $tags_options == '1' ) {
			$tags_options = get_site_option('sitewide_tags_blog');
		}
		if( is_array( $tags_options ) ) {
			if( $key == 'all' )
				return $tags_options;
			elseif( isset( $tags_options[$key] ) )
				return $tags_options[$key];
		}
		return get_site_option($key, $default);
	}

	function update( $key, $value = '', $flush = false ) {
		static $tags_options = '1';
		if( $tags_options == '1' ) {
			// don't save unless something has changed
			if( $key === true )
				return;
			$tags_options = get_site_option('sitewide_tags_blog');
		}
		if( !$tags_options ) {
			$tags_options = array();
		}
		if( $key !== true)
			$tags_options[$key] = $value;
		if( $flush || $key === true )
			return update_site_option( 'sitewide_tags_blog', $tags_options );
	}

	public function hooks() {
		if ( ! empty( $_GET['page'] ) && 'wds-multisite-aggregate' == $_GET['page'] ) {
			add_action( 'admin_init', array( $this, 'update_options' ) );
		}
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

			$tags_blog_id = $this->get( 'tags_blog_id' );
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
			if( $this->get( 'tags_blog_enabled' ) != $_POST[ 'tags_blog_enabled' ] )
				$this->get( 'tags_blog_enabled', 0, true );
			wp_redirect( add_query_arg( array( 'updated' => '1' ) ) );
			exit;
		}
		$this->get( 'tags_blog_enabled', 1 );

		if( ( isset( $_POST[ 'tags_blog' ] ) || isset( $_POST[ 'tags_blog_main_blog' ] ) ) && isset( $_POST[ 'tags_blog_public' ] ) ) {
			if( $_POST[ 'tags_blog_main_blog' ] == 1 ) {
				if( $current_site->blog_id )
					$id = $current_site->blog_id;
				else
					$id = $wpdb->get_var( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = '{$current_site->domain}' AND path = '{$current_site->path}'" );
				if( $id ) {
					$this->get( 'tags_blog_id', $id );
					$this->get( 'tags_blog_main_blog', 1 );
				} else {
					$this->get( 'tags_blog_main_blog', 0 );
				}
			} else {
				$this->get( 'tags_blog_main_blog', 0 );
				$tags_blog = $_POST[ 'tags_blog' ];
				$this->get( 'tags_blog', $tags_blog );
				if( constant( 'VHOST' ) == 'yes' ) {
					$domain = $tags_blog . '.' . $current_site->domain;
					$path = $current_site->path;
				} else {
					$domain = $current_site->domain;
					$path = trailingslashit( $current_site->path . $tags_blog );
				}
				$tags_blog_id = $wpdb->get_var( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = '$domain' AND path = '$path'" );
				if( $tags_blog_id ) {
					$this->update( 'tags_blog_id', $tags_blog_id );
				} else {
					$wpdb->hide_errors();
					$id = wpmu_create_blog( $domain, $path, __( 'Global Posts','wds-multisite-aggregate' ), $current_user->id , array( "public" => $_POST[ 'tags_blog_public' ] ), $current_site->id);
					$this->update( 'tags_blog_id', $id );
					$wpdb->show_errors();
				}
			}
			$tags_blog_public = (int)$_POST[ 'tags_blog_public' ];
			$this->update( 'tags_blog_public', $tags_blog_public );
			update_blog_option( $tags_blog_id, 'blog_public', $tags_blog_public );
			update_blog_status( $tags_blog_id, 'public', $tags_blog_public);
		}

		if( isset( $_POST[ 'tags_max_posts' ] ) )
			$this->update( 'tags_max_posts', (int)$_POST[ 'tags_max_posts' ] );

		if( $this->get( 'tags_blog_pages' ) != $_POST[ 'tags_blog_pages' ] )
			$this->update( 'tags_blog_pages', (int)$_POST[ 'tags_blog_pages' ] );

		if( $this->get( 'tags_blog_thumbs' ) != $_POST[ 'tags_blog_thumbs' ] )
			$this->update( 'tags_blog_thumbs', (int)$_POST[ 'tags_blog_thumbs' ] );

		if( $this->get( 'tags_blog_pub_check' ) != $_POST[ 'tags_blog_pub_check' ] ) {
			if( $tags_blog_public == 0 )
				$this->update( 'tags_blog_pub_check', (int)$_POST[ 'tags_blog_pub_check' ] );
			else
				$this->update( 'tags_blog_pub_check', 0 );
		}

		if( $_POST['tags_blog_postmeta'] != '' ) {
			$meta_keys = split( "\n", stripslashes( $_POST[ 'tags_blog_postmeta' ] ) );
			foreach( (array) $meta_keys as $key ) {
				$keys[] = trim( $key );
			}
			$this->update( "tags_blog_postmeta", $keys );
		} else {
			$this->update( "tags_blog_postmeta", '' );
		}

		// force write if changes saved
		$this->update( true );
		wp_redirect( add_query_arg( array( 'updated' => '1' ) ) );
		exit;
	}

}
