<?php
/**
 * História da campanha (formato editorial em "faixas").
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$spotinder_people   = (array) spotinder_field( 'story_people', get_the_ID(), array() );
	$spotinder_chapters = (array) spotinder_field( 'story_chapters', get_the_ID(), array() );
	$spotinder_track    = spotinder_parse_spotify_url( spotinder_field( 'story_soundtrack_url' ) );
	$spotinder_artists  = array_filter( array_map( 'trim', explode( "\n", (string) spotinder_field( 'story_shared_artists' ) ) ) );
	?>
	<article>
		<header class="relative overflow-hidden">
			<div class="campaign-bg" aria-hidden="true"></div>
			<div class="container-site relative mx-auto max-w-4xl space-y-6 py-20 text-center">
				<p class="eyebrow-pill mx-auto"><?php echo esc_html( spotinder_field( 'story_hashtag' ) ); ?> · <?php echo esc_html( spotinder_field( 'story_city' ) ); ?></p>
				<h1 class="text-balance text-4xl font-semibold leading-tight tracking-tight text-white md:text-6xl"><?php the_title(); ?></h1>
				<p class="text-pretty text-xl text-white/70"><?php echo esc_html( spotinder_field( 'story_subtitle' ) ); ?></p>
				<?php if ( has_post_thumbnail() ) : ?>
					<?php the_post_thumbnail( 'large', array( 'class' => 'mx-auto mt-8 rounded-[2rem]' ) ); ?>
				<?php endif; ?>
			</div>
		</header>

		<div class="container-site mx-auto max-w-4xl space-y-16 pb-10">
			<?php if ( $spotinder_people ) : ?>
				<ul class="grid gap-3 sm:grid-cols-2">
					<?php foreach ( $spotinder_people as $spotinder_p ) : ?>
						<li class="card flex items-center gap-4 p-4">
							<span class="grid size-14 shrink-0 place-items-center rounded-full text-lg font-semibold text-white" style="background: <?php echo esc_attr( spotinder_gradient( $spotinder_p['name'] ) ); ?>"><?php echo esc_html( spotinder_initials( $spotinder_p['name'] ) ); ?></span>
							<span>
								<span class="block font-semibold text-white"><?php echo esc_html( $spotinder_p['name'] . ( $spotinder_p['age'] ? ', ' . $spotinder_p['age'] : '' ) ); ?></span>
								<span class="block text-sm text-white/60"><?php echo esc_html( $spotinder_p['role'] ); ?></span>
								<span class="block text-xs text-white/40">🎧 <?php echo esc_html( $spotinder_p['artist'] ); ?></span>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( $spotinder_track || spotinder_field( 'story_soundtrack_label' ) ) : ?>
				<section class="card overflow-hidden p-3">
					<div class="flex items-center gap-4 rounded-2xl bg-gradient-to-br from-brand to-accent p-5">
						<span class="vinyl" aria-hidden="true"></span>
						<div>
							<p class="text-xs uppercase tracking-widest text-white/70"><?php echo esc_html( spotinder_opt( 'soundtrack_label' ) ); ?></p>
							<p class="text-lg font-semibold text-white"><?php echo esc_html( spotinder_field( 'story_soundtrack_label' ) ); ?></p>
						</div>
					</div>
					<?php if ( $spotinder_track ) : ?>
						<iframe class="mt-3 w-full rounded-xl" style="height:152px" src="<?php echo esc_url( 'https://open.spotify.com/embed/' . $spotinder_track['type'] . '/' . $spotinder_track['id'] . '?theme=0' ); ?>" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" title="<?php esc_attr_e( 'Player do Spotify', 'spotinder' ); ?>"></iframe>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php foreach ( $spotinder_chapters as $spotinder_i => $spotinder_ch ) : ?>
				<section class="grid gap-6 md:grid-cols-[8rem_1fr]">
					<div class="md:pt-2">
						<p class="font-mono text-5xl font-light text-white/15"><?php echo esc_html( sprintf( '%02d', $spotinder_i + 1 ) ); ?></p>
						<p class="text-xs uppercase tracking-widest text-accent"><?php echo esc_html( $spotinder_ch['label'] ); ?></p>
					</div>
					<div class="space-y-3">
						<h2 class="text-2xl font-semibold text-white"><?php echo esc_html( $spotinder_ch['title'] ); ?></h2>
						<p class="text-lg leading-relaxed text-white/70"><?php echo esc_html( $spotinder_ch['text'] ); ?></p>
					</div>
				</section>
				<?php if ( 1 === $spotinder_i && spotinder_field( 'story_quote' ) ) : ?>
					<blockquote class="border-l-2 border-accent py-2 pl-8">
						<p class="text-2xl leading-relaxed text-white md:text-3xl">“<?php echo esc_html( spotinder_field( 'story_quote' ) ); ?>”</p>
						<footer class="mt-3 text-white/50">— <?php echo esc_html( spotinder_field( 'story_quote_author' ) ); ?></footer>
					</blockquote>
				<?php endif; ?>
			<?php endforeach; ?>

			<?php if ( $spotinder_artists ) : ?>
				<section class="space-y-3">
					<h2 class="text-lg font-semibold text-white"><?php esc_html_e( 'O que uniu essa turma', 'spotinder' ); ?></h2>
					<ul class="flex flex-wrap gap-2">
						<?php foreach ( $spotinder_artists as $spotinder_artist ) : ?>
							<li><a class="chip" href="<?php echo esc_url( 'https://open.spotify.com/search/' . rawurlencode( $spotinder_artist ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $spotinder_artist ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<div class="prose-site text-white/60"><?php the_content(); ?></div>

			<div class="card flex flex-col items-center gap-5 p-10 text-center">
				<p class="text-2xl font-semibold text-white"><?php echo esc_html( spotinder_opt( 'final_cta_title' ) ); ?></p>
				<?php get_template_part( 'template-parts/cta-button', null, array( 'label' => spotinder_opt( 'final_cta_label' ) ) ); ?>
			</div>
		</div>
	</article>
	<?php
endwhile;

get_footer();
