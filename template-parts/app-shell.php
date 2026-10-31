<?php
/**
 * Moldura das páginas do app: exige login e monta o componente React.
 *
 * @package Spotinder
 *
 * @var array $args { island: string, props?: array }
 */

defined( 'ABSPATH' ) || exit;

if ( ! spotinder_is_member() ) : ?>
	<section class="container-site grid min-h-[70vh] place-items-center py-16">
		<div class="card max-w-md space-y-6 p-10 text-center">
			<span class="mx-auto grid size-16 place-items-center rounded-2xl bg-brand/20 text-brand"><?php echo spotinder_icon( 'lock', 'size-7' ); // phpcs:ignore ?></span>
			<h1 class="text-2xl font-semibold text-white"><?php esc_html_e( 'Entre para continuar', 'spotinder' ); ?></h1>
			<p class="text-white/60"><?php esc_html_e( 'A única forma de entrar é com sua conta Google. Leva menos de um minuto, e os artistas e as músicas do seu perfil é você quem escolhe.', 'spotinder' ); ?></p>
			<a href="<?php echo esc_url( spotinder_login_url( get_permalink() ) ); ?>" class="btn-primary btn-lg w-full justify-center" data-no-swup><?php echo spotinder_icon( 'google', 'size-5' ); // phpcs:ignore ?> <?php echo esc_html( spotinder_opt( 'login_label' ) ); ?></a>
			<p class="text-xs text-white/40">
				<a class="underline underline-offset-4" href="<?php echo esc_url( spotinder_page_url( 'privacy' ) ); ?>"><?php esc_html_e( 'Como usamos seus dados', 'spotinder' ); ?></a>
			</p>
		</div>
	</section>
	<?php
	return;
endif;
?>
<section class="container-site pb-28 pt-6 sm:pb-12">
	<div data-island="<?php echo esc_attr( $args['island'] ); ?>" data-props="<?php echo spotinder_json_attr( $args['props'] ?? new stdClass() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
		<div class="grid min-h-[60vh] place-items-center">
			<span class="loader" aria-label="<?php esc_attr_e( 'Carregando', 'spotinder' ); ?>"></span>
		</div>
	</div>
</section>
