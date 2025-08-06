<?php
/**
 * File_Utils
 *
 * @package PCP_Report_Command
 */

declare(strict_types=1);

namespace Nilambar\PCP_Report_Command\Utils;

use WP_Error;

/**
 * File_Utils class.
 *
 * @since 1.0.0
 */
class File_Utils {

	/**
	 * Writes content to a file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Full path to the file to be written.
	 * @param string $content Content to write to the file.
	 * @param bool   $overwrite Whether to overwrite existing files (default: true).
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function create_file( string $file_path, string $content = '', bool $overwrite = true ) {
		$file_path = wp_normalize_path( $file_path );

		// Check if file exists and overwrite is disabled.
		if ( ! $overwrite && file_exists( $file_path ) ) {
			return new WP_Error(
				'file_exists',
				'File already exists and overwrite is disabled',
				[ 'file' => $file_path ]
			);
		}

		// Ensure directory exists.
		$dir = dirname( $file_path );
		if ( ! file_exists( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return new WP_Error(
					'directory_creation_failed',
					'Could not create directory',
					[ 'directory' => $dir ]
				);
			}
		}

		// Verify directory is writable.
		if ( ! is_writable( $dir ) ) {
			return new WP_Error(
				'directory_not_writable',
				'Directory is not writable',
				[ 'directory' => $dir ]
			);
		}

		// Attempt to write the file.
		$result = file_put_contents( $file_path, $content, LOCK_EX );

		if ( false === $result ) {
			$error = error_get_last();
			return new WP_Error(
				'file_write_failed',
				'Could not write file',
				[
					'file'  => $file_path,
					'error' => $error ? $error['message'] : 'Unknown error',
				]
			);
		}

		return true;
	}
}
