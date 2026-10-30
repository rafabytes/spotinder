<?php
/**
 * Ícones (favicon, apple-touch, PWA) e metadados de compartilhamento (Open Graph / Twitter).
 *
 * - O ícone principal é o "Ícone do site" nativo do WordPress (Aparência > Personalizar >
 *   Identidade do site). Na ativação, o tema registra o seu como padrão.
 * - Enquanto o ícone padrão do tema estiver em uso, também entram o favicon SVG e o ícone
 *   maskable. Se o admin trocar o ícone, só o dele é usado.
 * - Os arquivos em assets/ são gerados por `npm run icons` (scripts/icons.mjs).
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

/**
 * URL de um arquivo de assets/.
 *
 * @param string $path Caminho relativo a assets/.
 */
function spotinder_asset_url( $path ) {
	$file = SPOTINDER_DIR . '/assets/' . $path;
	$ver  = file_exists( $file ) ? '?v=' . filemtime( $file ) : '';
	return SPOTINDER_URI . '/assets/' . $path . $ver;
}

/**
 * O ícone do site em uso é o padrão do tema (ou não há nenhum)?
 */
function spotinder_uses_default_icon() {
	$current = (int) get_option( 'site_icon' );
	return ! $current || $current === (int) get_option( 'spotinder_default_site_icon' );
}

/**
 * Registra o ícone do tema como "Ícone do site" (só se nenhum foi definido).
 *
 * @return int ID do anexo, ou 0.
 */
function spotinder_seed_site_icon() {
	if ( get_option( 'site_icon' ) ) {
		return (int) get_option( 'site_icon' );
	}

	$source = SPOTINDER_DIR . '/assets/icons/site-icon-512.png';
	if ( ! file_exists( $source ) ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// O sideload move o arquivo: trabalha numa cópia.
	$tmp = wp_tempnam( 'site-icon.png' );
	copy( $source, $tmp );

	$attachment_id = media_handle_sideload(
		array(
			'name'     => 'site-icon.png',
			'tmp_name' => $tmp,
		),
		0,
		sprintf( 'Ícone do site — %s', spotinder_brand() )
	);

	if ( is_wp_error( $attachment_id ) ) {
		wp_delete_file( $tmp );
		return 0;
	}

	// Mesmo contexto usado pelo Personalizador ao definir o ícone.
	update_post_meta( $attachment_id, '_wp_attachment_context', 'site-icon' );
	spotinder_generate_site_icon_sizes( $attachment_id );
	update_option( 'site_icon', $attachment_id );
	update_option( 'spotinder_default_site_icon', $attachment_id );

	return (int) $attachment_id;
}

/**
 * Gera os recortes próprios do Ícone do site (32, 180, 192, 270 px), como o Personalizador faz.
 *
 * @param int $attachment_id Anexo.
 */
function spotinder_generate_site_icon_sizes( $attachment_id ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-site-icon.php';

	$site_icon = new WP_Site_Icon();
	add_filter( 'intermediate_image_sizes_advanced', array( $site_icon, 'additional_sizes' ) );
	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, get_attached_file( $attachment_id ) ) );
	remove_filter( 'intermediate_image_sizes_advanced', array( $site_icon, 'additional_sizes' ) );
}

/**
 * Título da home: "Marca — slogan" (slogan editável no painel) quando o WP não tem descrição.
 */
add_filter(
	'document_title_parts',
	function ( $parts ) {
		if ( is_front_page() && empty( $parts['tagline'] ) ) {
			$parts['tagline'] = (string) spotinder_opt( 'brand_tagline' );
		}
		return $parts;
	}
);

/**
 * Ícones extras + manifest + metadados de app.
 */
add_action(
	'wp_head',
	function () {
		$brand = spotinder_brand();

		if ( spotinder_uses_default_icon() ) {
			// Navegadores modernos preferem o SVG (nítido em qualquer tamanho).
			printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( spotinder_asset_url( 'icons/icon.svg' ) ) );
			if ( ! get_option( 'site_icon' ) ) {
				printf( '<link rel="icon" href="%s" sizes="32x32">' . "\n", esc_url( spotinder_asset_url( 'icons/favicon-32.png' ) ) );
				printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( spotinder_asset_url( 'icons/apple-touch-icon.png' ) ) );
			}
		}

		printf( '<link rel="manifest" href="%s">' . "\n", esc_url( add_query_arg( 'spotinder_manifest', '1', home_url( '/' ) ) ) );
		printf( '<meta name="apple-mobile-web-app-title" content="%s">' . "\n", esc_attr( $brand ) );
		printf( '<meta name="application-name" content="%s">' . "\n", esc_attr( $brand ) );
	},
	3
);

/**
 * Com o ícone padrão do tema, o apple-touch usa a versão de fundo inteiro (o iOS
 * aplica a própria máscara; cantos transparentes ficariam pretos).
 */
add_filter(
	'site_icon_meta_tags',
	function ( $tags ) {
		if ( ! spotinder_uses_default_icon() ) {
			return $tags;
		}
		return array_map(
			function ( $tag ) {
				return str_contains( $tag, 'apple-touch-icon' )
					? sprintf( '<link rel="apple-touch-icon" href="%s" />', esc_url( spotinder_asset_url( 'icons/apple-touch-icon.png' ) ) )
					: $tag;
			},
			$tags
		);
	}
);

/**
 * Web App Manifest (?spotinder_manifest=1): nome, cores e ícones vêm do painel.
 */
add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = 'spotinder_manifest';
		return $vars;
	}
);

add_action(
	'template_redirect',
	function () {
		if ( ! get_query_var( 'spotinder_manifest' ) ) {
			return;
		}

		$icons = array();
		if ( spotinder_uses_default_icon() ) {
			$icons = array(
				array( 'src' => spotinder_asset_url( 'icons/icon-192.png' ), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ),
				array( 'src' => spotinder_asset_url( 'icons/icon-512.png' ), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ),
				array( 'src' => spotinder_asset_url( 'icons/icon-maskable-192.png' ), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable' ),
				array( 'src' => spotinder_asset_url( 'icons/icon-maskable-512.png' ), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ),
				array( 'src' => spotinder_asset_url( 'icons/icon.svg' ), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any' ),
			);
		} else {
			foreach ( array( 192, 512 ) as $size ) {
				$icons[] = array( 'src' => get_site_icon_url( $size ), 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => 'any' );
			}
		}

		$manifest = array(
			'name'             => spotinder_brand(),
			'short_name'       => spotinder_brand(),
			'description'      => (string) spotinder_opt( 'share_description' ),
			'lang'             => str_replace( '_', '-', get_locale() ),
			'start_url'        => spotinder_is_member() ? spotinder_page_url( 'discover' ) : home_url( '/' ),
			'scope'            => home_url( '/' ),
			'display'          => 'standalone',
			'orientation'      => 'portrait',
			'background_color' => '#0b0a10',
			'theme_color'      => '#0b0a10',
			'icons'            => $icons,
		);

		nocache_headers();
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		exit;
	},
	0
);

/**
 * Plugins de SEO já geram Open Graph — nesse caso o tema não duplica.
 */
function spotinder_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' );
}

/**
 * Imagem de compartilhamento da página atual.
 *
 * @return array{url:string,width:int,height:int,alt:string}
 */
function spotinder_share_image() {
	if ( is_singular() && has_post_thumbnail() ) {
		$src = wp_get_attachment_image_src( get_post_thumbnail_id(), 'full' );
		if ( $src ) {
			return array(
				'url'    => $src[0],
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
				'alt'    => get_the_title(),
			);
		}
	}

	$custom = spotinder_opt( 'share_image' );
	if ( is_array( $custom ) && ! empty( $custom['url'] ) ) {
		return array(
			'url'    => $custom['url'],
			'width'  => (int) $custom['width'],
			'height' => (int) $custom['height'],
			'alt'    => $custom['alt'] ? $custom['alt'] : spotinder_brand(),
		);
	}

	return array(
		'url'    => spotinder_asset_url( 'img/og-default.png' ),
		'width'  => 1200,
		'height' => 630,
		'alt'    => spotinder_brand() . ' — ' . spotinder_opt( 'brand_tagline' ),
	);
}

/**
 * Descrição da página atual.
 */
function spotinder_share_description() {
	$text = '';
	if ( is_singular() && ! is_front_page() ) {
		$post = get_post();
		$text = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( do_shortcode( $post->post_content ) ), 30, '…' );
		if ( 'spot_story' === $post->post_type && spotinder_field( 'story_subtitle', $post->ID ) ) {
			$text = spotinder_field( 'story_subtitle', $post->ID );
		}
	}
	return wp_strip_all_tags( $text ? $text : (string) spotinder_opt( 'share_description' ) );
}

add_action(
	'wp_head',
	function () {
		if ( spotinder_seo_plugin_active() ) {
			return;
		}

		$image = spotinder_share_image();
		$desc  = spotinder_share_description();
		$title = html_entity_decode( wp_get_document_title(), ENT_QUOTES, 'UTF-8' );
		$path  = (string) $GLOBALS['wp']->request;
		$url   = is_singular() ? get_permalink() : home_url( $path ? user_trailingslashit( $path ) : '/' );
		$tw    = ltrim( (string) spotinder_opt( 'share_twitter' ), '@' );

		$tags = array(
			array( 'name', 'description', $desc ),
			array( 'property', 'og:type', is_singular( array( 'post', 'spot_story', 'spot_event' ) ) ? 'article' : 'website' ),
			array( 'property', 'og:site_name', spotinder_brand() ),
			array( 'property', 'og:locale', get_locale() ),
			array( 'property', 'og:title', $title ),
			array( 'property', 'og:description', $desc ),
			array( 'property', 'og:url', $url ),
			array( 'property', 'og:image', $image['url'] ),
			array( 'property', 'og:image:width', $image['width'] ),
			array( 'property', 'og:image:height', $image['height'] ),
			array( 'property', 'og:image:alt', $image['alt'] ),
			array( 'name', 'twitter:card', 'summary_large_image' ),
			array( 'name', 'twitter:title', $title ),
			array( 'name', 'twitter:description', $desc ),
			array( 'name', 'twitter:image', $image['url'] ),
			array( 'name', 'twitter:image:alt', $image['alt'] ),
		);
		if ( $tw ) {
			$tags[] = array( 'name', 'twitter:site', '@' . $tw );
		}

		foreach ( $tags as list( $attr, $key, $value ) ) {
			if ( '' !== (string) $value ) {
				printf( '<meta %s="%s" content="%s">' . "\n", esc_attr( $attr ), esc_attr( $key ), esc_attr( $value ) );
			}
		}
	},
	4
);

/**
 * Marca (vinil com coração) inline — mesma do ícone, com as cores do painel.
 *
 * @param string $class Classes CSS.
 */
function spotinder_logo_mark( $class = 'size-8' ) {
	static $n = 0;
	++$n;
	$bg = 'spm-bg-' . $n;
	$lb = 'spm-lb-' . $n;

	return sprintf(
		'<svg class="%1$s" viewBox="0 0 512 512" aria-hidden="true"><defs><linearGradient id="%2$s" x1="0" y1="0" x2="1" y2="1"><stop offset="0" style="stop-color:var(--sp-primary)"/><stop offset="1" style="stop-color:var(--sp-accent)"/></linearGradient><linearGradient id="%3$s" x1="0" y1="0" x2="1" y2="1"><stop offset="0" style="stop-color:var(--sp-accent)"/><stop offset="1" style="stop-color:var(--sp-primary)"/></linearGradient></defs><rect width="512" height="512" rx="116" fill="url(#%2$s)"/><circle cx="256" cy="256" r="172" fill="#0B0A10"/><circle cx="256" cy="256" r="132" fill="none" stroke="#fff" stroke-opacity=".13" stroke-width="3"/><circle cx="256" cy="256" r="82" fill="url(#%3$s)"/><path d="M256 304C234 290 204 268 204 237a26 26 0 0 1 52-8a26 26 0 0 1 52 8c0 31-30 53-52 67z" fill="#fff"/></svg>',
		esc_attr( $class ),
		$bg,
		$lb
	);
}
