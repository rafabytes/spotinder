<?php
/**
 * Login exclusivo via Spotify (Authorization Code Flow) e ações OAuth pontuais.
 *
 * Rotas (query var spotinder_auth):
 *  - login     → pede apenas user-top-read
 *  - playlist  → pede playlist-modify-private só quando a pessoa clica em "salvar playlist"
 *  - callback  → Redirect URI cadastrada no Spotify
 *  - logout
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

const SPOTINDER_SCOPE_LOGIN    = 'user-top-read';
const SPOTINDER_SCOPE_PLAYLIST = 'playlist-modify-private';

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
			spotinder_start_oauth( SPOTINDER_SCOPE_LOGIN, array( 'intent' => 'login', 'redirect' => $redirect ) );
			break;

		case 'playlist':
			$match_id = isset( $_GET['match'] ) ? absint( $_GET['match'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! is_user_logged_in() || ! $match_id || ! spotinder_get_match_row( $match_id, get_current_user_id() ) ) {
				spotinder_redirect( spotinder_page_url( 'matches' ) );
			}
			spotinder_start_oauth( SPOTINDER_SCOPE_PLAYLIST, array( 'intent' => 'playlist', 'match' => $match_id ) );
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
 * @param string $scope   Escopos.
 * @param array  $payload Dados a recuperar no callback.
 */
function spotinder_start_oauth( $scope, $payload ) {
	if ( ! spotinder_spotify_configured() ) {
		spotinder_redirect( home_url( '/' ), 'not_configured' );
	}

	$state   = wp_generate_password( 32, false );
	$browser = wp_generate_password( 32, false );

	$payload['browser'] = wp_hash( $browser );
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

	wp_redirect( spotinder_spotify_authorize_url( $scope, $state ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
	exit;
}

/**
 * Lê um parâmetro da query string original. Necessário porque o WordPress remove
 * $_GET['error'] em WP::parse_request() — e é por "error" que o Spotify avisa cancelamentos.
 *
 * @param string $key Parâmetro.
 */
function spotinder_raw_query_arg( $key ) {
	parse_str( isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : '', $query ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	return isset( $query[ $key ] ) && is_string( $query[ $key ] ) ? sanitize_text_field( $query[ $key ] ) : '';
}

/**
 * Callback que chegou por outro host (ex.: http://127.0.0.1/... em desenvolvimento):
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
 * Callback do Spotify.
 */
function spotinder_oauth_callback() {
	// Validado pelo state + cookie (não há nonce: a requisição vem do Spotify).
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
		$back = 'playlist' === $payload['intent'] ? spotinder_page_url( 'matches' ) : home_url( '/' );
		spotinder_redirect( $back, 'access_denied' === $error ? 'cancelled' : 'spotify_error' );
	}

	$token = spotinder_spotify_exchange_code( $code );
	if ( is_wp_error( $token ) ) {
		spotinder_redirect( home_url( '/' ), 'spotify_error' );
	}

	if ( 'playlist' === $payload['intent'] ) {
		spotinder_finish_playlist( $token, (int) $payload['match'] );
	}

	spotinder_finish_login( $token, $payload['redirect'] ?? '' );
}

/**
 * Conclui o login: lê /me + tops, cria/atualiza o membro e autentica.
 *
 * @param string $token    Access token (descartado ao fim da requisição).
 * @param string $redirect Destino solicitado.
 */
function spotinder_finish_login( $token, $redirect ) {
	$me = spotinder_spotify_request( $token, 'GET', '/me' );
	if ( is_wp_error( $me ) ) {
		$status = $me->get_error_data()['status'] ?? 0;
		// 403 em modo de desenvolvimento = conta não está na lista de usuários do app.
		spotinder_redirect( home_url( '/' ), 403 === $status ? 'not_allowlisted' : 'spotify_error' );
	}

	$tops = spotinder_spotify_fetch_tops( $token );
	if ( is_wp_error( $tops ) ) {
		spotinder_redirect( home_url( '/' ), 'spotify_error' );
	}

	$user_id = spotinder_upsert_member( $me, $tops );
	if ( is_wp_error( $user_id ) ) {
		spotinder_redirect( home_url( '/' ), 'spotinder_banned' === $user_id->get_error_code() ? 'banned' : 'spotify_error' );
	}

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true, is_ssl() );

	if ( ! get_user_meta( $user_id, 'spotinder_onboarded', true ) || ! spotinder_has_consents( $user_id ) ) {
		spotinder_redirect( spotinder_page_url( 'profile' ) );
	}

	$target = $redirect ? wp_validate_redirect( $redirect, spotinder_page_url( 'discover' ) ) : spotinder_page_url( 'discover' );
	spotinder_redirect( $target, empty( $tops['artists'] ) ? 'no_artists' : 'synced' );
}

/**
 * Salva a playlist em comum na conta do usuário.
 *
 * @param string $token    Access token com playlist-modify-private.
 * @param int    $match_id Match.
 */
function spotinder_finish_playlist( $token, $match_id ) {
	$user_id = get_current_user_id();
	$back    = spotinder_page_url( 'matches' ) . '#match-' . $match_id;
	$match   = $user_id ? spotinder_get_match_row( $match_id, $user_id ) : null;

	if ( ! $match ) {
		spotinder_redirect( spotinder_page_url( 'matches' ) );
	}

	$other    = (int) ( (int) $match->user_a === $user_id ? $match->user_b : $match->user_a );
	$playlist = spotinder_common_playlist( $user_id, $other );
	$name     = sprintf( '%s × %s · %s', get_userdata( $user_id )->display_name, get_userdata( $other )->display_name, spotinder_brand() );

	$url = spotinder_spotify_create_playlist(
		$token,
		$name,
		sprintf( 'Playlist em comum criada pelo %s.', spotinder_brand() ),
		wp_list_pluck( $playlist, 'id' )
	);

	if ( is_wp_error( $url ) ) {
		wp_safe_redirect( add_query_arg( 'sp_notice', 'playlist_error', spotinder_page_url( 'matches' ) ) . '#match-' . $match_id );
		exit;
	}

	wp_safe_redirect( add_query_arg( 'sp_notice', 'playlist_saved', spotinder_page_url( 'matches' ) ) . '#match-' . $match_id );
	exit;
}

/**
 * Mensagens exibidas após redirecionamentos.
 */
function spotinder_notices() {
	return array(
		'not_configured'  => array( 'error', 'O login com Spotify ainda não foi configurado pela equipe.' ),
		'invalid_state'   => array( 'error', 'A sessão de login expirou. Tente de novo.' ),
		'cancelled'       => array( 'info', 'Tudo bem! Você cancelou a conexão com o Spotify.' ),
		'spotify_error'   => array( 'error', 'O Spotify não respondeu como esperado. Tente novamente em instantes.' ),
		'not_allowlisted' => array( 'error', 'Estamos em fase de testes fechados e sua conta Spotify ainda não está liberada.' ),
		'banned'          => array( 'error', 'Esta conta foi suspensa por violar os Termos de Uso.' ),
		'no_artists'      => array( 'info', 'Seu Spotify ainda não tem artistas mais ouvidos suficientes. Ouça um pouco mais e atualize seu perfil depois.' ),
		'synced'          => array( 'success', 'Seus artistas foram atualizados.' ),
		'playlist_saved'  => array( 'success', 'Playlist salva na sua conta do Spotify!' ),
		'playlist_error'  => array( 'error', 'Não foi possível salvar a playlist no Spotify.' ),
		'deleted'         => array( 'success', 'Sua conta e todos os seus dados foram apagados.' ),
	);
}
