<?php
/**
 * Botão principal: entra com o Google (visitante) ou leva ao app (membro).
 *
 * @package Spotinder
 *
 * @var array $args { label: string, class?: string }
 */

defined( 'ABSPATH' ) || exit;

$spotinder_member = spotinder_is_member();
$spotinder_class  = $args['class'] ?? 'btn-primary btn-lg';
?>
<?php if ( $spotinder_member ) : ?>
	<a href="<?php echo esc_url( spotinder_page_url( 'discover' ) ); ?>" class="<?php echo esc_attr( $spotinder_class ); ?>">
		<?php echo spotinder_icon( 'sparkle', 'size-5' ); // phpcs:ignore ?>
		<span><?php esc_html_e( 'Continuar descobrindo', 'spotinder' ); ?></span>
	</a>
<?php else : ?>
	<?php
	echo spotinder_google_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		array(
			'label' => $args['label'] ?? spotinder_opt( 'login_label' ),
			'size'  => 'lg',
		)
	);
	?>
<?php endif; ?>
