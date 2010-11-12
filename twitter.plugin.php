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
	const CONSUMER_KEY = 'vk8lo1Zqut4g0q1VA1r0BQ';
	const CONSUMER_SECRET = 'kI6xMYFvV2OUIBqA8F7m1OIhzOfZkPZLjkCmBJy5IE';
	
	/**
	 * Required plugin information
	 * @return array The array of information
	 **/
	public function info()
	{
		return array(
			'name' => 'Twitter',
			'version' => '0.16',
			'url' => 'http://habariproject.org/',
			'author' => 'Habari Community',
			'authorurl' => 'http://habariproject.org/',
			'license' => 'Apache License 2.0',
			'description' => 'Twitter plugin for Habari',
			'copyright' => '2009'
		);
	}

	/**
	 * Add update beacon support
	 **/
	public function action_update_check()
	{
	 	Update::add( 'Twitter', 'DD2774BA-96ED-11DC-ABEF-3BAA56D89593', $this->info->version );
	}

	/**
	 * Add help text to plugin configuration page
	 **/
	public function help()
	{
		$help = _t( "<p>This plugin does two things: Post a notification to your twitter stream linking to a newly published post, and retrieving and displaying your recent status update(s) on your blog. Either or both can be enabled.</p><p>A 'tweets' template file for themes is provided. To display it, use <code><?php $theme->twitter(); ?></code>.</p>" );
		return $help;
	}

	/**
	 * Sets the new 'hide_replies' option to '0' to mimic current, non-reply-hiding
	 * functionality, and 'twitter__limit' to '1', again to match earlier results.
	 **/

	public function action_plugin_activation( $file )
	{
		if( Plugins::id_from_file( $file ) == Plugins::id_from_file( __FILE__ ) ) {
			if ( Options::get( 'twitter__hide_replies' ) == null ) {
				Options::set( 'twitter__hide_replies', 0 );
			}
			if ( ( Options::get( 'twitter__linkify_urls' ) == null ) or ( Options::get( 'twitter__linkify_urls' ) > 1 ) ) {
				Options::set( 'twitter__linkify_urls', 0 );
			}
			if ( Options::get( 'twitter__hashtags_query' ) == null ) {
				Options::set( 'twitter__hashtags_query', 'http://hashtags.org/search?query=' );
			}
			if ( !Options::get( 'twitter__limit' ) ) {
				Options::set( 'twitter__limit', 1 );
			}
			if ( !Options::get( 'twitter__cache' ) ) {
				Options::set( 'twitter__cache', 60 );
			}
		}
	}
	
	/**
     * Add the Configure, Authorize and De-Authorize options for the plugin
     *
     * @access public
     * @param array $actions
     * @param string $plugin_id
     * @return array
     */
    public function filter_plugin_config( $actions, $plugin_id )
    {
		if ( $plugin_id == $this->plugin_id() ) {
			if ( User::identify()->info->twitter__access_token  ) {
				$actions['configure'] = _t( 'Configure' );
				$actions['deauthorize'] = _t( 'De-Authorize' );
			}
			else {
				$actions['authorize'] = _t( 'Authorize' );
			}
		}
		return $actions;
    }

	/**
	 * Respond to the user selecting an action on the plugin page
	 *
	 * @param string $plugin_id The string id of the acted-upon plugin
	 * @param string $action The action string supplied via the filter_plugin_config hook
	 */
	public function action_plugin_ui( $plugin_id, $action )
	{
		if ( $plugin_id == $this->plugin_id() ) {
			$ui = new FormUI( strtolower( __CLASS__ ) );
			$user = User::identify();
			require_once dirname( __FILE__ ) . '/lib/twitteroauth/twitteroauth.php';

			switch ( $action ){
				case 'authorize':
					unset( $_SESSION['TwitterReqToken'] ); // Just being safe.
					$oauth = new TwitterOAuth( Twitter::CONSUMER_KEY, Twitter::CONSUMER_SECRET );
					$oauth_token = $oauth->getRequestToken( URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'confirm' ) ) );
					$request_link = $oauth->getAuthorizeURL( $oauth_token );
					$reqToken = array( "request_link" => $request_link, "request_token" => $oauth_token['oauth_token'], "request_token_secret" => $oauth_token['oauth_token_secret'] );
					$_SESSION['TwitterReqToken'] = serialize( $reqToken );
					$ui->append( 'static', 'nocontent', '<h3>Authorize the Habari Twitter Plugin</h3>
														 <p>Authorize your blog to have access to your Twitter account.</p>
														 <p>Click the button below, and you will be taken to Twitter.com. If you\'re already logged in, you will be presented with the option to authorize your blog. Press the "Allow" button to do so, and you will come right back here.</p>
														 <br><p style="text-align:center"><a href="'.$reqToken['request_link'].'"><img src="'. URL::get_from_filesystem( __FILE__ ) .'/lib/twitter_connect.png" alt="Sign in with Twitter" /></a></p>
								');
					$ui->out();
					break;

				case 'confirm':
					if( !isset( $_SESSION['TwitterReqToken'] ) ){
						$auth_url = URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'authorize' ) );
						$ui->append( 'static', 'nocontent', '<p>'._t( 'Either you have already authorized Habari to access your Twitter account, or you have not yet done so.  Please ' ).'<strong><a href="' . $auth_url . '">'._t( 'try again' ).'</a></strong>.</p>');
						$ui->out();
					}
					else {
						$reqToken = unserialize( $_SESSION['TwitterReqToken'] );
						$oauth = new TwitterOAuth( Twitter::CONSUMER_KEY, Twitter::CONSUMER_SECRET, $reqToken['request_token'], $reqToken['request_token_secret'] );
				        $token = $oauth->getAccessToken($_GET['oauth_verifier']);
						$config_url = URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'Configure' ) );

						if( ! empty( $token ) && isset( $token['oauth_token'] ) ){
							$user->info->twitter__access_token = $token['oauth_token'];
							$user->info->twitter__access_token_secret = $token['oauth_token_secret'];
							$user->info->twitter__user_id = $token['user_id'];
							$user->info->commit();
							//echo '<form><p>'._t( 'Habari Twitter plugin successfully authorized. You can now configure the Twitter plugin.' ).'</p></form>';
							Session::notice( _t( 'Habari Twitter plugin successfully authorized.', 'twitter' ) );
							Utils::redirect( $config_url );
						}
						else{
							// TODO: We need to fudge something to report the error in the event something fails.  Sadly, the Twitter OAuth class we use doesn't seem to cater for errors very well and returns the Twitter XML response as an array key.
							// TODO: Also need to gracefully cater for when users click "Deny"
							echo '<form><p>'._t( 'There was a problem with your authorization.' ).'</p></form>';
						}
						unset( $_SESSION['TwitterReqToken'] );
					}
					break;
				case 'deauthorize':
					$user->info->twitter__user_id = '';
					$user->info->twitter__access_token = '';
					$user->info->twitter__access_token_secret = '';
					$user->info->commit();
					$reauth_url = URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'authorize' ) ) . '#plugin_options';
					$ui->append( 'static', 'nocontent', '<p>'._t( 'The Twitter Plugin authorization has been deleted. Please ensure you ' ) . '<a href="http://twitter.com/settings/connections" target="_blank">' . _t( 'revoke access ' ).'</a>'._t( 'from your Twitter account too.' ).'<p><p>'._t( 'Do you want to ' ).'<b><a href="'.$reauth_url.'">'._t( 're-authorize this plugin' ).'</a></b>?<p>' );
					Session::notice( _t( 'Habari Twitter plugin authorization revoked. <br>Don\'t forget to revoke access on Twitter itself.', 'twitter' ) );
					//Utils::redirect( $reauth_url );
					$ui->out();
					break;
				case 'configure':
					
					$ui = new FormUI( strtolower( __CLASS__ ) );

					$post_fieldset = $ui->append( 'fieldset', 'post_settings', _t( 'Autopost Updates from Habari', 'twitter' ) );

					$twitter_post = $post_fieldset->append( 'checkbox', 'post_status', 'twitter__post_status', _t( 'Autopost to Twitter:', 'twitter' ) );

					$twitter_post = $post_fieldset->append( 'text', 'prepend', 'twitter__prepend', _t( 'Prepend to Autopost:', 'twitter' ) );
					$twitter_post->value = "New Blog Post:";

					$tweet_fieldset = $ui->append( 'fieldset', 'tweet_settings', _t( 'Displaying Status Updates', 'twitter' ) );

					$twitter_limit = $tweet_fieldset->append( 'select', 'limit', 'twitter__limit', _t( 'Number of updates to show', 'twitter' ) );
					$twitter_limit->options = array_combine(range(1, 20), range(1, 20));

					$twitter_show = $tweet_fieldset->append( 'checkbox', 'hide_replies', 'twitter__hide_replies', _t( 'Do not show @replies', 'twitter' ) );

					$twitter_show = $tweet_fieldset->append( 'checkbox', 'linkify_urls', 'twitter__linkify_urls', _t('Linkify URLs') );

					$twitter_hashtags = $tweet_fieldset->append( 'text', 'hashtags_query', 'twitter__hashtags_query', _t( '#hashtags query link:', 'twitter' ) );

					$twitter_cache_time = $tweet_fieldset->append( 'text', 'cache', 'twitter__cache', _t( 'Cache expiry in seconds:', 'twitter' ) );

					$ui->on_success( array( $this, 'updated_config' ) );
					$ui->append( 'submit', 'save', _t( 'Save', 'twitter' ) );
					$ui->out();
					break;
			}
		}
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
				require_once dirname( __FILE__ ) . '/lib/twitteroauth/twitteroauth.php';
				$user = User::get_by_id( $post->user_id );
				$oauth = new TwitterOAuth( Twitter::CONSUMER_KEY, Twitter::CONSUMER_SECRET, $user->info->twitter__access_token, $user->info->twitter__access_token_secret );
				$oauth->post('statuses/update', array('status' => Options::get( 'twitter__prepend' ) . $post->title . ' ' . $post->permalink ) );
				Session::notice( 'Post Tweeted' );
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
							if ( ( !Options::get( 'twitter__hide_replies' ) ) || ( strpos( $status->text, '@' ) === false) ) {
								$notice = (object) array (
									'text' => (string) $status->text, 
									'time' => (string) $status->created_at, 
									'image_url' => (string) $status->user->profile_image_url
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
								'text' => 'No non-replies replies available from Twitter.', 
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
				Cache::set( 'twitter_notices', $notices, Options::get( 'twitter__cache' ), true );
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
