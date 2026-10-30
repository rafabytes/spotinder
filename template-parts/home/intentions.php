<?php
/**
 * Home — intenções (não é só namoro).
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

$spotinder_intentions = (array) spotinder_opt( 'intentions' );
?>
<section class="container-site py-20">
	<div class="card grid gap-10 overflow-hidden p-8 md:grid-cols-2 md:p-12">
		<div class="space-y-4">
			<p class="eyebrow"><?php echo esc_html( spotinder_opt( 'intentions_eyebrow' ) ); ?></p>
			<h2 class="section-title"><?php echo esc_html( spotinder_opt( 'intentions_title' ) ); ?></h2>
			<p class="text-white/60"><?php echo esc_html( spotinder_opt( 'intentions_text' ) ); ?></p>
		</div>
		<ul class="flex flex-wrap content-center gap-3">
			<?php foreach ( $spotinder_intentions as $spotinder_i => $spotinder_int ) : ?>
				<li class="intention-chip" style="--d: <?php echo (int) $spotinder_i; ?>">
					<span aria-hidden="true"><?php echo esc_html( $spotinder_int['emoji'] ); ?></span>
					<?php echo esc_html( $spotinder_int['label'] ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
