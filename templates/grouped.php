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
			// Deduplicate issues based on file and line.
			$unique_issues = [];
			foreach ( $type['issues'] as $issue ) {
				$file_key = $issue['file'];
				if ( $issue['has_location'] ) {
					$file_key .= ':' . $issue['line'];
				}

				// Only add if not already in unique_issues array.
				$found = false;
				foreach ( $unique_issues as $unique_issue ) {
					$unique_key = $unique_issue['file'];
					if ( $unique_issue['has_location'] ) {
						$unique_key .= ':' . $unique_issue['line'];
					}
					if ( $file_key === $unique_key ) {
						$found = true;
						break;
					}
				}

				if ( ! $found ) {
					$unique_issues[] = $issue;
				}
			}

			$issue_count        = count( $unique_issues );
			$is_single_file     = 1 === $issue_count;
			$has_multiple_files = $issue_count > 1;
			$max_displayed      = 3;
			$displayed_issues   = array_slice( $unique_issues, 0, $max_displayed );
			$has_more_than_max  = $issue_count > $max_displayed;
			?>

			<?php if ( $is_single_file ) : ?>
				<?php
				$issue        = $type['issues'][0];
				$file_display = esc_html( $issue['file'] );
				if ( $issue['has_location'] ) {
					$file_display .= ':' . esc_html( $issue['line'] );
				}
				?>
				<code><?php echo $file_display; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></code> -
			<?php endif; ?>

			<?php echo $type['message']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

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

				<?php if ( $has_more_than_max ) : ?>
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
