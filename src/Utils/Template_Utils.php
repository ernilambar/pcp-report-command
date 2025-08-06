<?php
/**
 * Template_Utils
 *
 * @package PCP_Report_Command
 */

declare(strict_types=1);

namespace Nilambar\PCP_Report_Command\Utils;

/**
 * Template_Utils class.
 *
 * Handles PHP template rendering for better readability and control.
 *
 * @since 1.0.0
 */
class Template_Utils {

	/**
	 * Renders a PHP template with the given data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template_path Full path to the template file.
	 * @param array  $data          Data to make available in the template.
	 * @return string Rendered template content.
	 */
	public static function render( string $template_path, array $data = [] ): string {
		if ( ! file_exists( $template_path ) ) {
			return '';
		}

		// Start output buffering.
		ob_start();

		// Call the template rendering function with data.
		self::render_template( $template_path, $data );

		// Get the buffered content and clean the buffer.
		return ob_get_clean();
	}

	/**
	 * Renders template file with explicit variable passing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template_path Full path to the template file.
	 * @param array  $data          Data array.
	 */
	private static function render_template( string $template_path, array $data ): void {
		$title      = $data['title'] ?? '';
		$issues     = $data['issues'] ?? [];
		$categories = $data['categories'] ?? [];

		// Include the template file with variables in scope.
		include $template_path;
	}

					/**
	 * Formats message text by converting backticks to pre/code tags.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message The message text to format.
	 * @return string Formatted message with pre/code tags.
	 */
	public static function format_message( string $message ): string {
		// Escape HTML first to prevent XSS
		$escaped_message = esc_html( $message );

		// Convert backticks to pre/code tags (handles backticks anywhere including end of message)
		$formatted_message = preg_replace( '/`([^`]+)`/', '<pre><code>$1</code></pre>', $escaped_message );

		return $formatted_message;
	}
}
