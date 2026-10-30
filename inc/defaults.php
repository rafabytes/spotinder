<?php
/**
 * Valores padrão de todos os campos editáveis do painel.
 * São gravados nas opções do ACF na primeira ativação (Ferramentas > Spotinder pode regravar).
 *
 * @package Spotinder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Padrões das opções globais.
 *
 * @return array<string,mixed>
 */
function spotinder_defaults() {
	static $defaults = null;
	if ( null !== $defaults ) {
		return $defaults;
	}

	$defaults = array(
		// Marca e aparência.
		'brand_name'            => 'Spotinder',
		'brand_tagline'         => 'Gente que soa parecido.',
		'logo'                  => '',
		'color_primary'         => '#7C3AED',
		'color_accent'          => '#FF4D6D',
		'color_highlight'       => '#C6FF3D',

		// Ícones e compartilhamento.
		'share_image'           => '',
		'share_description'     => 'Conecte seu Spotify e conheça pessoas pelo que elas realmente escutam. Artistas em primeiro lugar, sem chat: amizades, bandas e companhia para shows.',
		'share_twitter'         => '',

		// Spotify.
		'spotify_client_id'     => '',
		'spotify_client_secret' => '',
		'spotify_redirect_uri'  => '',

		// Regras do app.
		'min_age'               => 18,
		'deck_size'             => 20,
		'top_artists_shown'     => 8,
		'match_autoplay'        => true,
		'demo_auto_like'        => true,
		'demo_like_chance'      => 75,
		'show_demo_in_deck'     => true,
		'inactive_hide_days'    => 90,
		'inactive_delete_days'  => 365,
		'policy_version'        => '2026-10-30.3',

		// Header.
		'login_label'           => 'Entrar com Spotify',
		'nav_cta_label'         => 'Começar a ouvir junto',

		// Home — hero.
		'hero_eyebrow'          => 'Música em primeiro lugar. Sem chat.',
		'hero_title'            => 'Ninguém deveria ouvir sozinho.',
		'hero_text'             => 'Conecte seu Spotify e conheça pessoas pelo que elas realmente escutam. Em cada cartão, os artistas mais ouvidos vêm em destaque — a foto é opcional. Deu match? Vocês ganham uma playlist em comum e um jeito de se encontrar no próximo show.',
		'hero_cta_label'        => 'Conectar meu Spotify',
		'hero_secondary_label'  => 'Ver como funciona',
		'hero_stats'            => array(
			array(
				'value' => '3',
				'label' => 'artistas em destaque em cada cartão',
			),
			array(
				'value' => '1',
				'label' => 'playlist em comum por match',
			),
			array(
				'value' => '∞',
				'label' => 'amizades, bandas e rolês',
			),
		),

		// Home — cartões animados do destaque (um por gênero). A foto vem de assets/img/hero/perfil-{gênero}.jpg
		// e é importada para a Biblioteca de Mídia na ativação; troque em Spotinder > Página inicial.
		'hero_cards'            => array(
			array(
				'name'    => 'Júlia',
				'city'    => 'São Paulo, SP',
				'genre'   => 'Pop',
				'artists' => "Anitta\nDua Lipa\nLuísa Sonza\nTaylor Swift",
				'score'   => 92,
				'photo'   => '',
			),
			array(
				'name'    => 'Davi',
				'city'    => 'Recife, PE',
				'genre'   => 'Forró',
				'artists' => "Luiz Gonzaga\nDominguinhos\nElba Ramalho\nFalamansa",
				'score'   => 87,
				'photo'   => '',
			),
			array(
				'name'    => 'Lara',
				'city'    => 'Rio de Janeiro, RJ',
				'genre'   => 'Funk',
				'artists' => "Ludmilla\nPedro Sampaio\nMC Kevin o Chris\nMC Daniel",
				'score'   => 84,
				'photo'   => '',
			),
			array(
				'name'    => 'Gabriel',
				'city'    => 'Belo Horizonte, MG',
				'genre'   => 'MPB',
				'artists' => "Milton Nascimento\nGal Costa\nCaetano Veloso\nMarisa Monte",
				'score'   => 95,
				'photo'   => '',
			),
		),

		// Home — como funciona.
		'how_eyebrow'           => 'Como funciona',
		'how_title'             => 'Quatro passos entre você e sua próxima turma.',
		'how_steps'             => array(
			array(
				'emoji' => '🎧',
				'title' => 'Conecte o Spotify',
				'text'  => 'É o único jeito de entrar. Lemos só seus artistas e músicas mais ouvidos e seu perfil público — nada de e-mail ou histórico completo.',
			),
			array(
				'emoji' => '🃏',
				'title' => 'Deslize por gostos',
				'text'  => 'Cada cartão mostra os artistas favoritos de alguém e quanto vocês combinam. Curtiu o som? Deslize para a direita.',
			),
			array(
				'emoji' => '⚡',
				'title' => 'Deu som!',
				'text'  => 'Quando os dois curtem, rola um match com a trilha sonora de vocês tocando e uma playlist feita da interseção dos gostos.',
			),
			array(
				'emoji' => '📍',
				'title' => 'Combinem um rolê',
				'text'  => 'Sem chat: vocês veem as redes sociais que cada um escolheu mostrar e podem se convidar para eventos da agenda.',
			),
		),

		// Home — campanha.
		'campaign_eyebrow'      => 'Campanha #NinguémOuveSozinho',
		'campaign_title'        => 'Não era sobre amor. Era sobre não ouvir sozinho.',
		'campaign_text'         => 'Histórias reais de gente que se encontrou pelo fone de ouvido — e descobriu que o melhor do som é dividir.',
		'campaign_story'        => 0,
		'campaign_cta_label'    => 'Ler a história completa',

		// Home — intenções.
		'intentions_eyebrow'    => 'Não é (só) sobre namoro',
		'intentions_title'      => 'Você escolhe o que procura.',
		'intentions_text'       => 'Marque uma ou várias intenções no seu perfil. O deck prioriza quem procura o mesmo que você.',

		// Home — recursos.
		'features_eyebrow'      => 'O que tem aqui',
		'features_title'        => 'Feito para transformar gosto em encontro.',
		'features'              => array(
			array(
				'emoji' => '🎵',
				'title' => 'Trilha sonora do match',
				'text'  => 'Ao dar match, toca um trecho da música que mais representa vocês dois — via player oficial do Spotify.',
			),
			array(
				'emoji' => '💿',
				'title' => 'Playlist em comum',
				'text'  => 'No lugar do chat, uma playlist com o que vocês têm em comum. Dá para salvar direto na sua conta.',
			),
			array(
				'emoji' => '🎟️',
				'title' => 'Agenda de eventos',
				'text'  => 'Shows, audições coletivas e encontros oficiais. Marque presença e veja quais matches também vão.',
			),
			array(
				'emoji' => '🤝',
				'title' => 'Convites sem chat',
				'text'  => 'Proponha um rolê para um match — um show, um karaokê, uma feira de discos. A outra pessoa aceita ou não, sem pressão.',
			),
			array(
				'emoji' => '🛡️',
				'title' => 'Privacidade de verdade',
				'text'  => 'Não guardamos seus tokens do Spotify, não pedimos e-mail, a foto é opcional e você apaga tudo com um clique.',
			),
			array(
				'emoji' => '🚩',
				'title' => 'Bloqueio e denúncia',
				'text'  => 'Qualquer match pode ser desfeito, bloqueado ou denunciado a qualquer momento. Moderação humana revisa tudo.',
			),
		),

		// Home — eventos.
		'events_eyebrow'        => 'Agenda',
		'events_title'          => 'Próximos encontros para ouvir junto.',
		'events_text'           => 'Eventos cadastrados pela nossa equipe. Marque presença e descubra quais dos seus matches também vão.',
		'events_count'          => 3,
		'events_cta_label'      => 'Ver agenda completa',

		// Home — FAQ.
		'faq_eyebrow'           => 'Perguntas frequentes',
		'faq_title'             => 'Antes de dar play.',
		'faq'                   => array(
			array(
				'question' => 'Preciso ter Spotify Premium?',
				'answer'   => 'Não. Qualquer conta Spotify serve para entrar. O player de trechos funciona para todos; quem tem Premium e está logado no Spotify ouve a faixa completa.',
			),
			array(
				'question' => 'Por que não tem chat?',
				'answer'   => 'Porque a ideia é tirar a conversa do app e levar para a vida: vocês veem as redes que cada um escolheu mostrar, ganham uma playlist em comum e podem se convidar para eventos.',
			),
			array(
				'question' => 'É um app de namoro?',
				'answer'   => 'Não exclusivamente. A maioria das pessoas procura amizade, companhia para shows ou gente para montar uma banda. Se rolar algo mais, ótimo — mas não é o foco.',
			),
			array(
				'question' => 'O que vocês veem da minha conta Spotify?',
				'answer'   => 'Só seus artistas e músicas mais ouvidos dos últimos meses, o nome público do seu perfil e o endereço da sua foto do Spotify (que só aparece se você escolher usá-la). Não acessamos e-mail, playlists privadas nem o que você está ouvindo agora.',
			),
			array(
				'question' => 'Como apago minha conta?',
				'answer'   => 'Em Perfil > Privacidade, clique em "Apagar minha conta". Removemos tudo na hora: perfil, matches, convites e dados vindos do Spotify.',
			),
			array(
				'question' => 'Preciso colocar foto?',
				'answer'   => 'Não. A foto é opcional: sem ela, seu cartão mostra suas iniciais e os artistas continuam em destaque. Se quiser, use a foto do seu perfil do Spotify ou envie uma — removemos a localização e outros metadados da imagem, e você pode trocar ou apagar quando quiser.',
			),
			array(
				'question' => 'Vocês sabem onde eu estou?',
				'answer'   => 'Só se você ativar a localização, e mesmo assim de forma aproximada: seu aparelho arredonda a posição para uma área de cerca de 5 km antes de enviar, e guardamos apenas essa área e o nome do município. Outras pessoas veem só o município e a distância em faixas, como "até 10 km". Você desativa quando quiser e a região é apagada na hora.',
			),
		),

		// Home — CTA final.
		'final_cta_title'       => 'Seu próximo melhor amigo pode estar no mesmo refrão.',
		'final_cta_text'        => 'Leva menos de um minuto. Foto opcional, sem formulário, sem chat.',
		'final_cta_label'       => 'Conectar meu Spotify',

		// App — intenções e tipos de convite.
		'intentions'            => array(
			array(
				'slug'  => 'amizade',
				'emoji' => '🫂',
				'label' => 'Amizade',
			),
			array(
				'slug'  => 'shows',
				'emoji' => '🎤',
				'label' => 'Companhia para shows',
			),
			array(
				'slug'  => 'banda',
				'emoji' => '🎸',
				'label' => 'Montar uma banda',
			),
			array(
				'slug'  => 'indicacoes',
				'emoji' => '💿',
				'label' => 'Trocar indicações',
			),
			array(
				'slug'  => 'role',
				'emoji' => '🍻',
				'label' => 'Rolês e festas',
			),
			array(
				'slug'  => 'crush',
				'emoji' => '✨',
				'label' => 'Crush musical, se rolar',
			),
		),
		'invite_types'          => array(
			array(
				'slug'  => 'show',
				'emoji' => '🎤',
				'label' => 'Ir a um show',
			),
			array(
				'slug'  => 'audicao',
				'emoji' => '💿',
				'label' => 'Audição de um disco',
			),
			array(
				'slug'  => 'karaoke',
				'emoji' => '🎙️',
				'label' => 'Karaokê',
			),
			array(
				'slug'  => 'feira',
				'emoji' => '🛍️',
				'label' => 'Feira de discos',
			),
			array(
				'slug'  => 'jam',
				'emoji' => '🎸',
				'label' => 'Tocar junto / jam',
			),
			array(
				'slug'  => 'cafe',
				'emoji' => '☕',
				'label' => 'Café e indicações',
			),
		),

		// App — onboarding e consentimentos.
		'onboarding_title'      => 'Bora montar seu perfil sonoro.',
		'onboarding_text'       => 'Seus artistas já vieram do Spotify. Falta só dizer o que você procura e, se quiser, quais redes aparecem para seus matches.',
		'consent_age_label'     => 'Tenho 18 anos ou mais.',
		'consent_terms_label'   => 'Li e aceito os Termos de Uso e a Política de Privacidade.',
		'consent_share_label'   => 'Autorizo exibir meu nome público e meus artistas/músicas mais ouvidos para outras pessoas do app.',
		'consent_social_label'  => 'Entendo que as redes sociais que eu cadastrar serão mostradas para quem der match comigo e que a responsabilidade pelo contato fora do app é minha.',

		// App — foto de perfil.
		'photo_help'            => 'Opcional. Com foto, ela aparece no seu cartão para todas as pessoas do app; sem foto, mostramos suas iniciais. Use uma foto sua, sem nudez, crianças, documentos ou dados de contato.',
		'consent_photo_label'   => 'Autorizo exibir esta foto no meu cartão para todas as pessoas do app. Declaro que a foto é minha (ou tenho direito de usá-la), e entendo que ela pode ser removida pela moderação se violar os Termos.',

		// App — localização aproximada.
		'location_help'         => 'Opcional. Usamos só uma região aproximada (uma área de cerca de 5 km) para mostrar quem está perto e permitir filtrar por distância. Sua localização exata é arredondada no seu aparelho e nunca é enviada nem guardada.',
		'consent_location_label' => 'Autorizo usar minha região aproximada (município e distância em faixas, como "até 10 km") para mostrar meu perfil a pessoas próximas e atualizá-la quando eu abrir o app. Posso desativar quando quiser.',

		// App — aviso de redes sociais.
		'social_warning_title'  => 'Atenção antes de compartilhar suas redes',
		'social_warning_text'   => 'As redes que você cadastrar aparecem para todas as pessoas com quem você der match. A partir daí, a conversa acontece fora do app e é de responsabilidade de cada um. Prefira perfis sem endereço, telefone ou rotina expostos. Nunca envie dinheiro, documentos ou senhas. Você pode remover uma rede ou desfazer um match a qualquer momento.',

		// App — deck e match.
		'deck_title'            => 'Descobrir',
		'deck_empty_title'      => 'Você ouviu todo mundo por aqui.',
		'deck_empty_text'       => 'Volte mais tarde — gente nova entra todo dia. Enquanto isso, que tal dar uma olhada na agenda?',
		'match_title'           => 'Deu som!',
		'match_text'            => 'Vocês dois curtiram o gosto um do outro. Esta é a trilha sonora de vocês:',
		'soundtrack_label'      => 'A trilha sonora de vocês',
		'playlist_label'        => 'Playlist em comum',
		'matches_title'         => 'Seus matches',
		'matches_empty_text'    => 'Ainda sem matches. Continue deslizando — o som certo aparece.',
		'safety_tips'           => array(
			array( 'text' => 'Marque o primeiro encontro em lugar público e movimentado — um evento da agenda é perfeito.' ),
			array( 'text' => 'Avise alguém de confiança sobre onde e com quem você vai estar.' ),
			array( 'text' => 'Vá e volte por conta própria. Não aceite carona no primeiro encontro.' ),
			array( 'text' => 'Nunca envie dinheiro, PIX, documentos ou códigos de verificação.' ),
			array( 'text' => 'Algo estranho? Bloqueie e denuncie. A moderação analisa todas as denúncias.' ),
		),

		// Rodapé.
		'footer_text'           => 'Uma rede para unir pessoas pelo gosto musical. Amizades, bandas, companhia para shows — e o que mais tocar.',
		'footer_attribution'    => 'Dados de artistas e músicas fornecidos pelo Spotify. Conteúdo musical © respectivos titulares.',
		'footer_disclaimer'     => 'Projeto independente, sem afiliação, patrocínio ou endosso do Spotify AB. Spotify é marca registrada da Spotify AB.',
		'footer_social'         => array(),
		'spotify_logo'          => '',
	);

	return $defaults;
}

/**
 * Lista de redes sociais aceitas no perfil.
 *
 * @return array<string,array{label:string,url:string,placeholder:string}>
 */
function spotinder_social_networks() {
	return array(
		'instagram' => array(
			'label'       => 'Instagram',
			'url'         => 'https://instagram.com/%s',
			'placeholder' => 'seu.usuario',
		),
		'tiktok'    => array(
			'label'       => 'TikTok',
			'url'         => 'https://www.tiktok.com/@%s',
			'placeholder' => 'seuusuario',
		),
		'x'         => array(
			'label'       => 'X / Twitter',
			'url'         => 'https://x.com/%s',
			'placeholder' => 'seuusuario',
		),
		'bluesky'   => array(
			'label'       => 'Bluesky',
			'url'         => 'https://bsky.app/profile/%s',
			'placeholder' => 'voce.bsky.social',
		),
		'threads'   => array(
			'label'       => 'Threads',
			'url'         => 'https://www.threads.net/@%s',
			'placeholder' => 'seu.usuario',
		),
		'lastfm'    => array(
			'label'       => 'Last.fm',
			'url'         => 'https://www.last.fm/user/%s',
			'placeholder' => 'seuusuario',
		),
		'discord'   => array(
			'label'       => 'Discord',
			'url'         => '',
			'placeholder' => 'usuario',
		),
		'website'   => array(
			'label'       => 'Site',
			'url'         => '',
			'placeholder' => 'https://',
		),
	);
}

/**
 * Motivos de denúncia.
 *
 * @return array<string,string>
 */
function spotinder_report_reasons() {
	return array(
		'fake'       => 'Perfil falso ou golpe',
		'photo'      => 'Foto imprópria ou de outra pessoa',
		'location'   => 'Tentou descobrir onde estou ou me perseguiu',
		'harassment' => 'Assédio ou mensagens ofensivas',
		'minor'      => 'Parece ser menor de idade',
		'spam'       => 'Spam ou venda de produtos',
		'safety'     => 'Me senti inseguro(a) num encontro',
		'other'      => 'Outro motivo',
	);
}
