<?php
/**
 * Cliente mínimo do catálogo público do Spotify (Web API).
 *
 * Usa só o token do próprio app (client credentials): ninguém conecta a conta
 * do Spotify. Serve para a busca de artistas e músicas que cada membro escolhe,
 * para as fotos dos cartões do destaque e para os perfis de teste.
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
 * Token do próprio app (client credentials) — só lê metadados públicos do catálogo.
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
 * @param string $path   Caminho (ex.: /search).
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

/**
 * Busca do seletor de artistas/músicas do perfil. Cada resultado fica em cache
 * por um dia: ao salvar o perfil, o servidor confere os IDs escolhidos sem
 * consultar o Spotify de novo e sem confiar em nomes/imagens vindos do navegador.
 *
 * @param string $query Termo digitado.
 * @param string $type  artist|track.
 * @return array[]|WP_Error Itens normalizados.
 */
function spotinder_spotify_search( $query, $type ) {
	$token = spotinder_spotify_app_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}

	$data = spotinder_spotify_request(
		$token,
		'GET',
		'/search',
		array(
			'q'      => $query,
			'type'   => $type,
			'limit'  => 10,
			'market' => 'BR',
		)
	);
	if ( is_wp_error( $data ) ) {
		return $data;
	}

	$normalize = 'artist' === $type ? 'spotinder_normalize_artist' : 'spotinder_normalize_track';
	$items     = array();
	foreach ( (array) ( $data[ $type . 's' ]['items'] ?? array() ) as $raw ) {
		$item = $normalize( (array) $raw );
		if ( $item['id'] ) {
			set_transient( "spotinder_cat_{$type}_{$item['id']}", $item, DAY_IN_SECONDS );
			$items[] = $item;
		}
	}
	return $items;
}

/**
 * Um artista/faixa do catálogo pelo ID (cache da busca ou consulta direta).
 *
 * @param string $type artist|track.
 * @param string $id   ID do Spotify.
 * @return array|WP_Error Item normalizado.
 */
function spotinder_spotify_catalog_item( $type, $id ) {
	if ( ! preg_match( '/^[A-Za-z0-9]{22}$/', (string) $id ) ) {
		return new WP_Error( 'spotify_id', 'ID inválido.' );
	}

	$cached = get_transient( "spotinder_cat_{$type}_{$id}" );
	if ( $cached ) {
		return $cached;
	}

	$token = spotinder_spotify_app_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}

	$raw = spotinder_spotify_request( $token, 'GET', "/{$type}s/{$id}", array( 'market' => 'BR' ) );
	if ( is_wp_error( $raw ) ) {
		return $raw;
	}

	$item = 'artist' === $type ? spotinder_normalize_artist( $raw ) : spotinder_normalize_track( $raw );
	set_transient( "spotinder_cat_{$type}_{$id}", $item, DAY_IN_SECONDS );
	return $item;
}
