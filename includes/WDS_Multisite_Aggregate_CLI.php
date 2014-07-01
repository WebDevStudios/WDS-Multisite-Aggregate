<?php

class WDS_Multisite_Aggregate_CLI extends WP_CLI_Command {

	/**
	 * Aggregate posts from a specific blog
	 *
	 * ## Options
	 *
	 * id
	 * : The id of the blog to import
	 *
	 * ## EXAMPLES
	 * 		wp multisite_aggregate blog_id=1
	 *
	 * @synopsis [--id=<int>] [--post_count=<int>]
	 */
	public function from_blog( $args, $assoc_args ) {
		if ( ! isset( $assoc_args['id'] ) || ! is_numeric( $assoc_args['id'] ) ) {
			WP_CLI::error( '"--id=BLOG_ID" is required and must be a numeric value' );
		}
		$this->blog_id = $assoc_args['id'];

		$args = array();
		$args['post_count'] = isset( $assoc_args['post_count'] ) ? (int) $assoc_args['post_count'] : 0;

		switch_to_blog( $this->blog_id );
		$done = $this->plugin()->_populate_posts_from_blog( $args );
		restore_current_blog();

		if ( ! isset( $done['success'] ) ) {
			WP_CLI::error( 'There was an issue: '. print_r( $done ) );
		}

		if ( ! $done['success'] ) {
			$error = isset( $done['data']['data'] ) ? $done['data']['data'] : $done['data'];
			WP_CLI::error( 'Failure: '. print_r( $error, true ) );
		}

		$count = count( (array) $done['data']['posts_imported'] );
		if ( ! $count ) {
			WP_CLI::error( 'No posts could be imported.' );
		}

		WP_CLI::success( sprintf( __( 'Finished importing and/or updating %s posts!', 'wds-multisite-aggregate' ), $count ) );
	}

	/**
	 * Get blogs to Aggregate
	 *
	 * @synopsis
	 */
	public function blogs_to_aggregate() {
		WP_CLI::line( implode( ', ', (array) $this->plugin( 'options' )->get( 'blogs_to_import' ) ) );
	}

	/**
	 * Get Aggregate blog ID
	 *
	 * @synopsis
	 */
	public function aggregate_blog_id() {
		WP_CLI::line( $this->plugin( 'options' )->get( 'tags_blog_id' ) );
	}

	/**
	 * Get a specific option value from the Multisite Aggregate settings (or all of them)
	 *
	 * ## Options
	 *
	 * key
	 * : WDS Multisite Aggregate option key to retrieve. If not included, entire options array is displayed.
	 *
	 * ## EXAMPLES
	 * 		wp multisite_aggregate get_option
	 * 		wp multisite_aggregate get_option --key=tags_blog_id
	 *
	 * @synopsis [--key=<string>]
	 */
	public function get_option( $args, $assoc_args ) {
		$key = isset( $assoc_args['key'] ) && is_string( $assoc_args['key'] ) ? $assoc_args['key'] : 'all';
		WP_CLI::line( print_r( $this->plugin( 'options' )->get( $key ), true ) );
	}

	protected function plugin( $property = '' ) {
		global $WDS_Multisite_Aggregate;
		return $property ? $WDS_Multisite_Aggregate->$property : $WDS_Multisite_Aggregate;
	}
}

WP_CLI::add_command( 'multisite_aggregate', 'WDS_Multisite_Aggregate_CLI' );
