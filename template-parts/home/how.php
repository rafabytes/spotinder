<?php
/**
 * Home — como funciona.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

$spotinder_steps = (array) spotinder_opt( 'how_steps' );
?>
<section id="como-funciona" class="container-site scroll-mt-24 py-20">
	<div class="mb-12 max-w-2xl space-y-3">
		<p class="eyebrow"><?php echo esc_html( spotinder_opt( 'how_eyebrow' ) ); ?></p>
		<h2 class="section-title"><?php echo esc_html( spotinder_opt( 'how_title' ) ); ?></h2>
	</div>
	<?php // 3 passos (ou 6, 9…) ficam em 3 colunas; o resto em 4. ?>
	<ol class="grid gap-4 sm:grid-cols-2 <?php echo 0 === count( $spotinder_steps ) % 3 ? 'lg:grid-cols-3' : 'lg:grid-cols-4'; ?>">
		<?php foreach ( $spotinder_steps as $spotinder_i => $spotinder_step ) : ?>
			<li class="card relative space-y-3 p-6">
				<span class="absolute right-5 top-5 font-mono text-xs text-white/30"><?php echo esc_html( sprintf( '%02d', $spotinder_i + 1 ) ); ?></span>
				<span class="block text-3xl" aria-hidden="true"><?php echo esc_html( $spotinder_step['emoji'] ); ?></span>
				<h3 class="text-lg font-semibold text-white"><?php echo esc_html( $spotinder_step['title'] ); ?></h3>
				<?php if ( '' !== trim( (string) $spotinder_step['text'] ) ) : ?>
					<p class="text-sm leading-relaxed text-white/60"><?php echo esc_html( $spotinder_step['text'] ); ?></p>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
</section>
