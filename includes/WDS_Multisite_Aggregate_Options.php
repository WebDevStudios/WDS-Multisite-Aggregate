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
}
