# Spotinder — tema WordPress

Conecta pessoas pelo gosto musical. Login exclusivo via Spotify, deck estilo swipe com os artistas mais ouvidos no lugar de fotos, match com trilha sonora e playlist em comum, sem chat (só redes sociais + convites), agenda de eventos e campanha de histórias.

**Stack:** WordPress + Parcel + TailwindCSS v4 + Swup + React 19. Os conteúdos editáveis usam **ACF PRO**, com campos registrados em PHP.

> Antes de lançar, leia [docs/estudo-privacidade.md](docs/estudo-privacidade.md). O nome "Spotinder" e o modelo de matching têm riscos com a Developer Policy do Spotify.

## Instalação

1. Ative o plugin **Advanced Custom Fields PRO**.
2. Ative o tema. Na ativação, ele cria:
   - tabelas próprias;
   - páginas (Início, Descobrir, Matches, Perfil, Privacidade, Termos, Segurança);
   - 3 histórias da campanha, 5 eventos de exemplo, menus e 14 usuários de teste.
3. Em **Configurações > Links permanentes**, use "Nome do post".
4. Configure o Spotify em **Spotinder > Configurações > Spotify API** (passo a passo abaixo).

### Spotify

1. Crie um app em <https://developer.spotify.com/dashboard> e marque **Web API**.
2. Cadastre a Redirect URI mostrada no painel: `https://SEU-DOMINIO/?spotinder_auth=callback`.
   - O Spotify só aceita **HTTPS** ou loopback (`http://127.0.0.1`). `http://spotinder.local` **não funciona**. Para desenvolver localmente, use HTTPS no vhost do XAMPP ou acesse o site por `http://127.0.0.1/...`, com `home`/`siteurl` ajustados.
3. Em modo de desenvolvimento, adicione as contas de teste em *User Management*. O limite é 5, e o dono do app precisa de Premium.
4. Opcional: em **Spotinder > Ferramentas**, clique em "Completar com dados do Spotify". Os perfis de teste ganham fotos de artistas e IDs reais, e o player oficial passa a tocar as faixas.

## Desenvolvimento

```bash
npm install
npm run dev     # parcel watch → dist/
npm run build   # build de produção
```

`scripts/parcel.mjs` aponta o TEMP para `.parcel-tmp/` dentro do projeto. No Windows, quando o projeto fica em outro drive (ex.: `B:`), o Parcel falha ao mover arquivos do `C:\...\Temp`. Às vezes ele falha **sem aviso**, e o `dist/` fica desatualizado.

### Deploy (cPanel)

O `dist/` vai pronto no repositório, então o servidor não precisa de Node. Rode `npm run build` antes de cada commit que mexa em `src/`.

O cPanel clona o repositório fora da pasta pública (em `~/repositories/spotinder`). O `.cpanel.yml` copia o tema para `~/public_html/wp-content/themes/spotinder-theme/` com `rsync --delete`, sem levar `.git`, `src`, `scripts`, `docs` e arquivos do npm. Se o WordPress estiver em outra pasta, ajuste `DEPLOYPATH` no `.cpanel.yml`.

As fotos enviadas pelos usuários ficam em `wp-content/uploads/spotinder-photos`, fora do tema. O deploy não as apaga.

### Ícones e imagem de compartilhamento

```bash
npm run icons                                   # regera assets/icons e assets/img/og-default.png
npm run icons -- --brand="Novo Nome" --primary=#7C3AED --accent=#FF4D6D --title="Outro título"
```

- **Ícone do site:** na ativação, o tema registra `assets/icons/site-icon-512.png` como o Ícone do site do WP, que gera favicon 32, ícone do celular 180/192 e tile 270. Para trocar, use Aparência > Personalizar > Identidade do site. Enquanto o ícone padrão do tema estiver em uso, o site também publica `icon.svg`, a versão *maskable* e um `apple-touch-icon` de fundo inteiro.
- **Manifest (PWA):** `/?spotinder_manifest=1`. Nome e ícones acompanham o painel.
- **Compartilhamento:** tags Open Graph e Twitter Card. A imagem segue esta ordem: imagem destacada da página → "Imagem de compartilhamento padrão" (Spotinder > Configurações > Ícones e compartilhamento) → `assets/img/og-default.png`. Se Yoast, Rank Math, AIOSEO, SEOPress ou The SEO Framework estiverem ativos, o tema não gera essas tags.

### Localização aproximada

- O navegador arredonda a posição para uma célula de ~5 km antes de enviar (`src/js/location.js`). O servidor arredonda de novo e guarda só a célula e o município (`inc/location.php`). A posição exata nunca é guardada.
- Os nomes de municípios vêm de `data/municipios.json` (IBGE), sem serviços externos de mapa.
- A geolocalização do navegador só funciona em **HTTPS**. Em `http://spotinder.local` a opção aparece desativada. Para testar localmente, use HTTPS ou rode o Chrome com `--unsafely-treat-insecure-origin-as-secure=http://spotinder.local`.

### Migrações de conteúdo

`inc/migrations.php` atualiza textos de opções, FAQ, páginas legais e resumos quando o tema muda de versão de conteúdo. Ela roda na primeira visita de um admin ao wp-admin. Só substitui o que ainda estiver idêntico a uma versão anterior do tema. O que foi editado à mão vira um aviso no painel, e nesse caso a versão da política não sobe sozinha.

### Cartões animados do destaque

Os cartões ficam em Spotinder > Página inicial > Destaque > "Cartões animados". Cada um tem nome, cidade, gênero, foto, até 4 artistas e uma porcentagem. A animação está em `src/js/ui.js` (`initHeroDeck`) e respeita o "reduzir movimento" do sistema. As fotos dos artistas vêm do Spotify e são buscadas ao salvar a página no painel (ou em Ferramentas > "Completar com dados do Spotify").

**Fotos de perfil padrão** (`assets/img/hero/`, recortadas e sem metadados). Todas são do [Unsplash](https://unsplash.com/license), sob a Unsplash License (uso comercial gratuito, crédito não obrigatório):

| Cartão | Foto | Autor |
|---|---|---|
| Pop | [Mulher com fone, sorrindo](https://unsplash.com/photos/a-woman-wearing-headphones-and-smiling-at-the-camera-tBkHfYTWbVQ) | Vinicius "amnx" Amano |
| Forró | [Homem sorrindo de camiseta preta](https://unsplash.com/photos/smiling-man-wearing-black-t-shirt--4LszIR1OZ4) | Abstral Official |
| Funk | [Jovem fazendo selfie com fone](https://unsplash.com/photos/young-woman-taking-a-selfie-with-headphones-OuKFx-GIkoY) | Vitaly Gariev |
| MPB | [Homem sorrindo para a câmera](https://unsplash.com/photos/a-man-smiling-for-the-camera-N8lRH2uxih4) | The Connected Narrative |

## Onde editar cada coisa

| O quê | Onde |
|---|---|
| Marca, cores, logo, credenciais, regras (idade, retenção, deck), perfis de teste | Spotinder > Configurações |
| Todas as seções da home (hero, como funciona, campanha, intenções, recursos, eventos, FAQ, CTA) | Spotinder > Página inicial |
| Intenções, tipos de convite, textos de cadastro e consentimento, aviso de redes, textos do match, dicas de segurança | Spotinder > Textos do app |
| Rodapé, atribuição e logo do Spotify, redes da marca | Spotinder > Rodapé |
| Eventos (data, local, artistas relacionados, player) | Eventos |
| Histórias da campanha (pessoas, capítulos, citação, trilha) | Histórias (campanha) |
| Política, Termos, Segurança (com o bloco "Resumo em 30 segundos") | Páginas |
| Menus | Aparência > Menus (principal, rodapé, legais) |
| Denúncias, suspensões, usuários de teste, retenção | Spotinder > Ferramentas e moderação |

Nas páginas legais, `[spotinder_opcao campo="inactive_delete_days"]` (e também `min_age`, `brand_name`, `policy_version`, `inactive_hide_days`, `top_artists_shown`) mostra o valor configurado. Assim a política nunca fica desatualizada em relação às configurações.

## Arquitetura

```
inc/
  helpers.php         spotinder_opt() (ACF com fallback para defaults), URLs, ícones, shortcode
  defaults.php        todos os textos padrão (gravados no ACF na ativação)
  acf-fields.php      páginas de opções e grupos de campos
  post-types.php      spot_event (+ spot_event_type) e spot_story
  db.php              tabelas: swipes, matches, blocks, reports, rsvps, invites
  spotify-client.php  OAuth, /me/top/*, criação de playlist (endpoints 2026 com fallback), busca
  auth.php            ?spotinder_auth=login|playlist|callback|logout (sem guardar tokens)
  members.php         perfil, consentimentos, exportação e exclusão
  matching.php        compatibilidade, deck, swipe, match, trilha sonora, playlist em comum
  events.php          presenças, convites, Google Agenda e .ics
  rest.php            /wp-json/spotinder/v1/*
  privacy.php         exportador/apagador do WP, retenção diária, noindex, sem páginas de autor
  demo.php            conteúdo inicial e usuários de teste
  admin.php           ferramentas e moderação
src/js/
  app.js              Swup (containers #swup e #site-header) + montagem das ilhas React
  islands.js          <div data-island="Deck" data-props='{}'> → React
  components/         Deck, SwipeCard, MatchModal, Matches, MatchDetail, Invites, ReportDialog,
                      Profile, Onboarding, EventRsvp, MatchBadge, SpotifyEmbed
content/              HTML inicial de Privacidade, Termos e Segurança
```

### Endpoints REST (`spotinder/v1`, cookie + nonce)

| Método | Rota | Uso |
|---|---|---|
| GET / POST / DELETE | `/me` | Perfil próprio, cadastro e consentimentos, exclusão (`?confirm=APAGAR`) |
| GET | `/me/export` | Exportação LGPD |
| GET | `/summary` | Contagem de novidades (badge) |
| GET | `/deck?intention=` | Deck de descoberta |
| POST | `/swipe` | `{target, action: like|pass}` → `{match}` |
| GET / DELETE | `/matches`, `/matches/{id}` | Lista, detalhe e desfazer match |
| POST | `/matches/{id}/invites` | Criar convite |
| POST | `/invites/{id}` | Responder: `accepted`, `declined` ou `cancelled` |
| POST | `/block`, `/report` | Bloquear e denunciar |
| GET / POST | `/events/{id}/rsvp` | Presença em evento |
