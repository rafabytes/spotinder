<?php
/**
 * Migrações de conteúdo entre versões do tema.
 *
 * Atualizam textos de opções e páginas legais SOMENTE quando ainda estão com o texto
 * original de alguma versão anterior do tema — nada que o admin editou é sobrescrito.
 * O que não puder ser atualizado automaticamente vira um aviso no painel.
 *
 * Versões de conteúdo:
 *  1 — lançamento
 *  2 — foto de perfil opcional
 *  3 — localização aproximada opcional
 *  4 — cartões animados por gênero no destaque da home
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

const SPOTINDER_CONTENT_VERSION = 4;

add_action( 'admin_init', 'spotinder_run_migrations' );

/**
 * Executa as migrações pendentes.
 */
function spotinder_run_migrations() {
	if ( ! function_exists( 'update_field' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$version = (int) get_option( 'spotinder_content_version', 1 );
	if ( $version >= SPOTINDER_CONTENT_VERSION ) {
		return;
	}

	// Trava: várias requisições de admin (inclusive o heartbeat) podem chegar juntas.
	if ( ! add_option( 'spotinder_migration_lock', time(), '', false ) ) {
		if ( time() - (int) get_option( 'spotinder_migration_lock' ) < 5 * MINUTE_IN_SECONDS ) {
			return;
		}
		update_option( 'spotinder_migration_lock', time(), false );
	}

	$review = array();

	// Textos de opções (v2) e novas perguntas do FAQ (v2, v3).
	$new_questions = array();
	if ( $version < 2 ) {
		spotinder_migrate_option_texts( spotinder_migration_2_texts() );
		$new_questions[] = 'Preciso colocar foto?';
	}
	if ( $version < 3 ) {
		$new_questions[] = 'Vocês sabem onde eu estou?';
	}
	spotinder_migrate_append_faq( $new_questions );

	// v4: cartões do destaque com fotos na Biblioteca de Mídia.
	if ( $version < 4 ) {
		spotinder_seed_hero_cards();
	}

	// Páginas legais e resumos: qualquer versão anterior conhecida vai para a atual.
	$pages_ok = spotinder_migrate_legal_pages( $review );

	// Mudança nos dados tratados: pede novo aceite (LGPD art. 8º, §6º).
	$known_versions = array( '2026-10-30', '2026-10-30.2' );
	$current        = (string) get_field( 'policy_version', 'option', false );
	if ( $pages_ok && in_array( $current, $known_versions, true ) ) {
		update_field( 'field_sp_policy_version', spotinder_defaults()['policy_version'], 'option' );
	} elseif ( $current !== spotinder_defaults()['policy_version'] ) {
		$review[] = 'Versão da política/termos (Spotinder > Configurações > Regras do app): altere depois de revisar as páginas, para que todos aceitem os novos termos.';
	}

	update_option( 'spotinder_content_version', SPOTINDER_CONTENT_VERSION );
	if ( $review ) {
		update_option( 'spotinder_migration_notice', $review, false );
	}
	delete_option( 'spotinder_migration_lock' );
}

/**
 * Troca textos de opções e de itens de repeaters que ainda estejam com o texto antigo.
 * Cada repeater é lido e gravado UMA vez (releituras na mesma requisição vêm do cache do ACF).
 *
 * @param array $texts ['options' => [chave => [antigo, novo]], 'rows' => [[chave, índice, subcampo, antigo, novo]]].
 */
function spotinder_migrate_option_texts( $texts ) {
	foreach ( $texts['options'] as $key => list( $old, $new ) ) {
		if ( get_field( $key, 'option', false ) === $old ) {
			update_field( 'field_sp_' . $key, $new, 'option' );
		}
	}

	$by_key = array();
	foreach ( $texts['rows'] as list( $key, $index, $sub, $old, $new ) ) {
		$by_key[ $key ][] = array( $index, $sub, $old, $new );
	}
	foreach ( $by_key as $key => $changes ) {
		$rows = get_field( $key, 'option' );
		if ( ! is_array( $rows ) ) {
			continue;
		}
		$dirty = false;
		foreach ( $changes as list( $index, $sub, $old, $new ) ) {
			if ( isset( $rows[ $index ][ $sub ] ) && $rows[ $index ][ $sub ] === $old ) {
				$rows[ $index ][ $sub ] = $new;
				$dirty                  = true;
			}
		}
		if ( $dirty ) {
			update_field( 'field_sp_' . $key, $rows, 'option' );
		}
	}
}

/**
 * Acrescenta perguntas novas do FAQ (texto dos padrões), se ainda não existirem.
 *
 * @param string[] $questions Perguntas.
 */
function spotinder_migrate_append_faq( $questions ) {
	$faq = get_field( 'faq', 'option' );
	if ( ! $questions || ! is_array( $faq ) ) {
		return;
	}
	$existing = wp_list_pluck( $faq, 'question' );
	$dirty    = false;
	foreach ( spotinder_defaults()['faq'] as $item ) {
		if ( in_array( $item['question'], $questions, true ) && ! in_array( $item['question'], $existing, true ) ) {
			$faq[] = $item;
			$dirty = true;
		}
	}
	if ( $dirty ) {
		update_field( 'field_sp_faq', $faq, 'option' );
	}
}

/**
 * Atualiza páginas legais e resumos que estejam idênticos a uma versão anterior do tema.
 *
 * @param string[] $review Itens para revisão manual (por referência).
 * @return bool Todas as páginas legais estão na versão atual?
 */
function spotinder_migrate_legal_pages( &$review ) {
	$pages = (array) get_option( 'spotinder_pages', array() );

	// md5 do conteúdo/resumo de cada versão publicada (v1, v2).
	$known = array(
		'privacy' => array(
			'file'    => 'privacidade',
			'title'   => 'Política de Privacidade',
			'content' => array( 'be64b80bce1ab7eb3c4397b5c78c75c9', 'ef3404834daa268ece1f71243016d033' ),
			'summary' => array( '9ad1793a5f4d354a44787c643518ad6a', '7ebee35379935c8bddb26c7de362e1d4' ),
		),
		'terms'   => array(
			'file'    => 'termos',
			'title'   => 'Termos de Uso',
			'content' => array( '9e04a07c7c0439064e2b899559c4801b', '8d040222a614004ef50f2957cd4351ba' ),
			'summary' => array( '2aaec7c11a9498cb98e9e6a4b6afa4cb', 'c97dfbe69da3dc5ef9efee8e508ab133' ),
		),
		'safety'  => array(
			'file'    => 'seguranca',
			'title'   => 'Segurança e boas práticas',
			'content' => array( '6ecc59eb1e5d77c26431d762c247421b', 'ebfd4d71ddd04cee2a5225a26284b808' ),
			'summary' => array( '94b710fcfbd0f42dc3cf01bd075fef83' ),
		),
	);

	$summary_hash = fn( $rows ) => md5( wp_json_encode( array_map( fn( $r ) => array( $r['emoji'], $r['text'] ), (array) $rows ) ) );
	$current_rows = spotinder_legal_summaries();
	$all          = true;

	foreach ( $known as $key => $info ) {
		$page = empty( $pages[ $key ] ) ? null : get_post( $pages[ $key ] );
		if ( ! $page ) {
			continue;
		}

		// Resumo.
		$target  = $summary_hash( array_map( fn( $r ) => array( 'emoji' => $r[0], 'text' => $r[1] ), $current_rows[ $info['file'] ] ) );
		$summary = $summary_hash( get_field( 'legal_summary', $page->ID ) );
		if ( $summary !== $target ) {
			if ( in_array( $summary, $info['summary'], true ) ) {
				spotinder_seed_legal_summary( $page->ID, $info['file'], true );
			} else {
				$review[] = sprintf( '%s — "Resumo em 30 segundos" foi editado; inclua os pontos novos (foto e localização) manualmente.', $info['title'] );
			}
		}

		// Conteúdo.
		$file    = (string) file_get_contents( SPOTINDER_DIR . "/content/{$info['file']}.html" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = md5( trim( $page->post_content ) );
		if ( md5( trim( $file ) ) === $content ) {
			continue;
		}
		if ( ! in_array( $content, $info['content'], true ) ) {
			$review[] = sprintf( '%s — a página foi editada; inclua as seções de foto de perfil e localização aproximada manualmente (texto de referência em content/%s.html no tema).', $info['title'], $info['file'] );
			$all      = false;
			continue;
		}
		wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => $file,
			)
		);
		update_field( 'field_sp_legal_updated', wp_date( 'Ymd' ), $page->ID );
	}

	return $all;
}

/**
 * Textos alterados na v2 (antigo → novo). Também é a fonte dos padrões em defaults.php.
 */
function spotinder_migration_2_texts() {
	return array(
		'options' => array(
			'share_description' => array(
				'Conecte seu Spotify e conheça pessoas pelo que elas realmente escutam. Sem fotos, sem chat: amizades, bandas e companhia para shows.',
				'Conecte seu Spotify e conheça pessoas pelo que elas realmente escutam. Artistas em primeiro lugar, sem chat: amizades, bandas e companhia para shows.',
			),
			'hero_eyebrow'      => array(
				'Sem fotos. Sem chat. Só música.',
				'Música em primeiro lugar. Sem chat.',
			),
			'hero_text'         => array(
				'Conecte seu Spotify e conheça pessoas pelo que elas realmente escutam. Em vez de fotos, você vê os artistas mais ouvidos de cada um. Deu match? Vocês ganham uma playlist em comum e um jeito de se encontrar no próximo show.',
				'Conecte seu Spotify e conheça pessoas pelo que elas realmente escutam. Em cada cartão, os artistas mais ouvidos vêm em destaque — a foto é opcional. Deu match? Vocês ganham uma playlist em comum e um jeito de se encontrar no próximo show.',
			),
			'final_cta_text'    => array(
				'Leva menos de um minuto. Sem foto, sem formulário, sem chat.',
				'Leva menos de um minuto. Foto opcional, sem formulário, sem chat.',
			),
		),
		'rows'    => array(
			array( 'hero_stats', 0, 'value', '0', '3' ),
			array( 'hero_stats', 0, 'label', 'fotos de perfil', 'artistas em destaque em cada cartão' ),
			array( 'how_steps', 0, 'text', 'É o único jeito de entrar. Lemos só seus artistas e músicas mais ouvidos — nada de e-mail, foto ou histórico completo.', 'É o único jeito de entrar. Lemos só seus artistas e músicas mais ouvidos e seu perfil público — nada de e-mail ou histórico completo.' ),
			array( 'features', 4, 'text', 'Não guardamos seus tokens do Spotify, não pedimos foto nem e-mail e você apaga tudo com um clique.', 'Não guardamos seus tokens do Spotify, não pedimos e-mail, a foto é opcional e você apaga tudo com um clique.' ),
			array( 'faq', 3, 'answer', 'Só seus artistas e músicas mais ouvidos dos últimos meses e o nome público do seu perfil. Não acessamos e-mail, foto, playlists privadas nem o que você está ouvindo agora.', 'Só seus artistas e músicas mais ouvidos dos últimos meses, o nome público do seu perfil e o endereço da sua foto do Spotify (que só aparece se você escolher usá-la). Não acessamos e-mail, playlists privadas nem o que você está ouvindo agora.' ),
		),
	);
}

/**
 * Aviso no painel com o que precisa de revisão manual.
 */
add_action(
	'admin_notices',
	function () {
		$items = get_option( 'spotinder_migration_notice' );
		if ( ! $items || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_GET['spotinder_dismiss_migration'] ) && check_admin_referer( 'spotinder_dismiss_migration' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			delete_option( 'spotinder_migration_notice' );
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>Spotinder — atualização de conteúdo:</strong> revise manualmente:</p><ul style="list-style:disc;padding-left:20px">';
		foreach ( (array) $items as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}
		printf( '</ul><p><a href="%s">Já revisei, ocultar aviso</a></p></div>', esc_url( wp_nonce_url( add_query_arg( 'spotinder_dismiss_migration', '1' ), 'spotinder_dismiss_migration' ) ) );
	}
);
