<?php
/**
 * Home — hero com pilha de cartões animada (um perfil de exemplo por gênero).
 * Cartões editáveis em Spotinder > Página inicial > Destaque; a animação está em src/js/ui.js.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

$spotinder_cards = spotinder_hero_cards();
$spotinder_stats = (array) spotinder_opt( 'hero_stats' );
?>
<section class="relative overflow-hidden">
	<div class="hero-glow" aria-hidden="true"></div>
	<div class="container-site relative grid items-center gap-14 py-16 md:py-24 lg:grid-cols-[1.1fr_0.9fr]">
		<div class="space-y-8">
			<p class="eyebrow-pill"><?php echo esc_html( spotinder_opt( 'hero_eyebrow' ) ); ?></p>
			<h1 class="text-balance text-5xl font-semibold leading-[1.02] tracking-tight text-white md:text-7xl">
				<?php echo esc_html( spotinder_opt( 'hero_title' ) ); ?>
			</h1>
			<p class="max-w-xl text-pretty text-lg leading-relaxed text-white/70"><?php echo esc_html( spotinder_opt( 'hero_text' ) ); ?></p>
			<div class="flex flex-wrap items-center gap-3">
				<?php get_template_part( 'template-parts/cta-button', null, array( 'label' => spotinder_opt( 'hero_cta_label' ) ) ); ?>
				<a href="#como-funciona" class="btn-ghost btn-lg"><?php echo esc_html( spotinder_opt( 'hero_secondary_label' ) ); ?></a>
			</div>
			<?php if ( $spotinder_stats ) : ?>
				<dl class="grid max-w-lg grid-cols-3 gap-4 border-t border-white/10 pt-6">
					<?php foreach ( $spotinder_stats as $spotinder_stat ) : ?>
						<div>
							<dt class="sr-only"><?php echo esc_html( $spotinder_stat['label'] ); ?></dt>
							<dd class="text-3xl font-semibold text-white"><?php echo esc_html( $spotinder_stat['value'] ); ?></dd>
							<dd class="text-xs leading-snug text-white/50"><?php echo esc_html( $spotinder_stat['label'] ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
		</div>

		<?php if ( $spotinder_cards ) : ?>
			<div class="hero-deck relative mx-auto h-[470px] w-full max-w-[21rem]" data-hero-deck aria-hidden="true">
				<span class="hero-match" data-hero-match>Fez barulho! ♪</span>

				<?php foreach ( $spotinder_cards as $spotinder_i => $spotinder_card ) : ?>
					<?php
					$spotinder_top  = array_slice( $spotinder_card['artists'], 0, 3 );
					$spotinder_rest = array_slice( $spotinder_card['artists'], 3 );
					?>
					<article class="hero-swipe absolute inset-0 flex flex-col overflow-hidden rounded-[2rem] border border-white/10 bg-surface shadow-2xl shadow-black/60" data-slot="<?php echo (int) $spotinder_i; ?>">
						<span class="hero-stamp hero-stamp-like">Curti</span>
						<span class="hero-stamp hero-stamp-pass">Passo</span>

						<div class="relative grid h-[50%] shrink-0 grid-cols-3 grid-rows-2 gap-0.5 bg-ink">
							<?php foreach ( $spotinder_top as $spotinder_j => $spotinder_artist ) : ?>
								<div class="relative overflow-hidden <?php echo 0 === $spotinder_j ? ( 1 === count( $spotinder_top ) ? 'col-span-3 row-span-2' : 'col-span-2 row-span-2' ) : ( 2 === count( $spotinder_top ) ? 'row-span-2' : '' ); ?>" style="background: <?php echo esc_attr( spotinder_gradient( $spotinder_artist['name'] ) ); ?>">
									<?php if ( $spotinder_artist['image'] ) : ?>
										<img src="<?php echo esc_url( $spotinder_artist['image'] ); ?>" alt="" class="absolute inset-0 size-full object-cover" loading="lazy">
									<?php else : ?>
										<span class="absolute inset-0 grid place-items-center text-2xl font-bold text-white/30"><?php echo esc_html( spotinder_initials( $spotinder_artist['name'] ) ); ?></span>
									<?php endif; ?>
									<span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 to-transparent leading-tight text-white <?php echo 0 === $spotinder_j ? 'p-3 pb-11 pt-10' : 'p-2 pt-6'; ?>">
										<span class="block text-[9px] font-semibold uppercase tracking-widest text-white/60">#<?php echo (int) $spotinder_j + 1; ?> favorito</span>
										<span class="block font-semibold <?php echo 0 === $spotinder_j ? 'text-base' : 'text-xs'; ?>"><?php echo esc_html( $spotinder_artist['name'] ); ?></span>
									</span>
								</div>
							<?php endforeach; ?>
							<span class="absolute left-3 top-3 rounded-full bg-ink/80 px-3 py-1 text-xs font-semibold text-white backdrop-blur">🎧 <?php echo esc_html( $spotinder_card['genre'] ); ?></span>
						</div>

						<header class="relative flex items-center gap-3 py-3 pl-[6.75rem] pr-5">
							<span class="absolute -top-9 left-5 grid size-[4.5rem] place-items-center overflow-hidden rounded-full text-xl font-semibold text-white shadow-xl shadow-black/50 ring-4 ring-surface" style="background: <?php echo esc_attr( $spotinder_card['gradient'] ); ?>">
								<?php if ( $spotinder_card['photo'] ) : ?>
									<img src="<?php echo esc_url( $spotinder_card['photo'] ); ?>" alt="" class="size-full object-cover">
								<?php else : ?>
									<?php echo esc_html( $spotinder_card['initials'] ); ?>
								<?php endif; ?>
							</span>
							<div class="min-w-0 flex-1">
								<p class="truncate text-xl font-semibold leading-tight text-white"><?php echo esc_html( $spotinder_card['name'] ); ?></p>
								<p class="truncate text-xs text-white/50"><?php echo esc_html( $spotinder_card['city'] ); ?></p>
							</div>
							<span class="rounded-full bg-accent px-3 py-1 text-sm font-semibold text-white"><?php echo (int) $spotinder_card['score']; ?>%</span>
						</header>

						<div class="flex flex-1 flex-col gap-3 px-5 pb-5">
							<?php if ( $spotinder_rest ) : ?>
								<p class="text-[10px] font-semibold uppercase tracking-widest text-white/40"><?php esc_html_e( 'Também curte', 'spotinder' ); ?></p>
								<div class="flex flex-wrap gap-1.5">
									<?php foreach ( $spotinder_rest as $spotinder_artist ) : ?>
										<span class="inline-flex items-center gap-2 rounded-full bg-white/5 py-1 pl-1 pr-3 text-xs text-white/75">
											<span class="size-6 overflow-hidden rounded-full" style="background: <?php echo esc_attr( spotinder_gradient( $spotinder_artist['name'] ) ); ?>">
												<?php if ( $spotinder_artist['image'] ) : ?>
													<img src="<?php echo esc_url( $spotinder_artist['image'] ); ?>" alt="" class="size-full object-cover" loading="lazy">
												<?php endif; ?>
											</span>
											<?php echo esc_html( $spotinder_artist['name'] ); ?>
										</span>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
							<div class="mt-auto flex items-center justify-center gap-4">
								<span class="grid size-12 place-items-center rounded-full border border-white/15 text-rose-300"><?php echo spotinder_icon( 'x' ); // phpcs:ignore ?></span>
								<span class="grid size-14 place-items-center rounded-full bg-accent text-white shadow-lg shadow-accent/40"><?php echo spotinder_icon( 'heart' ); // phpcs:ignore ?></span>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
