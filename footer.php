<?php
/**
 * Rodapé.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

$spotinder_social = (array) spotinder_opt( 'footer_social' );
$spotinder_slogo  = spotinder_opt( 'spotify_logo' );
?>
</main>

<footer class="mt-24 border-t border-white/5 bg-surface/40">
	<div class="container-site grid gap-10 py-14 md:grid-cols-[1.4fr_1fr_1fr]">
		<div class="max-w-sm space-y-4">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="flex items-center gap-2 text-lg font-semibold text-white">
				<?php echo spotinder_logo_mark( 'size-9' ); // phpcs:ignore ?>
				<?php echo esc_html( spotinder_brand() ); ?>
			</a>
			<p class="text-sm leading-relaxed text-white/60"><?php echo esc_html( spotinder_opt( 'footer_text' ) ); ?></p>
			<?php if ( $spotinder_social ) : ?>
				<ul class="flex flex-wrap gap-2">
					<?php foreach ( $spotinder_social as $spotinder_item ) : ?>
						<li><a class="chip" href="<?php echo esc_url( $spotinder_item['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $spotinder_item['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<nav aria-label="<?php esc_attr_e( 'Rodapé', 'spotinder' ); ?>" class="footer-menu">
			<p class="eyebrow mb-3"><?php echo esc_html( spotinder_brand() ); ?></p>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => has_nav_menu( 'footer' ) ? 'footer' : 'primary',
					'container'      => false,
					'menu_class'     => 'space-y-2 text-sm',
					'fallback_cb'    => 'spotinder_fallback_menu',
					'depth'          => 1,
				)
			);
			?>
		</nav>

		<nav aria-label="<?php esc_attr_e( 'Legal', 'spotinder' ); ?>" class="footer-menu">
			<p class="eyebrow mb-3"><?php esc_html_e( 'Transparência', 'spotinder' ); ?></p>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'legal',
					'container'      => false,
					'menu_class'     => 'space-y-2 text-sm',
					'fallback_cb'    => false,
					'depth'          => 1,
				)
			);
			?>
		</nav>
	</div>

	<div class="border-t border-white/5">
		<div class="container-site flex flex-col gap-3 py-6 text-xs text-white/40 md:flex-row md:items-center md:justify-between">
			<p class="flex items-center gap-2">
				<?php if ( $spotinder_slogo ) : ?>
					<img src="<?php echo esc_url( $spotinder_slogo ); ?>" alt="Spotify" class="h-5 w-auto">
				<?php endif; ?>
				<span><?php echo esc_html( spotinder_opt( 'footer_attribution' ) ); ?></span>
			</p>
			<p><?php echo esc_html( spotinder_opt( 'footer_disclaimer' ) ); ?></p>
		</div>
		<p class="container-site pb-6 text-xs text-white/30">© <?php echo esc_html( wp_date( 'Y' ) . ' ' . spotinder_brand() ); ?></p>
	</div>
</footer>

<?php if ( spotinder_is_member() ) : ?>
	<?php get_template_part( 'template-parts/app-tabbar' ); ?>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
