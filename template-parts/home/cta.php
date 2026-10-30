<?php
/**
 * Home — chamada final.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="container-site py-20">
	<div class="relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-brand via-brand to-accent px-8 py-16 text-center md:px-16 md:py-24">
		<div class="equalizer" aria-hidden="true"><?php echo str_repeat( '<span></span>', 24 ); // phpcs:ignore ?></div>
		<div class="relative mx-auto max-w-2xl space-y-6">
			<h2 class="text-balance text-4xl font-semibold leading-tight tracking-tight text-white md:text-5xl"><?php echo esc_html( spotinder_opt( 'final_cta_title' ) ); ?></h2>
			<p class="text-lg text-white/80"><?php echo esc_html( spotinder_opt( 'final_cta_text' ) ); ?></p>
			<?php get_template_part( 'template-parts/cta-button', null, array( 'label' => spotinder_opt( 'final_cta_label' ), 'class' => 'btn-light btn-lg' ) ); ?>
		</div>
	</div>
</section>
