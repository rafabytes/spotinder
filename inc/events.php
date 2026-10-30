<?php
/**
 * Eventos (cadastrados pelo admin), presenças e convites entre matches.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dados formatados de um evento.
 *
 * @param int|WP_Post $post Evento.
 */
function spotinder_event_data( $post ) {
	$post  = get_post( $post );
	$start = (string) get_post_meta( $post->ID, 'event_start', true );
	$end   = (string) get_post_meta( $post->ID, 'event_end', true );

	return array(
		'id'        => $post->ID,
		'title'     => get_the_title( $post ),
		'url'       => get_permalink( $post ),
		'excerpt'   => wp_strip_all_tags( get_the_excerpt( $post ) ),
		'image'     => get_the_post_thumbnail_url( $post, 'spotinder-card' ),
		'start'     => $start,
		'end'       => $end,
		'day'       => spotinder_date( $start, 'd' ),
		'month'     => spotinder_date( $start, 'M' ),
		'weekday'   => spotinder_date( $start, 'D' ),
		'time'      => spotinder_date( $start, 'H\hi' ),
		'dateLabel' => spotinder_date( $start, 'D, d \d\e F · H\hi' ),
		'venue'     => (string) get_post_meta( $post->ID, 'event_venue', true ),
		'city'      => (string) get_post_meta( $post->ID, 'event_city', true ),
		'address'   => (string) get_post_meta( $post->ID, 'event_address', true ),
		'price'     => (string) get_post_meta( $post->ID, 'event_price', true ),
		'ticketUrl' => (string) get_post_meta( $post->ID, 'event_ticket_url', true ),
		'official'  => (bool) get_post_meta( $post->ID, 'event_official', true ),
		'artists'   => spotinder_event_artists( $post->ID ),
		'types'     => wp_list_pluck( (array) get_the_terms( $post, 'spot_event_type' ) ?: array(), 'name' ),
		'gradient'  => spotinder_gradient( 'event' . $post->ID ),
	);
}

/**
 * Artistas relacionados ao evento.
 *
 * @param int $post_id Evento.
 * @return string[]
 */
function spotinder_event_artists( $post_id ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n|,/', (string) get_post_meta( $post_id, 'event_artists', true ) ) ) ) );
}

/**
 * Próximos eventos.
 *
 * @param int $limit Quantidade.
 * @return WP_Post[]
 */
function spotinder_upcoming_events( $limit = 6 ) {
	return get_posts(
		array(
			'post_type'      => 'spot_event',
			'posts_per_page' => $limit,
			'meta_key'       => 'event_start',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'event_start',
					'value'   => current_time( 'mysql' ),
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
			),
		)
	);
}

/**
 * Quanto um evento combina com os artistas de um usuário (0 = nada).
 *
 * @param int $event_id Evento.
 * @param int $user_id  Usuário.
 * @return string[] Artistas do usuário presentes no evento.
 */
function spotinder_event_matches_user( $event_id, $user_id ) {
	$event = array_map( 'mb_strtolower', spotinder_event_artists( $event_id ) );
	$hits  = array();
	foreach ( spotinder_get_artists( $user_id ) as $artist ) {
		if ( in_array( mb_strtolower( $artist['name'] ), $event, true ) ) {
			$hits[] = $artist['name'];
		}
	}
	return $hits;
}

/**
 * Contagem de presenças.
 *
 * @param int $event_id Evento.
 * @return array{going:int,interested:int}
 */
function spotinder_event_rsvp_counts( $event_id ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS total FROM ' . spotinder_table( 'rsvps' ) . ' WHERE event_id = %d GROUP BY status', $event_id ), OBJECT_K );
	return array(
		'going'      => isset( $rows['going'] ) ? (int) $rows['going']->total : 0,
		'interested' => isset( $rows['interested'] ) ? (int) $rows['interested']->total : 0,
	);
}

/**
 * Presença do usuário num evento.
 *
 * @param int $event_id Evento.
 * @param int $user_id  Usuário.
 */
function spotinder_get_rsvp( $event_id, $user_id ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . spotinder_table( 'rsvps' ) . ' WHERE event_id = %d AND user_id = %d', $event_id, $user_id ) );
}

/**
 * Define presença (going|interested|'' para remover).
 *
 * @param int    $event_id Evento.
 * @param int    $user_id  Usuário.
 * @param string $status   Status.
 */
function spotinder_set_rsvp( $event_id, $user_id, $status ) {
	global $wpdb;
	$table = spotinder_table( 'rsvps' );

	if ( ! in_array( $status, array( 'going', 'interested' ), true ) ) {
		$wpdb->delete(
			$table,
			array(
				'event_id' => $event_id,
				'user_id'  => $user_id,
			)
		);
		return '';
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( $wpdb->prepare( "INSERT INTO $table (event_id, user_id, status, created_at) VALUES (%d, %d, %s, %s) ON DUPLICATE KEY UPDATE status = VALUES(status)", $event_id, $user_id, $status, current_time( 'mysql' ) ) );
	return $status;
}

/**
 * Matches do usuário que marcaram presença no evento
 * (respeitando quem escolheu não compartilhar eventos).
 *
 * @param int $event_id Evento.
 * @param int $user_id  Usuário.
 */
function spotinder_event_matches_going( $event_id, $user_id ) {
	global $wpdb;
	$matches = $wpdb->get_results(
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->prepare( 'SELECT id, user_a, user_b FROM ' . spotinder_table( 'matches' ) . ' WHERE user_a = %d OR user_b = %d', $user_id, $user_id )
	);

	$out = array();
	foreach ( $matches as $m ) {
		$other  = (int) ( (int) $m->user_a === $user_id ? $m->user_b : $m->user_a );
		$status = spotinder_get_rsvp( $event_id, $other );
		if ( $status && get_user_meta( $other, 'spotinder_share_events', true ) ) {
			$user  = get_userdata( $other );
			$out[] = array(
				'matchId'  => (int) $m->id,
				'name'     => $user->display_name,
				'initials' => spotinder_initials( $user->display_name ),
				'gradient' => spotinder_gradient( $user->user_login ),
				'photo'    => spotinder_photo_url( $other ),
				'status'   => $status,
			);
		}
	}
	return $out;
}

/**
 * Eventos sugeridos para um par: onde os dois vão + onde tocam artistas de ambos.
 *
 * @param int $a Usuário.
 * @param int $b Usuário.
 */
function spotinder_events_for_pair( $a, $b ) {
	$out = array();
	foreach ( spotinder_upcoming_events( 12 ) as $event ) {
		$rsvp_a = spotinder_get_rsvp( $event->ID, $a );
		$rsvp_b = get_user_meta( $b, 'spotinder_share_events', true ) ? spotinder_get_rsvp( $event->ID, $b ) : '';
		$hits   = array_unique( array_merge( spotinder_event_matches_user( $event->ID, $a ), spotinder_event_matches_user( $event->ID, $b ) ) );

		if ( ! $rsvp_b && ! $hits && ! get_post_meta( $event->ID, 'event_official', true ) ) {
			continue;
		}

		$data            = spotinder_event_data( $event );
		$data['mine']    = $rsvp_a;
		$data['theirs']  = $rsvp_b;
		$data['because'] = array_values( $hits );
		$out[]           = $data;
	}

	usort(
		$out,
		fn( $x, $y ) => ( (int) (bool) $y['theirs'] * 2 + count( $y['because'] ) ) <=> ( (int) (bool) $x['theirs'] * 2 + count( $x['because'] ) )
	);

	return array_slice( $out, 0, 4 );
}

/**
 * Convites de um match.
 *
 * @param int $match_id Match.
 * @param int $user_id  Quem vê.
 */
function spotinder_get_invites( $match_id, $user_id ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . spotinder_table( 'invites' ) . " WHERE match_id = %d AND status <> 'cancelled' ORDER BY created_at DESC LIMIT 20", $match_id ) );
	$types = array();
	foreach ( (array) spotinder_opt( 'invite_types' ) as $t ) {
		$types[ $t['slug'] ] = $t;
	}

	$out = array();
	foreach ( $rows as $row ) {
		$event = $row->event_id ? get_post( (int) $row->event_id ) : null;
		$type  = $types[ $row->kind ] ?? null;
		$start = $event ? (string) get_post_meta( $event->ID, 'event_start', true ) : (string) $row->proposed_at;

		$out[] = array(
			'id'        => (int) $row->id,
			'mine'      => (int) $row->from_id === $user_id,
			'status'    => $row->status,
			'kind'      => $row->kind,
			'kindLabel' => $event ? __( 'Evento da agenda', 'spotinder' ) : ( $type ? $type['emoji'] . ' ' . $type['label'] : $row->kind ),
			'event'     => $event ? array(
				'id'    => $event->ID,
				'title' => get_the_title( $event ),
				'url'   => get_permalink( $event ),
			) : null,
			'when'      => spotinder_date( $start, 'D, d/m · H\hi' ),
			'place'     => $event ? trim( get_post_meta( $event->ID, 'event_venue', true ) . ' — ' . get_post_meta( $event->ID, 'event_city', true ), ' —' ) : $row->place,
			'note'      => $row->note,
			'calendar'  => 'accepted' === $row->status ? array(
				'google' => spotinder_google_calendar_url( $row ),
				'ics'    => add_query_arg( array( 'spotinder_ics' => (int) $row->id ), home_url( '/' ) ),
			) : null,
		);
	}
	return $out;
}

/**
 * Cria um convite dentro de um match.
 *
 * @param int   $match_id Match.
 * @param int   $user_id  Quem convida.
 * @param array $data     kind, event_id, proposed_at, place, note.
 * @return int|WP_Error
 */
function spotinder_create_invite( $match_id, $user_id, $data ) {
	global $wpdb;

	$row = spotinder_get_match_row( $match_id, $user_id );
	if ( ! $row ) {
		return new WP_Error( 'spotinder_not_found', 'Match não encontrado.', array( 'status' => 404 ) );
	}

	// Sem chat: no máximo um convite pendente por pessoa em cada match.
	$pending = (int) $wpdb->get_var(
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->prepare( 'SELECT COUNT(*) FROM ' . spotinder_table( 'invites' ) . " WHERE match_id = %d AND from_id = %d AND status = 'pending'", $match_id, $user_id )
	);
	if ( $pending ) {
		return new WP_Error( 'spotinder_pending', 'Você já tem um convite aguardando resposta neste match.', array( 'status' => 409 ) );
	}

	$event_id = absint( $data['event_id'] ?? 0 );
	$kind     = sanitize_key( $data['kind'] ?? '' );

	if ( $event_id ) {
		if ( 'spot_event' !== get_post_type( $event_id ) ) {
			return new WP_Error( 'spotinder_invalid', 'Evento inválido.', array( 'status' => 400 ) );
		}
		$kind = 'event';
	} elseif ( ! in_array( $kind, wp_list_pluck( (array) spotinder_opt( 'invite_types' ), 'slug' ), true ) ) {
		return new WP_Error( 'spotinder_invalid', 'Escolha um tipo de rolê.', array( 'status' => 400 ) );
	}

	$proposed = null;
	if ( ! $event_id && ! empty( $data['proposed_at'] ) ) {
		// datetime-local do navegador, interpretado no fuso do site.
		$dt = date_create( (string) $data['proposed_at'], wp_timezone() );
		if ( ! $dt || $dt->getTimestamp() < time() ) {
			return new WP_Error( 'spotinder_invalid', 'Escolha uma data futura.', array( 'status' => 400 ) );
		}
		$proposed = $dt->format( 'Y-m-d H:i:s' );
	}

	$wpdb->insert(
		spotinder_table( 'invites' ),
		array(
			'match_id'    => $match_id,
			'from_id'     => $user_id,
			'to_id'       => (int) $row->user_a === $user_id ? (int) $row->user_b : (int) $row->user_a,
			'event_id'    => $event_id ? $event_id : null,
			'kind'        => $kind,
			'proposed_at' => $proposed,
			'place'       => sanitize_text_field( mb_substr( (string) ( $data['place'] ?? '' ), 0, 190 ) ),
			'note'        => sanitize_text_field( mb_substr( (string) ( $data['note'] ?? '' ), 0, 140 ) ),
			'status'      => 'pending',
			'created_at'  => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		)
	);

	return (int) $wpdb->insert_id;
}

/**
 * Responde (aceita/recusa) ou cancela um convite.
 *
 * @param int    $invite_id Convite.
 * @param int    $user_id   Usuário.
 * @param string $status    accepted|declined|cancelled.
 * @return true|WP_Error
 */
function spotinder_respond_invite( $invite_id, $user_id, $status ) {
	global $wpdb;
	$table = spotinder_table( 'invites' );
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $invite_id ) );

	if ( ! $row || ! in_array( $user_id, array( (int) $row->from_id, (int) $row->to_id ), true ) ) {
		return new WP_Error( 'spotinder_not_found', 'Convite não encontrado.', array( 'status' => 404 ) );
	}

	$allowed = (int) $row->from_id === $user_id ? array( 'cancelled' ) : array( 'accepted', 'declined' );
	if ( ! in_array( $status, $allowed, true ) ) {
		return new WP_Error( 'spotinder_invalid', 'Ação inválida.', array( 'status' => 400 ) );
	}

	$wpdb->update(
		$table,
		array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		),
		array( 'id' => $invite_id )
	);

	// Aceitou um evento da agenda: marca presença dos dois.
	if ( 'accepted' === $status && $row->event_id ) {
		spotinder_set_rsvp( (int) $row->event_id, (int) $row->from_id, 'going' );
		spotinder_set_rsvp( (int) $row->event_id, (int) $row->to_id, 'going' );
	}

	return true;
}

/**
 * Início/fim e textos de um convite para calendário.
 *
 * @param object $row Linha do convite.
 */
function spotinder_invite_calendar_data( $row ) {
	if ( $row->event_id ) {
		$event = get_post( (int) $row->event_id );
		$start = strtotime( get_gmt_from_date( (string) get_post_meta( $event->ID, 'event_start', true ) ) . ' UTC' );
		$end   = get_post_meta( $event->ID, 'event_end', true );
		$end   = $end ? strtotime( get_gmt_from_date( (string) $end ) . ' UTC' ) : $start + 3 * HOUR_IN_SECONDS;
		$title = get_the_title( $event );
		$place = trim( get_post_meta( $event->ID, 'event_venue', true ) . ', ' . get_post_meta( $event->ID, 'event_address', true ), ', ' );
	} else {
		$start = $row->proposed_at ? strtotime( get_gmt_from_date( $row->proposed_at ) . ' UTC' ) : time() + DAY_IN_SECONDS;
		$end   = $start + 2 * HOUR_IN_SECONDS;
		$types = wp_list_pluck( (array) spotinder_opt( 'invite_types' ), 'label', 'slug' );
		$title = sprintf( '%s · %s', spotinder_brand(), $types[ $row->kind ] ?? $row->kind );
		$place = $row->place;
	}
	return compact( 'start', 'end', 'title', 'place' );
}

/**
 * Link "adicionar ao Google Agenda".
 *
 * @param object $row Linha do convite.
 */
function spotinder_google_calendar_url( $row ) {
	$c = spotinder_invite_calendar_data( $row );
	return add_query_arg(
		array(
			'action'   => 'TEMPLATE',
			'text'     => rawurlencode( $c['title'] ),
			'dates'    => gmdate( 'Ymd\THis\Z', $c['start'] ) . '/' . gmdate( 'Ymd\THis\Z', $c['end'] ),
			'location' => rawurlencode( (string) $c['place'] ),
			'details'  => rawurlencode( 'Combinado pelo ' . spotinder_brand() . '. Encontre-se em local público e avise alguém de confiança.' ),
		),
		'https://calendar.google.com/calendar/render'
	);
}

/**
 * Download .ics de um convite aceito.
 */
add_action(
	'template_redirect',
	function () {
		if ( empty( $_GET['spotinder_ics'] ) || ! is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		global $wpdb;
		$user_id = get_current_user_id();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.NonceVerification.Recommended
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . spotinder_table( 'invites' ) . " WHERE id = %d AND status = 'accepted' AND (from_id = %d OR to_id = %d)", absint( $_GET['spotinder_ics'] ), $user_id, $user_id ) );
		if ( ! $row ) {
			return;
		}

		$c   = spotinder_invite_calendar_data( $row );
		$esc = fn( $s ) => str_replace( array( '\\', ',', ';', "\n" ), array( '\\\\', '\,', '\;', '\n' ), (string) $s );

		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="convite-' . (int) $row->id . '.ics"' );
		echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Spotinder//PT-BR\r\nBEGIN:VEVENT\r\n";
		echo 'UID:invite-' . (int) $row->id . '@' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . "\r\n";
		echo 'DTSTAMP:' . esc_html( gmdate( 'Ymd\THis\Z' ) ) . "\r\n";
		echo 'DTSTART:' . esc_html( gmdate( 'Ymd\THis\Z', $c['start'] ) ) . "\r\n";
		echo 'DTEND:' . esc_html( gmdate( 'Ymd\THis\Z', $c['end'] ) ) . "\r\n";
		echo 'SUMMARY:' . $esc( $c['title'] ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo 'LOCATION:' . $esc( $c['place'] ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "END:VEVENT\r\nEND:VCALENDAR\r\n";
		exit;
	}
);
