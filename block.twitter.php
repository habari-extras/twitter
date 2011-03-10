<ul>
<?php foreach ( $content->tweets as $tweet ) : ?>
	<li>
		<img src="<?php echo htmlspecialchars( $tweet->image_url ); ?>" alt="<?php echo urlencode( $content->username ); ?>">
		<?php echo $tweet->text . ' @ ' . $tweet->time; ?>
	</li>
	<?php endforeach; ?>
</ul>
<p><small>via <a href="http://twitter.com/<?php echo urlencode( $content->username ); ?>">Twitter</a></small></p>