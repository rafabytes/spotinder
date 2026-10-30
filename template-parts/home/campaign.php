<?php
/**
 * Home — campanha #NinguémOuveSozinho (história em destaque + outras histórias).
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

$spotinder_story_id = (int) spotinder_opt( 'campaign_story' );
if ( ! $spotinder_story_id || 'publish' !== get_post_status( $spotinder_story_id ) ) {
	$spotinder_latest   = get_posts(
		array(
			'post_type'   => 'spot_story',
			'numberposts' => 1,
			'fields'      => 'ids',
		)
	);
	$spotinder_story_id = $spotinder_latest ? (int) $spotinder_latest[0] : 0;
}

if ( ! $spotinder_story_id ) {
	return;
}

$spotinder_people   = (array) spotinder_field( 'story_people', $spotinder_story_id, array() );
$spotinder_chapters = (array) spotinder_field( 'story_chapters', $spotinder_story_id, array() );
$spotinder_track    = spotinder_parse_spotify_url( spotinder_field( 'story_soundtrack_url', $spotinder_story_id ) );
$spotinder_others   = get_posts(
	array(
		'post_type'   => 'spot_story',
		'numberposts' => 3,
		'exclude'     => array( $spotinder_story_id ),
	)
);
?>
<section id="campanha" class="relative scroll-mt-24 overflow-hidden py-20">
	<div class="campaign-bg" aria-hidden="true"></div>
	<div class="container-site relative space-y-14">
		<header class="mx-auto max-w-3xl space-y-5 text-center">
			<p class="eyebrow-pill mx-auto"><?php echo esc_html( spotinder_opt( 'campaign_eyebrow' ) ); ?></p>
			<h2 class="text-balance text-4xl font-semibold leading-tight tracking-tight text-white md:text-6xl"><?php echo esc_html( spotinder_opt( 'campaign_title' ) ); ?></h2>
			<p class="text-pretty text-lg text-white/70"><?php echo esc_html( spotinder_opt( 'campaign_text' ) ); ?></p>
		</header>

		<article class="grid gap-8 lg:grid-cols-[0.95fr_1.05fr]">
			<div class="card relative flex flex-col justify-between gap-10 overflow-hidden p-8 md:p-10">
				<div class="space-y-4">
					<p class="eyebrow"><?php echo esc_html( spotinder_field( 'story_city', $spotinder_story_id ) ); ?></p>
					<h3 class="text-3xl font-semibold leading-tight text-white"><?php echo esc_html( get_the_title( $spotinder_story_id ) ); ?></h3>
					<p class="text-white/60"><?php echo esc_html( spotinder_field( 'story_subtitle', $spotinder_story_id ) ); ?></p>
				</div>

				<?php if ( spotinder_field( 'story_quote', $spotinder_story_id ) ) : ?>
					<blockquote class="relative space-y-3 border-l-2 border-accent pl-6">
						<p class="text-xl leading-relaxed text-white md:text-2xl">“<?php echo esc_html( spotinder_field( 'story_quote', $spotinder_story_id ) ); ?>”</p>
						<footer class="text-sm text-white/50">— <?php echo esc_html( spotinder_field( 'story_quote_author', $spotinder_story_id ) ); ?></footer>
					</blockquote>
				<?php endif; ?>

				<ul class="grid grid-cols-2 gap-3">
					<?php foreach ( $spotinder_people as $spotinder_p ) : ?>
						<li class="flex items-center gap-3 rounded-2xl bg-white/5 p-3">
							<span class="grid size-11 shrink-0 place-items-center rounded-full text-sm font-semibold text-white" style="background: <?php echo esc_attr( spotinder_gradient( $spotinder_p['name'] ) ); ?>"><?php echo esc_html( spotinder_initials( $spotinder_p['name'] ) ); ?></span>
							<span class="min-w-0">
								<span class="block truncate text-sm font-semibold text-white"><?php echo esc_html( $spotinder_p['name'] . ( $spotinder_p['age'] ? ', ' . $spotinder_p['age'] : '' ) ); ?></span>
								<span class="block truncate text-xs text-white/50">🎧 <?php echo esc_html( $spotinder_p['artist'] ); ?></span>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="card flex flex-col p-3 md:p-4">
				<div class="flex items-center gap-4 rounded-2xl bg-gradient-to-br from-brand to-accent p-5">
					<span class="vinyl" aria-hidden="true"></span>
					<div class="min-w-0">
						<p class="text-xs uppercase tracking-widest text-white/70"><?php echo esc_html( spotinder_opt( 'soundtrack_label' ) ); ?></p>
						<p class="truncate text-lg font-semibold text-white"><?php echo esc_html( spotinder_field( 'story_soundtrack_label', $spotinder_story_id ) ); ?></p>
					</div>
				</div>

				<?php if ( $spotinder_track ) : ?>
					<iframe class="mt-3 w-full rounded-xl" style="height:80px" src="<?php echo esc_url( 'https://open.spotify.com/embed/' . $spotinder_track['type'] . '/' . $spotinder_track['id'] . '?theme=0' ); ?>" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" title="<?php esc_attr_e( 'Player do Spotify', 'spotinder' ); ?>"></iframe>
				<?php endif; ?>

				<ol class="mt-2 divide-y divide-white/5">
					<?php foreach ( $spotinder_chapters as $spotinder_i => $spotinder_ch ) : ?>
						<li class="grid grid-cols-[2.5rem_1fr] gap-3 px-3 py-5">
							<span class="pt-0.5 font-mono text-sm text-white/30"><?php echo esc_html( sprintf( '%02d', $spotinder_i + 1 ) ); ?></span>
							<div class="space-y-1.5">
								<p class="text-xs uppercase tracking-widest text-accent"><?php echo esc_html( $spotinder_ch['label'] ); ?></p>
								<h4 class="font-semibold text-white"><?php echo esc_html( $spotinder_ch['title'] ); ?></h4>
								<p class="line-clamp-3 text-sm leading-relaxed text-white/60"><?php echo esc_html( $spotinder_ch['text'] ); ?></p>
							</div>
						</li>
					<?php endforeach; ?>
				</ol>

				<div class="mt-auto p-3">
					<a href="<?php echo esc_url( get_permalink( $spotinder_story_id ) ); ?>" class="btn-ghost w-full justify-center"><?php echo esc_html( spotinder_opt( 'campaign_cta_label' ) ); ?> <?php echo spotinder_icon( 'arrow', 'size-4' ); // phpcs:ignore ?></a>
				</div>
			</div>
		</article>

		<?php if ( $spotinder_others ) : ?>
			<div class="grid gap-4 md:grid-cols-3">
				<?php foreach ( $spotinder_others as $spotinder_other ) : ?>
					<?php get_template_part( 'template-parts/story-card', null, array( 'story' => $spotinder_other ) ); ?>
				<?php endforeach; ?>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'spot_story' ) ); ?>" class="card grid place-items-center p-6 text-center text-white/70 transition hover:text-white">
					<span class="space-y-2">
						<span class="block text-3xl">💌</span>
						<span class="block font-semibold"><?php esc_html_e( 'Todas as histórias', 'spotinder' ); ?></span>
					</span>
				</a>
			</div>
		<?php endif; ?>
	</div>
</section>
