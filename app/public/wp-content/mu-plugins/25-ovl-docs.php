<?php
// OVL: Handles secure document storage, upload normalization, and download logging.

require_once __DIR__ . '/10-ovl-bootstrap.php'; // OVL: Reuse shared helpers/flags.

if ( ! function_exists( 'ovl_docs_base_dir' ) ) {
	/**
	 * Returns the absolute base directory for protected documents.
	 *
	 * @return string
	 */
	function ovl_docs_base_dir(): string {
		// OVL: Store files outside the public path to prevent direct access.
		return trailingslashit( ABSPATH . 'private/docs' );
	}
}

if ( ! function_exists( 'ovl_allowed_doc_extensions' ) ) {
	/**
	 * Returns the list of allowed document extensions.
	 *
	 * @return array
	 */
	function ovl_allowed_doc_extensions(): array {
		return [ 'pdf', 'xlsx', 'csv', 'zip', 'jpg', 'png' ];
	}
}

if ( ! function_exists( 'ovl_docs_log' ) ) {
	/**
	 * Writes a debug log entry when WP_DEBUG_LOG is enabled.
	 *
	 * @param string $message Short log message.
	 * @param array  $context Optional context.
	 */
	function ovl_docs_log( string $message, array $context = [] ): void {
		$line = '[OVL:docs] ' . $message;
		if ( ! empty( $context ) ) {
			$line .= ' ' . wp_json_encode( $context );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		$log_path = trailingslashit( ovl_docs_base_dir() ) . 'ovl-docs.log';
		wp_mkdir_p( dirname( $log_path ) );
		@file_put_contents( $log_path, gmdate( 'c' ) . ' ' . $line . PHP_EOL, FILE_APPEND ); // phpcs:ignore
	}
}

if ( ! function_exists( 'ovl_docs_set_force_clear_mode' ) ) {
	/**
	 * Enables or disables forced doc clearing mode.
	 *
	 * @param bool $enabled True to allow empty values to persist.
	 */
	function ovl_docs_set_force_clear_mode( bool $enabled ): void {
		$GLOBALS['ovl_docs_force_clear_mode'] = $enabled;
	}
}

if ( ! function_exists( 'ovl_docs_is_force_clear_mode' ) ) {
	/**
	 * Checks whether empty doc values should be honored.
	 *
	 * @return bool
	 */
	function ovl_docs_is_force_clear_mode(): bool {
		return ! empty( $GLOBALS['ovl_docs_force_clear_mode'] );
	}
}

if ( ! function_exists( 'ovl_post_docs_dir' ) ) {
	/**
	 * Returns the per-post directory path.
	 *
	 * @param int $post_id Property post ID.
	 *
	 * @return string
	 */
	function ovl_post_docs_dir( int $post_id ): string {
		// OVL: Keep each property's files in its own folder for isolation.
		return trailingslashit( ovl_docs_base_dir() . absint( $post_id ) );
	}
}

if ( ! function_exists( 'ovl_ensure_post_docs_dir' ) ) {
	/**
	 * Ensures the per-post directory exists and returns it.
	 *
	 * @param int $post_id Property post ID.
	 *
	 * @return string
	 */
	function ovl_ensure_post_docs_dir( int $post_id ): string {
		// OVL: Create the folder lazily when uploads happen.
		$dir = ovl_post_docs_dir( $post_id );
		wp_mkdir_p( $dir );

		return $dir;
	}
}

if ( ! function_exists( 'ovl_list_doc_basenames' ) ) {
	/**
	 * Lists valid document basenames within a directory.
	 *
	 * @param string $dir Absolute directory path.
	 *
	 * @return array<string>
	 */
	function ovl_list_doc_basenames( string $dir ): array {
		$dir = trailingslashit( $dir );
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return [];
		}

		$allowed   = ovl_allowed_doc_extensions();
		$basenames = [];

		foreach ( array_diff( scandir( $dir ), [ '.', '..' ] ) as $entry ) {
			$path = $dir . $entry;
			if ( ! is_file( $path ) ) {
				continue;
			}

			$ext = strtolower( (string) pathinfo( $entry, PATHINFO_EXTENSION ) );
			if ( '' === $ext || ! in_array( $ext, $allowed, true ) ) {
				continue;
			}

			$basenames[] = $entry;
		}

		return $basenames;
	}
}

if ( ! function_exists( 'ovl_remove_legacy_doc_file' ) ) {
	/**
	 * Deletes a legacy document stored directly under private/docs.
	 *
	 * @param string $basename File basename.
	 */
	function ovl_remove_legacy_doc_file( string $basename ): void {
		$basename = basename( trim( $basename ) );
		if ( '' === $basename ) {
			return;
		}

		$legacy_path = ovl_docs_base_dir() . $basename;
		if ( is_file( $legacy_path ) ) {
			@unlink( $legacy_path );
		}
	}
}

if ( ! function_exists( 'ovl_clear_post_docs' ) ) {
	/**
	 * Deletes all files inside a property's document directory.
	 *
	 * @param int $post_id Property post ID.
	 */
	function ovl_clear_post_docs( int $post_id ): void {
		// OVL: Remove stale documents when a new file replaces them.
		$dir = ovl_post_docs_dir( $post_id );
		if ( is_dir( $dir ) ) {
			foreach ( array_diff( scandir( $dir ), [ '.', '..' ] ) as $file ) {
				$path = $dir . $file;
				if ( is_file( $path ) ) {
					@unlink( $path );
				}
			}

			@rmdir( $dir );
		}

		if ( ! function_exists( 'get_post_meta' ) ) {
			return;
		}

		$basenames = [];

		$current_meta = get_post_meta( $post_id, 'doc_url', true );
		if ( ! empty( $current_meta ) ) {
			$basenames[] = $current_meta;
		}

		if ( function_exists( 'get_field_object' ) ) {
			$field = get_field_object( 'doc_url', $post_id, false, false );
			if ( $field && isset( $field['key'] ) ) {
				$field_meta = get_post_meta( $post_id, $field['key'], true );
				if ( ! empty( $field_meta ) ) {
					$basenames[] = $field_meta;
				}
			}
		}

		foreach ( $basenames as $basename ) {
			ovl_remove_legacy_doc_file( (string) $basename );
		}
	}
}

if ( ! function_exists( 'ovl_update_doc_meta' ) ) {
	/**
	 * Updates the stored doc_url meta with the provided basename.
	 *
	 * @param int    $post_id  Property post ID.
	 * @param string $basename Sanitized filename.
	 */
	function ovl_update_doc_meta( int $post_id, string $basename ): void {
		// OVL: Keep ACF/meta in sync after relocating uploads.
		if ( function_exists( 'get_field_object' ) ) {
			$field = get_field_object( 'doc_url', $post_id, false, false );
			if ( $field && isset( $field['key'] ) ) {
				update_post_meta( $post_id, $field['key'], $basename );
			}
		}

		update_post_meta( $post_id, 'doc_url', $basename );
	}
}

if ( ! function_exists( 'ovl_get_doc_basename' ) ) {
	/**
	 * Resolves the stored document filename for a property.
	 *
	 * @param int $post_id Property post ID.
	 *
	 * @return string
	 */
	function ovl_get_doc_basename( int $post_id ): string {
		// OVL: Normalize doc_url to a safe basename and auto-recover from disk.
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return '';
		}

		$doc_value = function_exists( 'get_field' ) ? get_field( 'doc_url', $post_id ) : get_post_meta( $post_id, 'doc_url', true );

		if ( empty( $doc_value ) ) {
			$post_files = ovl_list_doc_basenames( ovl_post_docs_dir( $post_id ) );
			if ( ! empty( $post_files ) ) {
				$doc_value = $post_files[0];
				ovl_update_doc_meta( $post_id, $doc_value );
			}
		}

		if ( empty( $doc_value ) ) {
			$legacy_files = ovl_list_doc_basenames( ovl_docs_base_dir() );
			if ( ! empty( $legacy_files ) ) {
				$doc_value = $legacy_files[0];
				ovl_update_doc_meta( $post_id, $doc_value );
			}
		}

		if ( empty( $doc_value ) ) {
			return '';
		}

		if ( is_array( $doc_value ) ) {
			if ( isset( $doc_value['filename'] ) ) {
				$doc_value = $doc_value['filename'];
			} elseif ( isset( $doc_value['url'] ) ) {
				$doc_value = basename( (string) $doc_value['url'] );
			} elseif ( isset( $doc_value['ID'] ) ) {
				$doc_value = basename( (string) get_post_meta( (int) $doc_value['ID'], '_wp_attached_file', true ) );
			}
		}

		$doc_value = basename( trim( (string) $doc_value ) );

		if ( '' === $doc_value || ! preg_match( '/^[\p{L}\p{N}._-]+$/u', $doc_value ) ) {
			return '';
		}

		return $doc_value;
	}
}

if ( ! function_exists( 'ovl_get_download_url' ) ) {
	/**
	 * Generates a signed download URL for a property's document.
	 *
	 * @param int $post_id Property post ID.
	 *
	 * @return string
	 */
	function ovl_get_download_url( int $post_id ): string {
		// OVL: Create a login-protected URL that hits download.php with nonce.
		$basename = ovl_get_doc_basename( $post_id );
		if ( '' === $basename ) {
			return '';
		}

		$nonce = wp_create_nonce( "ovl_download_{$post_id}" );

		return add_query_arg(
			[
				'file'  => $basename,
				'post'  => $post_id,
				'nonce' => $nonce,
			],
			home_url( '/download.php' )
		);
	}
}

	if ( ! function_exists( 'ovl_handle_doc_upload' ) ) {
	/**
	 * Moves uploaded documents into the private directory and normalizes meta.
	 *
	 * @param mixed $value    Value about to be saved.
	 * @param int   $post_id  Post ID.
	 * @param array $field    ACF field definition.
	 * @param mixed $original Original stored value.
	 *
	 * @return string
	 */
		function ovl_handle_doc_upload( $value, int $post_id, array $field, $original ): string {
			// OVL: Accept ACF file inputs and relocate them under /private/docs.
			if ( empty( $value ) ) {
				$allow_clear = ovl_docs_is_force_clear_mode();
				$allow_clear = (bool) apply_filters( 'ovl/docs/allow_empty_doc_value', $allow_clear, $post_id, $field, $original );

				if ( $allow_clear ) {
					ovl_docs_log(
						'allowing empty doc value via force clear',
						[
							'post_id' => $post_id,
							'user_id' => get_current_user_id(),
						]
					);
					ovl_clear_post_docs( $post_id );
					return '';
				}

				if ( ! empty( $original ) ) {
				if ( is_string( $original ) ) {
					return basename( trim( $original ) );
				}
				if ( is_array( $original ) && isset( $original['filename'] ) ) {
					return basename( (string) $original['filename'] );
				}
			}

			ovl_clear_post_docs( $post_id );
			return '';
		}

		if ( is_array( $value ) ) {
			if ( isset( $value['ID'] ) ) {
				$value = (int) $value['ID'];
			} elseif ( isset( $value['url'] ) ) {
				$value = (string) $value['url'];
			} else {
				return '';
			}
		}

		// OVL: Attachment ID provided. Copy file to private/docs.
		if ( is_numeric( $value ) ) {
			$attachment_id = (int) $value;
			$file_path     = get_attached_file( $attachment_id );

			if ( ! $file_path || ! file_exists( $file_path ) ) {
				return '';
			}

			$target_dir  = ovl_ensure_post_docs_dir( $post_id );
			$unique_name = wp_unique_filename( $target_dir, sanitize_file_name( basename( $file_path ) ) );
			$target_path = $target_dir . $unique_name;

			if ( ! @copy( $file_path, $target_path ) ) {
				return '';
			}

			// OVL: Remove any previous docs and the public attachment copy.
			foreach ( array_diff( scandir( $target_dir ), [ '.', '..', $unique_name ] ) as $file ) {
				$existing = $target_dir . $file;
				if ( is_file( $existing ) ) {
					@unlink( $existing );
				}
			}

			wp_delete_attachment( $attachment_id, true );
			ovl_update_doc_meta( $post_id, $unique_name );

			return $unique_name;
		}

		// OVL: Handle URLs within the uploads directory.
		if ( is_string( $value ) && false !== strpos( $value, '://' ) ) {
			$uploads = wp_get_upload_dir();
			if ( isset( $uploads['baseurl'], $uploads['basedir'] ) && str_starts_with( $value, $uploads['baseurl'] ) ) {
				$relative    = ltrim( str_replace( $uploads['baseurl'], '', $value ), '/' );
				$source_path = trailingslashit( $uploads['basedir'] ) . $relative;
				if ( file_exists( $source_path ) ) {
					$target_dir  = ovl_ensure_post_docs_dir( $post_id );
					$unique_name = wp_unique_filename( $target_dir, sanitize_file_name( basename( $source_path ) ) );
					$target_path = $target_dir . $unique_name;

					if ( @copy( $source_path, $target_path ) ) {
						foreach ( array_diff( scandir( $target_dir ), [ '.', '..', $unique_name ] ) as $file ) {
							$existing = $target_dir . $file;
							if ( is_file( $existing ) ) {
								@unlink( $existing );
							}
						}

						@unlink( $source_path );
						ovl_update_doc_meta( $post_id, $unique_name );

						return $unique_name;
					}
				}
			}

			return '';
		}

		if ( is_string( $value ) ) {
			return basename( trim( $value ) );
		}

		return '';
	}
}

add_filter( 'acf/update_value/name=doc_url', 'ovl_handle_doc_upload', 10, 4 ); // OVL: Normalize uploads into private storage.

if ( ! function_exists( 'ovl_log_download_event' ) ) {
	/**
	 * Logs download actions for future integrations.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $post_id Property ID.
	 * @param string $file    Downloaded filename.
	 */
	function ovl_log_download_event( int $user_id, int $post_id, string $file ): void {
		// OVL: Basic logging for audit; can be extended to webhooks later.
		error_log( sprintf( '[OVL:download] user=%d post=%d file=%s', $user_id, $post_id, $file ) ); // phpcs:ignore
	}
}

add_action( 'ovl/download_logged', 'ovl_log_download_event', 10, 3 ); // OVL: Preserve legacy hook for external listeners.
