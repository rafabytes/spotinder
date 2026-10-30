<?php
/**
 * Template Name: App — Matches
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part(
	'template-parts/app-shell',
	null,
	array(
		'island' => 'Matches',
		'props'  => array(
			'events' => array_map( 'spotinder_event_data', spotinder_upcoming_events( 20 ) ),
		),
	)
);
get_footer();
