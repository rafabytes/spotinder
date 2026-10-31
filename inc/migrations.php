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
 *  5 — login com Google e artistas/músicas escolhidos pela pessoa (sem conectar o Spotify)
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

const SPOTINDER_CONTENT_VERSION = 5;

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

	// v5: textos do login com Google e da seleção manual de artistas.
	if ( $version < 5 ) {
		spotinder_migrate_option_texts( spotinder_migration_5_texts() );
	}

	// Páginas legais e resumos: qualquer versão anterior conhecida vai para a atual.
	$pages_ok = spotinder_migrate_legal_pages( $review );

	// Mudança nos dados tratados: pede novo aceite (LGPD art. 8º, §6º).
	$known_versions = array( '2026-10-30', '2026-10-30.2', '2026-10-30.3' );
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
 * Troca textos de opções e de itens de repeaters que ainda estejam com um texto antigo.
 * Cada repeater é lido e gravado UMA vez (releituras na mesma requisição vêm do cache do ACF).
 *
 * @param array $texts ['options' => [chave => [antigo(s), novo]], 'rows' => [[chave, índice|null, subcampo, antigo(s), novo]]].
 *                     "Antigo" pode ser uma lista; índice null vale para qualquer item do repeater.
 */
function spotinder_migrate_option_texts( $texts ) {
	foreach ( $texts['options'] as $key => list( $old, $new ) ) {
		if ( in_array( get_field( $key, 'option', false ), (array) $old, true ) ) {
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
			foreach ( null === $index ? array_keys( $rows ) : array( $index ) as $i ) {
				if ( isset( $rows[ $i ][ $sub ] ) && in_array( $rows[ $i ][ $sub ], (array) $old, true ) ) {
					$rows[ $i ][ $sub ] = $new;
					$dirty              = true;
				}
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

	// md5 do conteúdo/resumo de cada versão publicada (v1, v2, v4).
	$known = array(
		'privacy' => array(
			'file'    => 'privacidade',
			'title'   => 'Política de Privacidade',
			'content' => array( 'be64b80bce1ab7eb3c4397b5c78c75c9', 'ef3404834daa268ece1f71243016d033', 'b2dff328d22c155c141a2c604fec3b3a' ),
			'summary' => array( '9ad1793a5f4d354a44787c643518ad6a', '7ebee35379935c8bddb26c7de362e1d4', 'caa32f2ec04ad5978ddf0ac55c7dfb41' ),
		),
		'terms'   => array(
			'file'    => 'termos',
			'title'   => 'Termos de Uso',
			'content' => array( '9e04a07c7c0439064e2b899559c4801b', '8d040222a614004ef50f2957cd4351ba', 'eb7f9b7bf44abcc22d3107b542dbe435' ),
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
				$review[] = sprintf( '%s — "Resumo em 30 segundos" foi editado; compare com o resumo padrão do tema e inclua os pontos novos manualmente.', $info['title'] );
			}
		}

		// Conteúdo.
		$file    = (string) file_get_contents( SPOTINDER_DIR . "/content/{$info['file']}.html" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = md5( trim( $page->post_content ) );
		if ( md5( trim( $file ) ) === $content ) {
			continue;
		}
		if ( ! in_array( $content, $info['content'], true ) ) {
			$review[] = sprintf( '%s — a página foi editada; inclua as mudanças desta versão (login com Google e artistas escolhidos pela pessoa) manualmente (texto de referência em content/%s.html no tema).', $info['title'], $info['file'] );
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
 * Textos alterados na v5 (antigo(s) → novo): login com Google, artistas escolhidos
 * pela pessoa e os textos da home editados em produção (que viraram o padrão).
 */
function spotinder_migration_5_texts() {
	$d = spotinder_defaults();
	$q = fn( $question ) => current( wp_list_filter( $d['faq'], array( 'question' => $question ) ) );

	return array(
		'options' => array(
			'share_description'   => array( 'Conecte seu Spotify e conheça pessoas pelo que elas realmente escutam. Artistas em primeiro lugar, sem chat: amizades, bandas e companhia para shows.', $d['share_description'] ),
			'login_label'         => array( 'Entrar com Spotify', $d['login_label'] ),
			'hero_eyebrow'        => array( array( 'Música em primeiro lugar. Sem chat.', 'Sem fotos. Sem chat. Só música.' ), $d['hero_eyebrow'] ),
			'hero_title'          => array( 'Ninguém deveria ouvir sozinho.', $d['hero_title'] ),
			'hero_text'           => array(
				array(
					'Conecte seu Spotify e conheça pessoas pelo que elas realmente escutam. Em cada cartão, os artistas mais ouvidos vêm em destaque — a foto é opcional. Deu match? Vocês ganham uma playlist em comum e um jeito de se encontrar no próximo show.',
					'Conecte seu Spotify e conheça pessoas pelo que elas realmente escutam. Em vez de fotos, você vê os artistas mais ouvidos de cada um. Deu match? Vocês ganham uma playlist em comum e um jeito de se encontrar no próximo show.',
				),
				$d['hero_text'],
			),
			'hero_cta_label'      => array( 'Conectar meu Spotify', $d['hero_cta_label'] ),
			'events_title'        => array( 'Próximos encontros para ouvir junto.', $d['events_title'] ),
			'events_text'         => array( 'Eventos cadastrados pela nossa equipe. Marque presença e descubra quais dos seus matches também vão.', $d['events_text'] ),
			'final_cta_text'      => array( array( 'Leva menos de um minuto. Foto opcional, sem formulário, sem chat.', 'Leva menos de um minuto. Sem foto, sem formulário, sem chat.' ), $d['final_cta_text'] ),
			'final_cta_label'     => array( 'Conectar meu Spotify', $d['final_cta_label'] ),
			'onboarding_text'     => array( 'Seus artistas já vieram do Spotify. Falta só dizer o que você procura e, se quiser, quais redes aparecem para seus matches.', $d['onboarding_text'] ),
			'consent_share_label' => array( 'Autorizo exibir meu nome público e meus artistas/músicas mais ouvidos para outras pessoas do app.', $d['consent_share_label'] ),
		),
		'rows'    => array(
			array( 'how_steps', null, 'title', 'Conecte o Spotify', $d['how_steps'][0]['title'] ),
			array(
				'how_steps',
				null,
				'text',
				array(
					'É o único jeito de entrar. Lemos só seus artistas e músicas mais ouvidos e seu perfil público — nada de e-mail ou histórico completo.',
					'É o único jeito de entrar. Lemos só seus artistas e músicas mais ouvidos — nada de e-mail, foto ou histórico completo.',
					'Usamos sua localização e sua biblioteca para encontrar o match ideal.',
				),
				$d['how_steps'][0]['text'],
			),
			array( 'how_steps', null, 'title', 'Deu som!', $d['how_steps'][2]['title'] ),
			array( 'features', null, 'text', 'No lugar do chat, uma playlist com o que vocês têm em comum. Dá para salvar direto na sua conta.', $d['features'][1]['text'] ),
			array(
				'features',
				null,
				'text',
				array(
					'Não guardamos seus tokens do Spotify, não pedimos e-mail, a foto é opcional e você apaga tudo com um clique.',
					'Não guardamos seus tokens do Spotify, não pedimos foto nem e-mail e você apaga tudo com um clique.',
				),
				$d['features'][4]['text'],
			),
			array( 'faq', null, 'question', 'Preciso ter Spotify Premium?', 'Preciso ter conta no Spotify?' ),
			array( 'faq', null, 'answer', 'Não. Qualquer conta Spotify serve para entrar. O player de trechos funciona para todos; quem tem Premium e está logado no Spotify ouve a faixa completa.', $q( 'Preciso ter conta no Spotify?' )['answer'] ),
			array( 'faq', null, 'question', 'O que vocês veem da minha conta Spotify?', 'O que vocês recebem da minha conta Google?' ),
			array(
				'faq',
				null,
				'answer',
				array(
					'Só seus artistas e músicas mais ouvidos dos últimos meses, o nome público do seu perfil e o endereço da sua foto do Spotify (que só aparece se você escolher usá-la). Não acessamos e-mail, playlists privadas nem o que você está ouvindo agora.',
					'Só seus artistas e músicas mais ouvidos dos últimos meses e o nome público do seu perfil. Não acessamos e-mail, foto, playlists privadas nem o que você está ouvindo agora.',
				),
				$q( 'O que vocês recebem da minha conta Google?' )['answer'],
			),
			array( 'faq', null, 'answer', 'Em Perfil > Privacidade, clique em "Apagar minha conta". Removemos tudo na hora: perfil, matches, convites e dados vindos do Spotify.', $q( 'Como apago minha conta?' )['answer'] ),
			array( 'faq', null, 'answer', 'Não. A foto é opcional: sem ela, seu cartão mostra suas iniciais e os artistas continuam em destaque. Se quiser, use a foto do seu perfil do Spotify ou envie uma — removemos a localização e outros metadados da imagem, e você pode trocar ou apagar quando quiser.', $q( 'Preciso colocar foto?' )['answer'] ),
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
