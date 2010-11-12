<!-- This file can be copied and modified in a theme directory -->

<div id="twitterbox">
	<ul>
		<?php foreach ($tweets as $tweet) : ?>
		<li>
			<img src="<?php echo htmlspecialchars( $tweet->image_url ); ?>" alt="<?php echo urlencode( Options::get( 'twitter__username' )); ?>">
			<?php echo $tweet->text . ' @ ' . $tweet->time; ?>
		</li>
		<?php endforeach; ?>
	</ul>
<p><small>via <a href="http://twitter.com/<?php echo urlencode( Options::get( 'twitter__username' )); ?>">Twitter</a></small></p>
 </div>
