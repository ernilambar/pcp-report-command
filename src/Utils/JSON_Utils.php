<?php
/**
 * JSON_Utils
 *
 * @package PCP_Report_Command
 */

declare(strict_types=1);

namespace Nilambar\PCP_Report_Command\Utils;

use Exception;
use JsonSchema\Validator;
use WP_Error;

/**
 * JSON_Utils class.
 *
 * @since 1.0.0
 */
class JSON_Utils {

	/**
	 * Reads and decodes a JSON file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file Path to the JSON file to read.
	 * @return array|WP_Error The decoded JSON data as array on success, WP_Error on failure.
	 */
	public static function read_json( string $file ) {
		// Check if file exists.
		if ( ! file_exists( $file ) ) {
			return new WP_Error( 'file_not_found', sprintf( 'File not found: %s', $file ) );
		}

		// Verify file is readable.
		if ( ! is_readable( $file ) ) {
			return new WP_Error( 'file_not_readable', sprintf( 'File is not readable: %s', $file ) );
		}

		$file_content = file_get_contents( $file );

		// Validate file read operation.
		if ( false === $file_content ) {
			return new WP_Error( 'file_read_error', sprintf( 'Could not read file: %s', $file ) );
		}

		$json_data = json_decode( $file_content, true );

		// Check for JSON parsing errors.
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'json_decode_error',
				sprintf( 'JSON decode error: %s', json_last_error_msg() ),
				[ 'file' => $file ]
			);
		}

		return $json_data;
	}

	/**
	 * Checks if given string is valid JSON.
	 *
	 * @since 1.0.0
	 *
	 * @param string $str String to check for validity.
	 * @return bool True if valid, otherwise false.
	 */
	public static function is_valid_json( string $str ): bool {
		json_decode( $str );

		return ( JSON_ERROR_NONE === json_last_error() );
	}

	/**
	 * Validates a JSON string against a schema file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $json_string The JSON string to validate.
	 * @param string $schema_file Path to the JSON schema file.
	 * @return bool|WP_Error True if valid, WP_Error on failure.
	 */
	public static function validate_json_string_with_schema( string $json_string, string $schema_file ) {
		// Decode the JSON string to validate.
		$data = json_decode( $json_string );
		if ( ! self::is_valid_json( $json_string ) ) {
			return new WP_Error(
				'json_decode_error',
				sprintf( 'JSON decode error: %s', json_last_error_msg() ),
				[ 'json_string' => $json_string ]
			);
		}

		return self::validate_json_data_with_schema( $data, $schema_file );
	}

	/**
	 * Validates decoded JSON data against a JSON schema file.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $data        The data to validate (usually array or object).
	 * @param string $schema_file Path to the JSON schema file.
	 * @return bool|WP_Error True if valid, WP_Error on failure.
	 */
	public static function validate_json_data_with_schema( $data, string $schema_file ) {
		// Read the schema file using existing method.
		$schema_data = self::read_json( $schema_file );
		if ( is_wp_error( $schema_data ) ) {
			return new WP_Error(
				'schema_read_error',
				sprintf( 'Failed to read schema file: %s', $schema_data->get_error_message() ),
				[ 'schema_file' => $schema_file ]
			);
		}

		// Convert data to object for validation.
		$json_data = json_decode( json_encode( $data ) );

		try {
			// Use the library for validation.
			$validator = new Validator();

			// Convert schema to object too
			$schema_object = json_decode( json_encode( $schema_data ) );

			$validator->validate( $json_data, $schema_object );

			if ( $validator->isValid() ) {
				return true;
			}

			// Collect validation errors.
			$errors = [];

			foreach ( $validator->getErrors() as $error ) {
				$errors[] = sprintf(
					'Property "%s": %s',
					$error['property'],
					$error['message']
				);
			}

			return new WP_Error(
				'json_validation_failed',
				sprintf( 'JSON validation failed: %s', implode( '; ', $errors ) ),
				[ 'errors' => $errors ]
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'validation_exception',
				sprintf( 'Validation exception: %s', $e->getMessage() ),
				[ 'exception' => $e ]
			);
		}
	}
}
