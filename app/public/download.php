<?php
declare(strict_types=1);

/**
 * Secure download gateway for member-only documents.
 *
 * Files are stored in /private/docs/ outside the public web root. This script validates
 * access and streams the file to authorised users. To swap to an S3 pre-signed URL,
 * replace the ovl_stream_local_file() call near the end with a redirect routine.
 */

define('WP_USE_THEMES', false);

require __DIR__ . '/wp-load.php';

if (!function_exists('status_header')) {
	status_header(500);
	header('Content-Type: text/plain; charset=utf-8');
	echo 'WordPress bootstrap failed.';
	exit;
}

/**
 * Send an error response and stop execution.
 */
function ovl_download_error(int $code, string $message): void {
	// error_log(sprintf('[DL:error] code=%d message=%s uri=%s', $code, $message, $_SERVER['REQUEST_URI'] ?? 'unknown'));
	status_header($code);
	header('Content-Type: text/plain; charset=utf-8');
	echo $message;
	exit;
}

$file_param = isset($_GET['file']) ? wp_unslash((string) $_GET['file']) : '';
$post_param = isset($_GET['post']) ? wp_unslash((string) $_GET['post']) : '';
$nonce      = isset($_GET['nonce']) ? wp_unslash((string) $_GET['nonce']) : '';

if ($file_param === '' || $post_param === '' || $nonce === '') {
	ovl_download_error(400, 'Bad Request');
}

$basename = basename($file_param);
if ($basename !== $file_param) {
	ovl_download_error(400, 'Bad Request');
}

if (!preg_match('/^[\p{L}\p{N}._-]+$/u', $basename)) {
	ovl_download_error(400, 'Bad Request');
}

$allowed_extensions = function_exists('ovl_allowed_doc_extensions')
	? ovl_allowed_doc_extensions()
	: ['pdf', 'xlsx', 'csv', 'zip', 'jpg', 'png'];
$ext                = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));

if ($ext === '' || !in_array($ext, $allowed_extensions, true)) {
	ovl_download_error(403, 'Forbidden');
}

$post_id = absint($post_param);
if ($post_id <= 0) {
	ovl_download_error(400, 'Bad Request');
}

if (!wp_verify_nonce($nonce, "ovl_download_{$post_id}")) {
	ovl_download_error(403, 'Forbidden');
}

if (!is_user_logged_in()) {
	ovl_download_error(403, 'Forbidden');
}

$required_capability = apply_filters('ovl_download_required_capability', 'read');

if (!current_user_can($required_capability)) {
	ovl_download_error(403, 'Forbidden');
}

$doc_field = '';
if (function_exists('get_field')) {
	$doc_field = (string) get_field('doc_url', $post_id);
} else {
	$doc_field = (string) get_post_meta($post_id, 'doc_url', true);
}

$base_dir = realpath(__DIR__ . '/private/docs');

if ($base_dir === false) {
	ovl_download_error(410, 'Gone');
}

$normalized_base = rtrim($base_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$post_specific   = $normalized_base . $post_id . DIRECTORY_SEPARATOR . $basename;
$legacy_path     = $normalized_base . $basename;

$file_path = '';

if (file_exists($post_specific)) {
	$file_path = $post_specific;
	// error_log('[DL] using post-specific doc: ' . $file_path);
} elseif (file_exists($legacy_path)) {
	$file_path = $legacy_path;
	// error_log('[DL] using legacy doc: ' . $file_path);
} else {
	// try glob within post directory as last resort
	$glob = glob($normalized_base . $post_id . DIRECTORY_SEPARATOR . '*');
	if (!empty($glob)) {
		$file_path = $glob[0];
		// error_log('[DL] glob fallback doc: ' . $file_path);
	}
}

if ($file_path !== '') {
	$real = realpath($file_path);
	if ($real !== false && strpos($real, $normalized_base) === 0) {
		$file_path = $real;
	}
}

if ($file_path === '') {
	ovl_download_error(404, 'Not Found');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = $finfo ? finfo_file($finfo, $file_path) : false;

if ($finfo) {
	finfo_close($finfo);
}

if ($mime === false || $mime === '') {
	$mime = 'application/octet-stream';
}

// Disable caching so links cannot be re-used indefinitely by intermediaries.
nocache_headers();
header('X-Content-Type-Options: nosniff');
// error_log(sprintf('[DL] preparing download path=%s mime=%s name=%s', $file_path, $mime, $basename));

/**
 * Stream a local file to the browser. Swap this out for a redirect to a pre-signed
 * URL when moving to S3 or other remote storage. For large payloads, prefer
 * X-Accel-Redirect (Nginx) or X-Sendfile (Apache/lighttpd).
 */
function ovl_stream_local_file(string $path, string $download_name, string $mime_type): bool {
	if (headers_sent()) {
		// error_log('[DL] headers already sent.');
		return false;
	}

	if (!is_readable($path)) {
		// error_log('[DL] file not readable: ' . $path);
		return false;
	}

	while (ob_get_level() > 0) {
		ob_end_clean();
	}

	set_time_limit(0);

	header('Content-Type: ' . $mime_type);
	header('Content-Disposition: attachment; filename="' . $download_name . '"; filename*=UTF-8\'\'' . rawurlencode($download_name));
	header('Content-Length: ' . (string) filesize($path));
	header('Accept-Ranges: none');
	header('Content-Transfer-Encoding: binary');

	// For Nginx with X-Accel-Redirect, replace readfile() with the header below:
	// header('X-Accel-Redirect: /protected/' . $download_name);
	// exit;

	$handle = fopen($path, 'rb');
	if (!$handle) {
		// error_log('[DL] fopen failed for ' . $path);
		return false;
	}

	while (!feof($handle)) {
		$chunk = fread($handle, 8192);
		if ($chunk === false) {
			fclose($handle);
			// error_log('[DL] fread failed for ' . $path);
			return false;
		}
		echo $chunk;
		flush();
	}

	fclose($handle);

	return true;
}

$streamed = ovl_stream_local_file($file_path, $basename, $mime);

if (!$streamed) {
	// error_log('[DL] stream failed for ' . $file_path);
	ovl_download_error(500, 'Download failed');
}

exit;
