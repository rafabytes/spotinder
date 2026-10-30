<?php
/**
 * Cartões animados do destaque da home (um perfil de exemplo por gênero).
 *
 * Fotos padrão: banco de imagens Unsplash (Unsplash License), recortadas e sem metadados,
 * em assets/img/hero/perfil-{gênero}.jpg. Créditos no README.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Foto padrão do tema para um gênero (pop, forro, funk, mpb).
 *
 * @param string $genre Gênero.
 * @return string Caminho do arquivo ou ''.
 */
function spotinder_hero_default_photo_file( $genre ) {
	$file = SPOTINDER_DIR . '/assets/img/hero/perfil-' . sanitize_title( $genre ) . '.jpg';
	return file_exists( $file ) ? $file : '';
}

/**
 * Cartões prontos para exibir.
 *
 * @return array[] name, city, genre, score, photo, initials, gradient, artists[name,image,url].
 */
function spotinder_hero_cards() {
	$catalog = (array) get_option( 'spotinder_demo_catalog', array() );
	$cards   = array();

	foreach ( (array) spotinder_opt( 'hero_cards' ) as $card ) {
		$name  = (string) ( $card['name'] ?? '' );
		$genre = (string) ( $card['genre'] ?? '' );
		$photo = (string) ( $card['photo'] ?? '' );

		// Sem foto escolhida no painel: usa a foto padrão do tema para o gênero.
		if ( ! $photo && spotinder_hero_default_photo_file( $genre ) ) {
			$photo = spotinder_asset_url( 'img/hero/perfil-' . sanitize_title( $genre ) . '.jpg' );
		}

		$artists = array();
		foreach ( array_slice( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) ( $card['artists'] ?? '' ) ) ) ), 0, 4 ) as $artist ) {
			$cached    = $catalog[ 'artist:' . spotinder_akey( $artist ) ] ?? array();
			$artists[] = array(
				'name'  => $artist,
				'image' => (string) ( $cached['image'] ?? '' ),
				'url'   => (string) ( $cached['url'] ?? '' ) ?: 'https://open.spotify.com/search/' . rawurlencode( $artist ),
			);
		}

		$cards[] = array(
			'name'     => $name,
			'city'     => (string) ( $card['city'] ?? '' ),
			'genre'    => $genre,
			'score'    => (int) ( $card['score'] ?? 0 ),
			'photo'    => $photo,
			'initials' => spotinder_initials( $name ),
			'gradient' => spotinder_gradient( $name . $genre ),
			'artists'  => $artists,
		);
	}

	return $cards;
}

/**
 * Nomes de artistas dos cartões (para "Completar com dados do Spotify").
 *
 * @return string[]
 */
function spotinder_hero_artist_names() {
	$names = array();
	foreach ( (array) spotinder_opt( 'hero_cards' ) as $card ) {
		$names = array_merge( $names, array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) ( $card['artists'] ?? '' ) ) ) ) );
	}
	return array_values( array_unique( $names ) );
}

/**
 * Garante os cartões no painel e importa as fotos padrão para a Biblioteca de Mídia,
 * para que apareçam (e possam ser trocadas) no campo "Foto de perfil".
 */
function spotinder_seed_hero_cards() {
	if ( ! function_exists( 'update_field' ) ) {
		return;
	}

	// Valores crus (foto = ID do anexo), com subcampos pelo nome — regravar não altera o que o admin escolheu.
	$raw  = get_field( 'hero_cards', 'option', false );
	$rows = array();
	if ( is_array( $raw ) && $raw ) {
		foreach ( $raw as $row ) {
			$clean = array();
			foreach ( (array) $row as $key => $value ) {
				$clean[ str_replace( 'field_sp_hero_cards__', '', $key ) ] = $value;
			}
			$rows[] = $clean;
		}
	} else {
		$rows = spotinder_defaults()['hero_cards'];
	}
	$ids = (array) get_option( 'spotinder_hero_photo_ids', array() );

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	foreach ( $rows as $i => $row ) {
		if ( ! empty( $row['photo'] ) ) {
			continue;
		}
		$slug = sanitize_title( $row['genre'] ?? '' );
		$file = spotinder_hero_default_photo_file( $slug );
		if ( ! $file ) {
			continue;
		}

		// Reaproveita a importação anterior, se ainda existir.
		if ( empty( $ids[ $slug ] ) || ! get_post( $ids[ $slug ] ) ) {
			$tmp = wp_tempnam( 'perfil-' . $slug . '.jpg' );
			copy( $file, $tmp );
			$id = media_handle_sideload(
				array(
					'name'     => 'perfil-' . $slug . '.jpg',
					'tmp_name' => $tmp,
				),
				0,
				sprintf( 'Perfil de exemplo — %s (foto: Unsplash)', $row['genre'] )
			);
			if ( is_wp_error( $id ) ) {
				wp_delete_file( $tmp );
				continue;
			}
			$ids[ $slug ] = (int) $id;
		}

		$rows[ $i ]['photo'] = (int) $ids[ $slug ];
	}

	update_option( 'spotinder_hero_photo_ids', $ids, false );
	update_field( 'field_sp_hero_cards', $rows, 'option' );
}

/**
 * Ao salvar Spotinder > Página inicial, busca no Spotify as fotos de artistas novos dos cartões.
 */
add_action(
	'acf/save_post',
	function ( $post_id ) {
		if ( 'options' !== $post_id || ! spotinder_spotify_configured() || ( $_GET['page'] ?? '' ) !== 'spotinder-home' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$cache = (array) get_option( 'spotinder_demo_catalog', array() );
		$dirty = false;
		foreach ( spotinder_hero_artist_names() as $artist ) {
			$key = 'artist:' . spotinder_akey( $artist );
			if ( isset( $cache[ $key ] ) ) {
				continue;
			}
			$found = spotinder_spotify_search_one( 'artist:"' . $artist . '"', 'artist' );
			if ( ! is_wp_error( $found ) ) {
				$cache[ $key ]         = spotinder_normalize_artist( $found );
				$cache[ $key ]['name'] = $artist;
				$dirty                 = true;
			}
		}
		if ( $dirty ) {
			update_option( 'spotinder_demo_catalog', $cache, false );
		}
	},
	20
);
