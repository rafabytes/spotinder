<?php
/**
 * Login exclusivo via Google (OpenID Connect).
 *
 * Rotas (query var spotinder_auth):
 *  - login     → pede apenas "openid profile"
 *  - callback  → Redirect URI cadastrada no Google Cloud
 *  - logout
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
			if ( is_user_logged_in() && spotinder_is_member() && ! $redirect ) {
				spotinder_redirect( spotinder_page_url( 'discover' ) );
			}
			spotinder_start_oauth( array( 'redirect' => $redirect ) );
			break;

		case 'callback':
			spotinder_forward_foreign_callback();
			spotinder_oauth_callback();
			break;

		case 'logout':
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'spotinder_logout' ) ) {
				wp_logout();
			}
			spotinder_redirect( home_url( '/' ) );
			break;
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

/**
 * Inicia o OAuth com estado assinado e vinculado ao navegador.
 *
 * @param array $payload Dados a recuperar no callback.
 */
function spotinder_start_oauth( $payload ) {
	if ( ! spotinder_google_configured() ) {
		spotinder_redirect( home_url( '/' ), 'not_configured' );
	}

	$state   = wp_generate_password( 32, false );
	$browser = wp_generate_password( 32, false );

	$payload['browser'] = wp_hash( $browser );
	$payload['nonce']   = wp_generate_password( 32, false );
	set_transient( 'spotinder_state_' . $state, $payload, 10 * MINUTE_IN_SECONDS );

	setcookie(
		'spotinder_oauth',
		$browser,
		array(
			'expires'  => time() + 10 * MINUTE_IN_SECONDS,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);

	wp_redirect( spotinder_google_authorize_url( $state, $payload['nonce'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
	exit;
}

/**
 * Lê um parâmetro da query string original. Necessário porque o WordPress remove
 * $_GET['error'] em WP::parse_request() — e é por "error" que o Google avisa cancelamentos.
 *
 * @param string $key Parâmetro.
 */
function spotinder_raw_query_arg( $key ) {
	parse_str( isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : '', $query ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	return isset( $query[ $key ] ) && is_string( $query[ $key ] ) ? sanitize_text_field( $query[ $key ] ) : '';
}

/**
 * Callback que chegou por outro host (ex.: http://localhost/... em desenvolvimento):
 * os cookies de sessão e do OAuth pertencem ao domínio do site, então repassa
 * code/state/error para lá. O destino é sempre o próprio home_url().
 */
function spotinder_forward_foreign_callback() {
	$home_host    = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$request_host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( preg_replace( '/:\d+$/', '', sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) ) : '';

	if ( ! $request_host || ! $home_host || $request_host === $home_host ) {
		return;
	}

	$args = array( 'spotinder_auth' => 'callback' );
	foreach ( array( 'code', 'state', 'error' ) as $key ) {
		$value = spotinder_raw_query_arg( $key );
		if ( '' !== $value ) {
			$args[ $key ] = rawurlencode( $value );
		}
	}

	wp_safe_redirect( add_query_arg( $args, home_url( '/' ) ) );
	exit;
}

/**
 * Callback do Google.
 */
function spotinder_oauth_callback() {
	// Validado pelo state + cookie (não há nonce do WP: a requisição vem do Google).
	$state = spotinder_raw_query_arg( 'state' );
	$code  = spotinder_raw_query_arg( 'code' );
	$error = sanitize_key( spotinder_raw_query_arg( 'error' ) );

	$payload = $state ? get_transient( 'spotinder_state_' . $state ) : false;
	delete_transient( 'spotinder_state_' . $state );

	$browser = isset( $_COOKIE['spotinder_oauth'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['spotinder_oauth'] ) ) : '';
	setcookie( 'spotinder_oauth', '', time() - 3600, COOKIEPATH ? COOKIEPATH : '/' );

	if ( ! $payload || ! $browser || ! hash_equals( $payload['browser'], wp_hash( $browser ) ) ) {
		spotinder_redirect( home_url( '/' ), 'invalid_state' );
	}

	if ( $error || ! $code ) {
		spotinder_redirect( home_url( '/' ), 'access_denied' === $error ? 'cancelled' : 'login_error' );
	}

	$account = spotinder_google_exchange_code( $code, (string) ( $payload['nonce'] ?? '' ) );
	if ( is_wp_error( $account ) ) {
		spotinder_redirect( home_url( '/' ), 'login_error' );
	}

	spotinder_finish_login( $account, $payload['redirect'] ?? '' );
}

/**
 * Conclui o login: cria/atualiza o membro e autentica.
 *
 * @param array  $account  Conta Google (spotinder_google_exchange_code()).
 * @param string $redirect Destino solicitado.
 */
function spotinder_finish_login( $account, $redirect ) {
	$user_id = spotinder_upsert_member( $account );
	if ( is_wp_error( $user_id ) ) {
		spotinder_redirect( home_url( '/' ), 'spotinder_banned' === $user_id->get_error_code() ? 'banned' : 'login_error' );
	}

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true, is_ssl() );

	if ( ! get_user_meta( $user_id, 'spotinder_onboarded', true ) || ! spotinder_has_consents( $user_id ) ) {
		spotinder_redirect( spotinder_page_url( 'profile' ) );
	}

	$target = $redirect ? wp_validate_redirect( $redirect, spotinder_page_url( 'discover' ) ) : spotinder_page_url( 'discover' );
	spotinder_redirect( $target );
}

/**
 * Mensagens exibidas após redirecionamentos.
 */
function spotinder_notices() {
	return array(
		'not_configured' => array( 'error', 'O login com Google ainda não foi configurado pela equipe.' ),
		'invalid_state'  => array( 'error', 'A sessão de login expirou. Tente de novo.' ),
		'cancelled'      => array( 'info', 'Tudo bem! Você cancelou a entrada com o Google.' ),
		'login_error'    => array( 'error', 'O Google não respondeu como esperado. Tente novamente em instantes.' ),
		'banned'         => array( 'error', 'Esta conta foi suspensa por violar os Termos de Uso.' ),
		'deleted'        => array( 'success', 'Sua conta e todos os seus dados foram apagados.' ),
	);
}
