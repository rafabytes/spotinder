<?php
/**
 * Membros: perfil, artistas e músicas escolhidos, consentimentos, exclusão e exportação.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Limites da seleção de artistas e músicas do perfil.
 */
const SPOTINDER_MIN_ARTISTS = 3;
const SPOTINDER_MAX_ARTISTS = 15;
const SPOTINDER_MAX_TRACKS  = 10;

/**
 * Busca o usuário WP vinculado a uma conta Google.
 *
 * @param string $google_id ID da conta ("sub" do OpenID Connect).
 */
function spotinder_find_member_by_google( $google_id ) {
	$users = get_users(
		array(
			'meta_key'   => 'spotinder_google_id',
			'meta_value' => $google_id,
			'number'     => 1,
			'fields'     => 'ID',
		)
	);
	return $users ? (int) $users[0] : 0;
}

/**
 * Cria ou atualiza o membro a partir da conta Google.
 * Não guardamos e-mail: só o ID da conta, o nome e o endereço da foto pública.
 *
 * @param array $account Resultado de spotinder_google_exchange_code().
 * @return int|WP_Error ID do usuário.
 */
function spotinder_upsert_member( $account ) {
	$google_id = (string) ( $account['sub'] ?? '' );
	if ( ! $google_id ) {
		return new WP_Error( 'google_profile', 'Conta Google inválida.' );
	}

	$user_id = spotinder_find_member_by_google( $google_id );

	if ( ! $user_id ) {
		// Só o primeiro nome por padrão; a pessoa pode trocar no cadastro.
		$name    = $account['given_name'] ? $account['given_name'] : $account['name'];
		$login   = 'g_' . substr( preg_replace( '/[^0-9]/', '', $google_id ), 0, 40 );
		$login   = username_exists( $login ) ? $login . '_' . wp_rand( 100, 999 ) : $login;
		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'display_name' => $name ? $name : __( 'Ouvinte', 'spotinder' ),
				'nickname'     => $name ? $name : $login,
				'role'         => 'subscriber',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		update_user_meta( $user_id, 'spotinder_google_id', $google_id );
		update_user_meta( $user_id, 'spotinder_visible', 1 );
		update_user_meta( $user_id, 'spotinder_share_events', 1 );
	}

	if ( get_user_meta( $user_id, 'spotinder_banned', true ) ) {
		return new WP_Error( 'spotinder_banned', 'Esta conta foi suspensa por violar os Termos de Uso.' );
	}

	// Endereço da foto pública da conta Google: só é exibida se a pessoa escolher usá-la.
	update_user_meta( $user_id, 'spotinder_google_photo', $account['picture'] );
	spotinder_touch( $user_id, true );

	return (int) $user_id;
}

/**
 * Salva artistas e músicas favoritos (substitui os anteriores, na ordem escolhida).
 *
 * @param int   $user_id Usuário.
 * @param array $artists Artistas normalizados.
 * @param array $tracks  Faixas normalizadas.
 */
function spotinder_save_music( $user_id, $artists, $tracks ) {
	update_user_meta( $user_id, 'spotinder_top_artists', array_values( $artists ) );
	update_user_meta( $user_id, 'spotinder_top_tracks', array_values( $tracks ) );
	update_user_meta( $user_id, 'spotinder_synced_at', current_time( 'mysql' ) );
}

/**
 * Confere a seleção enviada pelo navegador. Só IDs são aceitos de lá: nome, imagem
 * e link vêm do que já estava salvo ou do catálogo do Spotify. Artistas fora do
 * catálogo (banda local, por exemplo) entram só com o nome.
 *
 * @param int   $user_id Usuário.
 * @param array $artists Itens {id} ou {name}.
 * @param array $tracks  Itens {id}.
 * @return array{artists:array,tracks:array}|WP_Error
 */
function spotinder_resolve_music( $user_id, $artists, $tracks ) {
	$saved_artists = array();
	foreach ( spotinder_get_artists( $user_id ) as $a ) {
		$saved_artists[ $a['id'] ? $a['id'] : spotinder_akey( $a['name'] ) ] = $a;
	}
	$saved_tracks = array_column( spotinder_get_tracks( $user_id ), null, 'id' );

	$out_artists = array();
	foreach ( array_slice( (array) $artists, 0, SPOTINDER_MAX_ARTISTS ) as $item ) {
		$id   = sanitize_text_field( (string) ( $item['id'] ?? '' ) );
		$name = sanitize_text_field( mb_substr( (string) ( $item['name'] ?? '' ), 0, 80 ) );

		if ( $id ) {
			$artist = $saved_artists[ $id ] ?? spotinder_spotify_catalog_item( 'artist', $id );
			if ( is_wp_error( $artist ) ) {
				return new WP_Error( 'spotinder_music', 'Não conseguimos confirmar um dos artistas no catálogo. Tente de novo.', array( 'status' => 400 ) );
			}
		} elseif ( $name ) {
			$artist = $saved_artists[ spotinder_akey( $name ) ] ?? array(
				'id'     => '',
				'name'   => $name,
				'image'  => '',
				'url'    => '',
				'genres' => array(),
			);
		} else {
			continue;
		}

		$out_artists += array( spotinder_akey( $artist['name'] ) => $artist );
	}

	$out_tracks = array();
	foreach ( array_slice( (array) $tracks, 0, SPOTINDER_MAX_TRACKS ) as $item ) {
		$id    = sanitize_text_field( (string) ( $item['id'] ?? '' ) );
		$track = $saved_tracks[ $id ] ?? spotinder_spotify_catalog_item( 'track', $id );
		if ( is_wp_error( $track ) ) {
			return new WP_Error( 'spotinder_music', 'Não conseguimos confirmar uma das músicas no catálogo. Tente de novo.', array( 'status' => 400 ) );
		}
		$out_tracks += array( $track['id'] => $track );
	}

	if ( count( $out_artists ) < SPOTINDER_MIN_ARTISTS ) {
		/* translators: %d: mínimo de artistas. */
		return new WP_Error( 'spotinder_music', sprintf( 'Escolha pelo menos %d artistas.', SPOTINDER_MIN_ARTISTS ), array( 'status' => 400 ) );
	}

	return array(
		'artists' => array_values( $out_artists ),
		'tracks'  => array_values( $out_tracks ),
	);
}

/**
 * Registra atividade (no máximo uma escrita por hora).
 *
 * @param int  $user_id Usuário.
 * @param bool $force   Ignora o intervalo.
 */
function spotinder_touch( $user_id, $force = false ) {
	$last = (int) get_user_meta( $user_id, 'spotinder_last_active', true );
	if ( $force || time() - $last > HOUR_IN_SECONDS ) {
		update_user_meta( $user_id, 'spotinder_last_active', time() );
	}
}

/**
 * Artistas/faixas salvos.
 *
 * @param int $user_id Usuário.
 */
function spotinder_get_artists( $user_id ) {
	$a = get_user_meta( $user_id, 'spotinder_top_artists', true );
	return is_array( $a ) ? $a : array();
}

/**
 * Faixas salvas.
 *
 * @param int $user_id Usuário.
 */
function spotinder_get_tracks( $user_id ) {
	$t = get_user_meta( $user_id, 'spotinder_top_tracks', true );
	return is_array( $t ) ? $t : array();
}

/**
 * Gêneros mais frequentes entre os artistas do usuário.
 *
 * @param int $user_id Usuário.
 * @param int $limit   Quantidade.
 */
function spotinder_top_genres( $user_id, $limit = 5 ) {
	$count = array();
	foreach ( spotinder_get_artists( $user_id ) as $i => $artist ) {
		foreach ( (array) ( $artist['genres'] ?? array() ) as $g ) {
			$count[ $g ] = ( $count[ $g ] ?? 0 ) + ( 1 / ( 1 + $i * 0.1 ) );
		}
	}
	arsort( $count );
	return array_slice( array_keys( $count ), 0, $limit );
}

/**
 * Consentimentos válidos para a versão atual da política?
 *
 * @param int $user_id Usuário.
 */
function spotinder_has_consents( $user_id ) {
	$c = get_user_meta( $user_id, 'spotinder_consents', true );
	return is_array( $c )
		&& ! empty( $c['age'] ) && ! empty( $c['terms'] ) && ! empty( $c['share'] )
		&& ( $c['version'] ?? '' ) === (string) spotinder_opt( 'policy_version' );
}

/**
 * Perfil está apto a aparecer no deck?
 *
 * @param int $user_id Usuário.
 */
function spotinder_is_discoverable( $user_id ) {
	return get_user_meta( $user_id, 'spotinder_onboarded', true )
		&& get_user_meta( $user_id, 'spotinder_visible', true )
		&& ! get_user_meta( $user_id, 'spotinder_banned', true )
		// Contas antigas (login pelo Spotify) não aparecem: ninguém consegue mais entrar nelas.
		&& ( get_user_meta( $user_id, 'spotinder_google_id', true ) || get_user_meta( $user_id, 'spotinder_is_demo', true ) );
}

/**
 * Redes sociais com URL montada.
 *
 * @param int $user_id Usuário.
 */
function spotinder_get_socials( $user_id ) {
	$saved    = (array) get_user_meta( $user_id, 'spotinder_socials', true );
	$networks = spotinder_social_networks();
	$out      = array();

	foreach ( $networks as $key => $net ) {
		$handle = trim( (string) ( $saved[ $key ] ?? '' ) );
		if ( '' === $handle ) {
			continue;
		}
		if ( 'website' === $key ) {
			$url = esc_url( $handle );
		} elseif ( $net['url'] ) {
			$url = esc_url( sprintf( $net['url'], rawurlencode( ltrim( $handle, '@' ) ) ) );
		} else {
			$url = '';
		}
		$out[] = array(
			'network' => $key,
			'label'   => $net['label'],
			'handle'  => 'website' === $key ? preg_replace( '#^https?://#', '', $handle ) : '@' . ltrim( $handle, '@' ),
			'url'     => $url,
		);
	}
	return $out;
}

/**
 * Limpa um handle de rede social.
 *
 * @param string $network Rede.
 * @param string $value   Valor digitado.
 */
function spotinder_sanitize_social( $network, $value ) {
	$value = trim( wp_strip_all_tags( (string) $value ) );
	if ( '' === $value ) {
		return '';
	}
	if ( 'website' === $network ) {
		$url = esc_url_raw( $value, array( 'http', 'https' ) );
		return $url ? $url : '';
	}
	// Aceita URL colada: fica só com o usuário.
	$value = preg_replace( '#^https?://[^/]+/@?#i', '', $value );
	$value = ltrim( $value, '@' );
	return substr( preg_replace( '/[^A-Za-z0-9._\-]/', '', $value ), 0, 60 );
}

/**
 * Formato público de um membro.
 *
 * @param int    $user_id   Membro exibido.
 * @param int    $viewer_id Quem vê.
 * @param string $context   card|match|self.
 */
function spotinder_member_payload( $user_id, $viewer_id = 0, $context = 'card' ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return null;
	}

	$limit   = 'self' === $context ? 50 : (int) spotinder_opt( 'top_artists_shown' );
	$artists = spotinder_get_artists( $user_id );
	$compat  = $viewer_id && $viewer_id !== $user_id ? spotinder_compat( $viewer_id, $user_id ) : null;
	$shared  = $compat ? array_flip( $compat['shared_artists'] ) : array();

	// Artistas em comum primeiro: é o que mais interessa no cartão.
	if ( $shared ) {
		usort(
			$artists,
			function ( $a, $b ) use ( $shared ) {
				return (int) isset( $shared[ spotinder_akey( $b['name'] ) ] ) <=> (int) isset( $shared[ spotinder_akey( $a['name'] ) ] );
			}
		);
	}

	$payload = array(
		'id'         => (int) $user_id,
		'name'       => $user->display_name,
		'initials'   => spotinder_initials( $user->display_name ),
		'gradient'   => spotinder_gradient( $user->user_login ),
		'photo'      => spotinder_photo_url( $user_id ),
		'city'       => (string) get_user_meta( $user_id, 'spotinder_city', true ),
		'bio'        => (string) get_user_meta( $user_id, 'spotinder_bio', true ),
		'intentions' => array_values( array_filter( (array) get_user_meta( $user_id, 'spotinder_intentions', true ) ) ),
		'genres'     => spotinder_top_genres( $user_id ),
		'artists'    => array_map(
			function ( $a ) use ( $shared ) {
				return array(
					'id'     => $a['id'],
					'name'   => $a['name'],
					'image'  => $a['image'],
					'url'    => $a['url'] ? $a['url'] : 'https://open.spotify.com/search/' . rawurlencode( $a['name'] ),
					'shared' => isset( $shared[ spotinder_akey( $a['name'] ) ] ),
				);
			},
			array_slice( $artists, 0, $limit )
		),
		'isDemo'     => (bool) get_user_meta( $user_id, 'spotinder_is_demo', true ),
	);

	// Região aproximada (nome do município) e distância em faixas — nunca coordenadas.
	$region            = spotinder_get_region( $user_id );
	$payload['region'] = $region ? $region['label'] : '';
	if ( $viewer_id && $viewer_id !== $user_id ) {
		$payload['distance'] = spotinder_distance_label( spotinder_region_distance( $viewer_id, $user_id ) );
	}

	if ( $compat ) {
		$payload['compat'] = array(
			'score'         => $compat['score'],
			'sharedArtists' => count( $compat['shared_artists'] ),
			'sharedTracks'  => count( $compat['shared_tracks'] ),
			'sharedGenres'  => array_slice( $compat['shared_genres'], 0, 4 ),
		);
	}

	if ( 'match' === $context || 'self' === $context ) {
		$payload['socials'] = spotinder_get_socials( $user_id );
	}

	if ( 'self' === $context ) {
		$payload['tracks']      = spotinder_get_tracks( $user_id );
		$payload['socialsRaw']  = (object) array_filter( (array) get_user_meta( $user_id, 'spotinder_socials', true ) );
		$payload['visible']     = (bool) get_user_meta( $user_id, 'spotinder_visible', true );
		$payload['shareEvents'] = (bool) get_user_meta( $user_id, 'spotinder_share_events', true );
		$payload['onboarded']   = (bool) get_user_meta( $user_id, 'spotinder_onboarded', true );
		$payload['consents']    = spotinder_has_consents( $user_id ) ? get_user_meta( $user_id, 'spotinder_consents', true ) : null;
		$payload['syncedAt']    = spotinder_date( get_user_meta( $user_id, 'spotinder_synced_at', true ), 'd/m/Y H:i' );
		$payload['photoSource'] = get_user_meta( $user_id, 'spotinder_photo_source', true ) ?: 'none';
		$payload['location']    = array(
			'enabled' => (bool) $region,
			'label'   => $region ? $region['label'] : '',
			'updated' => $region ? wp_date( 'd/m/Y H:i', (int) $region['updated'] ) : '',
		);
		$payload['maxDistance'] = spotinder_max_distance( $user_id );
		$payload['photoChoice'] = array(
			'google'  => (string) get_user_meta( $user_id, 'spotinder_google_photo', true ),
			'upload'  => spotinder_uploaded_photo_url( $user_id ),
		);
	}

	return $payload;
}

/**
 * Atualiza o próprio perfil.
 *
 * @param int   $user_id Usuário.
 * @param array $data    Dados enviados.
 * @return true|WP_Error
 */
function spotinder_update_profile( $user_id, $data ) {
	// Redes sociais só são gravadas com o aviso de responsabilidade aceito.
	$clean_socials = null;
	if ( isset( $data['socials'] ) && is_array( $data['socials'] ) ) {
		$clean_socials = array();
		foreach ( array_keys( spotinder_social_networks() ) as $key ) {
			$value = spotinder_sanitize_social( $key, $data['socials'][ $key ] ?? '' );
			if ( $value ) {
				$clean_socials[ $key ] = $value;
			}
		}
		$current      = get_user_meta( $user_id, 'spotinder_consents', true );
		$social_agree = ! empty( $data['consents']['social'] ) || ! empty( $data['socialConsent'] ) || ( ! isset( $data['socialConsent'] ) && ! empty( $current['social'] ) );
		if ( $clean_socials && ! $social_agree ) {
			return new WP_Error( 'spotinder_consent', 'Para exibir redes sociais aos seus matches, confirme o aviso de responsabilidade.', array( 'status' => 400 ) );
		}
	}

	if ( isset( $data['artists'] ) || isset( $data['tracks'] ) ) {
		$music = spotinder_resolve_music( $user_id, (array) ( $data['artists'] ?? array() ), (array) ( $data['tracks'] ?? array() ) );
		if ( is_wp_error( $music ) ) {
			return $music;
		}
		spotinder_save_music( $user_id, $music['artists'], $music['tracks'] );
	}

	if ( isset( $data['name'] ) ) {
		$name = sanitize_text_field( mb_substr( (string) $data['name'], 0, 40 ) );
		if ( $name ) {
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $name,
				)
			);
		}
	}

	if ( isset( $data['bio'] ) ) {
		update_user_meta( $user_id, 'spotinder_bio', sanitize_textarea_field( mb_substr( (string) $data['bio'], 0, 240 ) ) );
	}

	if ( isset( $data['city'] ) ) {
		update_user_meta( $user_id, 'spotinder_city', sanitize_text_field( mb_substr( (string) $data['city'], 0, 60 ) ) );
	}

	if ( isset( $data['intentions'] ) ) {
		$valid = wp_list_pluck( (array) spotinder_opt( 'intentions' ), 'slug' );
		update_user_meta( $user_id, 'spotinder_intentions', array_values( array_intersect( (array) $data['intentions'], $valid ) ) );
	}

	if ( null !== $clean_socials ) {
		update_user_meta( $user_id, 'spotinder_socials', $clean_socials );
	}

	// Aceite isolado do aviso de redes (aba "Redes sociais"), sem mexer nos demais consentimentos.
	if ( isset( $data['socialConsent'] ) ) {
		$c = get_user_meta( $user_id, 'spotinder_consents', true );
		if ( is_array( $c ) ) {
			$c['social'] = $data['socialConsent'] && $clean_socials ? current_time( 'mysql' ) : '';
			update_user_meta( $user_id, 'spotinder_consents', $c );
		}
	}

	if ( isset( $data['visible'] ) ) {
		update_user_meta( $user_id, 'spotinder_visible', $data['visible'] ? 1 : 0 );
	}

	if ( isset( $data['maxDistance'] ) ) {
		$max = (int) $data['maxDistance'];
		update_user_meta( $user_id, 'spotinder_max_distance', in_array( $max, SPOTINDER_DISTANCES, true ) ? $max : 0 );
	}

	if ( isset( $data['shareEvents'] ) ) {
		update_user_meta( $user_id, 'spotinder_share_events', $data['shareEvents'] ? 1 : 0 );
	}

	if ( isset( $data['consents'] ) && is_array( $data['consents'] ) ) {
		$c = $data['consents'];
		if ( empty( $c['age'] ) || empty( $c['terms'] ) || empty( $c['share'] ) ) {
			return new WP_Error( 'spotinder_consent', 'Os consentimentos de idade, termos e exibição dos artistas são obrigatórios.', array( 'status' => 400 ) );
		}
		if ( count( spotinder_get_artists( $user_id ) ) < SPOTINDER_MIN_ARTISTS ) {
			/* translators: %d: mínimo de artistas. */
			return new WP_Error( 'spotinder_music', sprintf( 'Escolha pelo menos %d artistas antes de concluir.', SPOTINDER_MIN_ARTISTS ), array( 'status' => 400 ) );
		}
		$now = current_time( 'mysql' );
		$previous = get_user_meta( $user_id, 'spotinder_consents', true );
		update_user_meta(
			$user_id,
			'spotinder_consents',
			// Aceites registrados à parte (foto, localização) são preservados.
			array_merge(
				is_array( $previous ) ? $previous : array(),
				array(
					'age'     => $now,
					'terms'   => $now,
					'share'   => $now,
					'social'  => empty( $c['social'] ) ? '' : $now,
					'version' => (string) spotinder_opt( 'policy_version' ),
				)
			)
		);
		if ( ! get_user_meta( $user_id, 'spotinder_onboarded', true ) ) {
			update_user_meta( $user_id, 'spotinder_onboarded', $now );
		}
	}

	return true;
}

/**
 * Remove tudo de um membro: linhas próprias + usuário WP (com metadados).
 *
 * @param int $user_id Usuário.
 */
function spotinder_delete_member( $user_id ) {
	global $wpdb;

	$user_id = (int) $user_id;
	if ( ! $user_id || user_can( $user_id, 'edit_posts' ) ) {
		return false;
	}

	spotinder_purge_member_rows( $user_id );

	require_once ABSPATH . 'wp-admin/includes/user.php';
	return wp_delete_user( $user_id );
}

/**
 * Apaga as linhas das tabelas próprias ligadas ao usuário.
 *
 * @param int $user_id Usuário.
 */
function spotinder_purge_member_rows( $user_id ) {
	global $wpdb;

	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . spotinder_table( 'swipes' ) . ' WHERE user_id = %d OR target_id = %d', $user_id, $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . spotinder_table( 'matches' ) . ' WHERE user_a = %d OR user_b = %d', $user_id, $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . spotinder_table( 'blocks' ) . ' WHERE user_id = %d OR target_id = %d', $user_id, $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . spotinder_table( 'rsvps' ) . ' WHERE user_id = %d', $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . spotinder_table( 'invites' ) . ' WHERE from_id = %d OR to_id = %d', $user_id, $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	// Denúncias feitas PELA pessoa são anonimizadas; denúncias CONTRA ela são mantidas
	// para moderação (legítimo interesse/segurança), como descrito na política.
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . spotinder_table( 'reports' ) . ' SET reporter_id = 0 WHERE reporter_id = %d', $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

add_action( 'delete_user', 'spotinder_purge_member_rows' );

/**
 * Exporta todos os dados do membro (portabilidade — LGPD art. 18, V).
 *
 * @param int $user_id Usuário.
 */
function spotinder_export_member( $user_id ) {
	global $wpdb;
	$user = get_userdata( $user_id );

	return array(
		'exported_at' => current_time( 'c' ),
		'account'     => array(
			'display_name'   => $user->display_name,
			'registered'     => $user->user_registered,
			'google_id'      => get_user_meta( $user_id, 'spotinder_google_id', true ),
			'city'           => get_user_meta( $user_id, 'spotinder_city', true ),
			'bio'            => get_user_meta( $user_id, 'spotinder_bio', true ),
			'intentions'     => get_user_meta( $user_id, 'spotinder_intentions', true ),
			'socials'        => get_user_meta( $user_id, 'spotinder_socials', true ),
			'visible'        => (bool) get_user_meta( $user_id, 'spotinder_visible', true ),
			'consents'       => get_user_meta( $user_id, 'spotinder_consents', true ),
			'last_active'    => gmdate( 'c', (int) get_user_meta( $user_id, 'spotinder_last_active', true ) ),
			'music_updated'  => get_user_meta( $user_id, 'spotinder_synced_at', true ),
			'region'         => spotinder_get_region( $user_id ),
			'max_distance'   => spotinder_max_distance( $user_id ),
			'photo'          => array(
				'source'      => get_user_meta( $user_id, 'spotinder_photo_source', true ) ?: 'none',
				'google_url'  => get_user_meta( $user_id, 'spotinder_google_photo', true ),
				'upload_url'  => spotinder_uploaded_photo_url( $user_id ),
			),
		),
		'music'       => array(
			'artists' => spotinder_get_artists( $user_id ),
			'tracks'  => spotinder_get_tracks( $user_id ),
		),
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		'swipes'      => $wpdb->get_results( $wpdb->prepare( 'SELECT target_id, action, created_at FROM ' . spotinder_table( 'swipes' ) . ' WHERE user_id = %d', $user_id ), ARRAY_A ),
		'matches'     => $wpdb->get_results( $wpdb->prepare( 'SELECT id, user_a, user_b, score, created_at FROM ' . spotinder_table( 'matches' ) . ' WHERE user_a = %d OR user_b = %d', $user_id, $user_id ), ARRAY_A ),
		'blocks'      => $wpdb->get_results( $wpdb->prepare( 'SELECT target_id, created_at FROM ' . spotinder_table( 'blocks' ) . ' WHERE user_id = %d', $user_id ), ARRAY_A ),
		'reports'     => $wpdb->get_results( $wpdb->prepare( 'SELECT target_id, reason, details, status, created_at FROM ' . spotinder_table( 'reports' ) . ' WHERE reporter_id = %d', $user_id ), ARRAY_A ),
		'rsvps'       => $wpdb->get_results( $wpdb->prepare( 'SELECT event_id, status, created_at FROM ' . spotinder_table( 'rsvps' ) . ' WHERE user_id = %d', $user_id ), ARRAY_A ),
		'invites'     => $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . spotinder_table( 'invites' ) . ' WHERE from_id = %d OR to_id = %d', $user_id, $user_id ), ARRAY_A ),
		// phpcs:enable
	);
}
