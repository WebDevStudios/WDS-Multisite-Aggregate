<?php

/**
 * Settings handler
 *
 * @since  1.0.0
 */
class WDS_Multisite_Aggregate_Options {

	public $to_update = '1';
	public $to_get = '1';

	function get( $key, $default = false ) {
		if ( $this->to_get == '1' ) {
			$this->to_get = get_site_option( 'sitewide_tags_blog' );
		}
		if ( is_array( $this->to_get ) ) {
			if ( $key == 'all' ) {
				return $this->to_get;
			} elseif ( isset( $this->to_get[ $key ] ) ) {
				return $this->to_get[ $key ];
			}
		}
		return get_site_option( $key, $default );
	}

	function update( $key, $value = '', $flush = false ) {
		if ( $this->to_update == '1' ) {
			// don't save unless something has changed
			if ( $key === true ) {
				return;
			}
			$this->to_update = get_site_option( 'sitewide_tags_blog' );
		}
		if ( !$this->to_update ) {
			$this->to_update = array();
		}
		if ( $key !== true ) {
			$this->to_update[ $key ] = $value;
		}
		if ( $flush || $key === true ) {
			return update_site_option( 'sitewide_tags_blog', $this->to_update );
		}
	}

	public function hooks() {
		add_filter( 'sitewide_tags_allowed_post_types', array( $this, 'pages_filter' ) );
	}

	function pages_filter( $post_types ) {
		if ( $this->get( 'tags_blog_pages' ) ) {
			$post_types = array_merge( $post_types, array( 'page' => true ) );
		}
		return $post_types;
	}

	function update_options() {
		global $wpdb, $current_site, $wp_version;

		if ( ! isset( $_POST['tags_blog_enabled'] ) || !$_POST['tags_blog_enabled'] ) {
			if ( isset( $_POST['tags_blog_enabled'] ) && $this->get( 'tags_blog_enabled' ) != $_POST['tags_blog_enabled'] )
				$this->update( 'tags_blog_enabled', 0, true );
			wp_redirect( esc_url_raw( add_query_arg( array( 'updated' => '1' ) ) ) );
			exit;
		}
		$this->update( 'tags_blog_enabled', 1 );

		if ( ( isset( $_POST['tags_blog'] ) || isset( $_POST['tags_blog_main_blog'] ) ) && isset( $_POST['tags_blog_public'] ) ) {
			if ( isset( $_POST['tags_blog_main_blog'] ) && 1 == $_POST['tags_blog_main_blog'] ) {
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
				$aggregate_blog = sanitize_title( $_POST['tags_blog'] );
				$this->update( 'tags_blog', $aggregate_blog );
				if ( constant( 'VHOST' ) == 'yes' ) {
					$domain = $aggregate_blog . '.' . $current_site->domain;
					$path = $current_site->path;
				} else {
					$domain = $current_site->domain;
					$path = trailingslashit( $current_site->path . $aggregate_blog );
				}
				$aggregate_blog_id = $wpdb->get_var( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = '$domain' AND path = '$path'" );
				if ( $aggregate_blog_id ) {
					$this->update( 'tags_blog_id', $aggregate_blog_id );
				} else {
					$wpdb->hide_errors();
					$id = wpmu_create_blog( $domain, $path, __( 'Network Posts', 'wds-multisite-aggregate' ), get_current_user_id() , array( 'public' => $_POST['tags_blog_public'] ), $current_site->id);
					$this->update( 'tags_blog_id', $id );
					$wpdb->show_errors();
				}
			}
			$aggregate_blog_public = (int) $_POST['tags_blog_public'];
			$this->update( 'tags_blog_public', $aggregate_blog_public );
			update_blog_option( $aggregate_blog_id, 'blog_public', $aggregate_blog_public );
			update_blog_status( $aggregate_blog_id, 'public', $aggregate_blog_public);
		}

		$options_as_integers = array(
			'tags_max_posts',
		);
		foreach ( $options_as_integers as $option_key ) {
			if ( $set = $this->make_integer_from_request( $option_key ) ) {
				$this->update( $option_key, $set );
			}
		}

		$options_as_integers_maybe_set = array(
			'tags_blog_thumbs',
			'tags_blog_pages',
			'populate_all_blogs',
		);
		foreach ( $options_as_integers_maybe_set as $option_key ) {
			$set = $this->make_integer_from_request( $option_key );
			if ( $set != $this->get( $option_key ) ) {
				$this->update( $option_key, $set );
			}

		}

		if ( ( $set = $this->make_integer_from_request( 'tags_blog_pub_check' ) ) && $set != $this->get( 'tags_blog_pub_check' ) ) {
			$set = $aggregate_blog_public == 0 ? $set : 0;
			$this->update( 'tags_blog_pub_check', $set );
		}

		if ( isset( $_POST['tags_blog_postmeta'] ) && '' != $_POST['tags_blog_postmeta'] ) {
			$meta_keys = explode( "\n", strip_tags( stripslashes( $_POST['tags_blog_postmeta'] ) ) );
			$this->update( 'tags_blog_postmeta', array_map( 'trim', $meta_keys ) );
		} else {
			$this->update( 'tags_blog_postmeta', '' );
		}

		$blogs_to_import = $this->comma_delimited_to_array_from_request( 'blogs_to_import' );
		$this->update( 'blogs_to_import', $blogs_to_import );

		// force write if changes saved
		$this->update( true );
		wp_redirect( esc_url_raw( add_query_arg( array( 'updated' => '1' ) ) ) );
		exit;
	}

	function make_integer_from_request( $key ) {
		return isset( $_REQUEST[ $key ] ) ? (int) $_REQUEST[ $key ] : 0;
	}

	function comma_delimited_to_array_from_request( $key ) {
		return isset( $_REQUEST[ $key ] ) ? $this->comma_delimited_to_array( $_REQUEST[ $key ] ) : array();
	}

	function comma_delimited_to_array( $string ) {
		$array = (array) explode( ',', $string );
		$array = array_map( 'trim', $array );
		$array = array_map( array( $this, 'make_int' ), $array );
		$array = array_filter( $array );
		return $array;
	}


	function make_int( $item ) {
		return (int) str_ireplace( ' ', '', $item );
	}

}
