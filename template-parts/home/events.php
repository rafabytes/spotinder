<?php
/**
 * Home — próximos eventos.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

$spotinder_count = (int) spotinder_opt( 'events_count' );
$spotinder_items = $spotinder_count ? spotinder_upcoming_events( $spotinder_count ) : array();

if ( ! $spotinder_items ) {
	return;
}
?>
<section class="container-site py-20">
	<div class="mb-10 flex flex-wrap items-end justify-between gap-6">
		<div class="max-w-2xl space-y-3">
			<p class="eyebrow"><?php echo esc_html( spotinder_opt( 'events_eyebrow' ) ); ?></p>
			<h2 class="section-title"><?php echo esc_html( spotinder_opt( 'events_title' ) ); ?></h2>
			<p class="text-white/60"><?php echo esc_html( spotinder_opt( 'events_text' ) ); ?></p>
		</div>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'spot_event' ) ); ?>" class="btn-ghost"><?php echo esc_html( spotinder_opt( 'events_cta_label' ) ); ?> <?php echo spotinder_icon( 'arrow', 'size-4' ); // phpcs:ignore ?></a>
	</div>
	<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
		<?php foreach ( $spotinder_items as $spotinder_event ) : ?>
			<?php get_template_part( 'template-parts/event-card', null, array( 'event' => $spotinder_event ) ); ?>
		<?php endforeach; ?>
	</div>
</section>
