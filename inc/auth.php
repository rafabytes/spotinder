<?php
/**
 * Login exclusivo via Google (popup do Google Identity Services).
 *
 * Rotas (query var spotinder_auth):
 *  - login   → página com o botão do Google (destino dos links quando o JS ainda não carregou)
 *  - logout
 *
 * O código do popup chega por POST em /wp-json/spotinder/v1/auth/google.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = 'spotinder_auth';
		return $vars;
	}
);

add_action( 'template_redirect', 'spotinder_handle_auth', 1 );

/**
 * Roteador das ações de autenticação.
 */
function spotinder_handle_auth() {
	$action = get_query_var( 'spotinder_auth' );
	if ( ! $action ) {
		return;
	}

	nocache_headers();

	switch ( $action ) {
		case 'login':
			$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( is_user_logged_in() && spotinder_is_member() ) {
				spotinder_redirect( $redirect ? wp_validate_redirect( $redirect, spotinder_page_url( 'discover' ) ) : spotinder_page_url( 'discover' ) );
			}
			if ( ! spotinder_google_configured() ) {
				spotinder_redirect( home_url( '/' ), 'not_configured' );
			}
			add_filter( 'wp_robots', 'wp_robots_no_robots' );
			add_filter( 'pre_get_document_title', fn() => sprintf( '%s · %s', __( 'Entrar', 'spotinder' ), spotinder_brand() ) );
			get_header();
			get_template_part( 'template-parts/login-box', null, array( 'redirect' => $redirect ) );
			get_footer();
			exit;

		case 'logout':
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'spotinder_logout' ) ) {
				wp_logout();
			}
			spotinder_redirect( home_url( '/' ) );
			break;

		default:
			// Ex.: retorno antigo do login por redirecionamento (?spotinder_auth=callback).
			spotinder_redirect( home_url( '/' ) );
	}
}

/**
 * Redireciona e encerra.
 *
 * @param string $url    Destino.
 * @param array  $notice Aviso opcional (?sp_notice=).
 */
function spotinder_redirect( $url, $notice = '' ) {
	if ( $notice ) {
		$url = add_query_arg( 'sp_notice', $notice, $url );
	}
	wp_safe_redirect( $url );
	exit;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'spotinder/v1',
			'/auth/google',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'args'                => array(
					'code'     => array(
						'type'     => 'string',
						'required' => true,
					),
					'redirect' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'callback'            => 'spotinder_rest_google_login',
			)
		);
	}
);

/**
 * Recebe o código do popup, cria/atualiza o membro e autentica.
 *
 * Proteção contra CSRF de login, como recomenda o Google para o modo popup: o
 * cabeçalho X-Requested-With só pode ser enviado por JavaScript da própria origem
 * (outra origem dispararia um preflight de CORS, que o WordPress não libera).
 *
 * @param WP_REST_Request $req Requisição.
 * @return array|WP_Error {redirect}
 */
function spotinder_rest_google_login( WP_REST_Request $req ) {
	nocache_headers();

	if ( 'XmlHttpRequest' !== $req->get_header( 'X-Requested-With' ) ) {
		return new WP_Error( 'spotinder_auth', 'Requisição inválida.', array( 'status' => 400 ) );
	}
	if ( ! spotinder_google_configured() ) {
		return new WP_Error( 'spotinder_auth', spotinder_notices()['not_configured'][1], array( 'status' => 503 ) );
	}

	$account = spotinder_google_exchange_code( (string) $req['code'] );
	if ( is_wp_error( $account ) ) {
		return new WP_Error( 'spotinder_auth', spotinder_notices()['login_error'][1], array( 'status' => 502 ) );
	}

	$user_id = spotinder_upsert_member( $account );
	if ( is_wp_error( $user_id ) ) {
		$key = 'spotinder_banned' === $user_id->get_error_code() ? 'banned' : 'login_error';
		return new WP_Error( 'spotinder_auth', spotinder_notices()[ $key ][1], array( 'status' => 403 ) );
	}

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true, is_ssl() );

	if ( ! get_user_meta( $user_id, 'spotinder_onboarded', true ) || ! spotinder_has_consents( $user_id ) ) {
		return array( 'redirect' => spotinder_page_url( 'profile' ) );
	}

	$redirect = (string) $req['redirect'];
	return array( 'redirect' => $redirect ? wp_validate_redirect( $redirect, spotinder_page_url( 'discover' ) ) : spotinder_page_url( 'discover' ) );
}

/**
 * Mensagens exibidas após redirecionamentos.
 */
function spotinder_notices() {
	return array(
		'not_configured' => array( 'error', 'O login com Google ainda não foi configurado pela equipe.' ),
		'login_error'    => array( 'error', 'O Google não respondeu como esperado. Tente novamente em instantes.' ),
		'banned'         => array( 'error', 'Esta conta foi suspensa por violar os Termos de Uso.' ),
		'deleted'        => array( 'success', 'Sua conta e todos os seus dados foram apagados.' ),
	);
}
