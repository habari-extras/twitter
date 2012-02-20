<?php
/**
 * Twitter Plugin
 *
 * Lets you show your current Twitter status in your theme, as well
 * as an option automatically post your new posts to Twitter.
 *
 * Usage: <?php $theme->twitter(); ?> to show your latest tweet in a theme.
 * A sample tweets.php template is included with the plugin.This can be copied to your
 * active theme and modified.
 *
 **/

class Twitter extends Plugin
{
	const DEFAULT_CACHE_EXPIRE = 60; // seconds
	const CONSUMER_KEY = 'vk8lo1Zqut4g0q1VA1r0BQ';
	const CONSUMER_SECRET = 'kI6xMYFvV2OUIBqA8F7m1OIhzOfZkPZLjkCmBJy5IE';

	/**
	 * Sets the new 'hide_replies' option to '0' to mimic current, non-reply-hiding
	 * functionality, and 'twitter__limit' to '1', again to match earlier results.
	 **/

	public function action_plugin_activation($file)
	{
		if (Plugins::id_from_file($file) == Plugins::id_from_file(__FILE__)) {
			if (Options::get('twitter__hide_replies') == null) {
				Options::set('twitter__hide_replies', 0);
			}
			if ((Options::get('twitter__linkify_urls') == null) or (Options::get('twitter__linkify_urls') > 1)) {
				Options::set('twitter__linkify_urls', 0);
			}
			if (Options::get('twitter__hashtags_query') == null) {
				Options::set('twitter__hashtags_query', 'http://hashtags.org/search?query=');
			}
			if (!Options::get('twitter__limit')) {
				Options::set('twitter__limit', 1);
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
	public function filter_plugin_config($actions, $plugin_id)
	{

		if ($plugin_id == $this->plugin_id()) {

			if (User::identify()->info->twitter__access_token) {
				$actions['configure'] = _t('Configure');
				$actions['deauthorize'] = _t('De-Authorize');
			}
			else {
				$actions['authorize'] = _t('Authorize');
			}

		}
		return $actions;
	}

	/**
	 * Plugin UI - Displays the 'authorize' config option
	 *
	 * @access public
	 * @return void
	 */
	public function action_plugin_ui_authorize()
	{
		require_once dirname(__FILE__) . '/lib/twitteroauth/twitteroauth.php';
		unset($_SESSION['TwitterReqToken']); // Just being safe.

		$oauth = new TwitterOAuth(Twitter::CONSUMER_KEY, Twitter::CONSUMER_SECRET);
		$oauth_token = $oauth->getRequestToken(URL::get('admin', array('page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'confirm')));
		$request_link = $oauth->getAuthorizeURL($oauth_token);
		$reqToken = array("request_link" => $request_link, "request_token" => $oauth_token['oauth_token'], "request_token_secret" => $oauth_token['oauth_token_secret']);
		$_SESSION['TwitterReqToken'] = serialize($reqToken);

		$ui = new FormUI(strtolower(__CLASS__));
		$ui->append('static', 'nocontent',
			'<h3>Authorize the Habari Twitter Plugin</h3>
			 <p>Authorize your blog to have access to your Twitter account.</p>
			 <p>Click the button below, and you will be taken to Twitter.com. If you\'re already logged in, you will be presented with the option to authorize your blog. Press the "Allow" button to do so, and you will come right back here.</p>
			 <br><p style="text-align:center"><a href="' . $reqToken['request_link'] . '"><img src="' . URL::get_from_filesystem(__FILE__) . '/lib/twitter_connect.png" alt="Sign in with Twitter" /></a></p>'
		);
		$ui->out();
	}

	/**
	 * Plugin UI - Displays the 'confirm' config option.
	 *
	 * @access public
	 * @return void
	 */
	public function action_plugin_ui_confirm()
	{
		require_once dirname(__FILE__) . '/lib/twitteroauth/twitteroauth.php';
		$user = User::identify();
		$ui = new FormUI(strtolower(__CLASS__));
		if (!isset($_SESSION['TwitterReqToken'])) {
			$auth_url = URL::get('admin', array('page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'Authorize'));
			$ui->append('static', 'nocontent', '<p>' . _t('Either you have already authorized Habari to access your Twitter account, or you have not yet done so.  Please ') . '<strong><a href="' . $auth_url . '">' . _t('try again') . '</a></strong>.</p>');
			$ui->out();
		}
		else {
			$reqToken = unserialize($_SESSION['TwitterReqToken']);
			$oauth = new TwitterOAuth(Twitter::CONSUMER_KEY, Twitter::CONSUMER_SECRET, $reqToken['request_token'], $reqToken['request_token_secret']);
			$token = $oauth->getAccessToken($_GET['oauth_verifier']);
			$config_url = URL::get('admin', array('page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'Configure'));

			if (!empty($token) && isset($token['oauth_token'])) {
				$user->info->twitter__access_token = $token['oauth_token'];
				$user->info->twitter__access_token_secret = $token['oauth_token_secret'];
				$user->info->twitter__user_id = $token['user_id'];
				$user->info->commit();
				Session::notice(_t('Habari Twitter plugin successfully authorized.', 'twitter'));
				Utils::redirect($config_url);
			}
			else {
				// TODO: We need to fudge something to report the error in the event something fails.  Sadly, the Twitter OAuth class we use doesn't seem to cater for errors very well and returns the Twitter XML response as an array key.
				// TODO: Also need to gracefully cater for when users click "Deny"
				echo '<form><p>' . _t('There was a problem with your authorization.') . '</p></form>';
			}
			unset($_SESSION['TwitterReqToken']);
		}
	}


	/**
	 * Plugin UI - Displays the 'deauthorize' config option.
	 *
	 * @access public
	 * @return void
	 */
	public function action_plugin_ui_deauthorize()
	{
		$user = User::identify();
		$user->info->twitter__user_id = '';
		$user->info->twitter__access_token = '';
		$user->info->twitter__access_token_secret = '';
		$user->info->commit();
		$reauth_url = URL::get('admin', array('page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'Authorize')) . '#plugin_options';
		//$ui->append( 'static', 'nocontent', '<p>'._t( 'The Twitter Plugin authorization has been deleted. Please ensure you ' ) . '<a href="http://twitter.com/settings/connections" target="_blank">' . _t( 'revoke access ' ).'</a>'._t( 'from your Twitter account too.' ).'<p><p>'._t( 'Do you want to ' ).'<b><a href="'.$reauth_url.'">'._t( 're-authorize this plugin' ).'</a></b>?<p>' );
		Session::notice(_t('Habari Twitter plugin authorization revoked. <br>Don\'t forget to revoke access on Twitter itself.', 'twitter'));
		Utils::redirect($reauth_url);
	}


	/**
	 * Plugin UI - Displays the 'configure' config option.
	 *
	 * @access public
	 * @return void
	 */
	public function action_plugin_ui_configure()
	{
		$ui = new FormUI(strtolower(__CLASS__));

		$post_fieldset = $ui->append('fieldset', 'post_settings', _t('Autopost Updates from Habari', 'twitter'));

		$twitter_post = $post_fieldset->append('checkbox', 'post_status', 'twitter__post_status', _t('Autopost to Twitter:', 'twitter'));
		$twitter_post_nonanon = $post_fieldset->append('checkbox', 'post_nonanon', 'twitter__post_nonanon', _t("When autoposting, include posts that anonymous users can't read:", 'twitter'));

		$twitter_post = $post_fieldset->append('text', 'prepend', 'twitter__prepend', _t('Prepend to Autopost:', 'twitter'));
		$twitter_post->value = "New Blog Post:";

		$tweet_fieldset = $ui->append('fieldset', 'tweet_settings', _t('Displaying Status Updates', 'twitter'));

		$twitter_limit = $tweet_fieldset->append('select', 'limit', 'twitter__limit', _t('Number of updates to show', 'twitter'));
		$twitter_limit->options = array_combine(range(1, 20), range(1, 20));

		$twitter_show = $tweet_fieldset->append('checkbox', 'hide_replies', 'twitter__hide_replies', _t('Do not show @replies', 'twitter'));

		$twitter_show = $tweet_fieldset->append('checkbox', 'linkify_urls', 'twitter__linkify_urls', _t('Linkify URLs'));

		$twitter_hashtags = $tweet_fieldset->append('text', 'hashtags_query', 'twitter__hashtags_query', _t('#hashtags query link:', 'twitter'));

		$twitter_cache_time = $tweet_fieldset->append('text', 'cache', 'twitter__cache', _t('Cache expiry in seconds:', 'twitter'));

		$tweet_logins = $ui->append('fieldset', 'tweet_logins', _t('Logging In Using Twitter', 'twitter'));
		$twitterlogin = $tweet_logins->append('checkbox', 'twitterlogin', 'twitter__login', _t('Show button to log in with Twitter', 'twitter'));
		$twitterlogin = $tweet_logins->append('checkbox', 'twitterlogincreate', 'twitter__logincreate', _t('Create new users for unknown Twitter logins', 'twitter'));

		$ui->on_success(array($this, 'updated_config'));
		$ui->append('submit', 'save', _t('Save', 'twitter'));
		$ui->out();
	}

	/**
	 * Give the user a session message to confirm options were saved.
	 **/
	public function updated_config(FormUI $ui)
	{
		Session::notice(_t('Twitter options saved.', 'twitter'));
		$ui->save();
	}

	/**
	 * React to the update of a post status to 'published'
	 * @param Post $post The post object with the status change
	 * @param int $oldvalue The old status value
	 * @param int $newvalue The new status value
	 **/
	public function action_post_update_status($post, $oldvalue, $newvalue)
	{
		if (is_null($oldvalue)) return;
		if ($newvalue == Post::status('published') && $post->content_type == Post::type('entry') && $newvalue != $oldvalue) {
			if (Options::get('twitter__post_status') == '1') {

				$anon = User::anonymous();
				if(Options::get('twitter__post_nonanon') == '1' || $post->get_access($anon)->read) {

					require_once dirname(__FILE__) . '/lib/twitteroauth/twitteroauth.php';
					$user = User::get_by_id($post->user_id);
					$oauth = new TwitterOAuth(Twitter::CONSUMER_KEY, Twitter::CONSUMER_SECRET, $user->info->twitter__access_token, $user->info->twitter__access_token_secret);
					$oauth->post('statuses/update', array('status' => Options::get('twitter__prepend') . $post->title . ' ' . $post->permalink));
					Session::notice(_t('Post Tweeted', 'twitter'));
				}
				else {
					Session::notice(_t('Post not tweeted due to access restrictions', 'twitter'));
				}
			}
		}
	}

	public function action_post_insert_after($post)
	{
		return $this->action_post_update_status($post, -1, $post->status);
	}


	/**
	 * Add last Twitter status, time, and image to the available template vars
	 * @param Theme $theme The theme that will display the template
	 **/
	public function theme_twitter($theme)
	{
		$twitter = Options::get_group('twitter');
		$theme->tweets = $this->tweets($twitter['username'], $twitter['hide_replies'], $twitter['limit'], $twitter['cache'], $twitter['linkify_urls'], $twitter['hashtags_query']);
		return $theme->fetch('tweets');
	}

	/**
	 * Add twitter block to the list of selectable blocks
	 **/
	public function filter_block_list($block_list)
	{
		$block_list['twitter'] = _t('Twitter', 'twitter');
		return $block_list;
	}


	/**
	 * Configure the block
	 **/
	public function action_block_form_twitter($form, $block)
	{

		$tweet_fieldset = $form->append('fieldset', 'tweet_settings', _t('Displaying Status Updates', 'twitter'));

		$twitter_username = $tweet_fieldset->append('text', 'username', $block, _t('Twitter Username:', 'twitter'));

		$twitter_limit = $tweet_fieldset->append('select', 'limit', $block, _t('Number of updates to show', 'twitter'));
		$twitter_limit->options = array_combine(range(1, 20), range(1, 20));

		$twitter_show = $tweet_fieldset->append('checkbox', 'hide_replies', $block, _t('Do not show @replies', 'twitter'));

		$twitter_show = $tweet_fieldset->append('checkbox', 'linkify_urls', $block, _t('Linkify URLs', 'twitter'));

		$twitter_hashtags = $tweet_fieldset->append('text', 'hashtags_query', $block, _t('#hashtags query link:', 'twitter'));

		$twitter_cache_time = $tweet_fieldset->append('text', 'cache', $block, _t('Cache expiry in seconds:', 'twitter'));

		$form->append('submit', 'save', _t('Save', 'twitter'));
	}

	/**
	 * Populate the block
	 **/
	public function action_block_content_twitter($block, $theme)
	{
		$block->tweets = $this->tweets($block->username, $block->hide_replies, $block->limit, $block->cache, $block->linkify_urls, $block->hashtags_query);
	}

	/**
	 * Retrieve tweets
	 * @return array notices The tweets to display in the theme template or block
	 */
	public function tweets($username, $hide_replies = false, $limit = 5, $cache = 60, $linkify_urls = false, $hashtags_query = 'http://hashtags.org/search?query=')
	{
		$notices = array();
		if ($username != '') {
			$twitter_url = 'http://twitter.com/statuses/user_timeline/' . urlencode($username) . '.xml';

			// We only need to get a single tweet if we're hiding replies (otherwise we can rely on the maximum returned and hope there's a non-reply)
			if (!$hide_replies && $limit) {
				$twitter_url .= '?count=' . $limit;
			}

			if (Cache::has('twitter_notices')) {
				$notices = Cache::get('twitter_notices');
			}
			else {
				try {
					$r = new RemoteRequest($twitter_url);
					$r->set_timeout(10);
					$r->execute();
					$response = $r->get_response_body();

					$xml = @new SimpleXMLElement($response);
					// Check we've got a load of statuses returned
					if ($xml->getName() === 'statuses') {
						foreach ($xml->status as $status) {
							if ((!$hide_replies) || (strpos($status->text, '@') === FALSE)) {
								$notice = (object)array(
									'text' => (string)$status->text,
									'time' => (string)$status->created_at,
									'image_url' => (string)$status->user->profile_image_url,
									'id' => (int)$status->id,
									'permalink' => 'http://twitter.com/' . $username . '/status/' . (string)$status->id
								);

								$notices[] = $notice;
								if ($hide_replies && count($notices) >= $limit) {
									break;
								}
							}
							else {
								// it's a @. Keep going.
							}
						}
						if (!$notices) {
							$notice = (object)array(
								'text' => _t('No non-replies replies available from Twitter.', 'twitter'),
								'time' => '',
								'image_url' => ''
							);
						}
					}
					// You can get error as a root element if Twitter is in maintenance mode.
					else if ($xml->getName() === 'error') {
						$notice = (object)array(
							'text' => (string)$xml,
							'time' => '',
							'image_url' => ''
						);
					}
					// Um, yeah. We shouldn't ever hit this.
					else {
						$notice = (object)array(
							'text' => 'Received unexpected XML from Twitter.',
							'time' => '',
							'image_url' => ''
						);
					}
				}
				catch (Exception $e) {
					EventLog::log(_t('Twitter error: %1$s', array($e->getMessage()), 'twitter'), 'err', 'plugin', 'twitter');
					$notice = (object)array(
						'text' => 'Unable to contact Twitter.',
						'time' => '',
						'image_url' => ''
					);
				}
				if (!$notices) {
					$notices[] = $notice;
				}
				// Cache (even errors) to avoid hitting rate limit.
				Cache::set('twitter_notices', $notices, ($cache !== false ? $cache : Twitter::DEFAULT_CACHE_EXPIRE)); // , true );
			}
		}
		else {
			$notice = (object)array(
				'text' => _t('Please set your username in the <a href="%s">Twitter plugin config</a>', array(URL::get('admin', 'page=plugins&configure=' . $this->plugin_id . '&configaction=Configure') . '#plugin_' . $this->plugin_id), 'twitter'),
				'time' => '',
				'image_url' => ''
			);
			$notices[] = $notice;
		}
		if ($linkify_urls != FALSE) {
			foreach ($notices as $notice) {
				/* link to all http: */
				$notice->text = preg_replace('%https?://\S+?(?=(?:[.:?"!$&\'()*+,=]|)(?:\s|$))%i', "<a href=\"$0\">$0</a>", $notice->text);
				/* link to usernames */
				$notice->text = preg_replace("/(?<!\w)@([\w-_.]{1,64})/", "@<a href=\"http://twitter.com/$1\">$1</a>", $notice->text);
				/* link to hashtags */
				$notice->text = preg_replace('/(?<!\w)#((?>\d{1,64}|)[\w-.]{1,64})/',
					"<a href=\"" . $hashtags_query . "$1\">#$1</a>", $notice->text);
			}
		}
		return $notices;
	}

	/**
	 * On plugin init, add the template included with this plugin to the available templates in the theme
	 */
	public function action_init()
	{
		$this->add_template('tweets', dirname(__FILE__) . '/tweets.php');
		$this->add_template('block.twitter', dirname(__FILE__) . '/block.twitter.php');
	}

	function filter_rewrite_rules($rules)
	{
		$rules[] = RewriteRule::create_url_rule('"auth"/"twitter"/"go"', 'PluginHandler', 'auth_twitter_go');
		$rules[] = RewriteRule::create_url_rule('"auth"/"twitter"/"verify"', 'PluginHandler', 'auth_twitter_verify');
		return $rules;
	}

	/**
	 * Plugin UI - Displays the 'authorize' config option
	 *
	 * @access public
	 * @return void
	 */
	public function action_theme_loginform_after()
	{
		if(Options::get('twitter__login') == 1) {
			echo '<a href="' . URL::get('auth_twitter_go') . '"><img src="' . URL::get_from_filesystem(__FILE__) . '/lib/twitter_connect.png" alt="Sign in with Twitter" /></a>';
		}
	}

	public function action_plugin_act_auth_twitter_go()
	{
		require_once dirname(__FILE__) . '/lib/twitteroauth/twitteroauth.php';
		unset($_SESSION['TwitterReqToken']); // Just being safe.

		$oauth = new TwitterOAuth(Twitter::CONSUMER_KEY, Twitter::CONSUMER_SECRET);
		$oauth_token = $oauth->getRequestToken(URL::get('auth_twitter_verify'));
		$request_link = $oauth->getAuthorizeURL($oauth_token);
		$reqToken = array("request_link" => $request_link, "request_token" => $oauth_token['oauth_token'], "request_token_secret" => $oauth_token['oauth_token_secret']);
		$_SESSION['TwitterReqToken'] = serialize($reqToken);
		$_SESSION['TwitterLastPage'] = $_SERVER['HTTP_REFERRER'];

		header('location: ' . $reqToken['request_link']);
	}

	public function action_plugin_act_auth_twitter_verify()
	{
		require_once dirname(__FILE__) . '/lib/twitteroauth/twitteroauth.php';

		// If we don't have a token saved for this user...
		if (!isset($_SESSION['TwitterReqToken'])) {
			$auth_url = URL::get('admin', array('page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'Authorize'));
			Session::error(_t('Your login attempt failed due to a mismatched Twitter request token.  Please try again.'));
			$return_url = isset($_SESSION['TwitterLastPage']) ? $_SESSION['TwitterLastPage'] : Site::get_url('habari');
		}
		else {
			$reqToken = unserialize($_SESSION['TwitterReqToken']);
			$oauth = new TwitterOAuth(Twitter::CONSUMER_KEY, Twitter::CONSUMER_SECRET, $reqToken['request_token'], $reqToken['request_token_secret']);
			$token = $oauth->getAccessToken($_GET['oauth_verifier']);
			$return_url = isset($_SESSION['TwitterLastPage']) ? $_SESSION['TwitterLastPage'] : Site::get_url('habari');

			if (!empty($token) && isset($token['oauth_token'])) {
				// Need to find a user based on twitter user id
				$creds = $oauth->get('account/verify_credentials');

				// ->id ->name ->screen_name ->profile_image_url

				$users = Users::get(array(
					'info' => array('twitter__user_id' => $creds->id),
					'limit' => 1,
					'fetch_fn' => 'get_row',
				));

				if($users instanceof User) {
					$users->remember();
				}
				else {
					if(Options::get('twitter__logincreate')) {
						$user = User::create(array(
							'username' => '@' . $creds->screen_name,
							'email' => '',
							'password' => 'twitter' . sha1(rand()),
						));
						$user->info->twitter__user_id = $creds->id;
						$user->info->twitter__access_token = $token['oauth_token'];
						$user->info->twitter__access_token_secret = $token['oauth_token_secret'];
						$user->info->twitter__name = $creds->name;
						$user->info->displayname = $creds->name;
						$user->info->twitter__url = $creds->url;
						$user->info->url = $creds->url;
						$user->info->twitter__screen_name = $creds->screen_name;
						$user->info->twitter__profile_image_url = $creds->profile_image_url;
						$user->info->imageurl = $creds->profile_image_url;
						$user->info->commit();
						$user->remember();
						Session::notice(_t('Successfully authenticated via Twitter.', 'twitter'));
					}
					else {
						Session::error(_t('You have successfully authenticated via Twitter, but this site is not accepting new registrations.', 'twitter'));
					}
				}

			}
			else {
				// TODO: We need to fudge something to report the error in the event something fails.  Sadly, the Twitter OAuth class we use doesn't seem to cater for errors very well and returns the Twitter XML response as an array key.
				// TODO: Also need to gracefully cater for when users click "Deny"
				Session::error(_t('There was a problem with your authorization, or you explicitly denied the login at Twitter.'));
			}
			unset($_SESSION['TwitterReqToken']);
		}
		Utils::redirect($return_url);

	}

}


?>
