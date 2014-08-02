<?php

/**
 * Debug handler
 *
 * @since  1.0.1
 */
class WDS_Multisite_Aggregate_Debug {

	public function hooks() {
		add_action( 'post_submitbox_start', array( $this, 'test' ) );
	}

	public function test() {
		echo '<input type="hidden" name="aggregate_debug" value="1"/>';
	}

}
