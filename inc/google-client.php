<?php
/**
 * Login com o Google (OpenID Connect, Authorization Code Flow em popup).
 *
 * O botão abre o popup da biblioteca oficial do Google (Google Identity Services) e o
 * código volta ao navegador por postMessage — não por uma URL do site. Isso evita o
 * redirecionamento com ?iss=https://accounts.google.com, que o ModSecurity da
 * hospedagem bloqueia (406).
 *
 * Princípio de privacidade: pedimos só os escopos "openid profile" (ID da conta,
 * nome e foto pública) — nada de e-mail. Os tokens só vivem durante a troca do
 * código e nunca são gravados no banco.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

const SPOTINDER_GOOGLE_TOKEN = 'https://oauth2.googleapis.com/token';
const SPOTINDER_GOOGLE_SCOPE = 'openid profile';

/**
 * Credenciais configuradas?
 */
function spotinder_google_configured() {
	return spotinder_opt( 'google_client_id' ) && spotinder_opt( 'google_client_secret' );
}

/**
 * Troca o code pelo id_token e devolve os dados da conta.
 *
 * O id_token chega direto do endpoint de token do Google, por HTTPS e com o
 * client_secret: pela especificação (OpenID Connect Core, 3.1.3.7) a validação
 * TLS substitui a checagem da assinatura. Conferimos emissor, público e validade.
 *
 * @param string $code Código entregue pelo popup do Google.
 * @return array{sub:string,name:string,given_name:string,picture:string}|WP_Error
 */
function spotinder_google_exchange_code( $code ) {
	$response = wp_remote_post(
		SPOTINDER_GOOGLE_TOKEN,
		array(
			'timeout' => 15,
			'body'    => array(
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'client_id'     => spotinder_opt( 'google_client_id' ),
				'client_secret' => spotinder_opt( 'google_client_secret' ),
				// Valor fixo do fluxo em popup do Google Identity Services.
				'redirect_uri'  => 'postmessage',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status = (int) wp_remote_retrieve_response_code( $response );
	$data   = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $status >= 400 || empty( $data['id_token'] ) ) {
		return new WP_Error( 'google_token', $data['error_description'] ?? 'O Google não retornou o token.', array( 'status' => $status ) );
	}

	$parts  = explode( '.', (string) $data['id_token'] );
	$claims = count( $parts ) === 3 ? json_decode( (string) base64_decode( strtr( $parts[1], '-_', '+/' ) ), true ) : null; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

	if (
		! is_array( $claims )
		|| ! in_array( $claims['iss'] ?? '', array( 'https://accounts.google.com', 'accounts.google.com' ), true )
		|| ( $claims['aud'] ?? '' ) !== spotinder_opt( 'google_client_id' )
		|| (int) ( $claims['exp'] ?? 0 ) < time() - 60
		|| empty( $claims['sub'] )
	) {
		return new WP_Error( 'google_id_token', 'Token do Google inválido.' );
	}

	return array(
		'sub'        => sanitize_text_field( (string) $claims['sub'] ),
		'name'       => sanitize_text_field( (string) ( $claims['name'] ?? '' ) ),
		'given_name' => sanitize_text_field( (string) ( $claims['given_name'] ?? '' ) ),
		// A foto vem em 96 px; pede uma versão maior ao próprio Google.
		'picture'    => esc_url_raw( preg_replace( '/=s\d+-c$/', '=s400-c', (string) ( $claims['picture'] ?? '' ) ) ),
	);
}
