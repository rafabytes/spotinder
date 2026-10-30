<?php
/**
 * Aviso exibido após redirecionamentos (?sp_notice=).
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

$spotinder_key     = isset( $_GET['sp_notice'] ) ? sanitize_key( $_GET['sp_notice'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$spotinder_notices = spotinder_notices();

if ( ! $spotinder_key || ! isset( $spotinder_notices[ $spotinder_key ] ) ) {
	return;
}

list( $spotinder_type, $spotinder_text ) = $spotinder_notices[ $spotinder_key ];

$spotinder_colors = array(
	'success' => 'border-emerald-400/30 bg-emerald-400/10 text-emerald-100',
	'error'   => 'border-rose-400/30 bg-rose-400/10 text-rose-100',
	'info'    => 'border-white/15 bg-white/5 text-white/90',
);
?>
<div class="container-site pt-4" data-notice>
	<div role="status" class="flex items-start justify-between gap-4 rounded-2xl border px-4 py-3 text-sm <?php echo esc_attr( $spotinder_colors[ $spotinder_type ] ); ?>">
		<p><?php echo esc_html( $spotinder_text ); ?></p>
		<button type="button" class="opacity-70 hover:opacity-100" data-notice-close aria-label="<?php esc_attr_e( 'Fechar aviso', 'spotinder' ); ?>"><?php echo spotinder_icon( 'x', 'size-4' ); // phpcs:ignore ?></button>
	</div>
</div>
