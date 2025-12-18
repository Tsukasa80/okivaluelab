<?php
/**
 * Script dependencies for Property Meta block editor script.
 *
 * @package okivaluelab-child
 */

return array(
	'dependencies' => array(
		'wp-block-editor',
		'wp-blocks',
		'wp-components',
		'wp-element',
		'wp-i18n',
	),
	'version'      => filemtime( __DIR__ . '/editor.js' ),
);
