<?php
/**
 * Template Name: App — Descobrir (deck)
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/app-shell', null, array( 'island' => 'Deck' ) );
get_footer();
