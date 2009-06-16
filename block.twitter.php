<div id="twitter">
	<h3>From Twitter</h3>
	<a href="http://twitter.com/<?php echo urlencode( Options::get( 'twitter__username' )); ?>">
	<img src="<?php echo htmlspecialchars( $content->tweet_image_url ); ?>" alt="<?php echo urlencode( Options::get( 'twitter__username' )); ?>"></a>
	<span class="tweet"><?php echo $content->tweet_text; ?></span>
	<span> @ <?php echo HabariDateTime::date_create(strtotime($content->tweet_time))->format('M j, Y h:ia'); ?></span>
</div>

<div id="twitter">
	<h3>From Twitter</h3>
	<a href="http://twitter.com/<?php echo urlencode( Options::get( 'twitter__username' )); ?>">
	<img src="<?php echo htmlspecialchars( $tweet_image_url ); ?>" alt="<?php echo urlencode( Options::get( 'twitter__username' )); ?>"></a>
	<span class="tweet"><?php echo $tweet_text; ?></span>
	<span> @ <?php echo HabariDateTime::date_create(strtotime($tweet_time))->format('M j, Y h:ia'); ?></span>
</div>