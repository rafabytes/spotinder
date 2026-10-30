<?php
/**
 * Localização aproximada (opcional, como no Tinder — mas sem guardar a posição exata).
 *
 * Como funciona:
 * 1. O navegador obtém a posição (com permissão) e a ARREDONDA no próprio aparelho para
 *    o centro de uma célula de grade de ~5 km (SPOTINDER_GRID_DEG). A coordenada exata
 *    nunca é enviada.
 * 2. O servidor arredonda de novo (defesa em profundidade) e guarda só: centro da célula,
 *    nome do município mais próximo e a data da atualização. Sem histórico: cada
 *    atualização sobrescreve a anterior.
 * 3. O nome da região vem de uma tabela local de municípios do IBGE (data/municipios.json):
 *    nenhum serviço externo de geocodificação recebe a localização.
 * 4. Outros membros veem só o nome da região e a distância em faixas ("~25 km"), o que,
 *    somado à grade, impede descobrir a posição exata por triangulação.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

/** Tamanho da célula da grade em graus (~5,5 km de latitude). Deve ser igual ao do front-end (src/js/location.js). */
const SPOTINDER_GRID_DEG = 0.05;

/** Opções de distância máxima no deck (km; 0 = qualquer). */
const SPOTINDER_DISTANCES = array( 0, 10, 25, 50, 100, 250 );

/**
 * Arredonda uma coordenada para o centro da célula da grade.
 *
 * @param float $lat Latitude.
 * @param float $lng Longitude.
 * @return array{0:float,1:float}
 */
function spotinder_snap_coord( $lat, $lng ) {
	$snap = fn( $v ) => round( floor( $v / SPOTINDER_GRID_DEG ) * SPOTINDER_GRID_DEG + SPOTINDER_GRID_DEG / 2, 4 );
	return array( $snap( (float) $lat ), $snap( (float) $lng ) );
}

/**
 * Distância em km entre dois pontos (haversine).
 *
 * @param float $lat1 Latitude 1.
 * @param float $lng1 Longitude 1.
 * @param float $lat2 Latitude 2.
 * @param float $lng2 Longitude 2.
 */
function spotinder_haversine( $lat1, $lng1, $lat2, $lng2 ) {
	$r    = 6371;
	$dlat = deg2rad( $lat2 - $lat1 );
	$dlng = deg2rad( $lng2 - $lng1 );
	$a    = sin( $dlat / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dlng / 2 ) ** 2;
	return 2 * $r * asin( min( 1, sqrt( $a ) ) );
}

/**
 * Tabela de municípios: [nome, UF, lat, lng].
 *
 * @return array[]
 */
function spotinder_municipalities() {
	static $list = null;
	if ( null === $list ) {
		$file = SPOTINDER_DIR . '/data/municipios.json';
		$list = file_exists( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
	return $list;
}

/**
 * Município mais próximo de um ponto.
 *
 * @param float $lat Latitude.
 * @param float $lng Longitude.
 * @return array{name:string,uf:string,km:float}|null
 */
function spotinder_nearest_municipality( $lat, $lng ) {
	$best = null;
	$min  = INF;
	$cos  = cos( deg2rad( $lat ) );
	foreach ( spotinder_municipalities() as $m ) {
		// Aproximação equiretangular: suficiente para escolher o mais próximo.
		$d = ( $m[2] - $lat ) ** 2 + ( ( $m[3] - $lng ) * $cos ) ** 2;
		if ( $d < $min ) {
			$min  = $d;
			$best = $m;
		}
	}
	return $best ? array(
		'name' => $best[0],
		'uf'   => $best[1],
		'km'   => spotinder_haversine( $lat, $lng, $best[2], $best[3] ),
	) : null;
}

/**
 * Município pelo nome (usado para os perfis de teste).
 *
 * @param string $name Nome.
 * @return array|null [nome, UF, lat, lng].
 */
function spotinder_find_municipality( $name ) {
	$key = spotinder_akey( $name );
	foreach ( spotinder_municipalities() as $m ) {
		if ( spotinder_akey( $m[0] ) === $key ) {
			return $m;
		}
	}
	return null;
}

/**
 * Salva a região aproximada do membro.
 *
 * @param int   $user_id Usuário.
 * @param mixed $lat     Latitude (já arredondada pelo navegador; arredondamos de novo).
 * @param mixed $lng     Longitude.
 * @return true|WP_Error
 */
function spotinder_set_location( $user_id, $lat, $lng ) {
	if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) || abs( (float) $lat ) > 90 || abs( (float) $lng ) > 180 ) {
		return new WP_Error( 'spotinder_location', 'Localização inválida.', array( 'status' => 400 ) );
	}

	list( $cell_lat, $cell_lng ) = spotinder_snap_coord( $lat, $lng );

	$place = spotinder_nearest_municipality( $cell_lat, $cell_lng );
	$label = $place && $place['km'] <= 60
		? sprintf( '%s, %s', $place['name'], $place['uf'] )
		: __( 'Região sem município próximo', 'spotinder' );

	// Sobrescreve: não existe histórico de regiões.
	update_user_meta(
		$user_id,
		'spotinder_region',
		array(
			'lat'     => $cell_lat,
			'lng'     => $cell_lng,
			'label'   => $label,
			'updated' => time(),
		)
	);

	// Aceite registrado na primeira ativação (as atualizações automáticas não o renovam).
	$consents = get_user_meta( $user_id, 'spotinder_consents', true );
	$consents = is_array( $consents ) ? $consents : array();
	if ( empty( $consents['location'] ) ) {
		$consents['location'] = current_time( 'mysql' );
		update_user_meta( $user_id, 'spotinder_consents', $consents );
	}

	return true;
}

/**
 * Desativa a localização: apaga a região e o aceite.
 *
 * @param int $user_id Usuário.
 */
function spotinder_clear_location( $user_id ) {
	delete_user_meta( $user_id, 'spotinder_region' );
	delete_user_meta( $user_id, 'spotinder_max_distance' );
	$consents = get_user_meta( $user_id, 'spotinder_consents', true );
	if ( is_array( $consents ) ) {
		$consents['location'] = '';
		update_user_meta( $user_id, 'spotinder_consents', $consents );
	}
}

/**
 * Região do membro.
 *
 * @param int $user_id Usuário.
 * @return array{lat:float,lng:float,label:string,updated:int}|null
 */
function spotinder_get_region( $user_id ) {
	$region = get_user_meta( $user_id, 'spotinder_region', true );
	return is_array( $region ) && isset( $region['lat'], $region['lng'] ) ? $region : null;
}

/**
 * Distância em km entre as regiões de dois membros (null se algum não tiver região).
 *
 * @param int $a Usuário.
 * @param int $b Usuário.
 */
function spotinder_region_distance( $a, $b ) {
	$ra = spotinder_get_region( $a );
	$rb = spotinder_get_region( $b );
	if ( ! $ra || ! $rb ) {
		return null;
	}
	return spotinder_haversine( $ra['lat'], $ra['lng'], $rb['lat'], $rb['lng'] );
}

/**
 * Distância exibida em faixas (nunca o valor calculado).
 *
 * @param float|null $km Distância.
 */
function spotinder_distance_label( $km ) {
	if ( null === $km ) {
		return '';
	}
	if ( $km <= 5 ) {
		return __( 'a menos de 5 km', 'spotinder' );
	}
	foreach ( array( 10, 25, 50, 100, 250, 500 ) as $bucket ) {
		if ( $km <= $bucket ) {
			/* translators: %d: distância em km. */
			return sprintf( __( 'até %d km', 'spotinder' ), $bucket );
		}
	}
	return __( 'mais de 500 km', 'spotinder' );
}

/**
 * Distância máxima escolhida para o deck (0 = qualquer).
 *
 * @param int $user_id Usuário.
 */
function spotinder_max_distance( $user_id ) {
	$value = (int) get_user_meta( $user_id, 'spotinder_max_distance', true );
	return in_array( $value, SPOTINDER_DISTANCES, true ) ? $value : 0;
}

/**
 * Bônus de proximidade na ordenação do deck (o gosto musical continua sendo o principal).
 *
 * @param float|null $km Distância.
 */
function spotinder_distance_bonus( $km ) {
	if ( null === $km ) {
		return 0;
	}
	if ( $km <= 10 ) {
		return 6;
	}
	if ( $km <= 25 ) {
		return 4;
	}
	return $km <= 50 ? 2 : 0;
}
