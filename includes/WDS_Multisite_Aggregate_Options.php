<?php

class WDS_Multisite_Aggregate_Options {

	function get( $key, $default = false ) {
		static $tags_options = '1';
		if ( $tags_options == '1' ) {
			$tags_options = get_site_option('sitewide_tags_blog');
		}
		if ( is_array( $tags_options ) ) {
			if ( $key == 'all' )
				return $tags_options;
			elseif ( isset( $tags_options[$key] ) )
				return $tags_options[$key];
		}
		return get_site_option($key, $default);
	}

	function update( $key, $value = '', $flush = false ) {
		static $tags_options = '1';
		if ( $tags_options == '1' ) {
			// don't save unless something has changed
			if ( $key === true )
				return;
			$tags_options = get_site_option('sitewide_tags_blog');
		}
		if ( !$tags_options ) {
			$tags_options = array();
		}
		if ( $key !== true)
			$tags_options[$key] = $value;
		if ( $flush || $key === true )
			return update_site_option( 'sitewide_tags_blog', $tags_options );
	}

	public function hooks() {
		add_filter( 'sitewide_tags_allowed_post_types', array( $this, 'pages_filter' ) );
	}

	function pages_filter( $post_types ) {
		if ( $this->options->get( 'tags_blog_pages' ) ) {
			$post_types = array_merge( $post_types, array( 'page' => true ) );
		}
		return $post_types;
	}

	function update_options() {
		global $wpdb, $current_site, $current_user, $wp_version;

		if ( ! isset( $_POST['tags_blog_enabled'] ) || !$_POST['tags_blog_enabled'] ) {
			if ( $this->get( 'tags_blog_enabled' ) != $_POST['tags_blog_enabled'] )
				$this->update( 'tags_blog_enabled', 0, true );
			wp_redirect( add_query_arg( array( 'updated' => '1' ) ) );
			exit;
		}
		$this->update( 'tags_blog_enabled', 1 );

		if ( ( isset( $_POST['tags_blog'] ) || isset( $_POST['tags_blog_main_blog'] ) ) && isset( $_POST['tags_blog_public'] ) ) {
			if ( $_POST['tags_blog_main_blog'] == 1 ) {
				if ( $current_site->blog_id )
					$id = $current_site->blog_id;
				else
					$id = $wpdb->get_var( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = '{$current_site->domain}' AND path = '{$current_site->path}'" );
				if ( $id ) {
					$this->update( 'tags_blog_id', $id );
					$this->update( 'tags_blog_main_blog', 1 );
				} else {
					$this->update( 'tags_blog_main_blog', 0 );
				}
			} else {
				$this->update( 'tags_blog_main_blog', 0 );
				$tags_blog = $_POST['tags_blog'];
				$this->update( 'tags_blog', $tags_blog );
				if ( constant( 'VHOST' ) == 'yes' ) {
					$domain = $tags_blog . '.' . $current_site->domain;
					$path = $current_site->path;
				} else {
					$domain = $current_site->domain;
					$path = trailingslashit( $current_site->path . $tags_blog );
				}
				$tags_blog_id = $wpdb->get_var( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = '$domain' AND path = '$path'" );
				if ( $tags_blog_id ) {
					$this->update( 'tags_blog_id', $tags_blog_id );
				} else {
					$wpdb->hide_errors();
					$id = wpmu_create_blog( $domain, $path, __( 'Network Posts', 'wds-multisite-aggregate' ), $current_user->id , array( 'public' => $_POST['tags_blog_public'] ), $current_site->id);
					$this->update( 'tags_blog_id', $id );
					$wpdb->show_errors();
				}
			}
			$tags_blog_public = (int) $_POST['tags_blog_public'];
			$this->update( 'tags_blog_public', $tags_blog_public );
			update_blog_option( $tags_blog_id, 'blog_public', $tags_blog_public );
			update_blog_status( $tags_blog_id, 'public', $tags_blog_public);
		}

		if ( isset( $_POST['tags_max_posts'] ) )
			$this->update( 'tags_max_posts', (int) $_POST['tags_max_posts'] );

		if ( isset( $_POST['tags_blog_pages'] ) && $this->get( 'tags_blog_pages' ) != $_POST['tags_blog_pages'] ) {
			$this->update( 'tags_blog_pages', (int) $_POST['tags_blog_pages'] );
		}

		if ( isset( $_POST['tags_blog_thumbs'] ) && $this->get( 'tags_blog_thumbs' ) != $_POST['tags_blog_thumbs'] ) {
			$this->update( 'tags_blog_thumbs', (int) $_POST['tags_blog_thumbs'] );
		}

		if ( isset( $_POST['tags_blog_pub_check'] ) && $this->get( 'tags_blog_pub_check' ) != $_POST['tags_blog_pub_check'] ) {
			if ( $tags_blog_public == 0 )
				$this->update( 'tags_blog_pub_check', (int) $_POST['tags_blog_pub_check'] );
			else
				$this->update( 'tags_blog_pub_check', 0 );
		}

		if ( isset( $_POST['tags_blog_postmeta'] ) && '' != $_POST['tags_blog_postmeta'] ) {
			$meta_keys = split( "\n", stripslashes( $_POST['tags_blog_postmeta'] ) );
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
