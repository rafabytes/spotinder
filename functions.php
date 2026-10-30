<?php
/**
 * Spotinder — bootstrap do tema.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

define( 'SPOTINDER_VERSION', '1.0.0' );
define( 'SPOTINDER_DB_VERSION', '1.0.0' );
define( 'SPOTINDER_DIR', get_template_directory() );
define( 'SPOTINDER_URI', get_template_directory_uri() );

$spotinder_includes = array(
	'inc/helpers.php',
	'inc/defaults.php',
	'inc/setup.php',
	'inc/branding.php',
	'inc/login.php',
	'inc/post-types.php',
	'inc/acf-fields.php',
	'inc/db.php',
	'inc/spotify-client.php',
	'inc/members.php',
	'inc/photos.php',
	'inc/location.php',
	'inc/hero.php',
	'inc/auth.php',
	'inc/matching.php',
	'inc/events.php',
	'inc/rest.php',
	'inc/privacy.php',
	'inc/demo.php',
	'inc/migrations.php',
	'inc/admin.php',
);

foreach ( $spotinder_includes as $spotinder_file ) {
	require_once SPOTINDER_DIR . '/' . $spotinder_file;
}
