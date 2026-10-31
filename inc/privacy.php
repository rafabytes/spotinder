<?php
/**
 * Privacidade: integração com as ferramentas de privacidade do WordPress,
 * retenção automática e proteção contra enumeração de membros.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exportador e apagador (Ferramentas > Exportar/Apagar dados pessoais).
 * Membros não têm e-mail, então o admin busca pelo login (g_..., ou sp_... nas contas antigas).
 */
add_filter(
	'wp_privacy_personal_data_exporters',
	function ( $exporters ) {
		$exporters['spotinder'] = array(
			'exporter_friendly_name' => 'Spotinder',
			'callback'               => function ( $email_or_login ) {
				$user = get_user_by( 'email', $email_or_login ) ?: get_user_by( 'login', $email_or_login );
				if ( ! $user || ! spotinder_is_member( $user->ID ) ) {
					return array(
						'data' => array(),
						'done' => true,
					);
				}
				$export = spotinder_export_member( $user->ID );
				$data   = array();
				foreach ( $export as $group => $values ) {
					$data[] = array(
						'name'  => $group,
						'value' => is_scalar( $values ) ? (string) $values : wp_json_encode( $values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ),
					);
				}
				return array(
					'data' => array(
						array(
							'group_id'    => 'spotinder',
							'group_label' => 'Spotinder',
							'item_id'     => 'member-' . $user->ID,
							'data'        => $data,
						),
					),
					'done' => true,
				);
			},
		);
		return $exporters;
	}
);

add_filter(
	'wp_privacy_personal_data_erasers',
	function ( $erasers ) {
		$erasers['spotinder'] = array(
			'eraser_friendly_name' => 'Spotinder',
			'callback'             => function ( $email_or_login ) {
				$user    = get_user_by( 'email', $email_or_login ) ?: get_user_by( 'login', $email_or_login );
				$removed = false;
				if ( $user && spotinder_is_member( $user->ID ) ) {
					spotinder_purge_member_rows( $user->ID );
					spotinder_delete_photo_file( $user->ID );
					foreach ( array( 'spotinder_top_artists', 'spotinder_top_tracks', 'spotinder_socials', 'spotinder_bio', 'spotinder_city', 'spotinder_intentions', 'spotinder_spotify_url', 'spotinder_spotify_photo', 'spotinder_google_photo', 'spotinder_photo_source', 'spotinder_region', 'spotinder_max_distance' ) as $key ) {
						delete_user_meta( $user->ID, $key );
					}
					update_user_meta( $user->ID, 'spotinder_visible', 0 );
					$removed = true;
				}
				return array(
					'items_removed'  => $removed,
					'items_retained' => false,
					'messages'       => $removed ? array( 'Dados do Spotinder removidos. Para excluir a conta inteira, apague o usuário.' ) : array(),
					'done'           => true,
				);
			},
		);
		return $erasers;
	}
);

/**
 * Texto sugerido no guia de política de privacidade do WP.
 */
add_action(
	'admin_init',
	function () {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				'Spotinder',
				'<p>O tema Spotinder cria contas apenas via login com Google (escopos openid e profile, sem e-mail) e guarda o ID da conta Google, o nome e o endereço da foto pública, além do que o membro preenche (artistas e músicas favoritos, bio, cidade, intenções e redes sociais). Tokens do Google não são armazenados. Artistas e músicas vêm do catálogo público do Spotify, consultado com a credencial do próprio site. Membros podem exportar e apagar seus dados em Perfil > Privacidade. Contas inativas são apagadas automaticamente após o prazo configurado em Spotinder > Configurações.</p>'
			);
		}
	}
);

/**
 * Retenção: rotina diária que apaga contas inativas além do prazo.
 */
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'spotinder_daily_retention' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'spotinder_daily_retention' );
		}
	}
);

add_action( 'spotinder_daily_retention', 'spotinder_run_retention' );

/**
 * Apaga membros inativos (exceto usuários de teste).
 *
 * @return int Quantidade apagada.
 */
function spotinder_run_retention() {
	$days = max( 30, (int) spotinder_opt( 'inactive_delete_days' ) );
	$ids  = get_users(
		array(
			'fields'     => 'ID',
			'number'     => 200,
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'relation' => 'OR',
					array(
						'key'     => 'spotinder_google_id',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'spotinder_spotify_id',
						'compare' => 'EXISTS',
					),
				),
				array(
					'key'     => 'spotinder_is_demo',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'spotinder_last_active',
					'value'   => time() - $days * DAY_IN_SECONDS,
					'compare' => '<',
					'type'    => 'NUMERIC',
				),
			),
		)
	);

	foreach ( $ids as $id ) {
		spotinder_delete_member( (int) $id );
	}

	// Denúncias encerradas há mais de 6 meses (prazo descrito na política).
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . spotinder_table( 'reports' ) . " WHERE status <> 'open' AND created_at < %s", gmdate( 'Y-m-d H:i:s', time() - 180 * DAY_IN_SECONDS ) ) );

	return count( $ids );
}

add_action(
	'switch_theme',
	function () {
		wp_clear_scheduled_hook( 'spotinder_daily_retention' );
	}
);

/**
 * Sem páginas de autor: perfis de membros só aparecem dentro do app.
 */
add_action(
	'template_redirect',
	function () {
		if ( is_author() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}
);

/**
 * Sem sitemap de usuários.
 */
add_filter(
	'wp_sitemaps_add_provider',
	function ( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	},
	10,
	2
);

/**
 * Páginas do app não devem ser indexadas.
 */
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_page_template( array_values( spotinder_app_templates() ) ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}
);
