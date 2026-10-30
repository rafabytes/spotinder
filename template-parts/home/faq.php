<?php
/**
 * Home — perguntas frequentes.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

$spotinder_faq = (array) spotinder_opt( 'faq' );
if ( ! $spotinder_faq ) {
	return;
}
?>
<section class="container-site grid gap-10 py-20 lg:grid-cols-[0.8fr_1.2fr]">
	<div class="space-y-3">
		<p class="eyebrow"><?php echo esc_html( spotinder_opt( 'faq_eyebrow' ) ); ?></p>
		<h2 class="section-title"><?php echo esc_html( spotinder_opt( 'faq_title' ) ); ?></h2>
		<p class="pt-2 text-sm text-white/50">
			<a class="underline decoration-white/20 underline-offset-4 hover:text-white" href="<?php echo esc_url( spotinder_page_url( 'privacy' ) ); ?>"><?php esc_html_e( 'Leia a Política de Privacidade completa', 'spotinder' ); ?></a>
		</p>
	</div>
	<div class="space-y-3">
		<?php foreach ( $spotinder_faq as $spotinder_q ) : ?>
			<details class="faq card group p-0">
				<summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 font-medium text-white">
					<?php echo esc_html( $spotinder_q['question'] ); ?>
					<span class="grid size-8 shrink-0 place-items-center rounded-full bg-white/5 transition group-open:rotate-45">+</span>
				</summary>
				<p class="px-5 pb-5 text-sm leading-relaxed text-white/60"><?php echo esc_html( $spotinder_q['answer'] ); ?></p>
			</details>
		<?php endforeach; ?>
	</div>
</section>
