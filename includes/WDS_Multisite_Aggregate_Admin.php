<?php

/**
 * Handles setting up the admin page for the Aggregate site settings
 *
 * @since  1.0.0
 */
class WDS_Multisite_Aggregate_Admin {

	protected $settings_url = '';

	public function __construct( WDS_Multisite_Aggregate_Options $options ) {
		$this->options = $options;
		$this->title = __( 'Multisite Aggregate', 'wds-multisite-aggregate' );
	}

	public function hooks() {
		add_action( 'network_admin_menu', array( $this, 'network_add_pages' ) );
		add_action( 'init', array( $this, 'text_domain' ) );
	}

	function network_add_pages() {
		add_submenu_page( 'settings.php', $this->title, $this->title, 'manage_options', 'wds-multisite-aggregate', array( $this, 'admin_page' ) );
	}

	function text_domain() {
		load_muplugin_textdomain( 'wds-multisite-aggregate', MUPLUGINDIR . '/languages' );
	}

	function admin_page() {
		require_once( 'admin-page.php' );
	}

	function url() {
		if ( $this->settings_url ) {
			return $this->settings_url;
		}
		$this->settings_url = add_query_arg( array( 'page' => 'wds-multisite-aggregate' ), network_admin_url( 'settings.php' ) );
		return $this->settings_url;
	}
}
