<?php
/**
 * Configuração do tema: supports, menus, assets e restrições de acesso.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	function () {
		load_theme_textdomain( 'spotinder', SPOTINDER_DIR . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'custom-logo' );

		add_image_size( 'spotinder-card', 900, 600, true );

		register_nav_menus(
			array(
				'primary' => __( 'Menu principal', 'spotinder' ),
				'footer'  => __( 'Menu do rodapé', 'spotinder' ),
				'legal'   => __( 'Links legais (rodapé)', 'spotinder' ),
			)
		);
	}
);

/**
 * Assets compilados pelo Parcel.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		$css = SPOTINDER_DIR . '/dist/css/app.css';
		$js  = SPOTINDER_DIR . '/dist/js/app.js';

		if ( file_exists( $css ) ) {
			wp_enqueue_style( 'spotinder', SPOTINDER_URI . '/dist/css/app.css', array(), (string) filemtime( $css ) );
		}

		if ( file_exists( $js ) ) {
			wp_enqueue_script( 'spotinder', SPOTINDER_URI . '/dist/js/app.js', array(), (string) filemtime( $js ), array( 'strategy' => 'defer', 'in_footer' => true ) );
			wp_add_inline_script( 'spotinder', 'window.SPOTINDER = ' . wp_json_encode( spotinder_js_config() ) . ';', 'before' );
		}

		// Sem jQuery/estilos de bloco no front: o tema é todo Tailwind.
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );
	}
);

/**
 * O bundle já sai minificado pelo Parcel. O minificador de JS do LiteSpeed
 * Cache corrompe o código do React (apaga um `;` de um `for(;;)`) e o atraso
 * de JS faria `window.SPOTINDER` chegar fora de ordem: tira o bundle e a
 * configuração inline de qualquer otimização de JS de plugins.
 */
add_filter(
	'wp_script_attributes',
	function ( $attributes ) {
		if ( isset( $attributes['id'] ) && 'spotinder-js' === $attributes['id'] ) {
			$attributes['data-no-optimize'] = '1';
		}
		return $attributes;
	}
);
add_filter(
	'wp_inline_script_attributes',
	function ( $attributes ) {
		if ( isset( $attributes['id'] ) && 'spotinder-js-before' === $attributes['id'] ) {
			$attributes['data-no-optimize'] = '1';
		}
		return $attributes;
	}
);

/**
 * O CSS do tema também fica fora do LiteSpeed. Com "Carregar CSS de forma
 * assíncrona" (predefinição avançada), a página aparecia crua até o CSS chegar —
 * o logo em SVG ocupava a tela inteira. São ~85 KB já minificados pelo Parcel:
 * melhor bloquear a renderização do que piscar sem estilo.
 */
add_filter(
	'style_loader_tag',
	function ( $tag, $handle ) {
		if ( 'spotinder' === $handle && false === strpos( $tag, 'data-no-optimize' ) ) {
			$tag = str_replace( '<link ', '<link data-no-optimize="1" ', $tag );
		}
		return $tag;
	},
	10,
	2
);

/**
 * Configuração e textos repassados ao React.
 */
function spotinder_js_config() {
	$user_id = get_current_user_id();

	$strings_keys = array(
		'brand_name', 'login_label', 'onboarding_title', 'onboarding_text', 'consent_age_label', 'consent_terms_label',
		'consent_share_label', 'consent_social_label', 'photo_help', 'consent_photo_label', 'location_help', 'consent_location_label', 'social_warning_title', 'social_warning_text', 'deck_title',
		'deck_empty_title', 'deck_empty_text', 'match_title', 'match_text', 'soundtrack_label', 'playlist_label',
		'matches_title', 'matches_empty_text', 'footer_attribution',
	);
	$strings      = array();
	foreach ( $strings_keys as $key ) {
		$strings[ $key ] = (string) spotinder_opt( $key );
	}

	$me = null;
	if ( spotinder_is_member( $user_id ) ) {
		$user = wp_get_current_user();
		$me   = array(
			'id'       => $user_id,
			'name'     => $user->display_name,
			'initials' => spotinder_initials( $user->display_name ),
			'gradient' => spotinder_gradient( $user->user_login ),
			'photo'    => spotinder_photo_url( $user_id ),
		);
	}

	// Célula atual (já aproximada) para a atualização automática comparar sem reenviar à toa.
	$region = $user_id ? spotinder_get_region( $user_id ) : null;

	return array(
		'me'          => $me,
		'location'    => array(
			'enabled' => (bool) $region,
			'lat'     => $region ? (float) $region['lat'] : null,
			'lng'     => $region ? (float) $region['lng'] : null,
			'grid'    => SPOTINDER_GRID_DEG,
			'max'     => $user_id ? spotinder_max_distance( $user_id ) : 0,
			'options' => SPOTINDER_DISTANCES,
		),
		'restUrl'     => esc_url_raw( rest_url( 'spotinder/v1/' ) ),
		'nonce'       => $user_id ? wp_create_nonce( 'wp_rest' ) : '',
		'loggedIn'    => (bool) $user_id,
		'isMember'    => spotinder_is_member( $user_id ),
		'onboarded'   => $user_id ? (bool) get_user_meta( $user_id, 'spotinder_onboarded', true ) : false,
		'autoplay'    => (bool) spotinder_opt( 'match_autoplay' ),
		'minAge'      => (int) spotinder_opt( 'min_age' ),
		'urls'        => array(
			'home'     => home_url( '/' ),
			'discover' => spotinder_page_url( 'discover' ),
			'matches'  => spotinder_page_url( 'matches' ),
			'profile'  => spotinder_page_url( 'profile' ),
			'events'   => get_post_type_archive_link( 'spot_event' ),
			'privacy'  => spotinder_page_url( 'privacy' ),
			'terms'    => spotinder_page_url( 'terms' ),
			'safety'   => spotinder_page_url( 'safety' ),
			'login'    => spotinder_login_url(),
			'logout'   => spotinder_logout_url(),
		),
		'strings'     => $strings,
		'intentions'  => array_values( (array) spotinder_opt( 'intentions' ) ),
		'inviteTypes' => array_values( (array) spotinder_opt( 'invite_types' ) ),
		'safetyTips'  => wp_list_pluck( (array) spotinder_opt( 'safety_tips' ), 'text' ),
		'google'      => array(
			'clientId' => $user_id || ! spotinder_google_configured() ? '' : (string) spotinder_opt( 'google_client_id' ),
			'scope'    => SPOTINDER_GOOGLE_SCOPE,
		),
		'music'       => array(
			'catalog'    => spotinder_spotify_configured(),
			'minArtists' => SPOTINDER_MIN_ARTISTS,
			'maxArtists' => SPOTINDER_MAX_ARTISTS,
			'maxTracks'  => SPOTINDER_MAX_TRACKS,
		),
		'socials'     => spotinder_social_networks(),
		'reasons'     => spotinder_report_reasons(),
	);
}

/**
 * Cores da marca como variáveis CSS (editáveis no painel).
 */
add_action(
	'wp_head',
	function () {
		printf(
			'<style id="spotinder-vars" data-no-optimize="1">:root{--sp-primary:%s;--sp-accent:%s;--sp-highlight:%s;--sp-primary-rgb:%s;}</style>' . "\n",
			esc_html( spotinder_opt( 'color_primary' ) ),
			esc_html( spotinder_opt( 'color_accent' ) ),
			esc_html( spotinder_opt( 'color_highlight' ) ),
			esc_html( spotinder_hex_to_rgb( spotinder_opt( 'color_primary' ) ) )
		);
	},
	5
);

/**
 * Membros (contas via Google) não usam o wp-admin nem a barra de administração.
 */
add_filter(
	'show_admin_bar',
	function ( $show ) {
		return current_user_can( 'edit_posts' ) ? $show : false;
	}
);

add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || current_user_can( 'edit_posts' ) ) {
			return;
		}
		if ( spotinder_is_member() ) {
			wp_safe_redirect( spotinder_page_url( 'profile' ) );
			exit;
		}
	}
);

/**
 * Quem ainda não concluiu o cadastro (ou precisa aceitar uma nova versão
 * dos termos) vai direto para o perfil.
 */
add_action(
	'template_redirect',
	function () {
		$templates = spotinder_app_templates();
		if ( ! is_page_template( array( $templates['discover'], $templates['matches'] ) ) || ! spotinder_is_member() ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( ! get_user_meta( $user_id, 'spotinder_onboarded', true ) || ! spotinder_has_consents( $user_id ) ) {
			wp_safe_redirect( spotinder_page_url( 'profile' ) );
			exit;
		}
	}
);

/**
 * O único jeito de criar conta é pelo Google: desliga o cadastro nativo.
 */
add_filter( 'pre_option_users_can_register', '__return_zero' );
add_filter(
	'register_url',
	function () {
		return spotinder_login_url();
	}
);
add_action(
	'login_form_register',
	function () {
		wp_safe_redirect( spotinder_login_url() );
		exit;
	}
);

/**
 * Menu padrão quando nenhum menu foi atribuído.
 */
function spotinder_fallback_menu() {
	$items = array(
		array( get_post_type_archive_link( 'spot_event' ), __( 'Eventos', 'spotinder' ) ),
		spotinder_stories_enabled() ? array( get_post_type_archive_link( 'spot_story' ), __( 'Histórias', 'spotinder' ) ) : array( '', '' ),
		array( spotinder_page_url( 'safety' ), __( 'Segurança', 'spotinder' ) ),
	);
	echo '<ul class="menu">';
	foreach ( $items as $item ) {
		if ( $item[0] ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $item[0] ), esc_html( $item[1] ) );
		}
	}
	echo '</ul>';
}

/**
 * Classes no <body>.
 */
add_filter(
	'body_class',
	function ( $classes ) {
		$classes[] = is_user_logged_in() ? 'is-logged-in' : 'is-guest';
		if ( is_page_template( array_values( spotinder_app_templates() ) ) ) {
			$classes[] = 'is-app';
		}
		return $classes;
	}
);

/**
 * Remove ruídos do <head>.
 */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_generator' );
