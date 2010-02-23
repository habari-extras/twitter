<?php
/**
 * Twitter Plugin
 *
 * Lets you show your current Twitter status in your theme, as well
 * as an option automatically post your new posts to Twitter.
 *
 * Usage: <?php $theme->twitter(); ?> to show your latest tweet in a theme.
 * A sample tweets.php template is included with the plugin.  This can be copied to your
 * active theme and modified.
 *
 **/

class Twitter extends Plugin
{
	

	/**
	 * Add update beacon support
	 **/
	public function action_update_check()
	{
	 	Update::add( 'Twitter', 'DD2774BA-96ED-11DC-ABEF-3BAA56D89593', $this->info->version );
	}

	/**
	 * Sets the new 'hide_replies' option to '0' to mimic current, non-reply-hiding
	 * functionality, and 'twitter__limit' to '1', again to match earlier results.
	 **/

	public function action_plugin_activation( $file )
	{
		if(Plugins::id_from_file($file) == Plugins::id_from_file(__FILE__)) {
			if ( Options::get( 'twitter__hide_replies' ) == null ) {
				Options::set( 'twitter__hide_replies', 0 );
			}
			if (( Options::get( 'twitter__linkify_urls' ) == null ) or ( Options::get( 'twitter__linkify_urls' ) > 1 )) {
				Options::set( 'twitter__linkify_urls', 0 );
			}
			if ( Options::get( 'twitter__hashtags_query' ) == null ) {
				Options::set( 'twitter__hashtags_query', 'http://hashtags.org/search?query=' );
			}
			if ( !Options::get( 'twitter__limit' ) ) {
				Options::set( 'twitter__limit', 1 );
			}
		}
	}

	/**
	 * Simple plugin configuration
	 * @return FormUI The configuration form
	 **/
	public function configure()
	{
		$ui = new FormUI( 'twitter' );

		$twitter_username = $ui->append( 'text', 'username', 'twitter__username', 
			_t( 'Twitter Username:', 'twitter' ) );
		$twitter_password = $ui->append( 'password', 'password', 'twitter__password', 
			_t( 'Twitter Password:', 'twitter' ) );

		$post_fieldset = $ui->append( 'fieldset', 'post_settings', _t( 'Autopost Updates from Habari', 'twitter' ) );

		$twitter_post = $post_fieldset->append( 'checkbox', 'post_status', 'twitter__post_status', _t( 'Autopost to Twitter:', 'twitter' ) );

		$twitter_post = $post_fieldset->append( 'text', 'prepend', 'twitter__prepend', _t( 'Prepend to Autopost:', 'twitter' ) );
		$twitter_post->value = "New Blog Post:";

		$tweet_fieldset = $ui->append( 'fieldset', 'tweet_settings', _t( 'Displaying Status Updates', 'twitter' ) );
	
		$twitter_show = $tweet_fieldset->append( 'checkbox', 'show', 'twitter__show', _t( 'Display twitter status updates in Habari', 'twitter' ) );

		$twitter_limit = $tweet_fieldset->append( 'select', 'limit', 'twitter__limit', _t( 'Number of updates to show', 'twitter' ) );
		$twitter_limit->options = array_combine(range(1, 20), range(1, 20));

		$twitter_show = $tweet_fieldset->append( 'checkbox', 'hide_replies', 'twitter__hide_replies', _t( 'Do not show @replies', 'twitter' ) );

		$twitter_show = $tweet_fieldset->append( 'checkbox', 'linkify_urls', 'twitter__linkify_urls', 
			_t('Linkify URLs') );

		$twitter_hashtags = $tweet_fieldset->append( 'text', 'hashtags_query', 'twitter__hashtags_query', _t( '#hashtags query link:', 'twitter' ) );

		$twitter_cache_time = $tweet_fieldset->append( 'text', 'cache', 'twitter__cache', _t( 'Cache expiry in seconds:', 'twitter' ) );

		$ui->on_success( array( $this, 'updated_config' ) );
		$ui->append( 'submit', 'save', _t( 'Save', 'twitter' ) );
		return $ui;
	}

	/**
	 * Add Twitter options to the user profile page.
	 * Should only be displayed when a user accesses their own profile.
	**/
	public function action_form_user( $form, $edit_user )
	{
		$twitter_name = ( isset( $edit_user->info->twitter_name ) ) ? $edit_user->info->twitter_name : '';
		$twitter_pass = ( isset( $edit_user->info->twitter_pass ) ) ? $edit_user->info->twitter_pass : '';

		$twitter = $form->insert( 'page_controls', 'wrapper', 'twitter', _t( 'Twitter', 'twitter' ) );
		$twitter->class = 'container settings';
		$twitter->append( 'static', 'twitter', '<h2>' . htmlentities( _t( 'Twitter', 'twitter' ), ENT_COMPAT, 'UTF-8' ) . '</h2>' );
		
		$form->move_after( $twitter, $form->change_password );
		$twitter_name = $form->twitter->append( 'text', 'twitter_name', 'null:null', _t( 'Twitter Username', 'twitter' ), 'optionscontrol_text' );
		$twitter_name->class[] = 'item clear';
		$twitter_name->value = $edit_user->info->twitter_name;
		$twitter_name->charlimit = 64;
		$twitter_name->helptext = _t( 'Used for autoposting your published entries to Twitter' );

		$twitter_pass = $form->twitter->append( 'text', 'twitter_pass', 'null:null', _t( 'Twitter Password', 'twitter' ), 'optionscontrol_text' );
		$twitter_pass->class[] = 'item clear';
		$twitter_pass->type = 'password';
		$twitter_pass->value = $edit_user->info->twitter_pass;
		$twitter_pass->helptext = '';
	}

	/**
	 * Give the user a session message to confirm options were saved.
	 **/
	public function updated_config( FormUI $ui )
	{
		Session::notice( _t( 'Twitter options saved.', 'twitter' ) );
		$ui->save();
	}

	/**
	 * Add the Twitter options to the list of valid field names.
	 * This causes adminhandler to recognize the Twitter fields and
	 * to set the userinfo record appropriately
	**/
	public function filter_adminhandler_post_user_fields( $fields )
	{
		$fields['twitter_name'] = 'twitter_name';
		$fields['twitter_pass'] = 'twitter_pass';
		return $fields;
	}

	/**
	 * Post a status to Twitter
	 * @param string $tweet The new status to post
	 **/
	public function post_status( $tweet, $name, $pw )
	{
		$request = new RemoteRequest( 'http://twitter.com/statuses/update.xml', 'POST' );
		$request->add_header( array( 'Authorization' => 'Basic ' . base64_encode( "{$name}:{$pw}" ) ) );
		$request->set_body( 'source=habari&status=' . urlencode( $tweet ) );
		$request->execute();
	}

	/**
	 * React to the update of a post status to 'published'
	 * @param Post $post The post object with the status change
	 * @param int $oldvalue The old status value
	 * @param int $newvalue The new status value
	 **/
	public function action_post_update_status( $post, $oldvalue, $newvalue )
	{
		if ( is_null( $oldvalue ) ) return;
		if ( $newvalue == Post::status( 'published' ) && $post->content_type == Post::type('entry') && $newvalue != $oldvalue ) {
			if ( Options::get( 'twitter__post_status' ) == '1' ) {
				$user = User::get_by_id( $post->user_id );
				if ( ! empty( $user->info->twitter_name ) && ! empty( $user->info->twitter_pass ) ) {
					$name = $user->info->twitter_name;
					$pw = $user->info->twitter_pass;
				} else {
					$name = Options::get( 'twitter__username' );
					$pw = Options::get( 'twitter__password' );
				}
				$this->post_status( Options::get( 'twitter__prepend' ) . $post->title . ' ' . $post->permalink, $name, $pw );
			}
		}
	}

	public function action_post_insert_after( $post )
	{
		return $this->action_post_update_status( $post, -1, $post->status );
	}

	/**
	 * Add last Twitter status, time, and image to the available template vars
	 * @param Theme $theme The theme that will display the template
	 **/
	public function theme_twitter( $theme )
	{
		$notices = array();
		if ( Options::get( 'twitter__show' ) && Options::get( 'twitter__username' ) != '' ) {
			$twitter_url = 'http://twitter.com/statuses/user_timeline/' . urlencode( Options::get( 'twitter__username' ) ) . '.xml';
			
			// We only need to get a single tweet if we're hiding replies (otherwise we can rely on the maximum returned and hope there's a non-reply)
			if ( !Options::get( 'twitter__hide_replies' ) &&  Options::get( 'twitter__limit' ) ) {
				$twitter_url .= '?count=' . Options::get( 'twitter__limit' );
			}

			if ( Cache::has( 'twitter_notices' ) ) {
				$notices = Cache::get( 'twitter_notices' );
			}
			else {
				try {
					$r = new RemoteRequest( $twitter_url );
					$r->set_timeout( 10 );
					$r->execute();
					$response = $r->get_response_body();
					
					$xml = @new SimpleXMLElement( $response );
					// Check we've got a load of statuses returned
					if ( $xml->getName() === 'statuses' ) {
						foreach ( $xml->status as $status ) {							
							if ( ( !Options::get( 'twitter__hide_replies' ) ) || ( strpos( $status->text, '@' ) === FALSE ) ) {
								$notice = (object) array (
									'text' => (string) $status->text, 
									'time' => (string) $status->created_at, 
									'image_url' => (string) $status->user->profile_image_url,
									'id' => (int) $status->id,
									'permalink' => 'http://twitter.com/' . Options::get( 'twitter__username' ) . '/status/' . (string) $status->id
								);
								
								$notices[] = $notice;
								if ( Options::get( 'twitter__hide_replies' ) && count($notices) >= Options::get( 'twitter__limit' ) ) {
									break;
								}
							}
							else {
							// it's a @. Keep going.
							}
						}
						if ( !$notices ) {		
							$notice = (object) array (
								'text' => _t( 'No non-replies replies available from Twitter.', 'twitter' ), 
								'time' => '', 
								'image_url' => ''
							);
						}
					}
					// You can get error as a root element if Twitter is in maintenance mode.
					else if ( $xml->getName() === 'error' ) {
						$notice = (object) array (
							'text' => (string) $xml, 
							'time' => '', 
							'image_url' => ''
						);
					}
					// Um, yeah. We shouldn't ever hit this.
					else {
						$notice = (object) array (
							'text' => 'Received unexpected XML from Twitter.', 
							'time' => '', 
							'image_url' => ''
						);
					}
				}
				catch ( Exception $e ) {
					$notice = (object) array (
						'text' => 'Unable to contact Twitter.', 
						'time' => '', 
						'image_url' => ''
					);
				}
				if (!$notices)
					$notices[] = $notice;
				// Cache (even errors) to avoid hitting rate limit.
				Cache::set( 'twitter_notices', $notices, (int) Options::get( 'twitter__cache' ), true );
			}
		}
		else {
			$notice = (object) array (
				'text' => _t('Please set your username in the <a href="%s">Twitter plugin config</a>', array( URL::get( 'admin' , 'page=plugins&configure=' . $this->plugin_id . '&configaction=Configure' ) . '#plugin_' . $this->plugin_id ) , 'twitter' ), 
				'time' => '', 
				'image_url' => ''
			);
			$notices[] = $notice;
		}
		if ( Options::get( 'twitter__linkify_urls' ) != FALSE ) {
			foreach ($notices as $notice) {
				/* link to all http: */
				$notice->text = preg_replace( '%https?://\S+?(?=(?:[.:?"!$&\'()*+,=]|)(?:\s|$))%i', "<a href=\"$0\">$0</a>", $notice->text ); 
				/* link to usernames */
				$notice->text = preg_replace( "/(?<!\w)@([\w-_.]{1,64})/", "@<a href=\"http://twitter.com/$1\">$1</a>", $notice->text ); 
				/* link to hashtags */
				$notice->text = preg_replace( '/(?<!\w)#((?>\d{1,64}|)[\w-.]{1,64})/', 
				"<a href=\"" . Options::get('twitter__hashtags_query') ."$1\">#$1</a>", $notice->text ); 
			}
		}
		$theme->tweets = $notices;
		return $theme->fetch( 'tweets' );
	}

	/**
	 * On plugin init, add the template included with this plugin to the available templates in the theme
	 */
	public function action_init()
	{
		$this->add_template('tweets', dirname(__FILE__) . '/tweets.php');
	}
}

?>
