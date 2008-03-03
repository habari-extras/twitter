<!-- This file can be copied and modified in a theme directory -->

<div id="twitterbox">
 <img src="<?php echo htmlspecialchars( $tweet_image_url ); ?>" alt="<?php echo urlencode( Options::get( 'twitter:username' )); ?>">
 <p><?php echo htmlspecialchars( $tweet_text ) . ' @ ' . htmlspecialchars( $tweet_time ); ?></p>
<p><small>via <a href="http://twitter.com/<?php echo urlencode( Options::get( 'twitter:username' )); ?>">Twitter</a></small></p>
 </div>