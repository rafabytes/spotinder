<?php
/**
 * REST API consumida pelos componentes React (namespace spotinder/v1).
 * Autenticação por cookie + nonce wp_rest.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'spotinder_register_routes' );

/**
 * Permissão: membro logado, não banido.
 */
function spotinder_rest_member() {
	$user_id = get_current_user_id();
	if ( ! $user_id || ! spotinder_is_member( $user_id ) ) {
		return new WP_Error( 'spotinder_auth', 'Entre com o Spotify para continuar.', array( 'status' => 401 ) );
	}
	if ( get_user_meta( $user_id, 'spotinder_banned', true ) ) {
		return new WP_Error( 'spotinder_banned', 'Conta suspensa.', array( 'status' => 403 ) );
	}
	spotinder_touch( $user_id );
	return true;
}

/**
 * Permissão: membro que já concluiu o cadastro e aceitou a versão atual dos termos.
 */
function spotinder_rest_onboarded() {
	$check = spotinder_rest_member();
	if ( true !== $check ) {
		return $check;
	}
	$user_id = get_current_user_id();
	if ( ! get_user_meta( $user_id, 'spotinder_onboarded', true ) || ! spotinder_has_consents( $user_id ) ) {
		return new WP_Error( 'spotinder_onboarding', 'Conclua seu cadastro primeiro.', array( 'status' => 403 ) );
	}
	return true;
}

/**
 * Registra as rotas.
 */
function spotinder_register_routes() {
	$ns = 'spotinder/v1';
	$id = array(
		'id' => array(
			'type'     => 'integer',
			'required' => true,
		),
	);

	register_rest_route(
		$ns,
		'/me',
		array(
			array(
				'methods'             => 'GET',
				'permission_callback' => 'spotinder_rest_member',
				'callback'            => fn() => spotinder_member_payload( get_current_user_id(), 0, 'self' ),
			),
			array(
				'methods'             => 'POST',
				'permission_callback' => 'spotinder_rest_member',
				'callback'            => function ( WP_REST_Request $req ) {
					$result = spotinder_update_profile( get_current_user_id(), $req->get_json_params() );
					return is_wp_error( $result ) ? $result : spotinder_member_payload( get_current_user_id(), 0, 'self' );
				},
			),
			array(
				'methods'             => 'DELETE',
				'permission_callback' => 'spotinder_rest_member',
				'callback'            => function ( WP_REST_Request $req ) {
					if ( 'APAGAR' !== $req->get_param( 'confirm' ) ) {
						return new WP_Error( 'spotinder_confirm', 'Digite APAGAR para confirmar.', array( 'status' => 400 ) );
					}
					$user_id = get_current_user_id();
					wp_logout();
					spotinder_delete_member( $user_id );
					return array(
						'deleted'  => true,
						'redirect' => add_query_arg( 'sp_notice', 'deleted', home_url( '/' ) ),
					);
				},
			),
		)
	);

	// Envio de foto (multipart: campo "photo" + "consent").
	register_rest_route(
		$ns,
		'/me/photo',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'spotinder_rest_member',
			'callback'            => function ( WP_REST_Request $req ) {
				$user_id = get_current_user_id();
				$files   = $req->get_file_params();
				if ( ! filter_var( $req->get_param( 'consent' ), FILTER_VALIDATE_BOOLEAN ) ) {
					return new WP_Error( 'spotinder_photo', 'Para exibir uma foto, confirme o aviso sobre a foto.', array( 'status' => 400 ) );
				}
				$saved = spotinder_save_uploaded_photo( $user_id, $files['photo'] ?? array() );
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
				$set = spotinder_set_photo_source( $user_id, 'upload', true );
				return is_wp_error( $set ) ? $set : spotinder_member_payload( $user_id, 0, 'self' );
			},
		)
	);

	// Localização aproximada: o navegador envia a coordenada JÁ arredondada para a grade (~5 km).
	register_rest_route(
		$ns,
		'/me/location',
		array(
			array(
				'methods'             => 'POST',
				'permission_callback' => 'spotinder_rest_member',
				'args'                => array(
					'lat'     => array(
						'type'     => 'number',
						'required' => true,
					),
					'lng'     => array(
						'type'     => 'number',
						'required' => true,
					),
					'consent' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'callback'            => function ( WP_REST_Request $req ) {
					$user_id = get_current_user_id();
					// Primeira ativação exige aceite explícito; atualizações automáticas só valem se já estiver ativa.
					if ( ! $req['consent'] && ! spotinder_get_region( $user_id ) ) {
						return new WP_Error( 'spotinder_location', 'Confirme o aviso sobre a localização aproximada.', array( 'status' => 400 ) );
					}
					$set = spotinder_set_location( $user_id, $req['lat'], $req['lng'] );
					return is_wp_error( $set ) ? $set : spotinder_member_payload( $user_id, 0, 'self' );
				},
			),
			array(
				'methods'             => 'DELETE',
				'permission_callback' => 'spotinder_rest_member',
				'callback'            => function () {
					spotinder_clear_location( get_current_user_id() );
					return spotinder_member_payload( get_current_user_id(), 0, 'self' );
				},
			),
		)
	);

	// Escolha da origem da foto: none | spotify | upload.
	register_rest_route(
		$ns,
		'/me/photo-source',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'spotinder_rest_member',
			'args'                => array(
				'source'  => array(
					'type'     => 'string',
					'enum'     => array( 'none', 'spotify', 'upload' ),
					'required' => true,
				),
				'consent' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
			'callback'            => function ( WP_REST_Request $req ) {
				$user_id = get_current_user_id();
				$set     = spotinder_set_photo_source( $user_id, $req['source'], (bool) $req['consent'] );
				return is_wp_error( $set ) ? $set : spotinder_member_payload( $user_id, 0, 'self' );
			},
		)
	);

	register_rest_route(
		$ns,
		'/me/export',
		array(
			'methods'             => 'GET',
			'permission_callback' => 'spotinder_rest_member',
			'callback'            => fn() => spotinder_export_member( get_current_user_id() ),
		)
	);

	register_rest_route(
		$ns,
		'/summary',
		array(
			'methods'             => 'GET',
			'permission_callback' => 'spotinder_rest_member',
			'callback'            => function () {
				$matches = spotinder_get_matches( get_current_user_id() );
				return array(
					'newMatches' => count( array_filter( $matches, fn( $m ) => $m['isNew'] ) ),
					'invites'    => array_sum( wp_list_pluck( $matches, 'invites' ) ),
				);
			},
		)
	);

	register_rest_route(
		$ns,
		'/deck',
		array(
			'methods'             => 'GET',
			'permission_callback' => 'spotinder_rest_onboarded',
			'args'                => array(
				'intention' => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_key',
				),
			),
			'callback'            => fn( WP_REST_Request $req ) => spotinder_get_deck( get_current_user_id(), (string) $req->get_param( 'intention' ) ),
		)
	);

	register_rest_route(
		$ns,
		'/swipe',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'spotinder_rest_onboarded',
			'args'                => array(
				'target' => array(
					'type'     => 'integer',
					'required' => true,
				),
				'action' => array(
					'type'     => 'string',
					'enum'     => array( 'like', 'pass' ),
					'required' => true,
				),
			),
			'callback'            => fn( WP_REST_Request $req ) => spotinder_swipe( get_current_user_id(), (int) $req['target'], $req['action'] ),
		)
	);

	register_rest_route(
		$ns,
		'/matches',
		array(
			'methods'             => 'GET',
			'permission_callback' => 'spotinder_rest_onboarded',
			'callback'            => fn() => spotinder_get_matches( get_current_user_id() ),
		)
	);

	register_rest_route(
		$ns,
		'/matches/(?P<id>\d+)',
		array(
			array(
				'methods'             => 'GET',
				'permission_callback' => 'spotinder_rest_onboarded',
				'args'                => $id,
				'callback'            => function ( WP_REST_Request $req ) {
					$detail = spotinder_match_detail( (int) $req['id'], get_current_user_id() );
					return $detail ? $detail : new WP_Error( 'spotinder_not_found', 'Match não encontrado.', array( 'status' => 404 ) );
				},
			),
			array(
				'methods'             => 'DELETE',
				'permission_callback' => 'spotinder_rest_onboarded',
				'args'                => $id,
				'callback'            => fn( WP_REST_Request $req ) => array( 'deleted' => spotinder_unmatch( (int) $req['id'], get_current_user_id() ) ),
			),
		)
	);

	register_rest_route(
		$ns,
		'/matches/(?P<id>\d+)/invites',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'spotinder_rest_onboarded',
			'args'                => $id,
			'callback'            => function ( WP_REST_Request $req ) {
				$result = spotinder_create_invite( (int) $req['id'], get_current_user_id(), (array) $req->get_json_params() );
				return is_wp_error( $result ) ? $result : spotinder_get_invites( (int) $req['id'], get_current_user_id() );
			},
		)
	);

	register_rest_route(
		$ns,
		'/invites/(?P<id>\d+)',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'spotinder_rest_onboarded',
			'args'                => $id + array(
				'status' => array(
					'type'     => 'string',
					'enum'     => array( 'accepted', 'declined', 'cancelled' ),
					'required' => true,
				),
			),
			'callback'            => function ( WP_REST_Request $req ) {
				$result = spotinder_respond_invite( (int) $req['id'], get_current_user_id(), $req['status'] );
				return is_wp_error( $result ) ? $result : array( 'ok' => true );
			},
		)
	);

	register_rest_route(
		$ns,
		'/block',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'spotinder_rest_member',
			'args'                => array(
				'target' => array(
					'type'     => 'integer',
					'required' => true,
				),
			),
			'callback'            => fn( WP_REST_Request $req ) => array( 'blocked' => spotinder_block( get_current_user_id(), (int) $req['target'] ) ),
		)
	);

	register_rest_route(
		$ns,
		'/report',
		array(
			'methods'             => 'POST',
			'permission_callback' => 'spotinder_rest_member',
			'args'                => array(
				'target'  => array(
					'type'     => 'integer',
					'required' => true,
				),
				'reason'  => array(
					'type'     => 'string',
					'enum'     => array_keys( spotinder_report_reasons() ),
					'required' => true,
				),
				'details' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			'callback'            => function ( WP_REST_Request $req ) {
				global $wpdb;
				$user_id = get_current_user_id();
				$target  = (int) $req['target'];
				if ( ! get_userdata( $target ) || $target === $user_id ) {
					return new WP_Error( 'spotinder_invalid', 'Perfil inválido.', array( 'status' => 400 ) );
				}
				$wpdb->insert(
					spotinder_table( 'reports' ),
					array(
						'reporter_id' => $user_id,
						'target_id'   => $target,
						'reason'      => $req['reason'],
						'details'     => sanitize_textarea_field( mb_substr( (string) $req['details'], 0, 1000 ) ),
						'status'      => 'open',
						'created_at'  => current_time( 'mysql' ),
					)
				);
				// Quem denuncia não precisa continuar vendo a pessoa.
				spotinder_block( $user_id, $target );
				return array( 'reported' => true );
			},
		)
	);

	register_rest_route(
		$ns,
		'/events/(?P<id>\d+)/rsvp',
		array(
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => $id,
				'callback'            => function ( WP_REST_Request $req ) {
					$event_id = (int) $req['id'];
					$user_id  = get_current_user_id();
					$out      = array(
						'counts'  => spotinder_event_rsvp_counts( $event_id ),
						'mine'    => '',
						'matches' => array(),
						'because' => array(),
					);
					if ( $user_id && spotinder_is_member( $user_id ) ) {
						$out['mine']    = spotinder_get_rsvp( $event_id, $user_id );
						$out['matches'] = spotinder_event_matches_going( $event_id, $user_id );
						$out['because'] = spotinder_event_matches_user( $event_id, $user_id );
					}
					return $out;
				},
			),
			array(
				'methods'             => 'POST',
				'permission_callback' => 'spotinder_rest_onboarded',
				'args'                => $id + array(
					'status' => array(
						'type' => 'string',
						'enum' => array( 'going', 'interested', '' ),
					),
				),
				'callback'            => function ( WP_REST_Request $req ) {
					$event_id = (int) $req['id'];
					if ( 'spot_event' !== get_post_type( $event_id ) ) {
						return new WP_Error( 'spotinder_not_found', 'Evento não encontrado.', array( 'status' => 404 ) );
					}
					$mine = spotinder_set_rsvp( $event_id, get_current_user_id(), (string) $req['status'] );
					return array(
						'mine'   => $mine,
						'counts' => spotinder_event_rsvp_counts( $event_id ),
					);
				},
			),
		)
	);
}

/**
 * Remove o endpoint público /wp/v2/users para visitantes: perfis de membros
 * não devem ser enumeráveis.
 */
add_filter(
	'rest_endpoints',
	function ( $endpoints ) {
		if ( ! current_user_can( 'list_users' ) ) {
			unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}
);
