<?php
/**
 * Compatibilidade, deck, swipes, matches, trilha sonora e playlist em comum.
 *
 * O cálculo é propositalmente simples e explicável: interseção de artistas
 * (ponderada pela ordem em que cada um escolheu), músicas e gêneros em comum. Nada é usado
 * para publicidade nem salvo como "perfil comportamental".
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Chave de comparação de artista: nome normalizado (funciona também para
 * perfis de teste sem ID do Spotify).
 *
 * @param string $name Nome do artista.
 */
function spotinder_akey( $name ) {
	return mb_strtolower( trim( remove_accents( (string) $name ) ) );
}

/**
 * Chave de comparação de faixa: nome + artista principal.
 *
 * @param array $track Faixa normalizada.
 */
function spotinder_tkey( $track ) {
	return spotinder_akey( $track['name'] ?? '' ) . '|' . spotinder_akey( $track['artists'][0]['name'] ?? '' );
}

/**
 * Compatibilidade entre dois membros.
 *
 * @param int $a Usuário A.
 * @param int $b Usuário B.
 * @return array{score:int,shared_artists:string[],shared_tracks:string[],shared_genres:string[]} Chaves via spotinder_akey()/spotinder_tkey().
 */
function spotinder_compat( $a, $b ) {
	static $cache = array();
	$key = min( $a, $b ) . ':' . max( $a, $b );
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$weights = function ( $list ) {
		$n   = max( 1, count( $list ) );
		$out = array();
		foreach ( array_values( $list ) as $i => $item ) {
			// 1.0 para o primeiro da lista, caindo até 0.5 no fim.
			$out[ spotinder_akey( $item['name'] ) ] = 1 - ( $i / $n ) * 0.5;
		}
		return $out;
	};

	$wa = $weights( spotinder_get_artists( $a ) );
	$wb = $weights( spotinder_get_artists( $b ) );

	$shared_artists = array_map( 'strval', array_keys( array_intersect_key( $wa, $wb ) ) );
	$raw            = 0.0;
	foreach ( $shared_artists as $key ) {
		$raw += sqrt( $wa[ $key ] * $wb[ $key ] );
	}

	$ta            = array_map( 'spotinder_tkey', spotinder_get_tracks( $a ) );
	$tb            = array_map( 'spotinder_tkey', spotinder_get_tracks( $b ) );
	$shared_tracks = array_values( array_intersect( $ta, $tb ) );
	$raw          += 0.5 * count( $shared_tracks );

	$shared_genres = array_values( array_intersect( spotinder_top_genres( $a, 10 ), spotinder_top_genres( $b, 10 ) ) );
	$raw          += 0.15 * count( $shared_genres );

	// Curva de saturação: 1 artista em comum ≈ 27%, 3 ≈ 60%, 6 ≈ 85%.
	$score = (int) round( 100 * ( 1 - exp( -0.32 * $raw ) ) );

	$cache[ $key ] = array(
		'score'          => max( 0, min( 99, $score ) ),
		'shared_artists' => $shared_artists,
		'shared_tracks'  => $shared_tracks,
		'shared_genres'  => $shared_genres,
	);

	return $cache[ $key ];
}

/**
 * IDs que o usuário não deve ver (já avaliados ou bloqueios em qualquer direção).
 *
 * @param int $user_id Usuário.
 * @return int[]
 */
function spotinder_excluded_ids( $user_id ) {
	global $wpdb;
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
	$swiped  = $wpdb->get_col( $wpdb->prepare( 'SELECT target_id FROM ' . spotinder_table( 'swipes' ) . ' WHERE user_id = %d', $user_id ) );
	$blocked = $wpdb->get_col( $wpdb->prepare( 'SELECT target_id FROM ' . spotinder_table( 'blocks' ) . ' WHERE user_id = %d UNION SELECT user_id FROM ' . spotinder_table( 'blocks' ) . ' WHERE target_id = %d', $user_id, $user_id ) );
	// phpcs:enable
	return array_map( 'intval', array_merge( array( $user_id ), $swiped, $blocked ) );
}

/**
 * Monta o deck de descoberta.
 *
 * @param int    $user_id   Usuário.
 * @param string $intention Filtro opcional por intenção.
 * @return array[]
 */
function spotinder_get_deck( $user_id, $intention = '' ) {
	$meta_query = array(
		'relation' => 'AND',
		array(
			'key'     => 'spotinder_onboarded',
			'compare' => 'EXISTS',
		),
		array(
			'key'   => 'spotinder_visible',
			'value' => '1',
		),
		array(
			'key'     => 'spotinder_banned',
			'compare' => 'NOT EXISTS',
		),
	);

	$active_since = time() - DAY_IN_SECONDS * (int) spotinder_opt( 'inactive_hide_days' );

	if ( spotinder_opt( 'show_demo_in_deck' ) ) {
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => 'spotinder_last_active',
				'value'   => $active_since,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			),
			array(
				'key'     => 'spotinder_is_demo',
				'compare' => 'EXISTS',
			),
		);
	} else {
		$meta_query[] = array(
			'key'     => 'spotinder_last_active',
			'value'   => $active_since,
			'compare' => '>=',
			'type'    => 'NUMERIC',
		);
		$meta_query[] = array(
			'key'     => 'spotinder_is_demo',
			'compare' => 'NOT EXISTS',
		);
	}

	if ( $intention ) {
		$meta_query[] = array(
			'key'     => 'spotinder_intentions',
			'value'   => '"' . sanitize_key( $intention ) . '"',
			'compare' => 'LIKE',
		);
	}

	$candidates = get_users(
		array(
			'exclude'    => spotinder_excluded_ids( $user_id ),
			'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'number'     => 400,
			'fields'     => 'ID',
		)
	);

	$my_intentions = array_filter( (array) get_user_meta( $user_id, 'spotinder_intentions', true ) );
	$has_region    = (bool) spotinder_get_region( $user_id );
	$max_distance  = $has_region ? spotinder_max_distance( $user_id ) : 0;
	$ranked        = array();

	foreach ( $candidates as $candidate ) {
		$candidate = (int) $candidate;
		$distance  = $has_region ? spotinder_region_distance( $user_id, $candidate ) : null;

		// Distância máxima: quem não compartilha região fica de fora quando há limite.
		if ( $max_distance && ( null === $distance || $distance > $max_distance ) ) {
			continue;
		}

		$compat   = spotinder_compat( $user_id, $candidate );
		$overlap  = count( array_intersect( $my_intentions, array_filter( (array) get_user_meta( $candidate, 'spotinder_intentions', true ) ) ) );
		$ranked[] = array(
			'id'   => $candidate,
			'rank' => $compat['score'] + 4 * $overlap + spotinder_distance_bonus( $distance ),
		);
	}

	usort( $ranked, fn( $x, $y ) => $y['rank'] <=> $x['rank'] );

	$deck = array();
	foreach ( array_slice( $ranked, 0, (int) spotinder_opt( 'deck_size' ) ) as $row ) {
		$deck[] = spotinder_member_payload( $row['id'], $user_id, 'card' );
	}
	return array_values( array_filter( $deck ) );
}

/**
 * Registra um swipe e cria o match se for recíproco.
 *
 * @param int    $user_id   Quem desliza.
 * @param int    $target_id Alvo.
 * @param string $action    like|pass.
 * @return array|WP_Error ['match' => payload|null]
 */
function spotinder_swipe( $user_id, $target_id, $action ) {
	global $wpdb;

	if ( $user_id === $target_id || ! spotinder_is_discoverable( $target_id ) ) {
		return new WP_Error( 'spotinder_invalid_target', 'Perfil indisponível.', array( 'status' => 404 ) );
	}
	if ( in_array( $target_id, spotinder_excluded_ids( $user_id ), true ) && ! spotinder_swipe_exists( $user_id, $target_id ) ) {
		return new WP_Error( 'spotinder_invalid_target', 'Perfil indisponível.', array( 'status' => 404 ) );
	}

	$action = 'like' === $action ? 'like' : 'pass';

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . spotinder_table( 'swipes' ) . ' (user_id, target_id, action, created_at) VALUES (%d, %d, %s, %s) ON DUPLICATE KEY UPDATE action = VALUES(action), created_at = VALUES(created_at)', $user_id, $target_id, $action, current_time( 'mysql' ) ) );

	if ( 'like' !== $action ) {
		return array( 'match' => null );
	}

	// Usuários de teste podem curtir de volta para demonstrar o fluxo de match.
	if ( get_user_meta( $target_id, 'spotinder_is_demo', true ) && spotinder_opt( 'demo_auto_like' ) && ! spotinder_swipe_exists( $target_id, $user_id ) ) {
		$chance = (int) spotinder_opt( 'demo_like_chance' );
		$liked  = wp_rand( 1, 100 ) <= $chance || spotinder_compat( $user_id, $target_id )['score'] >= 50;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . spotinder_table( 'swipes' ) . ' (user_id, target_id, action, created_at) VALUES (%d, %d, %s, %s)', $target_id, $user_id, $liked ? 'like' : 'pass', current_time( 'mysql' ) ) );
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$reciprocal = $wpdb->get_var( $wpdb->prepare( 'SELECT action FROM ' . spotinder_table( 'swipes' ) . ' WHERE user_id = %d AND target_id = %d', $target_id, $user_id ) );

	if ( 'like' !== $reciprocal ) {
		return array( 'match' => null );
	}

	$match_id = spotinder_create_match( $user_id, $target_id );
	return array( 'match' => spotinder_match_detail( $match_id, $user_id ) );
}

/**
 * Já existe swipe de A para B?
 *
 * @param int $a Usuário.
 * @param int $b Alvo.
 */
function spotinder_swipe_exists( $a, $b ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . spotinder_table( 'swipes' ) . ' WHERE user_id = %d AND target_id = %d', $a, $b ) );
}

/**
 * Cria o match (idempotente).
 *
 * @param int $a Usuário.
 * @param int $b Usuário.
 * @return int ID do match.
 */
function spotinder_create_match( $a, $b ) {
	global $wpdb;
	$table = spotinder_table( 'matches' );
	$ua    = min( $a, $b );
	$ub    = max( $a, $b );

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE user_a = %d AND user_b = %d", $ua, $ub ) );
	if ( $existing ) {
		return (int) $existing;
	}

	$wpdb->insert(
		$table,
		array(
			'user_a'     => $ua,
			'user_b'     => $ub,
			'score'      => spotinder_compat( $ua, $ub )['score'],
			'soundtrack' => wp_json_encode( spotinder_pick_soundtrack( $ua, $ub ) ),
			'seen_a'     => $a === $ua ? 1 : 0,
			'seen_b'     => $a === $ub ? 1 : 0,
			'created_at' => current_time( 'mysql' ),
		)
	);

	return (int) $wpdb->insert_id;
}

/**
 * Escolhe "a música de vocês".
 * Ordem: faixa favorita dos dois → faixa de artista em comum → primeira favorita
 * de quem escolheu músicas.
 *
 * @param int $a Usuário.
 * @param int $b Usuário.
 */
function spotinder_pick_soundtrack( $a, $b ) {
	$ta = spotinder_get_tracks( $a );
	$tb = spotinder_get_tracks( $b );

	$keys_b = array_flip( array_map( 'spotinder_tkey', $tb ) );
	foreach ( $ta as $track ) {
		if ( isset( $keys_b[ spotinder_tkey( $track ) ] ) ) {
			return $track + array( 'reason' => 'Essa música está entre as favoritas de vocês dois.' );
		}
	}

	$compat = spotinder_compat( $a, $b );
	if ( $compat['shared_artists'] ) {
		$shared = array_flip( $compat['shared_artists'] );
		foreach ( array( $ta, $tb ) as $list ) {
			foreach ( $list as $track ) {
				foreach ( $track['artists'] as $artist ) {
					if ( isset( $shared[ spotinder_akey( $artist['name'] ) ] ) ) {
						return $track + array( 'reason' => sprintf( 'De %s, artista que vocês dois adoram.', $artist['name'] ) );
					}
				}
			}
		}
	}

	$pool = $ta ? $ta : $tb;
	if ( $pool ) {
		$owner = $ta ? $a : $b;
		return $pool[0] + array( 'reason' => sprintf( 'A favorita de %s — um bom começo.', get_userdata( $owner )->display_name ) );
	}

	return null;
}

/**
 * Playlist em comum (faixas ordenadas pelo "quanto é de vocês dois").
 *
 * @param int $a     Usuário.
 * @param int $b     Usuário.
 * @param int $limit Máximo.
 * @return array[]
 */
function spotinder_common_playlist( $a, $b, $limit = 30 ) {
	$ta     = spotinder_get_tracks( $a );
	$tb     = spotinder_get_tracks( $b );
	$name_a = get_userdata( $a )->display_name;
	$name_b = get_userdata( $b )->display_name;

	$artist_keys = fn( $user ) => array_flip( array_map( fn( $x ) => spotinder_akey( $x['name'] ), spotinder_get_artists( $user ) ) );
	$artists_a   = $artist_keys( $a );
	$artists_b   = $artist_keys( $b );
	$keys_b      = array_flip( array_map( 'spotinder_tkey', $tb ) );

	$out  = array();
	$seen = array();
	$add  = function ( $track, $reason, $tag ) use ( &$out, &$seen ) {
		$key = spotinder_tkey( $track );
		if ( isset( $seen[ $key ] ) ) {
			return;
		}
		$seen[ $key ] = true;
		$out[]        = $track + array(
			'reason' => $reason,
			'tag'    => $tag,
		);
	};

	foreach ( $ta as $t ) {
		if ( isset( $keys_b[ spotinder_tkey( $t ) ] ) ) {
			$add( $t, 'Favorita de vocês dois', 'both' );
		}
	}

	$by_known_artist = function ( $tracks, $other_artists ) use ( $add ) {
		foreach ( $tracks as $t ) {
			foreach ( $t['artists'] as $artist ) {
				if ( isset( $other_artists[ spotinder_akey( $artist['name'] ) ] ) ) {
					$add( $t, sprintf( 'De %s, que vocês dois ouvem', $artist['name'] ), 'artist' );
					break;
				}
			}
		}
	};
	$by_known_artist( $ta, $artists_b );
	$by_known_artist( $tb, $artists_a );

	// Completa intercalando as favoritas de cada um: indicação mútua.
	$max = max( count( $ta ), count( $tb ) );
	for ( $i = 0; $i < $max && count( $out ) < $limit; $i++ ) {
		if ( isset( $ta[ $i ] ) ) {
			$add( $ta[ $i ], sprintf( 'Indicação de %s', $name_a ), 'a' );
		}
		if ( isset( $tb[ $i ] ) ) {
			$add( $tb[ $i ], sprintf( 'Indicação de %s', $name_b ), 'b' );
		}
	}

	return array_slice( $out, 0, $limit );
}

/**
 * Linha do match, garantindo que o usuário participa dele.
 *
 * @param int $match_id Match.
 * @param int $user_id  Usuário.
 * @return object|null
 */
function spotinder_get_match_row( $match_id, $user_id ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . spotinder_table( 'matches' ) . ' WHERE id = %d AND (user_a = %d OR user_b = %d)', $match_id, $user_id, $user_id ) );
}

/**
 * Lista de matches do usuário.
 *
 * @param int $user_id Usuário.
 */
function spotinder_get_matches( $user_id ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . spotinder_table( 'matches' ) . ' WHERE user_a = %d OR user_b = %d ORDER BY created_at DESC', $user_id, $user_id ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$pending = $wpdb->get_results( $wpdb->prepare( "SELECT match_id, COUNT(*) AS total FROM " . spotinder_table( 'invites' ) . " WHERE to_id = %d AND status = 'pending' GROUP BY match_id", $user_id ), OBJECT_K );

	$out = array();
	foreach ( $rows as $row ) {
		$other = (int) ( (int) $row->user_a === $user_id ? $row->user_b : $row->user_a );
		$user  = get_userdata( $other );
		if ( ! $user ) {
			continue;
		}
		$seen  = (int) $row->user_a === $user_id ? $row->seen_a : $row->seen_b;
		$out[] = array(
			'id'        => (int) $row->id,
			'score'     => (int) $row->score,
			'createdAt' => spotinder_date( $row->created_at, 'd/m' ),
			'isNew'     => ! $seen,
			'invites'   => isset( $pending[ $row->id ] ) ? (int) $pending[ $row->id ]->total : 0,
			'partner'   => array(
				'id'       => $other,
				'name'     => $user->display_name,
				'initials' => spotinder_initials( $user->display_name ),
				'gradient' => spotinder_gradient( $user->user_login ),
				'photo'    => spotinder_photo_url( $other ),
				'artists'  => array_map(
					fn( $a ) => array(
						'name'  => $a['name'],
						'image' => $a['image'],
					),
					array_slice( spotinder_get_artists( $other ), 0, 3 )
				),
				'isDemo'   => (bool) get_user_meta( $other, 'spotinder_is_demo', true ),
			),
		);
	}
	return $out;
}

/**
 * Detalhe completo de um match (perfil + redes + trilha + playlist + convites + eventos).
 *
 * @param int $match_id Match.
 * @param int $user_id  Quem vê.
 * @return array|null
 */
function spotinder_match_detail( $match_id, $user_id ) {
	global $wpdb;

	$row = spotinder_get_match_row( $match_id, $user_id );
	if ( ! $row ) {
		return null;
	}

	$is_a  = (int) $row->user_a === $user_id;
	$other = (int) ( $is_a ? $row->user_b : $row->user_a );

	$wpdb->update( spotinder_table( 'matches' ), array( $is_a ? 'seen_a' : 'seen_b' => 1 ), array( 'id' => $match_id ) );

	return array(
		'id'         => (int) $row->id,
		'score'      => (int) $row->score,
		'createdAt'  => spotinder_date( $row->created_at, 'd/m/Y' ),
		'partner'    => spotinder_member_payload( $other, $user_id, 'match' ),
		'soundtrack' => json_decode( (string) $row->soundtrack, true ),
		'playlist'   => spotinder_common_playlist( $user_id, $other ),
		'invites'    => spotinder_get_invites( $match_id, $user_id ),
		'events'     => spotinder_events_for_pair( $user_id, $other ),
	);
}

/**
 * Desfaz o match (os swipes ficam, então os perfis não voltam ao deck).
 *
 * @param int $match_id Match.
 * @param int $user_id  Usuário.
 */
function spotinder_unmatch( $match_id, $user_id ) {
	global $wpdb;
	if ( ! spotinder_get_match_row( $match_id, $user_id ) ) {
		return false;
	}
	$wpdb->delete( spotinder_table( 'invites' ), array( 'match_id' => $match_id ) );
	return (bool) $wpdb->delete( spotinder_table( 'matches' ), array( 'id' => $match_id ) );
}

/**
 * Bloqueia alguém (desfaz match e convites; some dos dois decks).
 *
 * @param int $user_id   Quem bloqueia.
 * @param int $target_id Bloqueado.
 */
function spotinder_block( $user_id, $target_id ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . spotinder_table( 'blocks' ) . ' (user_id, target_id, created_at) VALUES (%d, %d, %s)', $user_id, $target_id, current_time( 'mysql' ) ) );

	$ua = min( $user_id, $target_id );
	$ub = max( $user_id, $target_id );
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$match_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . spotinder_table( 'matches' ) . ' WHERE user_a = %d AND user_b = %d', $ua, $ub ) );
	if ( $match_id ) {
		spotinder_unmatch( $match_id, $user_id );
	}
	return true;
}
