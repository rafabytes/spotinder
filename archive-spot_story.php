<?php
/**
 * Arquivo de histórias da campanha.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="container-site py-14">
	<header class="mx-auto mb-14 max-w-3xl space-y-4 text-center">
		<p class="eyebrow-pill mx-auto"><?php echo esc_html( spotinder_opt( 'campaign_eyebrow' ) ); ?></p>
		<h1 class="text-balance text-4xl font-semibold tracking-tight text-white md:text-6xl"><?php echo esc_html( spotinder_opt( 'campaign_title' ) ); ?></h1>
		<p class="text-lg text-white/60"><?php echo esc_html( spotinder_opt( 'campaign_text' ) ); ?></p>
	</header>
	<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
		<?php
		while ( have_posts() ) :
			the_post();
			get_template_part( 'template-parts/story-card', null, array( 'story' => get_post() ) );
		endwhile;
		?>
	</div>
	<div class="mt-10"><?php the_posts_pagination(); ?></div>
</section>
<?php
get_footer();
