<?php
/**
 * Moldura das páginas do app: exige login e monta o componente React.
 *
 * @package Spotinder
 *
 * @var array $args { island: string, props?: array }
 */

defined( 'ABSPATH' ) || exit;

if ( ! spotinder_is_member() ) :
	get_template_part( 'template-parts/login-box', null, array( 'redirect' => get_permalink() ) );
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
