<?php
/**
 * Template Name: App — Perfil e cadastro
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/app-shell', null, array( 'island' => 'Profile' ) );
get_footer();
