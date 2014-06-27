<div class="wrap">
	<?php
	$options = $this->options;
	if( !empty( $_REQUEST['updated'] ) && '1' == $_REQUEST['updated'] )
		echo '<div id="message" class="updated fade"><p><strong>' . __( 'Settings updated.', 'wpmu-mu-sitewide-tags' ) . '</strong></p></div>';
	?>
	<h2><?php echo $this->title; ?></h2>
	<form name="global_tags" action="" method="post">
		<input type="hidden" name="action" value="sitewidetags" />
		<?php
		wp_nonce_field('wds-multisite-aggregate');
		if( $options->get( 'tags_blog_public' ) === null )
			add_site_option( 'sitewide_tags_blog', array( 'tags_blog_public' => 1 ) );

		$tags_blog_enable = $options->get( 'tags_blog_enabled' );
		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('Tags Blog','wds-multisite-aggregate') ?></th>
				<td>
					<label><input name="tags_blog_enabled" type="checkbox" id="tags_blog_enabled" value="1" <?php if( $tags_blog_enable == 1 ) { echo "checked='checked'"; } ?> /> <strong><?php _e("Enabled","wpmu-sitewide-tags"); ?></strong></label><br />
		<?php
		if( !$tags_blog_enable ) {
			echo "</td></tr></table>
				<div class='submit'><input class='button-primary' type='submit' value='" . __( 'Update Settings', 'wpmu-mu-sitewide-tags' ) . "' /></div>
				</form>
				</div>";
			return false;
		}
		$tags_blog_public = $options->get( 'tags_blog_public' );
		$tags_blog_pages = $options->get( 'tags_blog_pages' );
		$tags_blog_thumbs = $options->get( 'tags_blog_thumbs' );
		$tags_blog_pub_check = $options->get( 'tags_blog_pub_check' );
		$tags_blog_postmeta = $options->get( 'tags_blog_postmeta' );
		?>
		<p class="description"><?php _e( "You can create your post archive in a specific 'tags' blog of your choosing, or you can use the main blog of your site. Each has it's own pros and cons.","wpmu-sitewide-tags"); ?></p>
		<ol><li><p><input name="tags_blog" type="text" id="tags_blog" style="width: 35%" value="<?php echo esc_attr( $options->get( 'tags_blog', 'Network Posts' ) ); ?>" size="45" /></p>
		<p class="description"><?php _e('<strong>Blogname</strong> of the blog your global tags and posts will live in. Blog will be created.','wds-multisite-aggregate') ?></p></li>
		<li><p><label><input name="tags_blog_main_blog" type="checkbox" id="tags_blog_main_blog" value="1" <?php if( $options->get( 'tags_blog_main_blog', 0 ) == 1 ) { echo "checked='checked'"; } ?> /> <strong><?php _e( "Post to main blog","wpmu-sitewide-tags" ); ?></strong></label></p>
		<p class="description"><?php _e('Create posts in your main blog. All posts will appear on the front page of your site. Remember to to add a post loop to home.php in the theme directory if it exists.','wds-multisite-aggregate') ?></p></li></ol>
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
					<label><input name="tags_blog_pages" type="checkbox" id="tags_blog_pages" value="1" <?php if( $tags_blog_pages == 1 ) { echo "checked='checked'"; } ?> /> <strong><?php _e("Enabled","wpmu-sitewide-tags"); ?></strong></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Include Post Thumbnails','wds-multisite-aggregate') ?></th>
				<td>
					<label><input name="tags_blog_thumbs" type="checkbox" id="tags_blog_thumbs" value="1" <?php if( $tags_blog_thumbs == 1 ) { echo "checked='checked'"; } ?> /> <strong><?php _e("Enabled","wpmu-sitewide-tags"); ?></strong></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Privacy','wds-multisite-aggregate') ?></th>
				<td>
					<p><label><input type='radio' name='tags_blog_public' value='1' <?php echo ( $tags_blog_public == 1 ? 'checked="checked"' : '' ) ?> /> <?php _e('Tags pages can be indexed by search engines.','wds-multisite-aggregate')?></label></p>
					<p><label><input type='radio' name='tags_blog_public' value='0' <?php echo ( $tags_blog_public == 0 ? 'checked="checked"' : '' ) ?> /> <?php _e('Tags pages will not be indexed by search engines.','wds-multisite-aggregate')?></label></p>

					<p><?php _e('Will your tags pages be visible to Google and other search engines?','wds-multisite-aggregate'); ?></p>

		<?php if( $tags_blog_public == 1 ) { ?>
					<input name="tags_blog_pub_check" type="hidden" value="0" />
		<?php } else { ?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Non-Public Blogs','wds-multisite-aggregate') ?></th>
				<td>
					<p><label><input name="tags_blog_pub_check" type="checkbox" id="tags_blog_pub_check" value="1" <?php if( $tags_blog_pub_check == 1 ) { echo "checked='checked'"; } ?> /> <strong><?php _e("Enabled","wpmu-sitewide-tags"); ?></strong></label></p>
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
		</table>
		<div class='submit'><input class='button-primary' type='submit' value='<?php _e( 'Update Settings', 'wpmu-mu-sitewide-tags' ) ?>' /></div>
	</form>
	<form name="global_tags" action="" method="GET">
		<input type="hidden" name="page" value="wds-multisite-aggregate" />
		<input type="hidden" name="action" value="populate_from_blogs" />
		<?php
		wp_nonce_field('wds-multisite-aggregate');
		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('Populate Posts','wds-multisite-aggregate') ?></th>
				<td>
					<p><label><?php printf( __( 'Blog ID: %s <strong>OR</strong>', 'wds-multisite-aggregate' ), '<input name="blog_to_populate" type="text" id="blog_to_populate" style="width: 15%" value="" size="5" /></label><label>' ); ?>&nbsp;&nbsp;<input name="populate_all_blogs" type="checkbox" id="populate_all_blogs" value="1" />&nbsp;<?php _e( 'All blogs', 'wds-multisite-aggregate' ); ?></label></p>
					<p><?php _e( 'Add posts from the blog named above or all blogs to the sitewide tags blog. This page will reload while copying the posts and may take a long time to finish.', 'wds-multisite-aggregate' ) ?><</p>
					<p><strong><em><?php _e( 'Note: Depending on your server resources, you may need to turn off other plugins while using the populate feature.', 'wds-multisite-aggregate' ) ?></em></strong></p>
				</td>
			</tr>
		</table>
		<div class='submit'><input class='button-primary' type='submit' value='<?php _e( 'Poplate Posts', 'wpmu-mu-sitewide-tags' ) ?>' /></div>
	</form>
</div>
<?php
