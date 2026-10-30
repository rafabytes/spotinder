<?php
/**
 * Agenda de eventos.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

get_header();

$spotinder_user        = spotinder_is_member() ? get_current_user_id() : 0;
$spotinder_recommended = array();
$spotinder_rest        = array();

while ( have_posts() ) {
	the_post();
	$spotinder_because = $spotinder_user ? spotinder_event_matches_user( get_the_ID(), $spotinder_user ) : array();
	$spotinder_item    = array(
		'event'   => get_post(),
		'because' => $spotinder_because,
	);
	if ( $spotinder_because ) {
		$spotinder_recommended[] = $spotinder_item;
	} else {
		$spotinder_rest[] = $spotinder_item;
	}
}
?>
<section class="container-site py-14">
	<header class="mb-12 max-w-2xl space-y-4">
		<p class="eyebrow"><?php echo esc_html( spotinder_opt( 'events_eyebrow' ) ); ?></p>
		<h1 class="text-4xl font-semibold tracking-tight text-white md:text-5xl"><?php echo esc_html( spotinder_opt( 'events_title' ) ); ?></h1>
		<p class="text-white/60"><?php echo esc_html( spotinder_opt( 'events_text' ) ); ?></p>
	</header>

	<?php
	$spotinder_terms = get_terms(
		array(
			'taxonomy'   => 'spot_event_type',
			'hide_empty' => true,
		)
	);
	if ( $spotinder_terms && ! is_wp_error( $spotinder_terms ) ) :
		?>
		<nav class="mb-10 flex flex-wrap gap-2" aria-label="<?php esc_attr_e( 'Tipos de evento', 'spotinder' ); ?>">
			<a class="chip <?php echo is_post_type_archive() ? 'chip-active' : ''; ?>" href="<?php echo esc_url( get_post_type_archive_link( 'spot_event' ) ); ?>"><?php esc_html_e( 'Todos', 'spotinder' ); ?></a>
			<?php foreach ( $spotinder_terms as $spotinder_term ) : ?>
				<a class="chip <?php echo is_tax( 'spot_event_type', $spotinder_term->term_id ) ? 'chip-active' : ''; ?>" href="<?php echo esc_url( get_term_link( $spotinder_term ) ); ?>"><?php echo esc_html( $spotinder_term->name ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php if ( $spotinder_recommended ) : ?>
		<h2 class="mb-5 text-xl font-semibold text-white">🎧 <?php esc_html_e( 'Recomendados pelo seu gosto', 'spotinder' ); ?></h2>
		<div class="mb-14 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
			<?php foreach ( $spotinder_recommended as $spotinder_item ) : ?>
				<?php get_template_part( 'template-parts/event-card', null, $spotinder_item ); ?>
			<?php endforeach; ?>
		</div>
		<?php if ( $spotinder_rest ) : ?>
			<h2 class="mb-5 text-xl font-semibold text-white"><?php esc_html_e( 'Toda a agenda', 'spotinder' ); ?></h2>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $spotinder_rest ) : ?>
		<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
			<?php foreach ( $spotinder_rest as $spotinder_item ) : ?>
				<?php get_template_part( 'template-parts/event-card', null, $spotinder_item ); ?>
			<?php endforeach; ?>
		</div>
	<?php elseif ( ! $spotinder_recommended ) : ?>
		<div class="card p-10 text-center text-white/60"><?php esc_html_e( 'Nenhum evento agendado por enquanto. Volte em breve!', 'spotinder' ); ?></div>
	<?php endif; ?>

	<div class="mt-10"><?php the_posts_pagination(); ?></div>
</section>
<?php
get_footer();
