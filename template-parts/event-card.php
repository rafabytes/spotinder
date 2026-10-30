<?php
/**
 * Cartão de evento.
 *
 * @package Spotinder
 *
 * @var array $args { event: WP_Post, because?: string[] }
 */

defined( 'ABSPATH' ) || exit;

$spotinder_e       = spotinder_event_data( $args['event'] );
$spotinder_because = $args['because'] ?? array();
$spotinder_counts  = spotinder_event_rsvp_counts( $spotinder_e['id'] );
?>
<article class="card group relative flex flex-col overflow-hidden">
	<div class="relative aspect-[16/9] overflow-hidden" style="background: <?php echo esc_attr( $spotinder_e['gradient'] ); ?>">
		<?php if ( $spotinder_e['image'] ) : ?>
			<img src="<?php echo esc_url( $spotinder_e['image'] ); ?>" alt="" class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
		<?php else : ?>
			<div class="absolute inset-0 grid place-items-center opacity-30"><?php echo spotinder_icon( 'ticket', 'size-24 text-white' ); // phpcs:ignore ?></div>
		<?php endif; ?>
		<div class="absolute left-4 top-4 rounded-2xl bg-ink/80 px-3 py-2 text-center leading-none backdrop-blur">
			<span class="block text-2xl font-semibold text-white"><?php echo esc_html( $spotinder_e['day'] ); ?></span>
			<span class="text-[11px] uppercase tracking-wider text-white/60"><?php echo esc_html( $spotinder_e['month'] ); ?></span>
		</div>
		<?php if ( $spotinder_e['official'] ) : ?>
			<span class="absolute right-4 top-4 rounded-full bg-highlight px-3 py-1 text-xs font-semibold text-ink"><?php esc_html_e( 'Encontro oficial', 'spotinder' ); ?></span>
		<?php endif; ?>
	</div>
	<div class="flex flex-1 flex-col gap-3 p-5">
		<?php if ( $spotinder_e['types'] ) : ?>
			<p class="eyebrow"><?php echo esc_html( implode( ' · ', $spotinder_e['types'] ) ); ?></p>
		<?php endif; ?>
		<h3 class="text-lg font-semibold leading-snug text-white">
			<a href="<?php echo esc_url( $spotinder_e['url'] ); ?>" class="after:absolute after:inset-0"><?php echo esc_html( $spotinder_e['title'] ); ?></a>
		</h3>
		<p class="flex items-center gap-2 text-sm text-white/60"><?php echo spotinder_icon( 'pin', 'size-4 shrink-0' ); // phpcs:ignore ?> <?php echo esc_html( trim( $spotinder_e['venue'] . ' · ' . $spotinder_e['city'], ' ·' ) ); ?></p>
		<p class="flex items-center gap-2 text-sm text-white/60"><?php echo spotinder_icon( 'calendar', 'size-4 shrink-0' ); // phpcs:ignore ?> <?php echo esc_html( $spotinder_e['dateLabel'] ); ?></p>
		<?php if ( $spotinder_because ) : ?>
			<p class="rounded-xl bg-brand/15 px-3 py-2 text-xs text-white/80">🎧 <?php echo esc_html( sprintf( __( 'Porque você ouve %s', 'spotinder' ), implode( ', ', array_slice( $spotinder_because, 0, 2 ) ) ) ); ?></p>
		<?php endif; ?>
		<div class="mt-auto flex items-center justify-between pt-2 text-xs text-white/50">
			<span><?php echo esc_html( $spotinder_e['price'] ); ?></span>
			<span><?php echo esc_html( sprintf( _n( '%d pessoa vai', '%d pessoas vão', $spotinder_counts['going'], 'spotinder' ), $spotinder_counts['going'] ) ); ?></span>
		</div>
	</div>
</article>
