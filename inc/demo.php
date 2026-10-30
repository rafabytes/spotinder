<?php
/**
 * Conteúdo inicial: páginas, opções padrão, histórias da campanha, eventos e usuários de teste.
 * Roda na ativação do tema e pode ser reexecutado em Spotinder > Ferramentas.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_switch_theme',
	function () {
		// Trava contra execuções simultâneas (duas requisições logo após ativar o tema).
		$lock = get_option( 'spotinder_seed_lock' );
		if ( $lock && time() - (int) $lock < 5 * MINUTE_IN_SECONDS ) {
			return;
		}
		if ( ! $lock && ! add_option( 'spotinder_seed_lock', time(), '', false ) ) {
			return;
		}
		update_option( 'spotinder_seed_lock', time(), false );

		// Instalação nova já nasce com o conteúdo da versão atual: não há o que migrar.
		if ( ! get_option( 'spotinder_pages' ) ) {
			update_option( 'spotinder_content_version', SPOTINDER_CONTENT_VERSION );
		}

		spotinder_install_tables();
		spotinder_seed_pages();
		spotinder_seed_options();
		spotinder_seed_stories();
		spotinder_seed_events();
		spotinder_seed_demo_users();
		spotinder_seed_menus();
		spotinder_seed_site_icon();
		spotinder_seed_hero_cards();
		flush_rewrite_rules();

		delete_option( 'spotinder_seed_lock' );
	}
);

/**
 * Cria (uma vez) as páginas do tema.
 */
function spotinder_seed_pages() {
	$ids = (array) get_option( 'spotinder_pages', array() );

	$pages = array(
		'home'     => array( 'Início', 'inicio', '', '' ),
		'discover' => array( 'Descobrir', 'descobrir', 'templates/app-discover.php', '' ),
		'matches'  => array( 'Matches', 'matches', 'templates/app-matches.php', '' ),
		'profile'  => array( 'Meu perfil', 'perfil', 'templates/app-profile.php', '' ),
		'privacy'  => array( 'Política de Privacidade', 'privacidade', 'templates/legal.php', 'privacidade' ),
		'terms'    => array( 'Termos de Uso', 'termos', 'templates/legal.php', 'termos' ),
		'safety'   => array( 'Segurança e boas práticas', 'seguranca', 'templates/legal.php', 'seguranca' ),
	);

	foreach ( $pages as $key => list( $title, $slug, $template, $content_file ) ) {
		if ( ! empty( $ids[ $key ] ) && get_post( $ids[ $key ] ) ) {
			continue;
		}

		$content = '';
		if ( $content_file && file_exists( SPOTINDER_DIR . "/content/{$content_file}.html" ) ) {
			$content = str_replace( '{{brand}}', spotinder_brand(), (string) file_get_contents( SPOTINDER_DIR . "/content/{$content_file}.html" ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		$existing = get_page_by_path( $slug );
		$page_id  = $existing ? $existing->ID : wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
			)
		);

		if ( $template ) {
			update_post_meta( $page_id, '_wp_page_template', $template );
		}
		$ids[ $key ] = (int) $page_id;

		if ( $content_file ) {
			spotinder_seed_legal_summary( $page_id, $content_file );
		}
	}

	update_option( 'spotinder_pages', $ids );
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $ids['home'] );
	update_option( 'wp_page_for_privacy_policy', $ids['privacy'] );
}

/**
 * Resumo em linguagem simples das páginas legais.
 *
 * @param int    $page_id Página.
 * @param string $which   privacidade|termos|seguranca.
 * @param bool   $force   Sobrescreve um resumo existente.
 */
function spotinder_seed_legal_summary( $page_id, $which, $force = false ) {
	if ( ! function_exists( 'update_field' ) || ( ! $force && get_field( 'legal_summary', $page_id ) ) ) {
		return;
	}

	$rows = array();
	foreach ( spotinder_legal_summaries()[ $which ] ?? array() as $row ) {
		$rows[] = array(
			'emoji' => $row[0],
			'text'  => $row[1],
		);
	}
	update_field( 'field_sp_legal_legal_summary', $rows, $page_id );
	update_field( 'field_sp_legal_updated', gmdate( 'Ymd' ), $page_id );
}

/**
 * Textos do "Resumo em 30 segundos" das páginas legais (versão atual).
 *
 * @return array<string,array<int,array{0:string,1:string}>>
 */
function spotinder_legal_summaries() {
	return array(
		'privacidade' => array(
			array( '🎧', 'Do Spotify lemos só seus artistas e músicas mais ouvidos e seu nome e foto públicos. Nada de e-mail, playlists privadas ou o que você está ouvindo agora.' ),
			array( '📸', 'Foto é opcional: use a do Spotify, envie uma ou fique só com as iniciais. Fotos enviadas perdem a localização e outros metadados, e você apaga quando quiser.' ),
			array( '📍', 'Localização é opcional: guardamos só uma região aproximada (~5 km) e o município. A posição exata é arredondada no seu aparelho e nunca é guardada.' ),
			array( '🔑', 'Não guardamos seus tokens de acesso do Spotify. Cada atualização exige que você autorize de novo.' ),
			array( '👀', 'Outras pessoas veem seu nome, foto (se você escolher uma), cidade ou região aproximada, bio, intenções e artistas. Suas redes sociais só aparecem para quem deu match com você.' ),
			array( '🚫', 'Não vendemos dados, não fazemos publicidade direcionada e não usamos seus dados para treinar inteligência artificial.' ),
			array( '🗑️', 'Você exporta ou apaga tudo em Perfil > Privacidade, na hora. Contas inativas são apagadas automaticamente.' ),
		),
		'termos'      => array(
			array( '🔞', 'Só para maiores de 18 anos.' ),
			array( '🤝', 'O objetivo é unir pessoas pela música: amizade, shows, bandas — não é um app exclusivamente de namoro.' ),
			array( '📸', 'Foto é opcional. Se usar uma, ela precisa ser sua, sem nudez, crianças, documentos ou contatos — a moderação pode removê-la.' ),
			array( '📍', 'Localização é opcional e sempre aproximada (área de ~5 km). É proibido usar o app para tentar descobrir onde alguém está.' ),
			array( '📱', 'Não há chat. Ao dar match, vocês veem as redes que cada um escolheu mostrar. O contato fora do app é responsabilidade de cada um.' ),
			array( '🚩', 'Respeito sempre. Golpes, assédio, perfis falsos e discurso de ódio levam à suspensão.' ),
		),
		'seguranca'   => array(
			array( '📍', 'Primeiro encontro sempre em lugar público — de preferência um evento da agenda.' ),
			array( '🗣️', 'Avise alguém de confiança sobre onde você vai e com quem.' ),
			array( '💸', 'Nunca envie dinheiro, PIX, documentos ou códigos.' ),
			array( '🛑', 'Desconfortável? Desfaça o match, bloqueie e denuncie.' ),
		),
	);
}

/**
 * Grava os padrões nas opções do ACF (só campos nunca salvos).
 *
 * @param bool $force Sobrescreve valores existentes.
 */
function spotinder_seed_options( $force = false ) {
	if ( ! function_exists( 'update_field' ) ) {
		return;
	}
	if ( function_exists( 'acf_get_field' ) && ! acf_get_field( 'field_sp_brand_name' ) ) {
		spotinder_register_acf();
	}

	foreach ( spotinder_defaults() as $name => $value ) {
		if ( in_array( $name, array( 'spotify_client_id', 'spotify_client_secret', 'logo', 'spotify_logo', 'campaign_story', 'share_image' ), true ) ) {
			continue;
		}
		if ( ! $force && null !== get_field( $name, 'option', false ) ) {
			continue;
		}
		update_field( 'field_sp_' . $name, $value, 'option' );
	}
}

/**
 * Histórias da campanha #NinguémOuveSozinho.
 */
function spotinder_seed_stories() {
	$stories = array(
		array(
			'slug'     => 'quatro-desconhecidos-um-refrao',
			'title'    => 'Quatro desconhecidos, um refrão',
			'excerpt'  => 'Uma professora aposentada, um estudante de 19 anos, uma enfermeira do plantão noturno e um DJ skatista. O que eles tinham em comum? Milton Nascimento no topo do Spotify.',
			'subtitle' => 'Como o Clube da Esquina juntou de novo gente de Belo Horizonte — 50 anos depois.',
			'city'     => 'Belo Horizonte, MG',
			'hashtag'  => '#NinguémOuveSozinho',
			'quote'    => 'Não era sobre amor. Era sobre não ouvir sozinha. Eu passei dois anos escutando esse disco na cozinha, achando que ninguém mais ligava.',
			'author'   => 'Dona Cida, 64',
			'track'    => 'Clube da Esquina Nº 2 — Milton Nascimento',
			'artists'  => "Milton Nascimento\nLô Borges\nGal Costa",
			'people'   => array(
				array( 'Dona Cida', 64, 'Professora de história aposentada', 'Milton Nascimento' ),
				array( 'Theo', 19, 'Estudante de design, descobriu o disco num vídeo de 15 segundos', 'Milton Nascimento' ),
				array( 'Joana', 31, 'Enfermeira, ouve MPB no caminho do plantão', 'Liniker' ),
				array( 'Rafa', 27, 'Skatista e DJ, vive de samplear discos antigos', 'Milton Nascimento' ),
			),
			'chapters' => array(
				array( 'Faixa 1', 'O cartão sem foto', 'Theo abriu o app esperando ver gente da faculdade. O primeiro cartão tinha Milton Nascimento, Lô Borges e Beto Guedes no topo, 91% de compatibilidade e uma cidade: Belo Horizonte. Deslizou para a direita sem pensar. Do outro lado, Dona Cida tinha entrado no app porque a neta insistiu. "Achei que ia ser coisa de namoro. Quando vi que era um menino de 19 anos que ouvia o mesmo disco que eu, ri sozinha."' ),
				array( 'Faixa 2', 'A trilha sonora', 'O match tocou "Clube da Esquina Nº 2" — a música que estava no top dos dois. Sem chat, o app mostrou o Instagram de cada um e uma playlist em comum. Theo mandou um direct com uma pergunta só: "a senhora já foi na esquina da Divinópolis com a Paraisópolis?". Ela tinha ido. Em 1972.' ),
				array( 'Faixa 3', 'A audição coletiva', 'Na agenda do app tinha uma audição coletiva do disco inteiro num bar de Santa Tereza. Theo convidou Dona Cida pelo próprio app. Joana marcou presença porque o evento apareceu como "recomendado para você". Rafa foi porque viu que dois matches dele também iam.' ),
				array( 'Faixa 4', 'O lado B', 'Quatro pessoas que nunca teriam se cruzado sentaram na mesma mesa. Dona Cida contou como era a cidade nos anos 70; Rafa mostrou os samples que fez com o disco; Joana chorou em "Cais". Hoje eles organizam o Clube do Lado B: uma audição de disco inteiro por mês, com mais de 40 pessoas. Nenhum casal. Muitos amigos.' ),
			),
			'content'  => '<p>Essa história é a inspiração da campanha <strong>#NinguémOuveSozinho</strong>. Ela mostra o que queremos ser: uma rede que aproxima gente pelo que ela ouve — não pela foto, não pela idade, não pelo bairro.</p><p><em>História ilustrativa criada para a campanha de lançamento, inspirada em relatos da comunidade. Os perfis correspondentes no app são usuários de teste.</em></p>',
		),
		array(
			'slug'     => 'a-banda-que-nasceu-num-swipe',
			'title'    => 'A banda que nasceu num swipe',
			'excerpt'  => 'Kenji queria alguém para tocar Luiz Gonzaga com distorção. Encontrou Bia e Marcos — e um nome de banda impronunciável.',
			'subtitle' => 'Forró, punk e três matches que viraram ensaio toda quinta.',
			'city'     => 'São Paulo, SP',
			'hashtag'  => '#NinguémOuveSozinho',
			'quote'    => 'Marquei "montar uma banda" no perfil meio de brincadeira. Duas semanas depois eu tinha uma baterista e um sanfoneiro.',
			'author'   => 'Kenji, 29',
			'track'    => 'Asa Branca — Luiz Gonzaga',
			'artists'  => "Luiz Gonzaga\nRamones\nRaimundos",
			'people'   => array(
				array( 'Kenji', 29, 'Guitarrista e analista de dados', 'Luiz Gonzaga' ),
				array( 'Bia', 24, 'Baterista nas horas vagas', 'Raimundos' ),
				array( 'Marcos', 35, 'Sanfoneiro que cresceu ouvindo The Clash', 'Dominguinhos' ),
			),
			'chapters' => array(
				array( 'Faixa 1', 'A intenção certa', 'O filtro de intenções fez o trabalho: Kenji só via quem também queria montar uma banda. Luiz Gonzaga e Ramones no mesmo cartão eram raros o suficiente para virar match na hora.' ),
				array( 'Faixa 2', 'O convite', 'Sem chat, Kenji usou o convite "Tocar junto / jam" e sugeriu um estúdio de ensaio no centro. Bia aceitou. Marcos aceitou o convite dela uma semana depois.' ),
				array( 'Faixa 3', 'O primeiro show', 'Hoje a banda toca em festas da comunidade. A playlist em comum dos três virou o setlist do primeiro show.' ),
			),
			'content'  => '<p><em>História ilustrativa criada para a campanha de lançamento. Os perfis correspondentes no app são usuários de teste.</em></p>',
		),
		array(
			'slug'     => 'companhia-para-o-festival',
			'title'    => 'Companhia para o festival',
			'excerpt'  => 'Aos 45, Luísa tinha o ingresso e ninguém para ir junto. O app achou duas pessoas que ouviam Chico Science tanto quanto ela.',
			'subtitle' => 'Nenhum amigo topou. Três desconhecidos toparam.',
			'city'     => 'Recife, PE',
			'hashtag'  => '#NinguémOuveSozinho',
			'quote'    => 'Eu não queria namorado, eu queria alguém para cantar A Praieira gritando do meu lado.',
			'author'   => 'Luísa, 45',
			'track'    => 'A Praieira — Chico Science & Nação Zumbi',
			'artists'  => "Chico Science & Nação Zumbi\nAlceu Valença",
			'people'   => array(
				array( 'Luísa', 45, 'Arquiteta e mãe de dois adolescentes', 'Chico Science & Nação Zumbi' ),
				array( 'Marcos', 35, 'Sanfoneiro', 'Dominguinhos' ),
				array( 'Seu Bené', 70, 'Ex-radialista', 'Luiz Gonzaga' ),
			),
			'chapters' => array(
				array( 'Faixa 1', 'O ingresso sobrando', 'Luísa marcou "Companhia para shows" e foi direto na agenda. Viu quais matches iam ao mesmo festival e mandou convites pelo app.' ),
				array( 'Faixa 2', 'O encontro', 'Combinaram de se encontrar no portão principal, de dia, cada um indo por conta própria — como recomendam as dicas de segurança do app.' ),
			),
			'content'  => '<p><em>História ilustrativa criada para a campanha de lançamento. Os perfis correspondentes no app são usuários de teste.</em></p>',
		),
	);

	$first_id = 0;
	foreach ( $stories as $i => $s ) {
		$existing = get_page_by_path( $s['slug'], OBJECT, 'spot_story' );
		if ( $existing ) {
			$first_id = $first_id ? $first_id : $existing->ID;
			continue;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'spot_story',
				'post_status'  => 'publish',
				'post_title'   => $s['title'],
				'post_name'    => $s['slug'],
				'post_excerpt' => $s['excerpt'],
				'post_content' => $s['content'],
				'post_date'    => wp_date( 'Y-m-d H:i:s', time() - ( $i + 1 ) * HOUR_IN_SECONDS ),
			)
		);
		$first_id = $first_id ? $first_id : $post_id;

		$people = array();
		foreach ( $s['people'] as $p ) {
			$people[] = array(
				'name'   => $p[0],
				'age'    => $p[1],
				'role'   => $p[2],
				'artist' => $p[3],
			);
		}
		$chapters = array();
		foreach ( $s['chapters'] as $c ) {
			$chapters[] = array(
				'label' => $c[0],
				'title' => $c[1],
				'text'  => $c[2],
			);
		}

		$fields = array(
			'story_subtitle'         => $s['subtitle'],
			'story_city'             => $s['city'],
			'story_hashtag'          => $s['hashtag'],
			'story_quote'            => $s['quote'],
			'story_quote_author'     => $s['author'],
			'story_soundtrack_label' => $s['track'],
			'story_shared_artists'   => $s['artists'],
		);

		if ( function_exists( 'update_field' ) ) {
			foreach ( $fields as $name => $value ) {
				update_field( 'field_sp_' . $name, $value, $post_id );
			}
			update_field( 'field_sp_story_story_people', $people, $post_id );
			update_field( 'field_sp_story_story_chapters', $chapters, $post_id );
		} else {
			foreach ( $fields as $name => $value ) {
				update_post_meta( $post_id, $name, $value );
			}
		}
	}

	if ( $first_id && function_exists( 'update_field' ) && ! get_field( 'campaign_story', 'option' ) ) {
		update_field( 'field_sp_campaign_story', $first_id, 'option' );
	}
}

/**
 * Eventos de exemplo (datas relativas a hoje).
 */
function spotinder_seed_events() {
	$types = array(
		'audicao'  => 'Audição coletiva',
		'festa'    => 'Festa',
		'karaoke'  => 'Karaokê',
		'feira'    => 'Feira de discos',
		'esporte'  => 'Música + movimento',
	);
	$term_ids = array();
	foreach ( $types as $slug => $name ) {
		$term = term_exists( $slug, 'spot_event_type' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'spot_event_type', array( 'slug' => $slug ) );
		}
		$term_ids[ $slug ] = is_array( $term ) ? (int) $term['term_id'] : 0;
	}

	$events = array(
		array( 'audicao-clube-da-esquina', 'Audição coletiva: Clube da Esquina do lado A ao lado B', 'audicao', 9, '19:30', 'Bar do Lado B', 'Santa Tereza, Belo Horizonte', 'Belo Horizonte', 'Gratuito (inscrição)', true, "Milton Nascimento\nLô Borges\nBeto Guedes\nToninho Horta", 'O disco inteiro, em vinil, no volume certo — e sem celular na mão durante a audição. Depois, roda de conversa e troca de indicações. Encontro oficial da comunidade.' ),
		array( 'forro-punk-quinta', 'Quinta Forró-Punk', 'festa', 14, '21:00', 'Galpão Asa Branca', 'Barra Funda, São Paulo', 'São Paulo', 'R$ 30', false, "Luiz Gonzaga\nRamones\nRaimundos\nDominguinhos\nPitty", 'Sanfona com distorção. DJs da comunidade alternam forró pé de serra e punk rock a noite toda.' ),
		array( 'karaoke-lado-b', 'Karaokê só de lado B', 'karaoke', 18, '20:00', 'Estúdio Refrão', 'Pinheiros, São Paulo', 'São Paulo', 'R$ 20', true, "Gal Costa\nTim Maia\nMarisa Monte\nParamore\nTaylor Swift", 'Proibido cantar o hit. Só faixas escondidas do fim do disco. Encontro oficial da comunidade — venha com um match ou venha conhecer.' ),
		array( 'feira-discos-recife', 'Feira de discos + troca de indicações', 'feira', 23, '14:00', 'Pátio de São Pedro', 'Centro, Recife', 'Recife', 'Gratuito', false, "Chico Science & Nação Zumbi\nAlceu Valença\nLenine\nLuiz Gonzaga", 'Expositores de vinil, fitas e CDs. Traga um disco para trocar e uma música para indicar.' ),
		array( 'corrida-synth', 'Corrida 5 km com playlist coletiva', 'esporte', 30, '07:00', 'Parque da Redenção', 'Porto Alegre', 'Porto Alegre', 'Gratuito', true, "Tame Impala\nArctic Monkeys\nThe Strokes\nTerno Rei", 'Cada participante adiciona uma faixa à playlist do grupo. Ritmo leve, conversa garantida no fim.' ),
	);

	foreach ( $events as $e ) {
		list( $slug, $title, $type, $in_days, $time, $venue, $address, $city, $price, $official, $artists, $content ) = $e;

		if ( get_page_by_path( $slug, OBJECT, 'spot_event' ) ) {
			continue;
		}

		$start   = wp_date( 'Y-m-d', time() + $in_days * DAY_IN_SECONDS ) . ' ' . $time . ':00';
		$end     = gmdate( 'Y-m-d H:i:s', strtotime( $start . ' UTC' ) + 4 * HOUR_IN_SECONDS ); // Aritmética pura sobre o horário local.
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'spot_event',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_excerpt' => wp_trim_words( $content, 22 ),
				'post_content' => '<p>' . $content . '</p><p><em>Evento de demonstração.</em></p>',
			)
		);

		wp_set_object_terms( $post_id, $term_ids[ $type ] ? array( $term_ids[ $type ] ) : array(), 'spot_event_type' );

		$fields = array(
			'event_start'    => $start,
			'event_end'      => $end,
			'event_venue'    => $venue,
			'event_address'  => $address,
			'event_city'     => $city,
			'event_price'    => $price,
			'event_official' => $official ? 1 : 0,
			'event_artists'  => $artists,
		);
		foreach ( $fields as $name => $value ) {
			if ( function_exists( 'update_field' ) ) {
				update_field( 'field_sp_' . $name, $value, $post_id );
			} else {
				update_post_meta( $post_id, $name, $value );
			}
		}
	}
}

/**
 * Perfis de teste. Artistas e músicas reais; pessoas fictícias.
 *
 * @return array[]
 */
function spotinder_demo_people() {
	return array(
		array( 'cida', 'Dona Cida', 'Belo Horizonte', 'Professora de história aposentada. Tenho vinil, tempo livre e muita história do Clube da Esquina pra contar.', array( 'amizade', 'indicacoes', 'shows' ), array( 'Milton Nascimento', 'Lô Borges', 'Gal Costa', 'Elis Regina', 'Beto Guedes', 'Chico Buarque', 'Caetano Veloso', 'Toninho Horta', 'Nana Caymmi', 'Gilberto Gil' ), array( 'Clube da Esquina Nº 2|Milton Nascimento', 'Trem Azul|Lô Borges', 'Travessia|Milton Nascimento', 'Como Nossos Pais|Elis Regina', 'Vapor Barato|Gal Costa', 'Sol de Primavera|Beto Guedes', 'Cálice|Chico Buarque' ) ),
		array( 'theo', 'Theo', 'Belo Horizonte', 'Design na UFMG. Descobri o Milton por um vídeo de 15 segundos e nunca mais voltei.', array( 'amizade', 'shows', 'indicacoes' ), array( 'Milton Nascimento', 'Tame Impala', 'Lô Borges', 'Tim Bernardes', 'Terno Rei', 'Clairo', 'Frank Ocean', 'Gal Costa', 'Boogarins', 'Mac DeMarco' ), array( 'Clube da Esquina Nº 2|Milton Nascimento', 'Let It Happen|Tame Impala', 'Trem Azul|Lô Borges', 'Recomeçar|Tim Bernardes', 'Bags|Clairo', 'Pink + White|Frank Ocean', 'Vapor Barato|Gal Costa' ) ),
		array( 'joana', 'Joana', 'Belo Horizonte', 'Enfermeira de plantão noturno. MPB no fone até chegar no hospital. Procuro companhia pra show de domingo.', array( 'amizade', 'shows', 'role' ), array( 'Liniker', 'Milton Nascimento', 'Gal Costa', 'Luedji Luna', 'Xênia França', 'Elis Regina', 'Djavan', 'Céu', 'Maria Bethânia', 'Marisa Monte' ), array( 'Clube da Esquina Nº 2|Milton Nascimento', 'Baby 95|Liniker', 'Vapor Barato|Gal Costa', 'Um Corpo no Mundo|Luedji Luna', 'Como Nossos Pais|Elis Regina', 'Oceano|Djavan' ) ),
		array( 'rafa', 'Rafa', 'Belo Horizonte', 'Skate de dia, discotecagem de noite. Sampleio disco antigo e adoro quem me apresenta coisa nova.', array( 'indicacoes', 'banda', 'role' ), array( 'Milton Nascimento', 'BaianaSystem', 'Kendrick Lamar', 'Lô Borges', 'Tim Maia', 'Jorge Ben Jor', 'Marcos Valle', 'Azymuth', 'J Dilla', 'Tyler, The Creator' ), array( 'Clube da Esquina Nº 2|Milton Nascimento', 'Lucro (Descomprimindo)|BaianaSystem', 'Trem Azul|Lô Borges', 'Azul da Cor do Mar|Tim Maia', 'Taj Mahal|Jorge Ben Jor', 'Jazz Carnival|Azymuth', 'Alright|Kendrick Lamar' ) ),
		array( 'kenji', 'Kenji', 'São Paulo', 'Guitarrista. Quero tocar Luiz Gonzaga com distorção. Procuro baterista e sanfoneiro(a).', array( 'banda', 'shows', 'role' ), array( 'Luiz Gonzaga', 'Ramones', 'Raimundos', 'Dominguinhos', 'Pitty', 'Os Paralamas do Sucesso', 'Fresno', 'Bad Religion', 'Elba Ramalho', 'Mestrinho' ), array( 'Asa Branca|Luiz Gonzaga', 'Eu Quero Ver o Oco|Raimundos', 'Blitzkrieg Bop|Ramones', 'Xote das Meninas|Luiz Gonzaga', 'Me Adora|Pitty', 'Lanterna dos Afogados|Os Paralamas do Sucesso' ) ),
		array( 'bia', 'Bia', 'São Paulo', 'Bateria, café forte e pogo. Se tiver sanfona, melhor ainda.', array( 'banda', 'role', 'amizade' ), array( 'Raimundos', 'Luiz Gonzaga', 'Pitty', 'Paramore', 'Elba Ramalho', 'Fresno', 'Charlie Brown Jr.', 'Ramones', 'Alceu Valença', 'Mestrinho' ), array( 'Eu Quero Ver o Oco|Raimundos', 'Asa Branca|Luiz Gonzaga', 'Me Adora|Pitty', 'Misery Business|Paramore', 'Anunciação|Alceu Valença', 'Blitzkrieg Bop|Ramones' ) ),
		array( 'marcos', 'Marcos', 'São Paulo', 'Sanfoneiro que cresceu ouvindo The Clash. Topo jam, feira de disco e forró até amanhecer.', array( 'banda', 'shows', 'indicacoes' ), array( 'Dominguinhos', 'Alceu Valença', 'Ramones', 'Raimundos', 'Chico Science & Nação Zumbi', 'Luiz Gonzaga', 'Mundo Livre S/A', 'The Clash', 'Elba Ramalho', 'Pitty' ), array( 'Asa Branca|Luiz Gonzaga', 'Blitzkrieg Bop|Ramones', 'A Praieira|Chico Science & Nação Zumbi', 'Anunciação|Alceu Valença', 'London Calling|The Clash', 'Eu Quero Ver o Oco|Raimundos' ) ),
		array( 'luisa', 'Luísa', 'Recife', 'Arquiteta, 45, mãe de dois. Tenho ingresso sobrando e zero amigos que gostam de manguebeat.', array( 'shows', 'amizade' ), array( 'Chico Science & Nação Zumbi', 'Alceu Valença', 'Coldplay', 'Lenine', 'Marisa Monte', 'Florence + The Machine', 'Arctic Monkeys', 'Nando Reis', 'Céu', 'Os Paralamas do Sucesso' ), array( 'A Praieira|Chico Science & Nação Zumbi', 'Anunciação|Alceu Valença', 'Yellow|Coldplay', 'Paciência|Lenine', 'Do I Wanna Know?|Arctic Monkeys', 'Dog Days Are Over|Florence + The Machine' ) ),
		array( 'dandara', 'Dandara', 'Salvador', 'Produtora cultural. Carnaval o ano inteiro no fone. Bora pra rua?', array( 'role', 'shows', 'crush' ), array( 'BaianaSystem', 'Luedji Luna', 'Liniker', 'Beyoncé', 'Russo Passapusso', 'Xênia França', 'Margareth Menezes', 'Gilberto Gil', 'Anitta', 'Djavan' ), array( 'Lucro (Descomprimindo)|BaianaSystem', 'Um Corpo no Mundo|Luedji Luna', 'Baby 95|Liniker', 'CUFF IT|Beyoncé', 'Andar com Fé|Gilberto Gil', 'Oceano|Djavan' ) ),
		array( 'pedro', 'Pedro', 'Porto Alegre', 'Programador, corredor e colecionador de camisetas de banda. Indie de 2006 é meu conforto.', array( 'amizade', 'shows', 'indicacoes' ), array( 'Arctic Monkeys', 'The Strokes', 'Tame Impala', 'Radiohead', 'Engenheiros do Hawaii', 'Terno Rei', 'Boogarins', 'Mac DeMarco', 'Arcade Fire', 'Interpol' ), array( 'Do I Wanna Know?|Arctic Monkeys', 'Last Nite|The Strokes', 'Let It Happen|Tame Impala', 'Karma Police|Radiohead', 'Era Um Garoto Que Como Eu Amava os Beatles e os Rolling Stones|Engenheiros do Hawaii', 'Chamber of Reflection|Mac DeMarco' ) ),
		array( 'aline', 'Aline', 'Curitiba', 'Sofro com letra triste desde 2008. Procuro gente pra cantar junto no karaokê sem julgamento.', array( 'amizade', 'role', 'crush' ), array( 'Taylor Swift', 'Olivia Rodrigo', 'Clairo', 'Phoebe Bridgers', 'Paramore', 'Lorde', 'boygenius', 'Mitski', 'Florence + The Machine', 'Tim Bernardes' ), array( 'Cruel Summer|Taylor Swift', 'drivers license|Olivia Rodrigo', 'Bags|Clairo', 'Motion Sickness|Phoebe Bridgers', 'Misery Business|Paramore', 'Green Light|Lorde' ) ),
		array( 'gui', 'Gui', 'Rio de Janeiro', 'Samba-rock, rap e boogie brasileiro. Discotecagem em festa de quintal é comigo.', array( 'role', 'indicacoes', 'banda' ), array( 'Jorge Ben Jor', 'Tim Maia', 'Marcos Valle', 'Azymuth', 'Seu Jorge', 'Djavan', 'Kendrick Lamar', "Racionais MC's", 'BK', 'Anderson .Paak' ), array( 'Taj Mahal|Jorge Ben Jor', 'Azul da Cor do Mar|Tim Maia', 'Jazz Carnival|Azymuth', 'Burguesinha|Seu Jorge', 'Alright|Kendrick Lamar', 'Oceano|Djavan' ) ),
		array( 'malu', 'Malu', 'Florianópolis', 'Violão na praia, MPB nova e velha. Quero amigos pra ir em show acústico.', array( 'amizade', 'shows', 'indicacoes' ), array( 'Marisa Monte', 'Tribalistas', 'Céu', 'Tim Bernardes', 'Clairo', 'Caetano Veloso', 'Gal Costa', 'Rubel', 'Anavitória', 'Phoebe Bridgers' ), array( 'Ainda Bem|Marisa Monte', 'Velha Infância|Tribalistas', 'Recomeçar|Tim Bernardes', 'Bags|Clairo', 'Vapor Barato|Gal Costa', 'Partilhar|Rubel' ) ),
		array( 'bene', 'Seu Bené', 'Fortaleza', 'Fui radialista por 40 anos. Hoje quero ensinar forró pra quem acha que é coisa de antigamente.', array( 'amizade', 'indicacoes', 'shows' ), array( 'Luiz Gonzaga', 'Dominguinhos', 'Fagner', 'Belchior', 'Ednardo', 'Elba Ramalho', 'Alceu Valença', 'Zé Ramalho', 'Gilberto Gil', 'Chico Buarque' ), array( 'Asa Branca|Luiz Gonzaga', 'Xote das Meninas|Luiz Gonzaga', 'Como Nossos Pais|Belchior', 'Anunciação|Alceu Valença', 'Admirável Gado Novo|Zé Ramalho', 'Andar com Fé|Gilberto Gil' ) ),
	);
}

/**
 * Cria/atualiza os usuários de teste.
 *
 * @return int Quantidade.
 */
function spotinder_seed_demo_users() {
	$count = 0;
	foreach ( spotinder_demo_people() as $p ) {
		list( $slug, $name, $city, $bio, $intentions, $artists, $tracks ) = $p;

		$login   = 'demo_' . $slug;
		$user    = get_user_by( 'login', $login );
		$user_id = $user ? $user->ID : wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'display_name' => $name,
				'nickname'     => $name,
				'role'         => 'subscriber',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			continue;
		}

		$cache = (array) get_option( 'spotinder_demo_catalog', array() );

		$artist_rows = array();
		foreach ( $artists as $artist ) {
			$artist_rows[] = $cache[ 'artist:' . spotinder_akey( $artist ) ] ?? array(
				'id'     => '',
				'name'   => $artist,
				'image'  => '',
				'url'    => '',
				'genres' => array(),
			);
		}

		$track_rows = array();
		foreach ( $tracks as $track ) {
			list( $title, $artist ) = explode( '|', $track );
			$track_rows[]           = $cache[ 'track:' . spotinder_akey( $title . '|' . $artist ) ] ?? array(
				'id'      => '',
				'name'    => $title,
				'artists' => array(
					array(
						'id'   => '',
						'name' => $artist,
					),
				),
				'album'   => '',
				'image'   => '',
				'url'     => '',
			);
		}

		$now = current_time( 'mysql' );
		update_user_meta( $user_id, 'spotinder_spotify_id', 'demo-' . $slug );
		update_user_meta( $user_id, 'spotinder_is_demo', 1 );
		update_user_meta( $user_id, 'spotinder_city', $city );
		$place = spotinder_find_municipality( $city );
		if ( $place ) {
			spotinder_set_location( $user_id, $place[2], $place[3] );
		}
		update_user_meta( $user_id, 'spotinder_bio', $bio );
		update_user_meta( $user_id, 'spotinder_intentions', $intentions );
		update_user_meta(
			$user_id,
			'spotinder_socials',
			array(
				'instagram' => 'demo.spotinder.' . $slug,
				'website'   => 'https://example.com/' . $slug,
			)
		);
		update_user_meta( $user_id, 'spotinder_visible', 1 );
		update_user_meta( $user_id, 'spotinder_share_events', 1 );
		update_user_meta( $user_id, 'spotinder_onboarded', $now );
		update_user_meta(
			$user_id,
			'spotinder_consents',
			array(
				'age'     => $now,
				'terms'   => $now,
				'share'   => $now,
				'social'  => $now,
				'version' => (string) spotinder_opt( 'policy_version' ),
			)
		);
		spotinder_save_tops( $user_id, $artist_rows, $track_rows );
		spotinder_touch( $user_id, true );
		++$count;
	}

	spotinder_seed_demo_rsvps();
	return $count;
}

/**
 * Presenças dos usuários de teste nos eventos (deixa "quem vai" com conteúdo).
 */
function spotinder_seed_demo_rsvps() {
	foreach ( spotinder_upcoming_events( 20 ) as $event ) {
		foreach ( spotinder_demo_people() as $p ) {
			$user = get_user_by( 'login', 'demo_' . $p[0] );
			if ( $user && spotinder_event_matches_user( $event->ID, $user->ID ) ) {
				spotinder_set_rsvp( $event->ID, $user->ID, 'going' );
			}
		}
	}
}

/**
 * Remove todos os usuários de teste.
 *
 * @return int Quantidade removida.
 */
function spotinder_remove_demo_users() {
	$ids = get_users(
		array(
			'meta_key' => 'spotinder_is_demo',
			'fields'   => 'ID',
			'number'   => -1,
		)
	);
	foreach ( $ids as $id ) {
		spotinder_delete_member( (int) $id );
	}
	return count( $ids );
}

/**
 * Completa os perfis de teste com IDs, imagens e links reais do catálogo do Spotify
 * (token do app, apenas metadados públicos). Resultado fica em cache.
 *
 * @return array{ok:int,fail:int}|WP_Error
 */
function spotinder_enrich_demo_catalog() {
	if ( ! spotinder_spotify_configured() ) {
		return new WP_Error( 'spotinder_not_configured', 'Configure o Client ID e o Client Secret primeiro.' );
	}
	$token = spotinder_spotify_app_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}

	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
	}

	$cache = (array) get_option( 'spotinder_demo_catalog', array() );
	$ok    = 0;
	$fail  = 0;

	// Artistas dos perfis de teste + dos cartões animados da home.
	$artists = spotinder_hero_artist_names();
	foreach ( spotinder_demo_people() as $p ) {
		$artists = array_merge( $artists, $p[5] );
	}
	foreach ( array_unique( $artists ) as $artist ) {
		$key = 'artist:' . spotinder_akey( $artist );
		if ( isset( $cache[ $key ] ) ) {
			continue;
		}
		$found = spotinder_spotify_search_one( 'artist:"' . $artist . '"', 'artist' );
		if ( is_wp_error( $found ) ) {
			++$fail;
			continue;
		}
		$normalized         = spotinder_normalize_artist( $found );
		$normalized['name'] = $artist; // Mantém a grafia usada no tema.
		$cache[ $key ]      = $normalized;
		++$ok;
	}

	foreach ( spotinder_demo_people() as $p ) {
		foreach ( $p[6] as $track ) {
			list( $title, $artist ) = explode( '|', $track );
			$key                    = 'track:' . spotinder_akey( $title . '|' . $artist );
			if ( isset( $cache[ $key ] ) ) {
				continue;
			}
			$found = spotinder_spotify_search_one( 'track:"' . $title . '" artist:"' . $artist . '"', 'track' );
			if ( is_wp_error( $found ) ) {
				++$fail;
				continue;
			}
			$normalized                       = spotinder_normalize_track( $found );
			$normalized['name']               = $title;
			$normalized['artists'][0]['name'] = $artist;
			$cache[ $key ]                    = $normalized;
			++$ok;
		}
	}

	update_option( 'spotinder_demo_catalog', $cache, false );
	spotinder_seed_demo_users();

	return compact( 'ok', 'fail' );
}

/**
 * Menus iniciais.
 */
function spotinder_seed_menus() {
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	$ids       = (array) get_option( 'spotinder_pages', array() );

	$archive = fn( $title, $post_type ) => array(
		'menu-item-title'  => $title,
		'menu-item-type'   => 'post_type_archive',
		'menu-item-object' => $post_type,
	);
	$page    = fn( $title, $key ) => empty( $ids[ $key ] ) ? null : array(
		'menu-item-title'     => $title,
		'menu-item-type'      => 'post_type',
		'menu-item-object'    => 'page',
		'menu-item-object-id' => (int) $ids[ $key ],
	);

	$menus = array(
		'primary' => array(
			'Menu principal',
			array(
				$archive( 'Eventos', 'spot_event' ),
				$archive( 'Histórias', 'spot_story' ),
				$page( 'Segurança', 'safety' ),
			),
		),
		'legal'   => array(
			'Links legais',
			array(
				$page( 'Política de Privacidade', 'privacy' ),
				$page( 'Termos de Uso', 'terms' ),
				$page( 'Segurança', 'safety' ),
			),
		),
	);

	foreach ( $menus as $location => list( $name, $items ) ) {
		if ( ! empty( $locations[ $location ] ) ) {
			continue;
		}
		$menu    = wp_get_nav_menu_object( $name );
		$menu_id = $menu ? $menu->term_id : wp_create_nav_menu( $name );
		if ( is_wp_error( $menu_id ) ) {
			continue;
		}
		if ( ! $menu ) {
			foreach ( array_filter( $items ) as $item ) {
				wp_update_nav_menu_item( $menu_id, 0, $item + array( 'menu-item-status' => 'publish' ) );
			}
		}
		$locations[ $location ] = $menu_id;
	}

	set_theme_mod( 'nav_menu_locations', $locations );
}
