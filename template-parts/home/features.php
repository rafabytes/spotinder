<?php
/**
 * Home — recursos.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

$spotinder_features = (array) spotinder_opt( 'features' );
?>
<section class="container-site py-20">
	<div class="mb-12 max-w-2xl space-y-3">
		<p class="eyebrow"><?php echo esc_html( spotinder_opt( 'features_eyebrow' ) ); ?></p>
		<h2 class="section-title"><?php echo esc_html( spotinder_opt( 'features_title' ) ); ?></h2>
	</div>
	<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
		<?php foreach ( $spotinder_features as $spotinder_f ) : ?>
			<div class="card group space-y-3 p-6 transition hover:-translate-y-1 hover:border-brand/40">
				<span class="grid size-12 place-items-center rounded-2xl bg-white/5 text-2xl transition group-hover:bg-brand/20" aria-hidden="true"><?php echo esc_html( $spotinder_f['emoji'] ); ?></span>
				<h3 class="text-lg font-semibold text-white"><?php echo esc_html( $spotinder_f['title'] ); ?></h3>
				<p class="text-sm leading-relaxed text-white/60"><?php echo esc_html( $spotinder_f['text'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>
