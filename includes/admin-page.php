<div class="wrap">
	<?php
	$options = $this->options;
	if( !empty( $_REQUEST['updated'] ) && '1' == $_REQUEST['updated'] )
		echo '<div id="message" class="updated fade"><p><strong>' . __( 'Settings updated.', 'wds-multisite-aggregate' ) . '</strong></p></div>';
	?>
	<h2><?php echo $this->title; ?></h2>
	<form name="global_tags" action="" method="post">
		<input type="hidden" name="action" value="sitewidetags" />
		<?php
		wp_nonce_field('wds-multisite-aggregate');
		if ( $options->get( 'tags_blog_public' ) === null ) {
			add_site_option( 'sitewide_tags_blog', array( 'tags_blog_public' => 1 ) );
		}

		$tags_blog_enable = $options->get( 'tags_blog_enabled' );
		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e( 'Aggregate Blog', 'wds-multisite-aggregate' ); ?></th>
				<td>
					<label><input name="tags_blog_enabled" type="checkbox" id="tags_blog_enabled" value="1" <?php checked( $tags_blog_enable ); ?> /> <strong><?php _e("Enabled","wpmu-sitewide-tags"); ?></strong></label><br />
		<?php
		if( !$tags_blog_enable ) {
			echo "</td></tr></table>
				<div class='submit'><input class='button-primary' type='submit' value='" . __( 'Update Settings', 'wds-multisite-aggregate' ) . "' /></div>
				</form>
				</div>";
			return false;
		}
		// wp_die( '<xmp>: '. print_r( $options->get( 'all' ), true ) .'</xmp>' );
		$tags_blog_postmeta = $options->get( 'tags_blog_postmeta' );
		$blogs_to_import    = $options->get( 'blogs_to_import', array() );
		$all_blogs          = $options->get( 'populate_all_blogs' );
		?>
		<p class="description"><?php _e( "You can create your post archive in a specific 'aggregate' blog of your choosing, or you can use the main blog of your site. Each has it's own pros and cons.","wpmu-sitewide-tags"); ?></p>
		<ol>
			<li><p><input name="tags_blog" type="text" id="tags_blog" style="width: 35%" value="<?php echo esc_attr( $options->get( 'tags_blog', 'Network Posts' ) ); ?>" size="45" /></p>
			<p class="description"><?php _e('<strong>Blogname</strong> of the blog your global tags and posts will live in. Blog will be created.','wds-multisite-aggregate') ?></p></li>
			<li><p><label><input name="tags_blog_main_blog" type="checkbox" id="tags_blog_main_blog" value="1" <?php checked( $options->get( 'tags_blog_main_blog', 0 ) == 1 ); ?> /> <strong><?php _e( "Post to main blog","wpmu-sitewide-tags" ); ?></strong></label></p>
			<p class="description"><?php _e('Create posts in your main blog. All posts will appear on the front page of your site. Remember to to add a post loop to home.php in the theme directory if it exists.','wds-multisite-aggregate') ?></p></li>
		</ol>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Max Posts','wds-multisite-aggregate') ?></th>
				<td>
					<p><input name="tags_max_posts" type="text" id="tags_max_posts" style="width: 15%" value="<?php echo intval( $options->get( 'tags_max_posts', 5000 ) ); ?>" size="5" /></p>
					<p class="description"><?php _e('The maximum number of posts stored in the tags blog.','wds-multisite-aggregate') ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Include Pages','wds-multisite-aggregate') ?></th>
				<td>
					<label><input name="tags_blog_pages" type="checkbox" id="tags_blog_pages" value="1" <?php checked( $options->get( 'tags_blog_pages' ) ); ?> /> <strong><?php _e("Enabled","wpmu-sitewide-tags"); ?></strong></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Include Post Thumbnails','wds-multisite-aggregate') ?></th>
				<td>
					<label><input name="tags_blog_thumbs" type="checkbox" id="tags_blog_thumbs" value="1" <?php checked( $options->get( 'tags_blog_thumbs' ) ); ?> /> <strong><?php _e("Enabled","wpmu-sitewide-tags"); ?></strong></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Privacy','wds-multisite-aggregate') ?></th>
				<td>
					<p><label><input type='radio' name='tags_blog_public' value='1' <?php checked( $options->get( 'tags_blog_public' ) ); ?> /> <?php _e('Aggregated pages can be indexed by search engines.','wds-multisite-aggregate')?></label></p>
					<p><label><input type='radio' name='tags_blog_public' value='0' <?php checked( ! $options->get( 'tags_blog_public' ) ); ?> /> <?php _e('Aggregated pages will not be indexed by search engines.','wds-multisite-aggregate')?></label></p>

					<p><?php _e('Will your tags pages be visible to Google and other search engines?','wds-multisite-aggregate'); ?></p>

		<?php if ( $options->get( 'tags_blog_public' ) ) { ?>
					<input name="tags_blog_pub_check" type="hidden" value="0" />
		<?php } else { ?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Non-Public Blogs','wds-multisite-aggregate') ?></th>
				<td>
					<p><label><input name="tags_blog_pub_check" type="checkbox" id="tags_blog_pub_check" value="1" <?php checked( $options->get( 'tags_blog_pub_check' ) ); ?> /> <strong><?php _e("Enabled","wpmu-sitewide-tags"); ?></strong></label></p>
					<p class="description"><?php _e('Include posts from blogs not indexed by search engines.','wds-multisite-aggregate'); ?></p>
			<?php } ?>
				</td>
				<tr valign="top">
					<th scope="row"><?php _e('Post Meta') ?></th>
					<td>
						<p><textarea name="tags_blog_postmeta" id="tags_blog_postmeta" cols='40' rows='5'><?php echo $tags_blog_postmeta == '' ? '' : @implode( "\n", $tags_blog_postmeta ); ?></textarea></p>
						<p class="description"><?php _e('If you want to copy custom fields with posts. One custom field per line.') ?></p>
					</td>
				</tr>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Blogs to Aggregate', 'wds-multisite-aggregate' ) ?></th>
				<td>
					<p><label><?php _e( 'Blog ID(s):', 'wds-multisite-aggregate' ); ?> <input name="blogs_to_import" type="text" id="blogs_to_import" style="width: 15%" value="<?php echo empty( $blogs_to_import ) ? '' : @implode( ',', $blogs_to_import ); ?>" <?php echo $all_blogs ? 'readonly="readonly"' : ''; ?>/></label></p>
					<p class="description"><?php _e( 'To import from multiple blogs, separate blog ids with commas.', 'wds-multisite-aggregate' ); ?></p>
					<p><label><strong>OR</strong>&nbsp;&nbsp;<input name="populate_all_blogs" type="checkbox" id="populate_all_blogs" value="1" <?php checked( $all_blogs ); ?> />&nbsp;<?php _e( 'All blogs', 'wds-multisite-aggregate' ); ?></label></p>
					<p class="description"><?php _e( 'Add posts from all blogs to the sitewide tags blog.', 'wds-multisite-aggregate' ) ?></p>
				</td>
			</tr>
		</table>
		<div class='submit'><input class='button-primary' type='submit' value='<?php _e( 'Update Settings', 'wds-multisite-aggregate' ) ?>' /></div>
	</form>
	<form name="global_tags" action="" method="GET">
		<input type="hidden" name="page" value="wds-multisite-aggregate" />
		<input type="hidden" name="action" value="populate_from_blogs" />
		<?php
		wp_nonce_field('wds-multisite-aggregate');
		?>
		<table class="form-table">
		</table>
			<tr valign="top">
				<td>
					<p><?php _e( 'This page will reload while copying the posts and may take a long time to finish.', 'wds-multisite-aggregate' ) ?></p>
					<p><strong><em><?php _e( 'Note: Depending on your server resources, you may need to turn off other plugins while using the back-populate feature.', 'wds-multisite-aggregate' ) ?></em></strong></p>
				</td>
			</tr>
		<div class='submit'><input class='button-primary' onclick="return confirm('<?php _e( 'Are you sure you want to back-populate? This could take a while.', 'wds-multisite-aggregate' ); ?>');"type='submit' value='<?php _e( 'Back Populate', 'wds-multisite-aggregate' ) ?>' /></div>
	</form>
</div>
