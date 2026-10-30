# Estudo de privacidade e uso da API do Spotify

Base para a Política de Privacidade, os Termos de Uso e as decisões técnicas do tema.
Data: 30/10/2026. **Não é parecer jurídico.** Antes de lançar, valide com um advogado e com o próprio Spotify.

Fontes consultadas:
- [Spotify Developer Policy](https://developer.spotify.com/policy) (vigente desde 15/05/2025)
- [Spotify — Update on Developer Access and Platform Security (06/02/2026)](https://developer.spotify.com/blog/2026-02-06-update-on-developer-access-and-platform-security)
- [TechCrunch — mudanças no modo de desenvolvimento (06/02/2026)](https://techcrunch.com/2026/02/06/spotify-changes-developer-mode-api-to-require-premium-accounts-limits-test-users/)
- [Resumo das mudanças da API em 2026 (vorplabs)](https://vorplabs.com/agent-tools/spotify-api-changes)
- [Relatos de migração dos endpoints de playlist](https://github.com/marcelmarais/spotify-mcp-server/issues/35)
- LGPD (Lei 13.709/2018) e Marco Civil da Internet (Lei 12.965/2014)

---

## 1. Riscos que bloqueiam o lançamento

Leia isto antes de qualquer outra coisa.

### 1.1 O nome "Spotinder" viola a Developer Policy
A seção VI.2 diz que o nome do app *"should not begin with 'Spot' or be confusing in sound or spelling to Spotify"*. "Spotinder" começa com "Spot" e ainda lembra "Tinder", que é marca do Match Group.
**O que fazer:** escolher outro nome antes de pedir qualquer aprovação. No tema, o nome é um campo (Spotinder > Configurações > Marca), então a troca não exige mexer no código.

### 1.2 Cláusula III.13: análise e "perfis de usuários"
A política proíbe *"analyze the Spotify Content or the Spotify Service for any purpose"*, incluindo *"building profiles of users"*. Calcular compatibilidade entre gostos pode ser lido como análise de conteúdo do Spotify. A ressalva *"including for the purpose of targeting them with advertising"* dá margem para uma leitura mais branda, mas não há garantia.
**O que fazer:** descrever o caso de uso por escrito ao Spotify e obter um ok explícito antes do lançamento público. As mitigações que já estão no código:
- o cálculo é simples, explicado na política e feito na hora;
- o resultado não é guardado como perfil nem usado para publicidade;
- nada é enviado para IA;
- a lista de artistas é substituída a cada sincronização, sem histórico.

### 1.3 Quota: hoje o app não consegue ter usuários reais em escala
- **Modo de desenvolvimento** (desde fev–mar/2026): no máximo **5 usuários** cadastrados manualmente no painel, e o dono do app precisa de **Spotify Premium**.
- **Extended quota** (desde 15/05/2025): só para **organizações** com empresa registrada, serviço já lançado e cerca de **250 mil usuários ativos por mês**. A análise pode levar até 6 semanas.

Na prática, o produto como está serve para protótipo ou beta fechado com até 5 pessoas. Para crescer, é preciso negociar com o Spotify ou ter uma alternativa (veja 1.5).

### 1.4 Prévias de áudio e "personalização"
A seção V.2.2 diz que as prévias de áudio *"may only be used to promote the underlying content"* e não podem ser usadas em produtos de personalização ou de mensagens. Desde nov/2024 o `preview_url` também não vem mais para apps novos.
**Solução adotada:** a "trilha sonora do match" usa o **player oficial do Spotify** (iFrame API), que segue os Widget Terms, mostra capa e metadados e leva de volta ao Spotify (atende II.4 e II.5). Sem login no Spotify, o player toca cerca de 30 segundos; com Premium logado, toca a faixa inteira. O autoplay pode ser desligado no painel.

### 1.5 Recomendação estratégica
Considere oferecer um caminho complementar, como escolher artistas manualmente ou importar do Last.fm. Assim o produto não depende 100% de uma autorização que o Spotify pode negar ou revogar. Isso contraria o pedido de que o Spotify seja "a única maneira de criar uma conta", então fica como decisão de produto.

---

## 2. Requisitos da Developer Policy e como o tema atende

| Requisito (seção) | Implementação |
|---|---|
| Política de privacidade clara sobre acesso, uso e divulgação (I.1.1) | Página `/privacidade/` criada na ativação, com resumo em linguagem simples, tabela de bases legais e uma tabela de "quem vê o quê" |
| Pedir só os dados necessários (I.1.1) | Login pede apenas `user-top-read`. `playlist-modify-private` é pedido só quando a pessoa clica em "Salvar no Spotify" (autorização incremental). Não pedimos `user-read-email` |
| Não enviar e-mail sem consentimento (I.1.1) | O app não coleta e-mail |
| Segurança padrão de mercado (I.4) | Tokens não são armazenados. Login OAuth com `state` + cookie vinculado ao navegador. REST com nonce. `/wp/v2/users` desativado. Sem páginas de autor |
| Desconectar com facilidade (I.1.2) | Perfil > Privacidade > Apagar minha conta (efeito imediato), com link para `spotify.com/account/apps` |
| Apagar os dados ao desconectar (I.1.2) | `spotinder_delete_member()` remove usuário, metadados e todas as linhas das tabelas próprias |
| Atribuição ao Spotify (II.4.1) | Texto "Artistas e músicas via Spotify" nos cartões + rodapé. Campo para o logo oficial em Spotify > Rodapé |
| Link de volta em metadados e capas (II.4.2) | Cada artista e cada faixa leva ao Spotify. Os players são os oficiais |
| Não reproduzir sem capa/metadados (II.5) | Só o player oficial é usado |
| Não treinar IA (III.14) | Proibido na política e não implementado |
| Não transferir dados para outro serviço (III.9) | Nada é exportado para terceiros. A exportação JSON é a portabilidade do próprio titular, permitida pela exceção |
| Não ser voltado a crianças (III.8) | 18+ autodeclarado. Denúncia "parece ser menor de idade". Política de remoção |
| Nome sem "Spot" (VI.2) | **Não atendido** (veja 1.1) |
| Uso não comercial (IV) | Sem venda de anúncios no app com streaming. Monetização precisa de revisão jurídica |

### Detalhe técnico: não detectamos a revogação feita no próprio Spotify
Como não guardamos tokens, não temos como saber quando alguém remove o app em `spotify.com/account/apps`. Para cumprir I.1.2 na prática:
- a política explica isso e recomenda apagar a conta no app primeiro;
- a rotina diária apaga contas inativas após *N* dias (configurável; o padrão é 365). A política mostra o valor real com `[spotinder_opcao campo="inactive_delete_days"]`;
- recomendação: reduzir o prazo para 180 dias, alinhado à expiração de 6 meses dos refresh tokens que o Spotify adotou em 2026.

---

## 3. LGPD: mapeamento

| Tratamento | Base legal | Observação |
|---|---|---|
| Conta e login | Execução de contrato (art. 7º, V) | ID e nome público do Spotify |
| Exibir artistas a outros e calcular compatibilidade | Consentimento (art. 7º, I e art. 8º) | Coletado em destaque, no último passo do cadastro, depois de a pessoa ver o próprio cartão. Fica registrado com data e versão |
| Exibir redes sociais aos matches | Consentimento específico | Checkbox à parte com aviso de responsabilidade. O servidor recusa salvar redes sem esse aceite |
| Moderação e segurança | Legítimo interesse (art. 7º, IX) | Recomendável fazer um LIA (teste de legítimo interesse) por escrito |
| Registros de acesso | Obrigação legal (Marco Civil, art. 15) | 6 meses, no servidor/hospedagem |

**Gosto musical como dado sensível?** Gosto musical não está na lista do art. 5º, II. Mesmo assim, pode sugerir religião (gospel), origem ou convicções. O tema não faz inferências e não usa gêneros para segmentar. Mantenha isso: qualquer filtro do tipo "religião" ou "etnia" a partir de gêneros transformaria o tratamento em sensível.

**Decisões automatizadas (art. 20):** a política explica os critérios da compatibilidade e oferece revisão pelo encarregado.

**Direitos do titular (art. 18):**
- acesso e portabilidade: botão "Baixar meus dados" (JSON);
- correção: edição do perfil;
- eliminação: "Apagar minha conta";
- revogação: pausar o perfil ou remover redes.

Também estão integrados às ferramentas nativas do WP (Ferramentas > Exportar/Apagar dados pessoais), buscando pelo login `sp_...`.

**Versão de política:** alterar "Versão da política/termos" no painel obriga todos a aceitar de novo no próximo acesso, porque o deck e os matches ficam bloqueados até o novo aceite.

---

## 3.1 Foto de perfil (adicionada na versão de conteúdo 2)

A foto é **opcional e opt-in**. As opções são: sem foto (iniciais, o padrão), foto do perfil do Spotify ou foto enviada pela pessoa.

| Ponto | Decisão |
|---|---|
| Base legal | Consentimento específico (art. 7º, I), registrado à parte em `consents.photo` com data. Quem escolhe "Sem foto" revoga o consentimento e a foto enviada é apagada |
| Foto do Spotify | `/me` já devolve `images` sem escopo extra. Guardamos só a URL (a imagem fica no CDN do Spotify) e ela só aparece se a pessoa escolher usá-la. A política foi corrigida, pois antes dizia "não acessamos sua foto" |
| Foto enviada | JPG/PNG/WebP de até 5 MB, com o conteúdo real verificado. É recortada em 640×640 e regravada, o que remove EXIF/GPS (testado: o arquivo final não contém o bloco EXIF). O original é descartado |
| Armazenamento | `uploads/spotinder-photos/` com nome aleatório de 32 caracteres e `index.php` contra listagem. Fica fora da Biblioteca de Mídia, então não aparece em `/wp/v2/media` |
| Dados sensíveis | Uma foto pode revelar origem racial/étnica (art. 11). Ela é só exibida: não entra no cálculo de compatibilidade nem na ordenação do deck, e não há reconhecimento facial |
| Visibilidade | Todos os membros (deck). Isso está explícito no aviso de consentimento e nos Termos (seção 4) |
| Moderação | Motivo de denúncia "Foto imprópria ou de outra pessoa" e ação "Remover foto" em Ferramentas e moderação |
| Retenção | A foto é apagada na hora ao trocar, escolher "Sem foto", excluir a conta ou ser removida pela moderação. O apagador de dados do WP também a remove |
| Novo aceite | A versão da política passou para `2026-10-30.2`. Todos os membros revisam os termos no próximo acesso (LGPD art. 8º, §6º: mudança de finalidade/dados) |

Spotify Developer Policy: a foto de perfil é dado pessoal do usuário obtido pela plataforma. O uso dela é coberto pela política de privacidade (I.1.1) e pela exclusão ao desconectar (I.1.2). Ela não é "Spotify Content" (metadados ou capas), então as regras de atribuição não se aplicam. As fotos dos artistas, por outro lado, são Spotify Content: elas aparecem com link para o Spotify e com atribuição.

## 3.2 Localização aproximada (versão de conteúdo 3)

A localização é **opcional e opt-in**. O requisito central é que a posição exata nunca seja guardada. O tema vai além: ela **nunca sai do aparelho**.

| Ponto | Decisão |
|---|---|
| Coleta | API de geolocalização do navegador com `enableHighAccuracy: false`. O navegador exige HTTPS. Em HTTP, a interface explica que não está disponível |
| Arredondamento no aparelho | `src/js/location.js` arredonda para o centro de uma célula de 0,05° (~5,5 km × ~5,2 km no Brasil) **antes** de enviar. Testado: com o aparelho em `-19.93212, -43.93811`, a requisição enviou só `-19.925, -43.925` |
| Defesa em profundidade | O servidor arredonda de novo (`spotinder_snap_coord()`). Mesmo um cliente adulterado que envie a posição exata tem só a célula gravada (testado) |
| O que é guardado | Só a região mais recente (`spotinder_region`): centro da célula, nome do município e data. Sem histórico: cada atualização sobrescreve a anterior |
| Nome da região | Tabela local de 5.570 municípios do IBGE (`data/municipios.json`, gerada a partir de kelvins/municipios-brasileiros). **Nenhum serviço de mapas** recebe coordenadas |
| Atualização automática | Ao abrir o app, se a localização estiver ativa **e** o navegador já tiver concedido permissão (o app nunca abre o pedido de permissão sozinho), no máximo a cada 30 min, e só envia se a célula mudou |
| O que os outros veem | Município e distância em **faixas** ("a menos de 5 km", "até 10/25/50/100/250/500 km", "mais de 500 km"). Coordenadas nunca vão para o navegador de outra pessoa (testado no JSON do deck) |
| Triangulação | Como a posição guardada já é a célula e as distâncias vêm em faixas, comparar distâncias de várias contas revela no máximo a área de ~5 km. Os Termos proíbem tentar isso, e há um motivo de denúncia específico ("Tentou descobrir onde estou ou me perseguiu") |
| Uso | Filtro de distância máxima no deck e bônus pequeno na ordenação (+6 até 10 km, +4 até 25 km, +2 até 50 km). O gosto musical continua sendo o critério principal |
| Base legal | Consentimento específico (art. 7º, I), registrado em `consents.location` na primeira ativação. Desativar apaga a região, a distância máxima e o aceite |
| Risco residual | Em municípios muito pequenos, o nome da cidade já identifica bastante. A página de Segurança orienta a deixar a localização desativada nesse caso |
| Novo aceite | A versão da política passou para `2026-10-30.3` |

Spotify Developer Policy: a localização não vem do Spotify e não é combinada com dados do Spotify para criar perfis de publicidade (III.13). Ela só filtra e ordena o deck.

## 4. Responsabilidade fora do app (redes sociais)

Sem chat, o contato acontece nas redes que cada pessoa escolhe mostrar. Medidas adotadas:
1. Aviso editável no cadastro e na tela do match ("Atenção antes de compartilhar suas redes").
2. Consentimento específico e registrado.
3. Redes aparecem **só para matches**. Nunca no deck, nunca para visitantes.
4. Página `/seguranca/` com dicas, sinais de alerta e canais de emergência: 190, 180, 100 e 188 (CVV).
5. Desfazer match, bloquear (o bloqueio vale nos dois sentidos) e denunciar. Quem denuncia bloqueia a pessoa automaticamente.
6. Convites no lugar do chat: uma proposta pendente por vez e recado de até 140 caracteres. Isso reduz a chance de assédio.
7. Eventos "oficiais" como sugestão de primeiro encontro em local público.

---

## 5. Pendências para o responsável legal preencher

Os itens entre `[preencher: ...]` nas páginas Política de Privacidade e Termos de Uso:
- razão social, CNPJ e endereço;
- nome e e-mail do encarregado (DPO);
- provedor de hospedagem e país;
- e-mail de contato para contestar suspensões.
