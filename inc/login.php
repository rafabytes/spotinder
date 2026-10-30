<?php
/**
 * Tela de login do WordPress (wp-login.php) com a identidade do site.
 * Membros entram só pelo Spotify; o acesso com senha é da equipe.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'login_enqueue_scripts',
	function () {
		$css = SPOTINDER_DIR . '/dist/css/login.css';
		if ( ! file_exists( $css ) ) {
			return;
		}

		wp_enqueue_style( 'spotinder-login', SPOTINDER_URI . '/dist/css/login.css', array( 'login' ), (string) filemtime( $css ) );

		// Logo: ícone do tema (ou o Ícone do site escolhido no painel) + cores da marca.
		$logo = spotinder_uses_default_icon() ? spotinder_asset_url( 'icons/icon.svg' ) : get_site_icon_url( 192 );
		wp_add_inline_style(
			'spotinder-login',
			sprintf(
				':root{--sp-primary:%s;--sp-accent:%s;--sp-highlight:%s;--sp-login-logo:url("%s");}',
				esc_html( spotinder_opt( 'color_primary' ) ),
				esc_html( spotinder_opt( 'color_accent' ) ),
				esc_html( spotinder_opt( 'color_highlight' ) ),
				esc_url( $logo )
			)
		);
	}
);

add_filter( 'login_headerurl', fn() => home_url( '/' ) );
add_filter( 'login_headertext', fn() => spotinder_brand() );

/**
 * Atalho para membros acima do formulário (só na tela de login, não na de recuperar senha).
 */
add_filter(
	'login_message',
	function ( $message ) {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'login' !== $action || isset( $_REQUEST['interim-login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $message;
		}

		$box = sprintf(
			'<div class="spotinder-member-login"><p>%s</p><a class="spotinder-spotify-btn" href="%s">%s<span>%s</span></a></div><p class="spotinder-staff-note">%s</p>',
			esc_html__( 'Faz parte da comunidade? Sua conta é o seu Spotify.', 'spotinder' ),
			esc_url( spotinder_login_url() ),
			spotinder_icon( 'spotify' ),
			esc_html( spotinder_opt( 'login_label' ) ),
			esc_html__( 'Acesso da equipe', 'spotinder' )
		);

		return $box . $message;
	}
);

/**
 * Título da aba.
 */
add_filter(
	'login_title',
	function ( $title ) {
		return sprintf( '%s · %s', __( 'Entrar', 'spotinder' ), spotinder_brand() );
	}
);
