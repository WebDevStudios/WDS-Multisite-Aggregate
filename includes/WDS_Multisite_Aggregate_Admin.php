<?php

class WDS_Multisite_Aggregate_Admin {

	public function hooks() {
		add_action( 'network_admin_menu', array( $this, 'network_add_pages' ) );
		add_action( 'init', array( $this, 'text_domain' ) );
	}

	function network_add_pages() {
		add_submenu_page( 'settings.php', 'Multisite Aggregate', 'Multisite Aggregate', 'manage_options', 'wds-multisite-aggregate', array( $this, 'admin_page' ) );
	}

	function text_domain() {
		load_muplugin_textdomain( 'wds-multisite-aggregate', MUPLUGINDIR . '/languages' );
	}

	function admin_page() {
		require_once( 'admin-page.php' );
	}

}
