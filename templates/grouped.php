<?php if ( ! empty( $title ) ) : ?>
	<h2><?php echo esc_html( $title ); ?></h2>
	<br><br>
<?php endif; ?>

<?php if ( ! empty( $categories ) ) : ?>
	<?php foreach ( $categories as $category ) : ?>
		<strong>## <?php echo esc_html( $category['name'] ); ?></strong>
		<br><br>

		<?php foreach ( $category['types'] as $type ) : ?>
			<strong><?php echo esc_html( $type['type'] ); ?>: <?php echo esc_html( $type['code'] ); ?></strong><br><br>

			<?php
			$issue_count         = count( $type['issues'] );
			$is_single_file      = 1 === $issue_count;
			$has_multiple_files  = $issue_count > 1;
			$max_displayed       = 3;
			$displayed_issues    = array_slice( $type['issues'], 0, $max_displayed );
			$has_more_than_three = $issue_count > $max_displayed;
			?>

			<?php if ( $is_single_file ) : ?>
				<?php $issue = $type['issues'][0]; ?>
				<code><?php echo esc_html( $issue['file'] ); ?>
				<?php
				if ( $issue['has_location'] ) :
					?>
					:<?php echo esc_html( $issue['line'] ); ?><?php endif; ?></code> -
			<?php endif; ?>

			<?php echo esc_html( $type['message'] ); ?>

			<?php if ( ! empty( $type['docs'] ) && $is_single_file ) : ?>
				<a href="<?php echo esc_url( $type['docs'] ); ?>" target="_blank">Learn more</a>
			<?php endif; ?>
			<br><br>

			<?php if ( $has_multiple_files ) : ?>
				<?php
				$files_output = '';

				foreach ( $displayed_issues as $issue ) {
					$files_output .= esc_html( $issue['file'] );

					if ( $issue['has_location'] ) {
						$files_output .= ':' . esc_html( $issue['line'] );
					}

					$files_output .= "\n";
				}
				?>
				<pre><code><?php echo $files_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></code></pre>

				<?php if ( $has_more_than_three ) : ?>
					… out of a total of <?php echo esc_html( $issue_count ); ?> incidences.<br><br>
				<?php endif; ?>

				<?php if ( ! empty( $type['docs'] ) ) : ?>
					<a href="<?php echo esc_url( $type['docs'] ); ?>" target="_blank">Learn more</a><br><br>
				<?php endif; ?>
			<?php endif; ?>

		<?php endforeach; ?>

		<br><br>
	<?php endforeach; ?>
<?php endif; ?>
