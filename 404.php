<?php
/**
 * 404.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="container-site grid min-h-[60vh] place-items-center py-20 text-center">
	<div class="space-y-6">
		<span class="vinyl vinyl-lg mx-auto block" aria-hidden="true"></span>
		<h1 class="text-4xl font-semibold text-white"><?php esc_html_e( 'Essa faixa pulou.', 'spotinder' ); ?></h1>
		<p class="text-white/60"><?php esc_html_e( 'A página que você procura não existe ou mudou de lugar.', 'spotinder' ); ?></p>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn-primary"><?php esc_html_e( 'Voltar ao início', 'spotinder' ); ?></a>
	</div>
</section>
<?php
get_footer();
