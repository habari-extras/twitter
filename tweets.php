<!-- This file can be copied and modified in a theme directory -->

<div id="twitterbox">
 <img src="<?php echo htmlspecialchars( $tweet_image_url ); ?>" alt="<?php echo urlencode( Options::get( 'twitter__username' )); ?>">
 <p><?php echo $tweet_text . ' @ ' . $tweet_time; ?></p>
<p><small>via <a href="http://twitter.com/<?php echo urlencode( Options::get( 'twitter__username' )); ?>">Twitter</a></small></p>
 </div>
