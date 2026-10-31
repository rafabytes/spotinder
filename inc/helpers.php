<?php
/**
 * Funções utilitárias usadas no tema todo.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lê um campo da página de opções (ACF) com fallback para o valor padrão do tema.
 * Assim o site funciona mesmo antes de alguém preencher o painel.
 *
 * @param string $name    Nome do campo.
 * @param mixed  $default Valor quando vazio. Se null, usa spotinder_defaults().
 * @return mixed
 */
function spotinder_opt( $name, $default = null ) {
	$defaults = spotinder_defaults();
	$fallback = null !== $default ? $default : ( $defaults[ $name ] ?? null );

	if ( ! function_exists( 'get_field' ) ) {
		return $fallback;
	}

	// Nunca salvo no painel: usa o padrão.
	if ( null === get_field( $name, 'option', false ) ) {
		return $fallback;
	}

	$value = get_field( $name, 'option' );

	// Verdadeiro/falso: respeita o "desmarcado" do admin.
	if ( is_bool( $fallback ) ) {
		return (bool) $value;
	}

	if ( null === $value || '' === $value || false === $value || ( is_array( $value ) && ! $value ) ) {
		return $fallback;
	}

	return $value;
}

/**
 * Lê um campo de post (ACF) com fallback para post meta.
 *
 * @param string   $name    Nome do campo.
 * @param int|null $post_id Post.
 * @param mixed    $default Valor padrão.
 * @return mixed
 */
function spotinder_field( $name, $post_id = null, $default = '' ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$value   = function_exists( 'get_field' ) ? get_field( $name, $post_id ) : get_post_meta( $post_id, $name, true );

	if ( null === $value || '' === $value || ( is_array( $value ) && ! $value ) ) {
		return $default;
	}
	return $value;
}

/**
 * Nome da marca (editável no painel).
 */
function spotinder_brand() {
	return spotinder_opt( 'brand_name' );
}

/**
 * Páginas do app, localizadas pelo template — não dependem de slug.
 *
 * @return array<string,string> chave => template.
 */
function spotinder_app_templates() {
	return array(
		'discover' => 'templates/app-discover.php',
		'matches'  => 'templates/app-matches.php',
		'profile'  => 'templates/app-profile.php',
	);
}

/**
 * URL de uma página do app (descobrir, matches, perfil) ou de páginas legais.
 *
 * @param string $key discover|matches|profile|privacy|terms|safety.
 */
function spotinder_page_url( $key ) {
	static $cache = array();

	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$page_id = 0;

	if ( 'privacy' === $key ) {
		$page_id = (int) get_option( 'wp_page_for_privacy_policy' );
	}

	if ( ! $page_id ) {
		$ids     = get_option( 'spotinder_pages', array() );
		$page_id = isset( $ids[ $key ] ) ? (int) $ids[ $key ] : 0;
	}

	if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
		$templates = spotinder_app_templates();
		if ( isset( $templates[ $key ] ) ) {
			$found = get_posts(
				array(
					'post_type'   => 'page',
					'meta_key'    => '_wp_page_template',
					'meta_value'  => $templates[ $key ],
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);
			$page_id = $found ? (int) $found[0] : 0;
		}
	}

	$cache[ $key ] = $page_id ? get_permalink( $page_id ) : home_url( '/' );
	return $cache[ $key ];
}

/**
 * URL que inicia o login com Google.
 *
 * @param string $redirect Para onde voltar depois.
 */
function spotinder_login_url( $redirect = '' ) {
	$args = array( 'spotinder_auth' => 'login' );
	if ( $redirect ) {
		$args['redirect_to'] = rawurlencode( $redirect );
	}
	return add_query_arg( $args, home_url( '/' ) );
}

/**
 * URL de logout com nonce.
 */
function spotinder_logout_url() {
	return add_query_arg(
		array(
			'spotinder_auth' => 'logout',
			'_wpnonce'       => wp_create_nonce( 'spotinder_logout' ),
		),
		home_url( '/' )
	);
}

/**
 * Botão "Entrar com o Google" no padrão visual do Google (logo oficial, fundo claro).
 *
 * Sem JavaScript (ou antes de a biblioteca do Google carregar), é um link para a
 * página de login. Com ela pronta, src/js/google-login.js abre o popup do Google.
 *
 * @param array $args {
 *     @type string $label    Texto (deve citar o Google, conforme as regras de marca).
 *     @type string $short    Texto curto em telas pequenas (opcional).
 *     @type string $redirect Para onde ir depois de entrar.
 *     @type string $size     md|lg.
 *     @type string $class    Classes extras.
 * }
 */
function spotinder_google_button( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'label'    => spotinder_opt( 'login_label' ),
			'short'    => '',
			'redirect' => '',
			'size'     => 'md',
			'class'    => '',
		)
	);

	$logo = '<svg class="gsi-btn-logo" width="20" height="20" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>';

	$label = $args['short']
		? sprintf( '<span class="hidden sm:inline">%s</span><span class="sm:hidden">%s</span>', esc_html( $args['label'] ), esc_html( $args['short'] ) )
		: sprintf( '<span>%s</span>', esc_html( $args['label'] ) );

	return sprintf(
		'<a href="%s" class="gsi-btn %s %s" data-google-login data-redirect="%s" data-no-swup>%s%s</a>',
		esc_url( spotinder_login_url( $args['redirect'] ) ),
		'lg' === $args['size'] ? 'gsi-btn-lg' : '',
		esc_attr( $args['class'] ),
		esc_url( $args['redirect'] ),
		$logo,
		$label
	);
}

/**
 * O usuário é um membro (conta criada via Google)?
 * O ID do Spotify identifica perfis de teste e contas antigas, do login pelo Spotify.
 *
 * @param int $user_id Usuário.
 */
function spotinder_is_member( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	return $user_id && ( get_user_meta( $user_id, 'spotinder_google_id', true ) || get_user_meta( $user_id, 'spotinder_spotify_id', true ) );
}

/**
 * Texto curto do painel com HTML básico (links, negrito, itálico, quebra de linha).
 * Links para fora do site abrem em nova guia.
 *
 * @param string $text Texto do painel.
 */
function spotinder_inline_html( $text ) {
	$html = wp_kses(
		(string) $text,
		array(
			'a'      => array(
				'href'  => true,
				'title' => true,
			),
			'strong' => array(),
			'em'     => array(),
			'br'     => array(),
		)
	);

	$home = trailingslashit( home_url() );
	return preg_replace_callback(
		'#<a\s([^>]*)>#i',
		function ( $m ) use ( $home ) {
			if ( ! preg_match( '#href="([^"]*)"#i', $m[1], $href ) || ! preg_match( '#^https?://#i', $href[1] ) || 0 === strpos( trailingslashit( $href[1] ), $home ) ) {
				return $m[0];
			}
			return '<a ' . $m[1] . ' target="_blank" rel="noopener">';
		},
		$html
	);
}

/**
 * Codifica dados para atributo HTML data-props.
 *
 * @param mixed $data Dados.
 */
function spotinder_json_attr( $data ) {
	return esc_attr( wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
}

/**
 * Converte hex para "r g b" (usado nas variáveis CSS).
 *
 * @param string $hex Cor.
 */
function spotinder_hex_to_rgb( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
		return '124 58 237';
	}
	return hexdec( substr( $hex, 0, 2 ) ) . ' ' . hexdec( substr( $hex, 2, 2 ) ) . ' ' . hexdec( substr( $hex, 4, 2 ) );
}

/**
 * Extrai o ID de uma URL/URI do Spotify (track, artist, playlist, album).
 *
 * @param string $url URL ou URI.
 * @return array{type:string,id:string}|null
 */
function spotinder_parse_spotify_url( $url ) {
	if ( preg_match( '#(?:open\.spotify\.com/(?:intl-[a-z]+/)?|spotify:)(track|artist|album|playlist|episode|show)[/:]([A-Za-z0-9]{22})#', (string) $url, $m ) ) {
		return array(
			'type' => $m[1],
			'id'   => $m[2],
		);
	}
	return null;
}

/**
 * Gradiente determinístico para avatares/cartões sem imagem.
 *
 * @param string $seed Texto base.
 */
function spotinder_gradient( $seed ) {
	$h1 = hexdec( substr( md5( $seed ), 0, 2 ) ) * 360 / 255;
	$h2 = ( $h1 + 70 ) % 360;
	return sprintf( 'linear-gradient(135deg, hsl(%d 85%% 60%%), hsl(%d 80%% 45%%))', $h1, $h2 );
}

/**
 * Iniciais de um nome.
 *
 * @param string $name Nome.
 */
function spotinder_initials( $name ) {
	$parts = preg_split( '/\s+/', trim( (string) $name ) );
	$out   = '';
	foreach ( array_slice( $parts, 0, 2 ) as $p ) {
		$out .= mb_strtoupper( mb_substr( $p, 0, 1 ) );
	}
	return $out ? $out : '♪';
}

/**
 * Ícones SVG inline (traço, herdam currentColor).
 *
 * @param string $name  Ícone.
 * @param string $class Classes.
 */
function spotinder_icon( $name, $class = 'size-5' ) {
	$paths = array(
		'google'   => '<path d="M20 12a8 8 0 1 1-2.34-5.66"/><path d="M20 12h-8"/>',
		'spotify'  => '<circle cx="12" cy="12" r="10"/><path d="M7 9.5c3.5-1 7.5-.6 10.5 1.2M7.5 12.8c2.8-.8 6-.4 8.5 1M8 15.8c2.2-.5 4.6-.3 6.5.8"/>',
		'heart'    => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z"/>',
		'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
		'music'    => '<path d="M9 18V5l11-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>',
		'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
		'pin'      => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
		'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'shield'   => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
		'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.5a5 5 0 0 1 5.5 5"/>',
		'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'ticket'   => '<path d="M3 8a2 2 0 0 0 0 4v0a2 2 0 0 1 0 4v2h18v-2a2 2 0 0 1 0-4 2 2 0 0 0 0-4V6H3Z"/><path d="M13 6v12" stroke-dasharray="2 2"/>',
		'sparkle'  => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
		'lock'     => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	return sprintf(
		'<svg class="%s" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
		esc_attr( $class ),
		$paths[ $name ]
	);
}

/**
 * [spotinder_opcao campo="inactive_delete_days"] — usado nas páginas legais para
 * que prazos e regras citados na política acompanhem as configurações reais.
 */
add_shortcode(
	'spotinder_opcao',
	function ( $atts ) {
		$atts = shortcode_atts( array( 'campo' => '' ), $atts );

		// Limites fixos do tema (inc/members.php).
		$limits = array(
			'min_artists' => SPOTINDER_MIN_ARTISTS,
			'max_artists' => SPOTINDER_MAX_ARTISTS,
			'max_tracks'  => SPOTINDER_MAX_TRACKS,
		);
		if ( isset( $limits[ $atts['campo'] ] ) ) {
			return (string) $limits[ $atts['campo'] ];
		}

		$allowed = array( 'brand_name', 'min_age', 'inactive_delete_days', 'inactive_hide_days', 'policy_version', 'top_artists_shown' );
		return in_array( $atts['campo'], $allowed, true ) ? esc_html( (string) spotinder_opt( $atts['campo'] ) ) : '';
	}
);

/**
 * Adiciona ids aos <h2> (âncoras do índice das páginas legais).
 *
 * @param string $html Conteúdo.
 */
function spotinder_heading_ids( $html ) {
	$used = array();
	return preg_replace_callback(
		'#<h2(?![^>]*\sid=)([^>]*)>(.*?)</h2>#s',
		function ( $m ) use ( &$used ) {
			$base = sanitize_title( wp_strip_all_tags( $m[2] ) );
			$id   = $base;
			$n    = 1;
			while ( isset( $used[ $id ] ) ) {
				$id = $base . '-' . ( ++$n );
			}
			$used[ $id ] = true;
			return '<h2 id="' . esc_attr( $id ) . '"' . $m[1] . '>' . $m[2] . '</h2>';
		},
		$html
	);
}

/**
 * Data/hora formatada no fuso do site.
 *
 * @param string $mysql_date Data no formato Y-m-d H:i:s.
 * @param string $format     Formato.
 */
function spotinder_date( $mysql_date, $format = 'd M · H\hi' ) {
	if ( ! $mysql_date ) {
		return '';
	}
	// Datas são gravadas no horário local do site (como o ACF faz).
	$dt = date_create( $mysql_date, wp_timezone() );
	return $dt ? wp_date( $format, $dt->getTimestamp() ) : '';
}
