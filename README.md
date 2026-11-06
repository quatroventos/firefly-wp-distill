# firefly-distill

Ferramenta **interna** da operação SEO/GEO Firefly / Quatro Ventos.

Gera HTML/CSS puro de páginas WordPress na nuvem Firefly, grava no disco do cliente e o servidor web serve o arquivo **antes** do PHP/WP. Suporta landing pages multi-host (um WP, vários domínios) e sites com um único host.

Não é produto à venda. Sem planos, créditos, trial ou marketplace.

- PRD: [PROJETO.md](PROJETO.md)
- Brief de implementação: [CURSOR.md](CURSOR.md)

## Layout

```
plugin/firefly-distill/   plugin WordPress enxuto (PHP 8.1+)
cloud/apps/api/           Laravel 11 (orquestrador, HMAC, Horizon)
cloud/apps/worker/        Node 20 + Playwright + destilação
cloud/packages/           schemas JSON + cliente OpenSEO
infra/                    snippets Nginx/Apache
fixtures/                 CSV de hosts + wp-env
```

## Subir a nuvem (local)

```bash
cp cloud/.env.example cloud/.env
# preencha APP_KEY, senhas e WORKER_TOKEN (placeholders vazios no example)
cd cloud
docker compose up --build
```

Health: `http://localhost:8000/api/v1/health`

Não publique 80/443 neste compose. No EasyPanel use `cloud/docker-compose.easypanel.yml` e aponte o domínio só para o serviço `api:8000`.

## Plugin

```bash
cd plugin/firefly-distill
composer install
composer dump-autoload -o
```

Instale a pasta `plugin/firefly-distill` como plugin WP. Na ativação o plugin cria as tabelas `ffd_*` e o host primário a partir de `home_url()`.

No `wp-config.php`:

```php
define('FFD_HMAC_SECRET', 'cole-o-secret-gerado-na-nuvem');
```

Na nuvem (depois do compose):

```bash
docker compose exec api php artisan ffd:site https://incavel.com.br --label=Incavel --plugin=https://incavel.com.br/wp-json/firefly-distill/v1
```

Cole `site_key_id` e a Cloud URL na tela Conexão do plugin. O HMAC secret não é exibido no admin.

Testes unitários (sem WordPress):

```bash
cd plugin/firefly-distill
./vendor/bin/phpunit
```

## Teste Incavel

Os vhosts atuais permanecem. Cole o snippet de `infra/nginx/lp-vhost.conf.example` **antes** do `proxy_pass` existente. O plugin gera `hosts.map.conf` só com hosts `static_enabled=1` e avisa reload pendente — não executa comando no servidor.

Ver [docs/multi-host-incavel.md](docs/multi-host-incavel.md) e [docs/bypass.md](docs/bypass.md).
