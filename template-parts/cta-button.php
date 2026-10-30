<?php
/**
 * Botão principal: conecta o Spotify (visitante) ou leva ao app (membro).
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
	<a href="<?php echo esc_url( spotinder_login_url() ); ?>" class="<?php echo esc_attr( $spotinder_class ); ?>" data-no-swup>
		<?php echo spotinder_icon( 'spotify', 'size-5' ); // phpcs:ignore ?>
		<span><?php echo esc_html( $args['label'] ?? spotinder_opt( 'login_label' ) ); ?></span>
	</a>
<?php endif; ?>
