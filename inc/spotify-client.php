<?php
/**
 * Cliente mínimo da Web API do Spotify.
 *
 * Princípio de privacidade: tokens de usuário só vivem durante a requisição
 * do callback OAuth — nunca são gravados no banco.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

const SPOTINDER_SPOTIFY_ACCOUNTS = 'https://accounts.spotify.com';
const SPOTINDER_SPOTIFY_API      = 'https://api.spotify.com/v1';

/**
 * Credenciais configuradas?
 */
function spotinder_spotify_configured() {
	return spotinder_opt( 'spotify_client_id' ) && spotinder_opt( 'spotify_client_secret' );
}

/**
 * Cabeçalho Basic com client_id:client_secret.
 */
function spotinder_spotify_basic_auth() {
	return 'Basic ' . base64_encode( spotinder_opt( 'spotify_client_id' ) . ':' . spotinder_opt( 'spotify_client_secret' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/**
 * URL de autorização.
 *
 * @param string $scope Escopos separados por espaço.
 * @param string $state Estado anti-CSRF.
 */
function spotinder_spotify_authorize_url( $scope, $state ) {
	return SPOTINDER_SPOTIFY_ACCOUNTS . '/authorize?' . http_build_query(
		array(
			'response_type' => 'code',
			'client_id'     => spotinder_opt( 'spotify_client_id' ),
			'scope'         => $scope,
			'redirect_uri'  => spotinder_redirect_uri(),
			'state'         => $state,
		),
		'',
		'&',
		PHP_QUERY_RFC3986
	);
}

/**
 * Troca o code por um access token.
 *
 * @param string $code Código recebido no callback.
 * @return string|WP_Error Access token.
 */
function spotinder_spotify_exchange_code( $code ) {
	$response = wp_remote_post(
		SPOTINDER_SPOTIFY_ACCOUNTS . '/api/token',
		array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => spotinder_spotify_basic_auth(),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'    => array(
				'grant_type'   => 'authorization_code',
				'code'         => $code,
				'redirect_uri' => spotinder_redirect_uri(),
			),
		)
	);

	$data = spotinder_spotify_decode( $response );
	if ( is_wp_error( $data ) ) {
		return $data;
	}

	return empty( $data['access_token'] ) ? new WP_Error( 'spotify_token', 'Spotify não retornou token.' ) : $data['access_token'];
}

/**
 * Token do próprio app (client credentials) — usado só para buscar metadados
 * públicos de artistas/músicas dos usuários de teste.
 *
 * @return string|WP_Error
 */
function spotinder_spotify_app_token() {
	$cached = get_transient( 'spotinder_app_token' );
	if ( $cached ) {
		return $cached;
	}

	$response = wp_remote_post(
		SPOTINDER_SPOTIFY_ACCOUNTS . '/api/token',
		array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => spotinder_spotify_basic_auth(),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'    => array( 'grant_type' => 'client_credentials' ),
		)
	);

	$data = spotinder_spotify_decode( $response );
	if ( is_wp_error( $data ) ) {
		return $data;
	}

	set_transient( 'spotinder_app_token', $data['access_token'], max( 60, (int) $data['expires_in'] - 120 ) );
	return $data['access_token'];
}

/**
 * Requisição autenticada à Web API.
 *
 * @param string $token  Token.
 * @param string $method GET|POST.
 * @param string $path   Caminho (ex.: /me/top/artists).
 * @param array  $args   Query (GET) ou corpo JSON (POST).
 * @return array|WP_Error
 */
function spotinder_spotify_request( $token, $method, $path, $args = array() ) {
	$url     = SPOTINDER_SPOTIFY_API . $path;
	$request = array(
		'method'  => $method,
		'timeout' => 15,
		'headers' => array( 'Authorization' => 'Bearer ' . $token ),
	);

	if ( 'GET' === $method && $args ) {
		$url = add_query_arg( $args, $url );
	} elseif ( 'GET' !== $method ) {
		$request['headers']['Content-Type'] = 'application/json';
		$request['body']                    = wp_json_encode( $args );
	}

	return spotinder_spotify_decode( wp_remote_request( $url, $request ) );
}

/**
 * Decodifica resposta e normaliza erros.
 *
 * @param array|WP_Error $response Resposta HTTP.
 * @return array|WP_Error
 */
function spotinder_spotify_decode( $response ) {
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code >= 400 ) {
		$message = $body['error']['message'] ?? ( $body['error_description'] ?? ( is_string( $body['error'] ?? null ) ? $body['error'] : 'Erro na API do Spotify' ) );
		return new WP_Error( 'spotify_http_' . $code, $message, array( 'status' => $code ) );
	}

	return is_array( $body ) ? $body : array();
}

/**
 * Escolhe a imagem mais próxima de um tamanho alvo.
 *
 * @param array $images Lista de imagens do Spotify.
 * @param int   $target Largura desejada.
 */
function spotinder_pick_image( $images, $target = 320 ) {
	if ( empty( $images ) || ! is_array( $images ) ) {
		return '';
	}
	usort(
		$images,
		function ( $a, $b ) use ( $target ) {
			return abs( (int) ( $a['width'] ?? $target ) - $target ) <=> abs( (int) ( $b['width'] ?? $target ) - $target );
		}
	);
	return esc_url_raw( $images[0]['url'] ?? '' );
}

/**
 * Normaliza um artista para o formato salvo (somente o necessário).
 *
 * @param array $artist Objeto artista do Spotify.
 */
function spotinder_normalize_artist( $artist ) {
	return array(
		'id'     => sanitize_text_field( $artist['id'] ?? '' ),
		'name'   => sanitize_text_field( $artist['name'] ?? '' ),
		'image'  => spotinder_pick_image( $artist['images'] ?? array() ),
		'url'    => esc_url_raw( $artist['external_urls']['spotify'] ?? '' ),
		'genres' => array_values( array_map( 'sanitize_text_field', array_slice( (array) ( $artist['genres'] ?? array() ), 0, 5 ) ) ),
	);
}

/**
 * Normaliza uma faixa.
 *
 * @param array $track Objeto faixa do Spotify.
 */
function spotinder_normalize_track( $track ) {
	$artists = array();
	foreach ( (array) ( $track['artists'] ?? array() ) as $a ) {
		$artists[] = array(
			'id'   => sanitize_text_field( $a['id'] ?? '' ),
			'name' => sanitize_text_field( $a['name'] ?? '' ),
		);
	}

	return array(
		'id'      => sanitize_text_field( $track['id'] ?? '' ),
		'name'    => sanitize_text_field( $track['name'] ?? '' ),
		'artists' => $artists,
		'album'   => sanitize_text_field( $track['album']['name'] ?? '' ),
		'image'   => spotinder_pick_image( $track['album']['images'] ?? array(), 300 ),
		'url'     => esc_url_raw( $track['external_urls']['spotify'] ?? '' ),
	);
}

/**
 * Busca os tops do usuário logo após o OAuth.
 *
 * @param string $token Access token.
 * @return array{artists:array,tracks:array}|WP_Error
 */
function spotinder_spotify_fetch_tops( $token ) {
	$artists = spotinder_spotify_request(
		$token,
		'GET',
		'/me/top/artists',
		array(
			'limit'      => 30,
			'time_range' => 'medium_term',
		)
	);
	if ( is_wp_error( $artists ) ) {
		return $artists;
	}

	$tracks = spotinder_spotify_request(
		$token,
		'GET',
		'/me/top/tracks',
		array(
			'limit'      => 50,
			'time_range' => 'medium_term',
		)
	);
	if ( is_wp_error( $tracks ) ) {
		return $tracks;
	}

	// Conta nova ou pouco usada: tenta o período curto.
	if ( empty( $artists['items'] ) ) {
		$short = spotinder_spotify_request(
			$token,
			'GET',
			'/me/top/artists',
			array(
				'limit'      => 30,
				'time_range' => 'short_term',
			)
		);
		if ( ! is_wp_error( $short ) ) {
			$artists = $short;
		}
	}

	return array(
		'artists' => array_values( array_filter( array_map( 'spotinder_normalize_artist', (array) ( $artists['items'] ?? array() ) ), fn( $a ) => $a['id'] ) ),
		'tracks'  => array_values( array_filter( array_map( 'spotinder_normalize_track', (array) ( $tracks['items'] ?? array() ) ), fn( $t ) => $t['id'] ) ),
	);
}

/**
 * Cria uma playlist privada na conta do usuário e adiciona as faixas.
 * Tenta os endpoints atuais (fev/2026) e cai para os antigos se necessário.
 *
 * @param string   $token       Access token com playlist-modify-private.
 * @param string   $name        Nome.
 * @param string   $description Descrição.
 * @param string[] $track_ids   IDs das faixas.
 * @return string|WP_Error URL da playlist.
 */
function spotinder_spotify_create_playlist( $token, $name, $description, $track_ids ) {
	$payload = array(
		'name'        => $name,
		'description' => $description,
		'public'      => false,
	);

	$playlist = spotinder_spotify_request( $token, 'POST', '/me/playlists', $payload );

	if ( is_wp_error( $playlist ) ) {
		$me = spotinder_spotify_request( $token, 'GET', '/me' );
		if ( is_wp_error( $me ) ) {
			return $me;
		}
		$playlist = spotinder_spotify_request( $token, 'POST', '/users/' . rawurlencode( $me['id'] ) . '/playlists', $payload );
		if ( is_wp_error( $playlist ) ) {
			return $playlist;
		}
	}

	$uris = array_map( fn( $id ) => 'spotify:track:' . $id, array_slice( array_filter( $track_ids ), 0, 100 ) );

	if ( $uris ) {
		$added = spotinder_spotify_request( $token, 'POST', '/playlists/' . rawurlencode( $playlist['id'] ) . '/items', array( 'uris' => $uris ) );
		if ( is_wp_error( $added ) ) {
			$added = spotinder_spotify_request( $token, 'POST', '/playlists/' . rawurlencode( $playlist['id'] ) . '/tracks', array( 'uris' => $uris ) );
		}
		if ( is_wp_error( $added ) ) {
			return $added;
		}
	}

	return $playlist['external_urls']['spotify'] ?? 'https://open.spotify.com/playlist/' . $playlist['id'];
}

/**
 * Busca no catálogo (token do app). Limite de 10 itens desde fev/2026.
 *
 * @param string $query Termo.
 * @param string $type  artist|track.
 * @return array|WP_Error Primeiro resultado.
 */
function spotinder_spotify_search_one( $query, $type ) {
	$token = spotinder_spotify_app_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}

	$data = spotinder_spotify_request(
		$token,
		'GET',
		'/search',
		array(
			'q'     => $query,
			'type'  => $type,
			'limit' => 1,
		)
	);
	if ( is_wp_error( $data ) ) {
		return $data;
	}

	$items = $data[ $type . 's' ]['items'] ?? array();
	return $items ? $items[0] : new WP_Error( 'spotify_not_found', 'Nada encontrado para ' . $query );
}
