<?php
/**
 * Página padrão.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article class="container-site max-w-3xl py-14">
		<h1 class="mb-8 text-4xl font-semibold tracking-tight text-white md:text-5xl"><?php the_title(); ?></h1>
		<div class="prose-site"><?php the_content(); ?></div>
	</article>
	<?php
endwhile;

get_footer();
