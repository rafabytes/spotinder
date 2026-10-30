<?php
/**
 * Cartão de história da campanha.
 *
 * @package Spotinder
 *
 * @var array $args { story: WP_Post }
 */

defined( 'ABSPATH' ) || exit;

$spotinder_story  = $args['story'];
$spotinder_people = (array) spotinder_field( 'story_people', $spotinder_story->ID, array() );
?>
<article class="card group relative flex flex-col gap-4 p-6">
	<div class="flex -space-x-3">
		<?php foreach ( array_slice( $spotinder_people, 0, 4 ) as $spotinder_p ) : ?>
			<span class="grid size-11 place-items-center rounded-full text-sm font-semibold text-white ring-4 ring-surface" style="background: <?php echo esc_attr( spotinder_gradient( $spotinder_p['name'] ) ); ?>"><?php echo esc_html( spotinder_initials( $spotinder_p['name'] ) ); ?></span>
		<?php endforeach; ?>
	</div>
	<p class="eyebrow"><?php echo esc_html( spotinder_field( 'story_city', $spotinder_story->ID ) ); ?></p>
	<h3 class="text-xl font-semibold leading-snug text-white">
		<a href="<?php echo esc_url( get_permalink( $spotinder_story ) ); ?>" class="after:absolute after:inset-0"><?php echo esc_html( get_the_title( $spotinder_story ) ); ?></a>
	</h3>
	<p class="text-sm leading-relaxed text-white/60"><?php echo esc_html( get_the_excerpt( $spotinder_story ) ); ?></p>
	<?php $spotinder_track = spotinder_field( 'story_soundtrack_label', $spotinder_story->ID ); ?>
	<?php if ( $spotinder_track ) : ?>
		<p class="mt-auto flex items-center gap-2 text-xs text-white/50"><?php echo spotinder_icon( 'music', 'size-4 text-accent' ); // phpcs:ignore ?> <?php echo esc_html( $spotinder_track ); ?></p>
	<?php endif; ?>
</article>
