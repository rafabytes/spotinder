<?php
/**
 * Barra de abas inferior (mobile) para membros — estilo app.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

$spotinder_tabs = array(
	array( spotinder_page_url( 'discover' ), 'sparkle', __( 'Descobrir', 'spotinder' ) ),
	array( spotinder_page_url( 'matches' ), 'heart', __( 'Matches', 'spotinder' ) ),
	array( get_post_type_archive_link( 'spot_event' ), 'calendar', __( 'Eventos', 'spotinder' ) ),
	array( spotinder_page_url( 'profile' ), 'users', __( 'Perfil', 'spotinder' ) ),
);
?>
<nav class="app-tabbar fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-ink/90 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl sm:hidden" aria-label="<?php esc_attr_e( 'App', 'spotinder' ); ?>" data-tabbar>
	<ul class="grid grid-cols-4">
		<?php foreach ( $spotinder_tabs as $spotinder_tab ) : ?>
			<li>
				<a href="<?php echo esc_url( $spotinder_tab[0] ); ?>" class="flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium text-white/50 aria-[current=page]:text-white">
					<?php echo spotinder_icon( $spotinder_tab[1], 'size-5' ); // phpcs:ignore ?>
					<?php echo esc_html( $spotinder_tab[2] ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
