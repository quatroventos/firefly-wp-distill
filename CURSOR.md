# CURSOR.md — Brief de implementação firefly-distill

| Campo | Valor |
|-------|-------|
| **Projeto** | firefly-distill (ferramenta **interna** Quatro Ventos / Firefly) |
| **Autor** | Gabriel Roloff |
| **Data** | 2026-11-06 (America/Sao_Paulo) |
| **Base** | `PROJETO.md` v2.2 (PRD). Em conflito, **PROJETO.md vence**; este arquivo diz *como* implementar |
| **Idioma** | pt-BR (docs, UI do wp-admin, comentários de domínio). Identificadores de código em inglês |

---

## 1. Objetivo

Construir um sistema que pega sites WordPress de clientes com contrato SEO/GEO da Firefly e:

1. Gera, na **nuvem Firefly**, uma versão **HTML/CSS puro** de cada página pública (crawl com bypass → destilação Impeccable → LLM → SEO/GEO → loop Lighthouse).
2. **Grava o HTML no disco do cliente** e o servidor web (Apache/Nginx/LiteSpeed/reverse proxy) serve esse arquivo **antes** do PHP/WP.
3. Suporta **landing pages multi-host (caso Incavel)**: várias LPs no mesmo WP, cada uma respondendo como site próprio em outro domínio, com pasta, `robots.txt`, `sitemap.xml` e `llm.txt` **próprios**, configuradas numa **tela de mapeamento** no wp-admin.
4. Regenera **incrementalmente** (só a página/host que mudou).
5. Mostra um **dashboard** com Lighthouse/PSI + SEO de **todas** as URLs, por hostname, cruzado com **OpenSEO**, e um backlog de **sugestões de IA** revisáveis.

### O que NÃO fazer

- ❌ SaaS / produto vendável: sem planos, preços, créditos, trial, checkout, marketplace, white-label.
- ❌ WordPress headless + React/Next/Astro/qualquer SSR Node para o visitante.
- ❌ Rodar LLM, Playwright, Chromium, Lighthouse ou Impeccable **dentro do PHP do cliente**.
- ❌ Gerar HTML dentro de hooks do WP (hooks **só enfileiram**).
- ❌ Usar o estático como input de regeneração (sempre origem via **bypass**).
- ❌ Servir estático para POST, usuário logado, wp-admin, wp-json, preview, bypass.
- ❌ Misturar `robots.txt`/`sitemap.xml`/`llm.txt` da LP com os do site mãe ou com o sitemap global do Yoast/Rank Math.
- ❌ Escolher pasta no disco a partir do header `Host` cru (sempre allowlist).
- ❌ WooCommerce checkout, área de membros, WP Multisite.
- ❌ Commitar segredos. Só `.env.example` com placeholders.
- ❌ Inventar preços ou métricas de negócio em docs/UI.

---

## 2. Stack

| Camada | Tecnologia | Observações |
|--------|------------|-------------|
| Plugin WP | **PHP 8.1+**, WP 6.4+, sem framework, autoload PSR-4 via Composer (classmap no zip final), `$wpdb` + `dbDelta` | Enxuto: zero dependência pesada; UI com WP List Table + JS vanilla |
| Nuvem API | **Laravel 11+** (PHP 8.3), Horizon + Redis (fila), MySQL 8 ou Postgres 15 | Orquestrador, HMAC, jobs, dashboard interno, sync OpenSEO |
| Worker | **Node 20+ / TypeScript**, **Playwright** (Chromium), **Lighthouse** (lib/LHCI), cliente LLM provider-agnostic | Container efêmero por job; puxa job da API |
| Destilação | Skill **Impeccable** (design system → tokens + componentes) executada pelo agente no worker | Saída: `design-tokens.json` + `assets/site.css` |
| SEO data | **OpenSEO** (projeto do cliente; GSC, keywords, indexação, oportunidades) | Cliente isolado em `packages/openseo-client`; por site e por host |
| Storage | S3-compatível (artefatos, screenshots, logs) | Retenção interna |
| Entrega | REST `receive-build` do plugin **ou** SFTP | Mesmo layout de disco |
| Infra | Docker Compose (dev), Hetzner/infra Firefly (prod) | |

---

## 3. Arquitetura (bullets)

- **WordPress = fonte da verdade.** O plugin descobre URLs, resolve mapeamento post→host e enfileira mudanças.
- **Plugin → Nuvem:** `POST /api/v1/sites/{site}/enqueue` assinado (HMAC + timestamp). Debounce 60s por host.
- **Nuvem:** Laravel cria `job` (scope `full | host | host_meta | pages | audit | suggest`) e o coloca no Redis.
- **Worker:** `GET /api/v1/worker/jobs/next` → recebe payload (hosts, paths, token de bypass, canal de entrega).
- **Crawl:** Playwright abre `https://{host}{path}?firefly-distill=origin&ff_sig=…&ff_exp=…` **no host público**, com `Host` correto → reverse proxy cai no WP (bypass).
- **Geração:** Impeccable destila tokens (reusa `design-tokens.json` do host se válido) → LLM gera HTML semântico + CSS global → pass SEO/GEO → Lighthouse (≤3 iterações).
- **Host meta:** worker gera `robots.txt`, `sitemap.xml`, `llm.txt` do host a cada job daquele host.
- **Entrega:** arquivos em `builds/{host}/{build_id}/` (REST ou SFTP) → plugin valida manifest/checksums → symlink atômico `current/{host}`.
- **Servir:** Nginx/Apache do vhost faz `try_files` em `current/{host}/…` **antes** do `proxy_pass`/PHP; pasta vem de `map $host` gerado pelo plugin (`hosts.map.conf`).
- **Audit:** Lighthouse/PSI de todas as URLs do host (origin e build) → `page_audits`.
- **OpenSEO sync:** por host quando houver `openseo_project_id`/GSC própria; senão herda do site e filtra por hostname.
- **Agente de sugestões:** lê origem + destilado + `page_audits` + fatia OpenSEO → `suggestions` (backlog humano). Apply = Fase 2.
- **Webhook** nuvem → plugin com status, audits e suggestions (espelho local para o wp-admin).

```
Visitante → vhost {host} → try_files current/{host}/… ─hit→ HTML
                                     └─miss/POST/bypass/logado→ proxy_pass → WP
WP hook → plugin (host,path) → API Laravel → Redis → Worker Node
   → crawl {host}+bypass → Impeccable+LLM → SEO → Lighthouse
   → robots/sitemap/llm.txt → receive-build → builds/{host}/{id} → symlink current/{host}
```

---

## 4. Estrutura de pastas do monorepo

```
firefly-distill/
  PROJETO.md                         # PRD v2.2
  CURSOR.md                          # este brief
  README.md
  .editorconfig  .gitignore
  plugin/
    firefly-distill/
      firefly-distill.php            # bootstrap, constantes, autoload
      composer.json
      uninstall.php
      src/
        Plugin.php                   # registra módulos
        Install/Schema.php           # dbDelta ffd_*
        Admin/
          Menu.php
          StatusPage.php
          HostsMappingPage.php       # tela de mapeamento
          HostsListTable.php
          DashboardPage.php
          UrlDetailPage.php
          SuggestionsPage.php
          JobsPage.php  BuildsPage.php  ExclusionsPage.php  ConnectionPage.php  HelpPage.php
          assets/admin.js  admin.css # vanilla
        Hosts/
          HostRepository.php
          HostResolver.php           # post_id → [(host, path)]
          HostImporter.php           # CSV host,post_id,...
          HostsMapWriter.php         # gera hosts.map.conf
          HostValidator.php          # ^[a-z0-9.-]+$, unicidade
        Crawl/UrlDiscoverer.php  Crawl/BypassToken.php
        Queue/Enqueuer.php  Queue/Hooks.php  Queue/Debounce.php
        Rest/
          Routes.php
          StatusController.php  UrlsController.php  QueueController.php
          HostsController.php        # CRUD, import, search-posts, rebuild, rollback
          ReceiveBuildController.php
          BuildsController.php  ServeController.php
          AuditsController.php  SuggestionsController.php  WebhookController.php
        Serving/
          Publisher.php              # symlink atômico por host
          Exclusions.php
          SnippetGenerator.php       # Nginx/Apache por host
          SeoGuards.php              # canonical p/ host público, exclusão do sitemap global
        Security/Hmac.php  Security/PathGuard.php
        Model/{Host,HostRoute,QueueItem,Build,Page,PageAudit,Suggestion}.php
      tests/                         # PHPUnit + wp-env
  cloud/
    apps/
      api/                           # Laravel 11+
        app/Models/{Site,Host,Job,JobPage,Build,LighthouseRun,PageAudit,Suggestion,Artifact}.php
        app/Http/Controllers/Api/V1/...
        app/Http/Middleware/VerifySiteHmac.php  VerifyWorkerToken.php
        app/Jobs/{DispatchJob,SyncOpenSeo,RunSuggestAgent}.php
        app/Services/{BypassSigner,Debouncer,DeliveryService}.php
        database/migrations/...
        routes/api.php
        resources/views/dashboard/... # dashboard interno (Blade, sem SPA)
        tests/
      worker/                        # Node 20 + TS
        src/index.ts                 # loop: pull job → run → report
        src/crawl/origin.ts          # Playwright + bypass + Host
        src/distill/impeccable.ts
        src/generate/llm.ts  src/generate/html.ts
        src/seo/onpage.ts
        src/hostmeta/{robots,sitemap,llmtxt}.ts
        src/lighthouse/run.ts
        src/deliver/{rest,sftp}.ts
        src/manifest.ts
        test/
    packages/
      shared/                        # JSON Schemas: manifest, job payload, webhook, CSV de hosts
        schemas/{manifest,job,webhook,hosts-import}.schema.json
      openseo-client/                # cliente PHP (Laravel package)
    docker-compose.yml
    Dockerfile.api  Dockerfile.worker
    .env.example
  infra/
    nginx/ffd-maps.conf  nginx/lp-vhost.conf.example  nginx/ffd-proxy-headers.conf
    apache/lp-vhost.conf.example
  fixtures/
    wp/                              # wp-env com site mãe + 2 LPs mapeadas
    hosts.csv
  docs/
    rewrite-apache.md  rewrite-nginx.md  bypass.md
    multi-host-incavel.md  openseo-dashboard.md  suggestions-agent.md
```

---

## 5. Modelo de dados

### Plugin (`{$wpdb->prefix}ffd_*`, criado por `dbDelta`)

**`ffd_hosts`**

| Coluna | Tipo | Regra |
|--------|------|-------|
| id | BIGINT UNSIGNED PK AI | |
| host | VARCHAR(253) UNIQUE | minúsculo, `^[a-z0-9.-]+$`, sem esquema/porta |
| post_id | BIGINT UNSIGNED | raiz `/`; único entre hosts |
| https | TINYINT(1) | default 1 |
| flag_robots_index | TINYINT(1) | default 1 |
| flag_sitemap | TINYINT(1) | default 1 |
| flag_llm_txt | TINYINT(1) | default 1 |
| static_enabled | TINYINT(1) | default 0 (liga após 1º build ok) |
| openseo_project_id | VARCHAR(64) NULL | vazio = herda `ffd_settings` |
| gsc_property | VARCHAR(255) NULL | |
| current_build_id | VARCHAR(64) NULL | |
| is_primary | TINYINT(1) | 1 = host do `home_url()` |
| created_at / updated_at | DATETIME | |

**`ffd_host_routes`**: id, host_id (FK), path (VARCHAR 255, começa e termina com `/`), post_id, in_sitemap. UNIQUE(host_id, path).

**`ffd_queue`**: id, host, path, url, post_id, reason (`save_post|delete|menu|host_meta|manual`), status (`pending|sent|done|error`), cloud_job_id, enqueued_at, processed_at.

**`ffd_builds`**: id, host, build_id, path, is_current, page_count, lighthouse_summary JSON, created_at.

**`ffd_pages`**: id, host, build_id, path, source_url, output_relpath, content_hash CHAR(64), post_modified_gmt, lh_scores JSON.

**`ffd_page_audits`** (espelho): id, host, path, url (pública), phase (`origin|build`), performance, seo, accessibility, best_practices, psi_json, openseo_slice, audited_at. INDEX(host, path).

**`ffd_suggestions`** (espelho): id, cloud_suggestion_id, host, url, category, severity, title, body, status (`open|accepted|rejected|applied|deferred`), source (`agent|openseo|lighthouse`), updated_at.

**Option `ffd_settings`** (autoload=no): cloud_url, site_key_id, serve_enabled, retention_builds (5), exclusions[], openseo_project_id (default do site). Segredo HMAC: constante `FFD_HMAC_SECRET` em `wp-config.php` ou option autoload=no — nunca exibido.

> Não usar CPT para hosts (tabela dá unicidade/índices). CPT não é necessário no MVP.

### Nuvem (migrations Laravel)

| Tabela | Campos |
|--------|--------|
| sites | id, home_url, hmac_secret (encrypted cast), delivery (`rest|sftp`), sftp_meta (encrypted, nullable), openseo_project_id, client_label, active |
| hosts | id, site_id, host, post_id, routes_json, https, flag_robots_index, flag_sitemap, flag_llm_txt, static_enabled, **openseo_project_id (nullable)**, gsc_property, current_build_id, UNIQUE(site_id, host) |
| jobs | id, site_id, host_id (nullable p/ full), scope, status, urls_json, error, started_at, finished_at |
| job_pages | id, job_id, host_id, path, status, origin_hash, output_hash, output_relpath |
| builds | id, job_id, host_id, build_id, checksum, delivered_at, activated_at |
| lighthouse_runs | id, job_id, job_page_id, phase, scores_json |
| page_audits | id, site_id, host_id, path, url, phase, performance, seo, accessibility, best_practices, psi_json, openseo_slice, audited_at |
| suggestions | id, site_id, host_id, url, category, severity, title, body, status, source, dedupe_hash, payload_json, timestamps |
| artifacts | id, job_id, kind, s3_key, expires_at |

Status do job: `queued → crawling → distilling → generating → seo → optimizing → lighthouse → hostmeta → packaging → delivering → auditing → suggesting → completed | completed_with_warnings | failed`.

---

## 6. Telas wp-admin (menu “firefly-distill”, `manage_options`)

1. **Status** — serve on/off global, hosts ativos, build `current` por host, fila, última sync, vínculo OpenSEO.
2. **Hosts / Mapeamento** ⭐
   - Lista (WP_List_Table): host público, https, post raiz (título + ID + link editar), **permalink interno** vs **URL pública** lado a lado, flags robots / sitemap / llm.txt, estático on/off, último build, média Lighthouse, OpenSEO.
   - Form adicionar/editar: host (validação ao vivo), https, **busca de página/post por título** (autocomplete → `GET /hosts/search-posts?q=`; tipos públicos), rotas extras (path → post via mesma busca), flags, `openseo_project_id`, `gsc_property`.
   - Ações: Testar host (HEAD público + HEAD com bypass, mostra se veio estático ou WP via header `X-FFD: static|origin`), Rebuild host, Rollback host, Ver `robots.txt`/`sitemap.xml`/`llm.txt` publicados, Copiar snippet Nginx/Apache do host.
   - **Import opcional:** textarea/upload CSV `host,post_id[,path][,https][,robots][,sitemap][,llm][,static]` → dry-run com erros por linha (host inválido/duplicado, post inexistente/não publicado, post raiz repetido) → Confirmar.
   - Export: CSV + download de `hosts.map.conf`.
3. **Dashboard** — seletor de host; médias por host; tabela por URL pública (permalink interno em cinza) com Perf/SEO/A11y/BP origin×build, cliques/impressões/posição (OpenSEO), indexação, nº sugestões abertas.
4. **URL detail** — scores before/after, top audits, fatia OpenSEO, links origem (bypass) × estático, sugestões.
5. **Sugestões** — filtros host/categoria/severidade/status; aceitar/rejeitar/adiar.
6. **Fila / jobs** — por host; rebuild página/host/full; re-auditar.
7. **Builds** — por host; ativar; rollback; retenção.
8. **Exclusões** — paths nunca estáticos.
9. **Conexão** — cloud_url, site key mascarada, teste HMAC/SFTP/OpenSEO.
10. **Ajuda** — limites MVP, bypass, snippets.

UI: pt-BR, `wp_nonce` em toda ação, `esc_*` em toda saída, sem framework JS.

---

## 7. APIs

### Plugin — `wp-json/firefly-distill/v1`

| Método | Rota | Auth | Função |
|--------|------|------|--------|
| GET | `/status` | admin ou HMAC | estado geral + por host |
| GET | `/urls?host=` | admin ou HMAC | URLs públicas `(host, path, post_id, post_modified_gmt)` ≤50/host |
| GET | `/hosts` | admin ou HMAC | mapeamentos + flags + rotas |
| POST/PUT/DELETE | `/hosts`, `/hosts/{id}` | admin | CRUD (regrava `hosts.map.conf`, sync nuvem) |
| POST | `/hosts/import` | admin | `{csv, dry_run}` → `{rows:[{line, ok, errors[]}]}` |
| GET | `/hosts/search-posts?q=` | admin | `[{id, title, type, permalink}]` (máx 20) |
| POST | `/hosts/{id}/rebuild` · `/hosts/{id}/rollback` | admin | job/rollback do host |
| POST | `/queue` | admin ou HMAC | enfileira `(host, path)` |
| POST | `/receive-build` | HMAC + token de job | arquivos de `builds/{host}/{id}/` |
| POST | `/builds/{host}/{id}/activate` | admin ou HMAC | symlink `current/{host}` |
| POST | `/serve` | admin | on/off global |
| GET | `/audits?host=` · `/audits/detail?url=` | admin | espelho |
| GET/POST | `/suggestions` · `/suggestions/{id}` | admin | backlog / status |
| POST | `/webhook` | HMAC | status de job, audits, suggestions |

### Nuvem — `/api/v1`

| Método | Rota | Auth | Função |
|--------|------|------|--------|
| POST | `/sites/{site}/enqueue` | HMAC site | `{items:[{host, path, post_id, reason}]}` → debounce → job |
| PUT | `/sites/{site}/hosts` | HMAC site | sync completo do mapeamento |
| POST | `/sites/{site}/jobs` | interno | `{scope, host?, paths?}` |
| GET | `/sites/{site}/jobs/{id}` | interno/HMAC | status |
| GET | `/worker/jobs/next` | token worker | payload do job |
| POST | `/worker/jobs/{id}/events` | token worker | progresso/fase |
| POST | `/worker/jobs/{id}/audits` · `/suggestions` | token worker | resultados |
| GET | `/sites/{site}/hosts/{host}/audits` | interno | dashboard |
| POST | `/sites/{site}/hosts/{host}/openseo/sync` | interno | sync OpenSEO do host |

**HMAC:** headers `X-FFD-Key`, `X-FFD-Timestamp`, `X-FFD-Signature = hex(hmac_sha256(secret, ts + "\n" + method + "\n" + path + "\n" + sha256(body)))`; janela ±300s; nonce anti-replay em cache.

**Bypass token:** `ff_sig = hmac(secret, host + "\n" + path + "\n" + ff_exp)`, `ff_exp` ≤ 2h. O plugin valida em `template_redirect`/`init` e responde `X-FFD: origin`; inválido → 403 só quando o parâmetro estiver presente.

---

## 8. Regras de rewrite / bypass

Servir estático **somente** se **todas** forem verdadeiras:

1. Método `GET` ou `HEAD`.
2. Sem `firefly-distill=origin` na query.
3. Sem cookie `wordpress_logged_in_*` nem `firefly_distill_bypass=1`.
4. Path não começa com `/wp-admin`, `/wp-json`, `/wp-login.php`, `/xmlrpc.php`, `/wp-cron.php`, nem está nas exclusões.
5. Host está na allowlist (`hosts.map.conf`, `static_enabled=1`).
6. Existe `current/{host}{uri}` ou `current/{host}{uri}index.html` ou `current/{host}{uri}/index.html`.

Senão → fluxo atual (`proxy_pass` para o WP, ou `index.php`).

```nginx
map $host $ff_host_dir { default ""; include /var/www/SITE/wp-content/uploads/firefly-distill/hosts.map.conf; }
map $args $ff_q_bypass { default 0; "~(^|&)firefly-distill=origin(&|$)" 1; }
map $http_cookie $ff_c_skip { default 0; "~*wordpress_logged_in_" 1; "~*firefly_distill_bypass=1" 1; }
map "$request_method:$ff_q_bypass:$ff_c_skip:$ff_host_dir" $ff_static { default 0; "~^(GET|HEAD):0:0:.+$" 1; }

location / {
    error_page 418 = @wp;
    if ($ff_static = 0) { return 418; }
    root /var/www/SITE/wp-content/uploads/firefly-distill;
    try_files /current/$ff_host_dir$uri /current/$ff_host_dir${uri}index.html /current/$ff_host_dir$uri/index.html @wp;
}
location @wp { proxy_pass http://wp_origin; proxy_set_header Host $host; proxy_set_header X-Forwarded-Proto $scheme; }
```

- Sitemaps globais no vhost da LP (`/sitemap_index.xml`, `/wp-sitemap*.xml`, `/*-sitemap*.xml`) → 301 para `/sitemap.xml`.
- `hosts.map.conf` gerado pelo plugin: uma linha `host host;` por host com `static_enabled=1`. Após regravar, a equipe recarrega o Nginx (`nginx -t && nginx -s reload`) — o plugin **não** executa comandos no servidor; só mostra aviso “reload pendente”.
- Header `add_header X-FFD static;` no hit (para o teste do admin).
- Apache: snippet equivalente em `infra/apache/lp-vhost.conf.example` (ver PROJETO §7.5).

---

## 9. Fluxo incremental

1. `save_post` / `transition_post_status` / `before_delete_post` → `HostResolver::forPost($post_id)` → lista `(host, path)` (raiz e rotas extras; se não mapeado → host primário + permalink).
2. `Enqueuer` grava `ffd_queue` e agenda envio (transient debounce 60s por host; WP-Cron single event).
3. Envio → `POST /sites/{site}/enqueue`. Nuvem agrupa por host → job `scope=host`.
4. Worker: crawl com bypass das rotas → `origin_hash`; se igual ao manifest atual e build ok → no-op (só `hostmeta` se flags mudaram).
5. Gera páginas mudadas; copia demais arquivos do build anterior do host; **sempre** regenera `robots.txt`, `sitemap.xml` (lastmod), `llm.txt`.
6. Entrega em `builds/{host}/{novo_id}/` → `manifest.json` completo → plugin valida → symlink `current/{host}`.
7. Audit Lighthouse/PSI das rotas do host → `page_audits` → suggest → webhook.
8. Mudança só de flags na tela de mapeamento → `scope=host_meta` (sem LLM).
9. `wp_update_nav_menu` / customizer → enfileira todos os hosts (debounce).
10. Nunca ler `current/` como input.

---

## 10. Multi-host LPs (Incavel)

- 1 WP, N hosts. Host primário = `home_url()`. Cada LP = registro em `ffd_hosts` (post raiz + rotas extras).
- Disco: `builds/{host}/{id}/` + `current/{host}` (symlink por host). `current/` é diretório real.
- Cada pasta de host contém `index.html`, subpastas, `assets/`, `robots.txt`, `sitemap.xml`, `llm.txt`, `manifest.json`.
- `robots.txt`: index=1 → `Allow: /` + `Sitemap: https://{host}/sitemap.xml`; index=0 → `Disallow: /`.
- `sitemap.xml`: só rotas do host com `in_sitemap=1`, `<loc>` com URL pública, `lastmod` do post.
- `llm.txt`: Markdown (nome/oferta, público, região, CTA/contato público, URLs do host). Alias `/llms.txt` opcional.
- HTML estático: canonical, `og:url` e JSON-LD com URL pública do host; links internos reescritos de permalink interno → URL pública quando o destino é rota do mesmo host.
- Site mãe: `SeoGuards` exclui post_ids mapeados do sitemap global (`wpseo_exclude_from_sitemap_by_post_ids` no Yoast; equivalente Rank Math/core `wp_sitemaps_posts_query_args`) e emite canonical para o host público.
- Forms: `action` preservado apontando para endpoint WP (POST nunca estático). Teste de aceite obrigatório.
- Crawl no host público + bypass; fallback com `hostResolverRules`/`--host-resolver-rules` do Chromium apontando para o IP da origem quando o DNS ainda não migrou.
- O proxy/rewrite atual da Incavel que leva host→página do WP **continua**; o distill só entra antes dele.

---

## 11. Dashboard OpenSEO

- Agregação por `host_id`; seletor de host no wp-admin e no dashboard Laravel interno.
- `openseo_project_id` do host (se preenchido) senão do site; filtro por hostname nos dados herdados.
- Sync: pós-job e diário (scheduler Laravel). Puxa GSC (cliques, impressões, CTR, posição por página), keywords/queries, indexação/cobertura, oportunidades.
- Join `(host, path)` normalizado (trailing slash, home, minúsculas no host).
- Oportunidades OpenSEO → `suggestions` com `source=openseo`, `dedupe_hash = sha256(host+path+category+title_normalizado)`.
- `packages/openseo-client` é a única porta para o OpenSEO (interface + fake para testes). Não acoplar controllers à API externa.

---

## 12. Critérios de aceite (MVP)

- [ ] Plugin ativa em PHP 8.1 sem warnings; `dbDelta` cria todas as `ffd_*`; `uninstall.php` remove options/tabelas (não apaga builds sem confirmação).
- [ ] Tela de **mapeamento**: cadastrar host, buscar post por título, ver permalink interno × URL pública, salvar flags; import CSV com dry-run e erros por linha.
- [ ] `hosts.map.conf` gerado contém só hosts com `static_enabled=1`.
- [ ] Fixture (site mãe + 2 LPs): GET anônimo em cada host serve `current/{host}/…` com `X-FFD: static`; PHP não executa (log do WP vazio).
- [ ] `https://{lp}/robots.txt`, `/sitemap.xml`, `/llm.txt` vêm da pasta da LP e **não** citam URLs do site mãe nem de outra LP; `/sitemap_index.xml` na LP → 301 `/sitemap.xml`.
- [ ] Logado, `?firefly-distill=origin` válido, POST e `/wp-admin` → WP em todos os hosts.
- [ ] POST do form da LP chega ao WP e o plugin de form registra a entrada.
- [ ] Host fora da allowlist (Host forjado) → nunca serve estático de outra pasta.
- [ ] Editar o post raiz da LP A → job só do host A; builds/`current` de B e do mãe inalterados; robots/sitemap/llm.txt de A republicados (lastmod novo).
- [ ] Alterar só flag `flag_robots_index` → job `host_meta` sem chamada LLM; `robots.txt` muda.
- [ ] Rollback de um host em 1 clique; outros hosts não mudam.
- [ ] `receive-build` rejeita `..`, `.php`, `.phtml`, `.htaccess`, host não mapeado.
- [ ] Dashboard: todas as URLs de todos os hosts com scores; filtro por host; dados OpenSEO por host quando há projeto próprio.
- [ ] ≥1 sugestão por URL auditada; aceitar/rejeitar/adiar persiste e sincroniza.
- [ ] Lighthouse mobile do build: Perf ≥90, SEO ≥95, A11y ≥90, BP ≥95 ou `completed_with_warnings`.
- [ ] Nenhum segredo no repo; `.env.example` só com placeholders; nenhuma menção a planos/preços.

---

## 13. Prompt inicial para colar no Cursor

```
Você vai implementar o monorepo firefly-distill. Leia PROJETO.md (v2.2, PRD) e CURSOR.md (brief) antes de codar.
Regras duras:
- Ferramenta INTERNA Firefly. Nada de SaaS, planos, preços, créditos, trial. Sem headless React.
- Visitante recebe HTML/CSS puro gravado no disco do cliente e servido ANTES do WP/proxy.
- LLM, Playwright, Impeccable e Lighthouse rodam só na nuvem (Laravel API + worker Node/TS). Plugin WP (PHP 8.1+) é enxuto.
- Hooks WP só enfileiram. Crawl sempre com bypass assinado (?firefly-distill=origin&ff_sig&ff_exp) no host público correto.
- Multi-host (Incavel): disco por HOST em uploads/firefly-distill/builds/{host}/{id} + symlink current/{host}.
  Nginx faz try_files por $host (allowlist via map gerado pelo plugin) antes do proxy_pass.
  Cada host tem robots.txt, sitemap.xml e llm.txt próprios; nunca misturar com o site mãe/Yoast.
- wp-admin: tela de mapeamento post/página → host (busca por título, permalink interno vs URL pública,
  https, flags robots/sitemap/llm.txt, estático on/off, import CSV host,post_id).
- Forms/POST, logados, wp-admin, wp-json sempre no WP.
- Incremental por host; dashboard Lighthouse/PSI + OpenSEO por hostname; sugestões IA só como backlog.
Tarefa agora (Sprint 0): crie a estrutura de pastas da seção 4 do CURSOR.md e os arquivos da seção 14, na ordem.
- Plugin: bootstrap, Schema (ffd_hosts, ffd_host_routes, ffd_queue, ffd_builds, ffd_pages, ffd_page_audits, ffd_suggestions),
  Hmac, HostValidator, HostRepository, REST /status e /hosts (GET), menu com tela de mapeamento listando hosts.
- Cloud: Laravel com migrations sites/hosts/jobs, middleware HMAC, rota GET /api/v1/health; worker Node com loop stub.
- JSON Schemas em cloud/packages/shared. docker-compose (mysql, redis, api, worker). .env.example sem segredos.
- Testes: PHPUnit p/ HostValidator e Hmac; Pest p/ HMAC middleware; vitest p/ manifest schema.
UI e docs em pt-BR. Ao final, liste o que foi criado e como rodar localmente. Não implemente além do Sprint 0 sem pedir.
```

---

## 14. Arquivos a criar primeiro (Sprint 0, nesta ordem)

1. `README.md` — visão curta, links PROJETO.md/CURSOR.md, como rodar.
2. `.editorconfig`, `.gitignore` (vendor, node_modules, .env, builds de fixture).
3. `cloud/packages/shared/schemas/manifest.schema.json` — `{host, build_id, created_at, pages:[{path, post_id, source_url, output_relpath, origin_hash, output_hash, post_modified_gmt}], hostmeta:{robots, sitemap, llm_txt}}`.
4. `cloud/packages/shared/schemas/job.schema.json`, `webhook.schema.json`, `hosts-import.schema.json`.
5. `plugin/firefly-distill/firefly-distill.php` + `composer.json` (PSR-4 `FireflyDistill\\`).
6. `plugin/firefly-distill/src/Plugin.php`.
7. `plugin/firefly-distill/src/Install/Schema.php` (todas as `ffd_*`, versão de schema em option).
8. `plugin/firefly-distill/src/Security/Hmac.php` + `Security/PathGuard.php`.
9. `plugin/firefly-distill/src/Hosts/HostValidator.php` + `HostRepository.php` + `Model/Host.php`.
10. `plugin/firefly-distill/src/Rest/Routes.php` + `StatusController.php` + `HostsController.php` (GET).
11. `plugin/firefly-distill/src/Admin/Menu.php` + `HostsMappingPage.php` + `HostsListTable.php` (listagem; form na Sprint 1).
12. `plugin/firefly-distill/uninstall.php`.
13. `plugin/firefly-distill/tests/` (bootstrap wp-env, `HostValidatorTest`, `HmacTest`).
14. `cloud/apps/api/` — Laravel novo; migrations `sites`, `hosts`, `jobs`; `VerifySiteHmac`; `GET /api/v1/health`.
15. `cloud/apps/worker/` — `package.json`, `tsconfig.json`, `src/index.ts` (pull loop stub), `src/manifest.ts` + teste vitest contra o schema.
16. `cloud/docker-compose.yml`, `Dockerfile.api`, `Dockerfile.worker`, `cloud/.env.example` (`APP_KEY=`, `DB_*`, `REDIS_*`, `LLM_PROVIDER=`, `LLM_API_KEY=`, `S3_*`, `WORKER_TOKEN=`, `OPENSEO_*=` — todos vazios).
17. `infra/nginx/ffd-maps.conf`, `infra/nginx/lp-vhost.conf.example`, `infra/nginx/ffd-proxy-headers.conf`, `infra/apache/lp-vhost.conf.example`.
18. `fixtures/hosts.csv` (site mãe + 2 LPs fictícias `*.example.test`) + `fixtures/wp/.wp-env.json`.
19. `docs/multi-host-incavel.md` (resumo de PROJETO §7) e `docs/bypass.md`.

Sprint 1 em diante: seguir o roadmap de PROJETO.md §16 (Sprints 0–9).
