<?php
/**
 * Evento.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$spotinder_e       = spotinder_event_data( get_the_ID() );
	$spotinder_spotify = spotinder_parse_spotify_url( spotinder_field( 'event_spotify_url' ) );
	?>
	<article class="container-site py-12">
		<a href="<?php echo esc_url( get_post_type_archive_link( 'spot_event' ) ); ?>" class="mb-8 inline-flex items-center gap-2 text-sm text-white/50 hover:text-white">← <?php esc_html_e( 'Agenda', 'spotinder' ); ?></a>

		<div class="grid gap-10 lg:grid-cols-[1.3fr_0.7fr]">
			<div class="space-y-8">
				<div class="relative aspect-[2/1] overflow-hidden rounded-[2rem]" style="background: <?php echo esc_attr( $spotinder_e['gradient'] ); ?>">
					<?php if ( $spotinder_e['image'] ) : ?>
						<img src="<?php echo esc_url( get_the_post_thumbnail_url( null, 'large' ) ); ?>" alt="" class="absolute inset-0 size-full object-cover">
					<?php endif; ?>
					<?php if ( $spotinder_e['official'] ) : ?>
						<span class="absolute left-5 top-5 rounded-full bg-highlight px-3 py-1 text-xs font-semibold text-ink"><?php esc_html_e( 'Encontro oficial da comunidade', 'spotinder' ); ?></span>
					<?php endif; ?>
				</div>

				<header class="space-y-3">
					<?php if ( $spotinder_e['types'] ) : ?>
						<p class="eyebrow"><?php echo esc_html( implode( ' · ', $spotinder_e['types'] ) ); ?></p>
					<?php endif; ?>
					<h1 class="text-balance text-4xl font-semibold tracking-tight text-white md:text-5xl"><?php the_title(); ?></h1>
				</header>

				<div class="prose-site"><?php the_content(); ?></div>

				<?php if ( $spotinder_e['artists'] ) : ?>
					<section class="space-y-3">
						<h2 class="text-lg font-semibold text-white"><?php esc_html_e( 'Vai tocar', 'spotinder' ); ?></h2>
						<ul class="flex flex-wrap gap-2">
							<?php foreach ( $spotinder_e['artists'] as $spotinder_artist ) : ?>
								<li><a class="chip" href="<?php echo esc_url( 'https://open.spotify.com/search/' . rawurlencode( $spotinder_artist ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $spotinder_artist ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( $spotinder_spotify ) : ?>
					<iframe class="w-full rounded-2xl" style="height:352px" src="<?php echo esc_url( 'https://open.spotify.com/embed/' . $spotinder_spotify['type'] . '/' . $spotinder_spotify['id'] . '?theme=0' ); ?>" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" title="<?php esc_attr_e( 'Player do Spotify', 'spotinder' ); ?>"></iframe>
				<?php endif; ?>
			</div>

			<aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
				<div class="card space-y-4 p-6">
					<p class="flex items-start gap-3 text-white/80"><?php echo spotinder_icon( 'calendar', 'size-5 shrink-0 text-brand' ); // phpcs:ignore ?> <span><?php echo esc_html( $spotinder_e['dateLabel'] ); ?></span></p>
					<p class="flex items-start gap-3 text-white/80"><?php echo spotinder_icon( 'pin', 'size-5 shrink-0 text-brand' ); // phpcs:ignore ?>
						<span><strong class="block text-white"><?php echo esc_html( $spotinder_e['venue'] ); ?></strong><?php echo esc_html( $spotinder_e['address'] ); ?></span>
					</p>
					<?php if ( $spotinder_e['price'] ) : ?>
						<p class="flex items-start gap-3 text-white/80"><?php echo spotinder_icon( 'ticket', 'size-5 shrink-0 text-brand' ); // phpcs:ignore ?> <?php echo esc_html( $spotinder_e['price'] ); ?></p>
					<?php endif; ?>
					<?php if ( $spotinder_e['ticketUrl'] ) : ?>
						<a href="<?php echo esc_url( $spotinder_e['ticketUrl'] ); ?>" class="btn-ghost w-full justify-center" target="_blank" rel="noopener"><?php esc_html_e( 'Ingressos / inscrição', 'spotinder' ); ?></a>
					<?php endif; ?>
				</div>

				<div class="card p-6" data-island="EventRsvp" data-props="<?php echo spotinder_json_attr( array( 'eventId' => get_the_ID() ) ); // phpcs:ignore ?>">
					<span class="loader mx-auto block"></span>
				</div>

				<div class="card space-y-2 p-6 text-sm text-white/60">
					<p class="flex items-center gap-2 font-semibold text-white"><?php echo spotinder_icon( 'shield', 'size-5 text-highlight' ); // phpcs:ignore ?> <?php esc_html_e( 'Vai encontrar um match?', 'spotinder' ); ?></p>
					<p><?php esc_html_e( 'Combine o ponto de encontro dentro do evento, vá por conta própria e avise alguém de confiança.', 'spotinder' ); ?></p>
					<a class="underline underline-offset-4" href="<?php echo esc_url( spotinder_page_url( 'safety' ) ); ?>"><?php esc_html_e( 'Dicas de segurança', 'spotinder' ); ?></a>
				</div>
			</aside>
		</div>
	</article>
	<?php
endwhile;

get_footer();
