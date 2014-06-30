<?php

/**
 * Handles removing posts from aggregate site when a site is removed or made private
 *
 * @since  1.0.0
 */
class WDS_Multisite_Aggregate_Remove {

	public function __construct( WDS_Multisite_Aggregate_Options $options ) {
		$this->options = $options;
	}

	public function hooks() {
		add_action( 'update_option_blog_public', array( $this, 'maybe_remove_blogs_posts' ), 10, 2 );

		// complete blog actions ($blog_id != 0)
		add_action( 'delete_blog', array( $this, 'remove_blogs_posts' ), 10, 1 );
		add_action( 'archive_blog', array( $this, 'remove_blogs_posts' ), 10, 1 );
		add_action( 'deactivate_blog', array( $this, 'remove_blogs_posts' ), 10, 1 );
		add_action( 'make_spam_blog', array( $this, 'remove_blogs_posts' ), 10, 1 );
		add_action( 'mature_blog', array( $this, 'remove_blogs_posts' ), 10, 1 );
		// single post actions ($blog_id == 0)
		add_action( 'transition_post_status', array( $this, 'remove_blogs_posts' ));
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
	function maybe_remove_blogs_posts( $old, $new ) {
		global $wpdb;

		$tags_blog_id = $this->options->get( 'tags_blog_id' );

		if ( !$tags_blog_id ) {
			return;
		}

		// the tags blog
		if ( $tags_blog_id == $wpdb->blogid ) {
			return;
		}

		if ( $new == 0 ) {
			$this->remove_blogs_posts( $wpdb->blogid );
		}
	}

	/**
	 * remove all posts from a given blog ($blog_id != 0)
	 * - used if a blog is deleted or marked as deactivatd, spam, archive, mature
	 * - also runs if a blog is switched to a none public blog (called by
	 *   public_blog_update), more details on public_blog_update
	 * removes some posts if the limit is reached ($blog_id == 0)
	 * - triggered by other actions but without an given blog_id
	 * - number of posts to delete in $max_to_del
	 *
	 * @param $blog_id
	 */
	function remove_blogs_posts( $blog_id = 0 ) {
		global $wpdb;
		$tags_blog_id = $this->options->get( 'tags_blog_id' );
		$max_to_del = 10;

		if ( !$tags_blog_id )
			return;

		/* actions on the tags blog */
		if ( ($blog_id == 0) && ($wpdb->blogid == $tags_blog_id) )
			return;
		if ( $tags_blog_id == $blog_id )
			return;

		switch_to_blog( $tags_blog_id );

		if ( $blog_id != 0 ) {
			$posts = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE guid LIKE '" . $blog_id . ".%' OR guid LIKE '" . esc_url( $blog_id ) . ".%'" );
			if ( is_array( $posts ) && !empty( $posts ) ) {
				foreach( $posts as $p_id ) {
					wp_delete_post( $p_id );
				}
			}
		} else {
			/* delete all posts over the max limit */
			if ( mt_rand( 0, 10 ) ) {
				$allowed_post_types = apply_filters( 'sitewide_tags_allowed_post_types', array( 'post' => true ) );
				if ( is_array( $allowed_post_types ) && !empty( $allowed_post_types ) ) {
					$post_types = array();
					foreach( $allowed_post_types as $k => $v ) {
						if ( $v ) {
							$post_types[] = $k;
						}
					}
					if ( is_array( $post_types ) && !empty( $post_types ) ) {
						if ( count( $post_types ) > 1 )
							$where = "IN ('" . join( "','", $post_types ) . "') ";
						else
							$where = "= '" . $post_types[0] . "' ";
					} else {
						$where = "= 'post' ";
					}
					$posts = $wpdb->get_results( "SELECT ID, guid FROM {$wpdb->posts} WHERE post_status='publish' AND post_type {$where} ORDER BY ID DESC limit " . $this->options->get( 'tags_max_posts', 5000 ) . ", " . $max_to_del );
					if ( is_array( $posts ) && !empty( $posts ) ) {
						foreach( $posts as $p ) {
							if ( preg_match('|^.*\.([0-9]+)$|', $p->guid, $matches) && intval( $matches[1] ) > 0 )
								wp_delete_post( $p->ID );
						}
					}
				}
			}
		}
		restore_current_blog();
	}

}
