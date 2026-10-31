<?php
/**
 * Páginas de opções e grupos de campos do ACF PRO, registrados em código
 * (versionados com o tema). Tudo que aparece no site é editável por aqui.
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Monta um campo ACF com chave determinística e valor padrão do tema.
 *
 * @param string $name  Nome.
 * @param string $label Rótulo.
 * @param string $type  Tipo ACF.
 * @param array  $extra Configurações extras.
 * @param string $scope Prefixo da chave (para subcampos).
 */
function spotinder_acf_field( $name, $label, $type = 'text', $extra = array(), $scope = '' ) {
	$defaults = spotinder_defaults();
	$field    = array(
		'key'   => 'field_sp_' . ( $scope ? $scope . '_' : '' ) . $name,
		'name'  => $name,
		'label' => $label,
		'type'  => $type,
	);

	if ( ! $scope && isset( $defaults[ $name ] ) && is_scalar( $defaults[ $name ] ) && ! in_array( $type, array( 'image', 'post_object' ), true ) ) {
		$field['default_value'] = $defaults[ $name ];
	}

	if ( 'textarea' === $type ) {
		$field['rows']      = 3;
		$field['new_lines'] = '';
	}

	return array_merge( $field, $extra );
}

/**
 * Aba ACF.
 *
 * @param string $id    ID.
 * @param string $label Rótulo.
 */
function spotinder_acf_tab( $id, $label ) {
	return array(
		'key'       => 'field_sp_tab_' . $id,
		'label'     => $label,
		'name'      => '',
		'type'      => 'tab',
		'placement' => 'left',
	);
}

/**
 * Repeater com subcampos simples.
 *
 * @param string $name   Nome.
 * @param string $label  Rótulo.
 * @param array  $subs   [nome => [rótulo, tipo]].
 * @param string $button Texto do botão.
 * @param string $scope  Prefixo (para repeaters em posts).
 */
function spotinder_acf_repeater( $name, $label, $subs, $button = 'Adicionar', $scope = '' ) {
	// Subcampos usam "__" (ex.: field_sp_features__title) para não colidir com campos
	// irmãos como "features_title".
	$sub_fields = array();
	foreach ( $subs as $sub_name => $conf ) {
		$sub_fields[] = spotinder_acf_field( $sub_name, $conf[0], $conf[1] ?? 'text', $conf[2] ?? array(), ( $scope ? $scope . '_' : '' ) . $name . '_' );
	}

	return spotinder_acf_field(
		$name,
		$label,
		'repeater',
		array(
			'layout'       => 'block',
			'button_label' => $button,
			'sub_fields'   => $sub_fields,
		),
		$scope
	);
}

add_action( 'acf/init', 'spotinder_register_acf' );

/**
 * Registra páginas de opções e grupos.
 */
function spotinder_register_acf() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	if ( function_exists( 'acf_add_options_page' ) ) {
		acf_add_options_page(
			array(
				'page_title' => 'Spotinder — Configurações',
				'menu_title' => 'Spotinder',
				'menu_slug'  => 'spotinder-settings',
				'capability' => 'manage_options',
				'icon_url'   => 'dashicons-format-audio',
				'position'   => 3,
				'redirect'   => false,
			)
		);
		acf_add_options_sub_page(
			array(
				'page_title'  => 'Página inicial',
				'menu_title'  => 'Página inicial',
				'menu_slug'   => 'spotinder-home',
				'parent_slug' => 'spotinder-settings',
			)
		);
		acf_add_options_sub_page(
			array(
				'page_title'  => 'Textos do app',
				'menu_title'  => 'Textos do app',
				'menu_slug'   => 'spotinder-app',
				'parent_slug' => 'spotinder-settings',
			)
		);
		acf_add_options_sub_page(
			array(
				'page_title'  => 'Rodapé',
				'menu_title'  => 'Rodapé',
				'menu_slug'   => 'spotinder-footer',
				'parent_slug' => 'spotinder-settings',
			)
		);
	}

	$options = function ( $slug ) {
		return array(
			array(
				array(
					'param'    => 'options_page',
					'operator' => '==',
					'value'    => $slug,
				),
			),
		);
	};

	$js_origin = esc_html( untrailingslashit( preg_replace( '#^(https?://[^/]+).*$#', '$1', home_url() ) ) );

	// Geral.
	acf_add_local_field_group(
		array(
			'key'      => 'group_sp_general',
			'title'    => 'Configurações gerais',
			'location' => $options( 'spotinder-settings' ),
			'fields'   => array(
				spotinder_acf_tab( 'brand', 'Marca' ),
				spotinder_acf_field(
					'brand_name',
					'Nome da marca',
					'text',
					array( 'instructions' => 'Atenção: a Developer Policy do Spotify proíbe nomes de apps que comecem com "Spot" ou soem parecido com Spotify. Defina o nome definitivo antes de pedir aprovação ao Spotify.' )
				),
				spotinder_acf_field( 'brand_tagline', 'Slogan' ),
				spotinder_acf_field( 'logo', 'Logo (opcional)', 'image', array( 'return_format' => 'url', 'instructions' => 'Se vazio, o nome da marca é exibido em texto.' ) ),
				spotinder_acf_field( 'color_primary', 'Cor principal', 'color_picker' ),
				spotinder_acf_field( 'color_accent', 'Cor de destaque (match)', 'color_picker' ),
				spotinder_acf_field( 'color_highlight', 'Cor de realce', 'color_picker' ),

				spotinder_acf_tab( 'share', 'Ícones e compartilhamento' ),
				spotinder_acf_field(
					'share_help',
					'Ícone do site',
					'message',
					array(
						'message'  => sprintf(
							'O ícone (favicon, ícone do celular e do app) é o <strong>Ícone do site</strong> do WordPress: <a href="%s">Aparência &gt; Personalizar &gt; Identidade do site</a>. Use uma imagem quadrada de pelo menos 512×512 px. Enquanto o ícone padrão do tema estiver em uso, o site também publica a versão SVG e a versão "maskable" (Android).<br><br>Para regerar o ícone e a imagem de compartilhamento com outro nome ou outras cores: <code>npm run icons -- --brand="Nome" --primary=#7C3AED --accent=#FF4D6D</code>.',
							esc_url( admin_url( 'customize.php?autofocus[section]=title_tagline' ) )
						),
						'esc_html' => 0,
					)
				),
				spotinder_acf_field(
					'share_image',
					'Imagem de compartilhamento padrão',
					'image',
					array(
						'return_format' => 'array',
						'preview_size'  => 'medium',
						'min_width'     => 600,
						'instructions'  => 'Aparece no WhatsApp, Instagram, Facebook, X, LinkedIn etc. Ideal: 1200×630 px. Se vazio, usa a imagem do tema. Páginas, histórias e eventos com imagem destacada usam a própria imagem.',
					)
				),
				spotinder_acf_field( 'share_description', 'Descrição padrão para compartilhamento e buscadores', 'textarea', array( 'instructions' => 'Até ~160 caracteres. Histórias e eventos usam o próprio resumo.' ) ),
				spotinder_acf_field( 'share_twitter', 'Perfil no X/Twitter (opcional)', 'text', array( 'placeholder' => '@perfil' ) ),

				spotinder_acf_tab( 'google', 'Login com Google' ),
				spotinder_acf_field(
					'google_help',
					'Como configurar',
					'message',
					array(
						'message'  => "1. No <a href=\"https://console.cloud.google.com/apis/credentials\" target=\"_blank\">Google Cloud Console</a>, configure a <em>Tela de consentimento OAuth</em> (tipo Externo; escopos <code>openid</code> e <code>.../auth/userinfo.profile</code> — não peça e-mail) e publique o app.<br>2. Em <em>Credenciais &gt; ID do cliente OAuth</em> (tipo <em>Aplicativo da Web</em>), cadastre em <em>Origens JavaScript autorizadas</em> exatamente:<br><code style=\"user-select:all\">{$js_origin}</code><br>O login abre num popup do Google, então <strong>não é preciso URI de redirecionamento</strong>. O Google só aceita origens em HTTPS ou <code>http://localhost</code>: para testar localmente, acesse o site por localhost e cadastre essa origem também.<br>3. Escopos pedidos: <code>openid profile</code> (ID da conta, nome e foto pública). Nenhum token é armazenado.",
						'esc_html' => 0,
					)
				),
				spotinder_acf_field( 'google_client_id', 'Client ID' ),
				spotinder_acf_field( 'google_client_secret', 'Client Secret', 'password' ),

				spotinder_acf_tab( 'spotify', 'Catálogo do Spotify' ),
				spotinder_acf_field(
					'spotify_help',
					'Como configurar',
					'message',
					array(
						'message'  => "Usado na busca de artistas e músicas do perfil, nas fotos dos cartões do destaque e nos perfis de teste. Ninguém conecta a conta do Spotify: o site usa só a credencial do próprio app (<em>client credentials</em>), que não depende da lista de usuários liberados.<br>1. Crie um app em <a href=\"https://developer.spotify.com/dashboard\" target=\"_blank\">developer.spotify.com/dashboard</a> (marque <em>Web API</em>). O painel exige uma Redirect URI, mas ela não é usada: pode ser a do site.<br>2. Copie o Client ID e o Client Secret abaixo.",
						'esc_html' => 0,
					)
				),
				spotinder_acf_field( 'spotify_client_id', 'Client ID' ),
				spotinder_acf_field( 'spotify_client_secret', 'Client Secret', 'password' ),

				spotinder_acf_tab( 'rules', 'Regras do app' ),
				spotinder_acf_field( 'min_age', 'Idade mínima', 'number', array( 'min' => 18, 'instructions' => 'Autodeclarada no cadastro. O Spotify proíbe apps voltados a crianças.' ) ),
				spotinder_acf_field( 'deck_size', 'Cartões por carregamento do deck', 'number', array( 'min' => 5, 'max' => 50 ) ),
				spotinder_acf_field( 'top_artists_shown', 'Artistas exibidos por cartão', 'number', array( 'min' => 3, 'max' => 20 ) ),
				spotinder_acf_field( 'match_autoplay', 'Tocar a trilha sonora automaticamente no match', 'true_false', array( 'ui' => 1 ) ),
				spotinder_acf_field( 'inactive_hide_days', 'Ocultar do deck perfis inativos há (dias)', 'number', array( 'min' => 7 ) ),
				spotinder_acf_field( 'inactive_delete_days', 'Excluir contas inativas há (dias)', 'number', array( 'min' => 30, 'instructions' => 'Retenção mínima: apagamos tudo de quem não acessa há esse tempo. Deixe coerente com a Política de Privacidade.' ) ),
				spotinder_acf_field( 'policy_version', 'Versão da política/termos', 'text', array( 'instructions' => 'Ao alterar, todos os membros precisam aceitar os termos de novo no próximo acesso.' ) ),

				spotinder_acf_tab( 'demo', 'Usuários de teste' ),
				spotinder_acf_field( 'show_demo_in_deck', 'Mostrar usuários de teste no deck', 'true_false', array( 'ui' => 1, 'instructions' => 'Desative antes de lançar. Gerencie em Spotinder > Ferramentas.' ) ),
				spotinder_acf_field( 'demo_auto_like', 'Usuários de teste curtem de volta', 'true_false', array( 'ui' => 1 ) ),
				spotinder_acf_field( 'demo_like_chance', 'Chance de curtir de volta (%)', 'number', array( 'min' => 0, 'max' => 100 ) ),

				spotinder_acf_tab( 'header', 'Cabeçalho' ),
				spotinder_acf_field( 'login_label', 'Texto do botão de login', 'text', array( 'instructions' => 'Aparece no botão do Google, ao lado do logo: mantenha "Google" no texto (regras de marca do Google).' ) ),
				spotinder_acf_field( 'nav_cta_label', 'Texto do botão do menu (logado)' ),
			),
		)
	);

	// Página inicial.
	$emoji_title_text = array(
		'emoji' => array( 'Emoji/ícone' ),
		'title' => array( 'Título' ),
		'text'  => array( 'Texto', 'textarea' ),
	);

	acf_add_local_field_group(
		array(
			'key'      => 'group_sp_home',
			'title'    => 'Página inicial',
			'location' => $options( 'spotinder-home' ),
			'fields'   => array(
				spotinder_acf_tab( 'hero', 'Destaque (hero)' ),
				spotinder_acf_field( 'hero_eyebrow', 'Chamada acima do título' ),
				spotinder_acf_field( 'hero_title', 'Título' ),
				spotinder_acf_field( 'hero_text', 'Texto', 'textarea' ),
				spotinder_acf_field( 'hero_cta_label', 'Botão principal', 'text', array( 'instructions' => 'Aparece no botão do Google, ao lado do logo: mantenha "Google" no texto (regras de marca do Google).' ) ),
				spotinder_acf_field( 'hero_secondary_label', 'Botão secundário' ),
				spotinder_acf_repeater(
					'hero_stats',
					'Números',
					array(
						'value' => array( 'Valor' ),
						'label' => array( 'Legenda' ),
					),
					'Adicionar número'
				),
				spotinder_acf_repeater(
					'hero_cards',
					'Cartões animados (passam em loop ao lado do título)',
					array(
						'name'    => array( 'Nome', 'text', array( 'wrapper' => array( 'width' => 34 ) ) ),
						'city'    => array( 'Cidade', 'text', array( 'wrapper' => array( 'width' => 33 ) ) ),
						'genre'   => array( 'Gênero', 'text', array( 'wrapper' => array( 'width' => 33 ), 'instructions' => 'Pop, Forró, Funk e MPB têm foto padrão no tema.' ) ),
						'photo'   => array( 'Foto de perfil', 'image', array( 'return_format' => 'url', 'preview_size' => 'thumbnail', 'wrapper' => array( 'width' => 34 ) ) ),
						'artists' => array( 'Artistas (um por linha, até 4)', 'textarea', array( 'rows' => 4, 'wrapper' => array( 'width' => 33 ), 'instructions' => 'As fotos dos artistas vêm do Spotify (Ferramentas > Completar com dados do Spotify).' ) ),
						'score'   => array( 'Compatibilidade (%)', 'number', array( 'min' => 0, 'max' => 99, 'wrapper' => array( 'width' => 33 ) ) ),
					),
					'Adicionar cartão'
				),

				spotinder_acf_tab( 'how', 'Como funciona' ),
				spotinder_acf_field( 'how_eyebrow', 'Chamada' ),
				spotinder_acf_field( 'how_title', 'Título' ),
				spotinder_acf_repeater( 'how_steps', 'Passos', $emoji_title_text, 'Adicionar passo' ),

				spotinder_acf_tab( 'campaign', 'Campanha' ),
				spotinder_acf_field( 'campaign_enabled', 'Mostrar as histórias da campanha no site', 'true_false', array( 'ui' => 1, 'instructions' => 'Desligado: some a seção da página inicial, o item "Histórias" dos menus e os sitemaps, e /historias/ volta para a página inicial. As histórias continuam salvas aqui no painel.' ) ),
				spotinder_acf_field( 'campaign_eyebrow', 'Chamada / hashtag' ),
				spotinder_acf_field( 'campaign_title', 'Título da campanha' ),
				spotinder_acf_field( 'campaign_text', 'Texto', 'textarea' ),
				spotinder_acf_field(
					'campaign_story',
					'História em destaque',
					'post_object',
					array(
						'post_type'     => array( 'spot_story' ),
						'return_format' => 'id',
						'allow_null'    => 1,
						'instructions'  => 'Se vazio, usa a história mais recente.',
					)
				),
				spotinder_acf_field( 'campaign_cta_label', 'Texto do botão' ),

				spotinder_acf_tab( 'intentions', 'Intenções' ),
				spotinder_acf_field( 'intentions_eyebrow', 'Chamada' ),
				spotinder_acf_field( 'intentions_title', 'Título' ),
				spotinder_acf_field( 'intentions_text', 'Texto', 'textarea', array( 'instructions' => 'A lista de intenções é editada em Spotinder > Textos do app.' ) ),

				spotinder_acf_tab( 'features', 'Recursos' ),
				spotinder_acf_field( 'features_eyebrow', 'Chamada' ),
				spotinder_acf_field( 'features_title', 'Título' ),
				spotinder_acf_repeater( 'features', 'Recursos', $emoji_title_text, 'Adicionar recurso' ),

				spotinder_acf_tab( 'events', 'Eventos' ),
				spotinder_acf_field( 'events_eyebrow', 'Chamada' ),
				spotinder_acf_field( 'events_title', 'Título' ),
				spotinder_acf_field( 'events_text', 'Texto', 'textarea' ),
				spotinder_acf_field( 'events_count', 'Quantidade exibida', 'number', array( 'min' => 0, 'max' => 12 ) ),
				spotinder_acf_field( 'events_cta_label', 'Texto do botão' ),

				spotinder_acf_tab( 'faq', 'FAQ' ),
				spotinder_acf_field( 'faq_eyebrow', 'Chamada' ),
				spotinder_acf_field( 'faq_title', 'Título' ),
				spotinder_acf_repeater(
					'faq',
					'Perguntas',
					array(
						'question' => array( 'Pergunta' ),
						'answer'   => array( 'Resposta', 'textarea' ),
					),
					'Adicionar pergunta'
				),

				spotinder_acf_tab( 'final', 'Chamada final' ),
				spotinder_acf_field( 'final_cta_title', 'Título' ),
				spotinder_acf_field( 'final_cta_text', 'Texto', 'textarea' ),
				spotinder_acf_field( 'final_cta_label', 'Texto do botão', 'text', array( 'instructions' => 'Aparece no botão do Google, ao lado do logo: mantenha "Google" no texto (regras de marca do Google).' ) ),
			),
		)
	);

	// Textos do app.
	acf_add_local_field_group(
		array(
			'key'      => 'group_sp_app',
			'title'    => 'Textos do app',
			'location' => $options( 'spotinder-app' ),
			'fields'   => array(
				spotinder_acf_tab( 'lists', 'Intenções e convites' ),
				spotinder_acf_repeater(
					'intentions',
					'Intenções (o que a pessoa procura)',
					array(
						'slug'  => array( 'Identificador (sem espaços)', 'text', array( 'instructions' => 'Não altere depois de lançado: é o que fica salvo no perfil.' ) ),
						'emoji' => array( 'Emoji' ),
						'label' => array( 'Rótulo' ),
					),
					'Adicionar intenção'
				),
				spotinder_acf_repeater(
					'invite_types',
					'Tipos de convite (rolê livre)',
					array(
						'slug'  => array( 'Identificador' ),
						'emoji' => array( 'Emoji' ),
						'label' => array( 'Rótulo' ),
					),
					'Adicionar tipo'
				),

				spotinder_acf_tab( 'onboarding', 'Cadastro e consentimentos' ),
				spotinder_acf_field( 'onboarding_title', 'Título do cadastro' ),
				spotinder_acf_field( 'onboarding_text', 'Texto do cadastro', 'textarea' ),
				spotinder_acf_field( 'consent_age_label', 'Consentimento: idade' ),
				spotinder_acf_field( 'consent_terms_label', 'Consentimento: termos e política' ),
				spotinder_acf_field( 'consent_share_label', 'Consentimento: exibir artistas', 'textarea' ),
				spotinder_acf_field( 'consent_social_label', 'Consentimento: redes sociais', 'textarea' ),
				spotinder_acf_field( 'photo_help', 'Foto de perfil — orientação', 'textarea' ),
				spotinder_acf_field( 'consent_photo_label', 'Consentimento: foto de perfil', 'textarea' ),
				spotinder_acf_field( 'location_help', 'Localização — orientação', 'textarea' ),
				spotinder_acf_field( 'consent_location_label', 'Consentimento: localização aproximada', 'textarea' ),
				spotinder_acf_field( 'social_warning_title', 'Aviso de redes sociais — título' ),
				spotinder_acf_field( 'social_warning_text', 'Aviso de redes sociais — texto', 'textarea' ),

				spotinder_acf_tab( 'deck', 'Deck e match' ),
				spotinder_acf_field( 'deck_title', 'Título do deck' ),
				spotinder_acf_field( 'deck_empty_title', 'Deck vazio — título' ),
				spotinder_acf_field( 'deck_empty_text', 'Deck vazio — texto', 'textarea' ),
				spotinder_acf_field( 'match_title', 'Match — título' ),
				spotinder_acf_field( 'match_text', 'Match — texto', 'textarea' ),
				spotinder_acf_field( 'soundtrack_label', 'Rótulo da trilha sonora' ),
				spotinder_acf_field( 'playlist_label', 'Rótulo da playlist em comum' ),
				spotinder_acf_field( 'matches_title', 'Matches — título' ),
				spotinder_acf_field( 'matches_empty_text', 'Matches — vazio', 'textarea' ),

				spotinder_acf_tab( 'safety', 'Segurança' ),
				spotinder_acf_repeater(
					'safety_tips',
					'Dicas de segurança (exibidas nos matches e convites)',
					array( 'text' => array( 'Dica', 'textarea' ) ),
					'Adicionar dica'
				),
			),
		)
	);

	// Rodapé.
	acf_add_local_field_group(
		array(
			'key'      => 'group_sp_footer',
			'title'    => 'Rodapé',
			'location' => $options( 'spotinder-footer' ),
			'fields'   => array(
				spotinder_acf_field( 'footer_text', 'Texto institucional', 'textarea', array( 'instructions' => 'Aceita links: <code>&lt;a href="https://..."&gt;texto&lt;/a&gt;</code>. Links para fora do site abrem em nova guia.' ) ),
				spotinder_acf_field( 'footer_attribution', 'Atribuição ao Spotify', 'textarea', array( 'instructions' => 'Exigida pela Developer Policy (II.4): todo conteúdo do Spotify precisa ser atribuído. Aceita links: <code>&lt;a href="https://..."&gt;texto&lt;/a&gt;</code>.' ) ),
				spotinder_acf_field( 'spotify_logo', 'Logo oficial do Spotify (atribuição)', 'image', array( 'return_format' => 'url', 'instructions' => 'Baixe o logo oficial em developer.spotify.com/documentation/design e siga as regras de uso (tamanho mínimo, sem alterar cores).' ) ),
				spotinder_acf_field( 'footer_disclaimer', 'Aviso de não afiliação / créditos', 'textarea', array( 'instructions' => 'Aceita links: <code>&lt;a href="https://..."&gt;texto&lt;/a&gt;</code>. Links para fora do site abrem em nova guia.' ) ),
				spotinder_acf_repeater(
					'footer_social',
					'Redes da marca',
					array(
						'label' => array( 'Nome' ),
						'url'   => array( 'URL', 'url' ),
					),
					'Adicionar rede'
				),
			),
		)
	);

	// Eventos.
	acf_add_local_field_group(
		array(
			'key'      => 'group_sp_event',
			'title'    => 'Dados do evento',
			'position' => 'acf_after_title',
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'spot_event',
					),
				),
			),
			'fields'   => array(
				spotinder_acf_field( 'event_start', 'Início', 'date_time_picker', array( 'required' => 1, 'display_format' => 'd/m/Y H:i', 'return_format' => 'Y-m-d H:i:s', 'first_day' => 0, 'wrapper' => array( 'width' => 50 ) ) ),
				spotinder_acf_field( 'event_end', 'Término', 'date_time_picker', array( 'display_format' => 'd/m/Y H:i', 'return_format' => 'Y-m-d H:i:s', 'first_day' => 0, 'wrapper' => array( 'width' => 50 ) ) ),
				spotinder_acf_field( 'event_venue', 'Local', 'text', array( 'wrapper' => array( 'width' => 50 ) ) ),
				spotinder_acf_field( 'event_city', 'Cidade', 'text', array( 'wrapper' => array( 'width' => 50 ) ) ),
				spotinder_acf_field( 'event_address', 'Endereço' ),
				spotinder_acf_field( 'event_price', 'Preço', 'text', array( 'placeholder' => 'Gratuito / R$ 30', 'wrapper' => array( 'width' => 50 ) ) ),
				spotinder_acf_field( 'event_ticket_url', 'Link de ingresso/inscrição', 'url', array( 'wrapper' => array( 'width' => 50 ) ) ),
				spotinder_acf_field( 'event_official', 'Encontro oficial da comunidade', 'true_false', array( 'ui' => 1, 'instructions' => 'Destaca o evento como organizado pela equipe.' ) ),
				spotinder_acf_field( 'event_artists', 'Artistas relacionados (um por linha)', 'textarea', array( 'rows' => 4, 'instructions' => 'Usado para recomendar o evento a quem tem esses artistas entre os favoritos.' ) ),
				spotinder_acf_field( 'event_spotify_url', 'Link do Spotify (playlist/álbum/artista)', 'url', array( 'instructions' => 'Exibido com o player oficial do Spotify na página do evento.' ) ),
			),
		)
	);

	// Histórias.
	acf_add_local_field_group(
		array(
			'key'      => 'group_sp_story',
			'title'    => 'Dados da história',
			'position' => 'acf_after_title',
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'spot_story',
					),
				),
			),
			'fields'   => array(
				spotinder_acf_field( 'story_subtitle', 'Subtítulo' ),
				spotinder_acf_field( 'story_city', 'Cidade', 'text', array( 'wrapper' => array( 'width' => 50 ) ) ),
				spotinder_acf_field( 'story_hashtag', 'Hashtag', 'text', array( 'wrapper' => array( 'width' => 50 ) ) ),
				spotinder_acf_field( 'story_quote', 'Citação de destaque', 'textarea' ),
				spotinder_acf_field( 'story_quote_author', 'Autor(a) da citação' ),
				spotinder_acf_field( 'story_soundtrack_url', 'Trilha sonora (link de música do Spotify)', 'url' ),
				spotinder_acf_field( 'story_soundtrack_label', 'Nome da trilha sonora', 'text', array( 'placeholder' => 'Música — Artista' ) ),
				spotinder_acf_field( 'story_shared_artists', 'Artistas em comum (um por linha)', 'textarea', array( 'rows' => 4 ) ),
				spotinder_acf_repeater(
					'story_people',
					'Pessoas da história',
					array(
						'name'   => array( 'Nome' ),
						'age'    => array( 'Idade', 'number' ),
						'role'   => array( 'Quem é' ),
						'artist' => array( 'Artista favorito' ),
					),
					'Adicionar pessoa',
					'story'
				),
				spotinder_acf_repeater(
					'story_chapters',
					'Capítulos',
					array(
						'label' => array( 'Marcador (ex.: Faixa 1)' ),
						'title' => array( 'Título' ),
						'text'  => array( 'Texto', 'textarea' ),
					),
					'Adicionar capítulo',
					'story'
				),
			),
		)
	);

	// Páginas legais.
	acf_add_local_field_group(
		array(
			'key'      => 'group_sp_legal',
			'title'    => 'Resumo em linguagem simples',
			'position' => 'acf_after_title',
			'location' => array(
				array(
					array(
						'param'    => 'page_template',
						'operator' => '==',
						'value'    => 'templates/legal.php',
					),
				),
			),
			'fields'   => array(
				spotinder_acf_field( 'legal_updated', 'Última atualização', 'date_picker', array( 'display_format' => 'd/m/Y', 'return_format' => 'Y-m-d' ) ),
				spotinder_acf_repeater(
					'legal_summary',
					'Pontos principais (exibidos no topo)',
					array(
						'emoji' => array( 'Emoji' ),
						'text'  => array( 'Texto', 'textarea' ),
					),
					'Adicionar ponto',
					'legal'
				),
			),
		)
	);
}

/**
 * Aviso no admin quando o ACF PRO não está ativo.
 */
add_action(
	'admin_notices',
	function () {
		if ( function_exists( 'acf_add_options_page' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>Spotinder:</strong> ative o plugin <em>Advanced Custom Fields PRO</em> para editar os conteúdos do tema pelo painel. Enquanto isso, os textos padrão são exibidos.</p></div>';
	}
);
