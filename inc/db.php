<?php
/**
 * Tabelas próprias: swipes, matches, bloqueios, denúncias, presenças e convites.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nome completo de uma tabela do tema.
 *
 * @param string $name swipes|matches|blocks|reports|rsvps|invites.
 */
function spotinder_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'spotinder_' . $name;
}

/**
 * Cria/atualiza as tabelas.
 */
function spotinder_install_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();

	$sql = array();

	$sql[] = 'CREATE TABLE ' . spotinder_table( 'swipes' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		target_id bigint(20) unsigned NOT NULL,
		action varchar(8) NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY pair (user_id,target_id),
		KEY target_id (target_id)
	) $charset;";

	$sql[] = 'CREATE TABLE ' . spotinder_table( 'matches' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_a bigint(20) unsigned NOT NULL,
		user_b bigint(20) unsigned NOT NULL,
		score tinyint(3) unsigned NOT NULL DEFAULT 0,
		soundtrack longtext NULL,
		seen_a tinyint(1) NOT NULL DEFAULT 0,
		seen_b tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY pair (user_a,user_b),
		KEY user_b (user_b)
	) $charset;";

	$sql[] = 'CREATE TABLE ' . spotinder_table( 'blocks' ) . " (
		user_id bigint(20) unsigned NOT NULL,
		target_id bigint(20) unsigned NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (user_id,target_id),
		KEY target_id (target_id)
	) $charset;";

	$sql[] = 'CREATE TABLE ' . spotinder_table( 'reports' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		reporter_id bigint(20) unsigned NOT NULL,
		target_id bigint(20) unsigned NOT NULL,
		reason varchar(40) NOT NULL,
		details text NULL,
		status varchar(20) NOT NULL DEFAULT 'open',
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY target_id (target_id),
		KEY status (status)
	) $charset;";

	$sql[] = 'CREATE TABLE ' . spotinder_table( 'rsvps' ) . " (
		event_id bigint(20) unsigned NOT NULL,
		user_id bigint(20) unsigned NOT NULL,
		status varchar(20) NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (event_id,user_id),
		KEY user_id (user_id)
	) $charset;";

	$sql[] = 'CREATE TABLE ' . spotinder_table( 'invites' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		match_id bigint(20) unsigned NOT NULL,
		from_id bigint(20) unsigned NOT NULL,
		to_id bigint(20) unsigned NOT NULL,
		event_id bigint(20) unsigned NULL,
		kind varchar(30) NOT NULL,
		proposed_at datetime NULL,
		place varchar(190) NOT NULL DEFAULT '',
		note varchar(280) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'pending',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY match_id (match_id),
		KEY to_id (to_id)
	) $charset;";

	foreach ( $sql as $statement ) {
		dbDelta( $statement );
	}

	update_option( 'spotinder_db_version', SPOTINDER_DB_VERSION );
}

add_action(
	'init',
	function () {
		if ( get_option( 'spotinder_db_version' ) !== SPOTINDER_DB_VERSION ) {
			spotinder_install_tables();
		}
	},
	1
);
