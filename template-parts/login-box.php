<?php
/**
 * Caixa "Entre para continuar" com o botão do Google (página de login e páginas do app).
 *
 * @package Spotinder
 *
 * @var array $args { redirect?: string }
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="container-site grid min-h-[70vh] place-items-center py-16">
	<div class="card max-w-md space-y-6 p-10 text-center">
		<span class="mx-auto grid size-16 place-items-center rounded-2xl bg-brand/20 text-brand"><?php echo spotinder_icon( 'lock', 'size-7' ); // phpcs:ignore ?></span>
		<h1 class="text-2xl font-semibold text-white"><?php esc_html_e( 'Entre para continuar', 'spotinder' ); ?></h1>
		<p class="text-white/60"><?php esc_html_e( 'A única forma de entrar é com sua conta Google. Leva menos de um minuto, e os artistas e as músicas do seu perfil é você quem escolhe.', 'spotinder' ); ?></p>
		<?php
		echo spotinder_google_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			array(
				'redirect' => $args['redirect'] ?? '',
				'size'     => 'lg',
				'class'    => 'w-full',
			)
		);
		?>
		<p class="text-xs text-white/40">
			<a class="underline underline-offset-4" href="<?php echo esc_url( spotinder_page_url( 'privacy' ) ); ?>"><?php esc_html_e( 'Como usamos seus dados', 'spotinder' ); ?></a>
		</p>
	</div>
</section>
