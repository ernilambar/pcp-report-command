<?php if ( ! empty( $title ) ) : ?>
	<h2><?php echo esc_html( $title ); ?></h2>
	<br><br>
<?php endif; ?>

<?php if ( ! empty( $issues ) ) : ?>
	<?php foreach ( $issues as $issue ) : ?>
		<strong><?php echo esc_html( $issue['file'] ); ?></strong><br><br>
		<strong><?php echo esc_html( $issue['type'] ); ?>: <?php echo esc_html( $issue['code'] ); ?></strong>
		<?php if ( $issue['has_location'] ) : ?>
			- Line <?php echo $issue['line']; ?>, Column <?php echo $issue['column']; ?>
		<?php endif; ?>
		<br><br>
		<?php echo $issue['message']; ?>
		<?php if ( ! empty( $issue['docs'] ) ) : ?>
			<a href="<?php echo esc_url( $issue['docs'] ); ?>" target="_blank">Learn more</a>
		<?php endif; ?>
		<br><br>

	<?php endforeach; ?>
<?php else : ?>
	<p>No issues found.</p>
<?php endif; ?>
