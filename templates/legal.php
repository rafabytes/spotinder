<?php
/**
 * Template Name: Página legal (com resumo e índice)
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$spotinder_summary = (array) spotinder_field( 'legal_summary', get_the_ID(), array() );
	$spotinder_updated = spotinder_field( 'legal_updated', get_the_ID() );

	// Índice a partir dos <h2> do conteúdo (ids adicionados em spotinder_heading_ids()).
	$spotinder_content = spotinder_heading_ids( apply_filters( 'the_content', get_the_content() ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	preg_match_all( '#<h2 id="([^"]+)"[^>]*>(.*?)</h2>#s', $spotinder_content, $spotinder_toc, PREG_SET_ORDER );
	?>
	<article class="container-site py-14">
		<header class="mb-10 max-w-3xl space-y-4">
			<p class="eyebrow"><?php esc_html_e( 'Transparência', 'spotinder' ); ?></p>
			<h1 class="text-4xl font-semibold tracking-tight text-white md:text-5xl"><?php the_title(); ?></h1>
			<?php if ( $spotinder_updated ) : ?>
				<p class="text-sm text-white/50"><?php echo esc_html( sprintf( __( 'Última atualização: %s', 'spotinder' ), spotinder_date( $spotinder_updated, 'd/m/Y' ) ) ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( $spotinder_summary ) : ?>
			<section class="card mb-12 max-w-4xl p-6 md:p-8" aria-labelledby="resumo">
				<h2 id="resumo" class="mb-5 text-lg font-semibold text-white"><?php esc_html_e( 'Resumo em 30 segundos', 'spotinder' ); ?></h2>
				<ul class="grid gap-4 md:grid-cols-2">
					<?php foreach ( $spotinder_summary as $spotinder_item ) : ?>
						<li class="flex gap-3 text-sm leading-relaxed text-white/75">
							<span class="text-xl" aria-hidden="true"><?php echo esc_html( $spotinder_item['emoji'] ); ?></span>
							<span><?php echo esc_html( $spotinder_item['text'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<div class="grid gap-12 lg:grid-cols-[16rem_1fr]">
			<?php if ( $spotinder_toc ) : ?>
				<nav class="hidden lg:block" aria-label="<?php esc_attr_e( 'Índice', 'spotinder' ); ?>">
					<ol class="sticky top-24 space-y-2 border-l border-white/10 text-sm">
						<?php foreach ( $spotinder_toc as $spotinder_h ) : ?>
							<li><a class="-ml-px block border-l border-transparent pl-4 text-white/50 hover:border-white hover:text-white" href="#<?php echo esc_attr( $spotinder_h[1] ); ?>"><?php echo esc_html( wp_strip_all_tags( $spotinder_h[2] ) ); ?></a></li>
						<?php endforeach; ?>
					</ol>
				</nav>
			<?php endif; ?>
			<div class="prose-site max-w-3xl">
				<?php echo $spotinder_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</article>
	<?php
endwhile;

get_footer();
