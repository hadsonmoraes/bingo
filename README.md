# Bingo — Globo físico com sorteio manual

Sistema de bingo presencial para operar com um **globo físico girado manualmente por um operador**. O sistema **não sorteia números automaticamente** e **não simula globo virtual**: cada pedra retirada do globo é identificada pelo operador e registrada manualmente.

## Stack

- Laravel (PHP 8.4) + Blade + SQLite (funciona 100% offline)
- Tailwind CSS + JavaScript (Vite)
- Docker Compose (Apache + PHP 8.4)

## Como rodar (Docker)

```bash
# primeira vez
docker compose build
docker compose run --rm --user "$(id -u):$(id -g)" node npm install
make npm ARGS="run build"
docker compose up -d
docker compose exec app php artisan migrate:fresh --seed

# usar
open http://localhost:8000
```

## Por que `composer` e `php` falham na sua máquina?

O projeto roda com **PHP 8.4** (Laravel 13 exige PHP ^8.3), mas sua máquina tem o PHP **8.2** do XAMPP em `/opt/lampp/bin/php`. Qualquer `composer ...` no host para com:

```
Root composer.json requires php ^8.3 but your php version (8.2.12) does not satisfy
```

**Tudo precisa rodar dentro do Docker.** O Composer agora vem instalado dentro da imagem, e o [Makefile](Makefile) já encapsula os comandos com o UID correto do seu usuário:

| Comando | Equivalente |
|---|---|
| `make composer ARGS="install"` | `composer install` |
| `make composer ARGS="require pacote"` | instalar dependência |
| `make artisan ARGS="migrate"` | `php artisan migrate` |
| `make test` | `php artisan test` |
| `make pint` | `vendor/bin/pint` |
| `make migrate` | `php artisan migrate:fresh --seed` |
| `make npm ARGS="run build"` | `npm run build` (via Docker) |
| `make up` / `make down` | subir / derrubar |

Sem o Makefile, o Composer dentro do container deve ser chamado assim (para os arquivos não ficarem com dono `root`):

```bash
docker compose exec -T -e COMPOSER_HOME=/tmp/composer --user "$(id -u):$(id -g)" app composer install
```

Comandos úteis:

```bash
make test                                        # testes
make pint                                        # estilo de código
make npm ARGS="run build"                        # rebuild dos assets
docker compose exec app php artisan route:list   # rotas
```

## Laravel Boost (MCP) no VS Code + Copilot

O projeto usa o **Laravel Boost** com tudo configurado para o **Copilot do VS Code** (sem depender do Claude Code):

| Item | Local | Função |
|---|---|---|
| [`.vscode/mcp.json`](.vscode/mcp.json) | VS Code | Servidor MCP `laravel-boost` para o Copilot |
| [`.github/skills/`](.github/skills) | VS Code | 5 skills: `laravel-best-practices`, `tailwindcss-development`, `testing-best-practices`, `deploying-to-cloud` e `infer-conventions` |
| [`AGENTS.md`](AGENTS.md) | raiz | Diretrizes de agente (estrutura, convenções, uso das ferramentas Boost) |
| [`.mcp.json`](.mcp.json) | raiz | Mesma config MCP para outros agents, se quiser |

O MCP roda **dentro do Docker** (o host só precisa do Docker — o PHP 8.2 local não é usado). Ao abrir o workspace no VS Code, o Copilot detecta o servidor `laravel-boost` e pede aprovação para usá-lo. Ferramentas disponíveis ao agente:

`application-info` · `database-schema` · `database-query` (somente leitura) · `database-connections` · `search-docs` (documentação oficial versionada) · `last-error` · `read-log-entries` · `browser-logs` · `get-absolute-url` · `record-rule`

Comandos Boost (dentro do container):

```bash
docker compose exec app php artisan boost:install       # instalar/atualizar guidelines e skills
docker compose exec app php artisan boost:list-skills   # listar skills
docker compose exec app php artisan boost:update        # atualizar guidelines
```

## Fluxo da partida

1. **Criar a partida** com a identidade do evento: nome, instituição, logotipo, data, valor da cartela e cor do tema (formatos: 75 bolas com B-I-N-G-O ou intervalo personalizado).
2. **Cadastrar prêmios** com regra de vitória (cartela cheia, uma linha, duas linhas, quatro cantos ou padrão personalizado), valor estimado e imagem opcional.
3. **Gerar cartelas** por lote, com códigos únicos, e imprimir o modelo profissional: cabeçalho personalizado, prêmios na própria cartela, **um bloco numérico por prêmio**, área do comprador e canhoto destacável opcional.
4. **Iniciar a partida** e abrir a **tela de sorteio** (operador) e a **tela pública** (TV/projetor).
5. O operador gira o globo, retira a pedra, digita o número no **teclado numérico** e clica em **“Confirmar pedra”**.
6. Acompanhe o painel **Cartelas na boa**: cartelas e blocos a 1, 2 ou 3 números (configurável) de completar a condição do prêmio correspondente, com os números que faltam.
7. Quando alguém declarar bingo, o operador informa o código da cartela e o prêmio em **“Verificar ganhador”**. A validação usa **exclusivamente** os números registrados na rodada e o **bloco correspondente ao prêmio**; o nome do comprador cadastrado preenche o ganhador automaticamente e a tela exibe **“TEMS UM GANHADOR!”**.

## Cartelas profissionais

- **Cabeçalho personalizado**: nome do evento, instituição, logotipo (opcional), título BINGO, código único, data e valor da cartela — sem logotipo, um cabeçalho tipográfico elegante é usado.
- **Prêmios na cartela**: seção “PRÊMIOS DO BINGO” alimentada pelo cadastro do evento, com nome, modalidade e valor estimado.
- **Um bloco numérico por prêmio**: cada prêmio cadastrado gera um bloco independente (B1, B2, …), com combinação válida e legenda fora da grade — 1 prêmio = 1 bloco, calculado automaticamente. A quantidade de blocos nunca é fixa no código.
- **Blocos numéricos** grandes e legíveis, com contraste para impressão em preto e branco; casa livre destacada (75 bolas).
- **Comprador**: linhas para preenchimento manual (nome, telefone, assinatura) ou cadastro digital na página de cartelas — nunca obrigatório.
- **QR Code de acompanhamento** (opcional, ativo por padrão): cada cartela impressa (prévia e PDF) carrega um QR que abre a página pública da própria cartela (`/games/{id}/card/{codigo}`) — o comprador escaneia com a câmera do celular e acompanha o sorteio marcando os números em tempo real, com “faltam N” por bloco e aviso de BINGO. Gere/imprima as cartelas pelo mesmo endereço que o público usará no dia do evento (o QR aponta para o endereço usado na geração).
- **Canhoto destacável** (opcional) repetindo o código da cartela, com linha de corte fora da grade.
- **Emissão**: pré-visualização fiel à folha A4 (210 × 297 mm), impressão direta e **PDF A4 gerado no servidor** (dompdf); cartelas de bloco único permitem 1, 2 ou 4 por página e cartelas com vários blocos usam uma folha por cartela. Se houver mais blocos do que cabe com legibilidade, o sistema avisa e permite dividir os blocos em mais de uma folha — os números nunca são reduzidos a um tamanho ilegível.
- **Controle**: busca por código, comprador ou telefone; situações disponível / vendida / em jogo / vencedora / cancelada; reimpressão sem gerar novos números; lotes numerados; prêmios congelados por cartela na emissão (alterações depois não mudam cartelas já impressas) e aviso para gerar um novo lote.

## Arte personalizada (exportar/importar)

- **“Baixar arte para editar”**: SVG vetorial em A4 (milímetros) por cartela ou modelo-base com áreas reservadas nomeadas — abre em Inkscape, Illustrator, CorelDRAW e Canva. O PDF A4 continua disponível para intercâmbio. PSD não é gerado (não há geração confiável de camadas editáveis neste ambiente).
- **“Importar arte personalizada”** (`/games/{id}/card-templates`): envie SVG, PNG ou PDF (máx. 4 MB, com validação de conteúdo — SVG com scripts é rejeitado), mapeie as áreas dos dados dinâmicos em milímetros (cabeçalho, código, prêmios, cada bloco, comprador, canhoto) com pré-visualização A4, salve como modelo reutilizável, duplique, defina um modelo padrão e aplique-o à pré-visualização e ao PDF.
- **Arte e dados separados**: a arte importada define a aparência; números, identificadores, prêmios e a relação bloco↔prêmio continuam controlados pelo sistema. Importar ou alterar a arte **nunca** modifica os dados do jogo. Para embutir a arte no PDF final use PNG (o dompdf do projeto não rasteriza SVG/PDF); se faltar área para algum bloco ou prêmio, um aviso é exibido antes da geração.

## Painel de cartelas e blocos na boa

Atualizado após cada pedra, distinguindo **cartelas físicas** de **blocos numéricos** (uma cartela com quatro prêmios continua sendo UMA cartela, com quatro oportunidades). Mostra cartelas/blocos em jogo, na boa, distantes, vencedores e cancelados. Um bloco está “na boa” quando falta no máximo o limite configurado (0–3) para completar a condição do seu prêmio — sempre considerando a melhor forma possível. A tela mostra cartela, bloco, prêmio e os números exatos que faltam. Dados pessoais do comprador não aparecem no estado da partida (nem no telão público).

## Tela do operador (`/games/{id}/draw`)

- Campo + teclado numérico de toque e botão grande **“Confirmar pedra”**.
- **Verificar ganhador aceita leitura de código de barras/QR**: o campo recebe o código puro (C-0001) ou a URL completa impressa no QR (leitores USB funcionam como teclado; o sistema extrai o código automaticamente). O botão **📷** escaneia o QR pela câmera quando o navegador suporta (BarcodeDetector — requer HTTPS ou acesso via `localhost`; na rede local via HTTP a câmera é bloqueada pelo próprio navegador, use um leitor USB).
- Última pedra em destaque (letra + número), painel completo com os sorteados, histórico da rodada e contador de restantes.
- **Desfazer último registro** com confirmação e motivo obrigatório — a correção fica registrada no histórico.
- **Desfazer validação de ganhador** com confirmação e motivo obrigatório (ex.: cartela não foi vendida ou validação por engano): o prêmio volta à disputa, a cartela e o bloco retornam ao jogo e a correção fica no histórico. A validação em cartela sem comprador cadastrado exibe um aviso.
- Alerta visual e sonoro ao tentar registrar número duplicado ou fora do intervalo.
- Pausar/retomar a partida a qualquer momento.
- Cada pedra é salva no banco imediatamente: a partida é recuperada automaticamente após qualquer interrupção (basta recarregar a página).

## Tela pública (`/games/{id}/screen`)

Otimizada para TV/projetor, com número gigante da última pedra (letra + número), pedras anteriores, painel completo, prêmio em disputa com a regra, mensagem de ganhador validado e botão de tela cheia. Atualiza sozinha via polling local.

## Acompanhamento da cartela pelo comprador (`/games/{id}/card/{codigo}`)

Página pública mobile, destino do **QR Code impresso na cartela**. O comprador escaneia com a câmera nativa do celular e vê a própria grade com os números sorteados marcados, a última pedra, quantos faltam para cada bloco completar o seu prêmio e os ganhadores validados — atualizando sozinha via polling local. Quando um bloco da cartela completa a condição, a página vibra e exibe **“Bingo! Chame o operador para validar!”**. Dados pessoais (comprador/telefone) nunca aparecem; apenas os números da cartela informada.

## Histórico

Todas as partidas ficam guardadas no SQLite. Em **Partidas** você vê todas; dentro de cada uma há a página **Histórico da partida** com as pedras em ordem, todos os eventos (início, pausas, retomadas, correções com motivo, ganhadores validados) e os dados de cada cartela.

## Regras de segurança implementadas

- Aceita apenas números válidos do formato selecionado; bloqueia duplicados na rodada.
- Exige confirmação (modal com motivo) para qualquer correção administrativa.
- Nenhum sorteio automático, nenhuma animação de globo virtual, nenhuma aleatoriedade sobre o número informado — a aleatoriedade existe apenas na geração das cartelas.
- A validação de ganhador considera somente os números registrados naquela rodada e a regra do prêmio configurada.
