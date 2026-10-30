<?php
/**
 * Página inicial: campanha + como funciona + agenda.
 * Todos os textos vêm de Spotinder > Página inicial.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

get_header();

$spotinder_sections = array( 'hero', 'how', 'campaign', 'intentions', 'features', 'events', 'faq', 'cta' );

foreach ( $spotinder_sections as $spotinder_section ) {
	get_template_part( 'template-parts/home/' . $spotinder_section );
}

get_footer();
