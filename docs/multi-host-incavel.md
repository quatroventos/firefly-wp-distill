# Multi-host (Incavel)

Um WordPress, N hosts. O host primário nasce da ativação (`home_url()`). Cada LP é um registro em `ffd_hosts` (post raiz + rotas extras).

## Disco

```
uploads/firefly-distill/builds/{host}/{build_id}/
uploads/firefly-distill/current/{host} -> ../builds/{host}/{id}
uploads/firefly-distill/hosts.map.conf
```

Cada pasta de host tem `index.html`, `assets/`, `robots.txt`, `sitemap.xml`, `llm.txt`, `manifest.json`.

## Nginx

Os vhosts atuais da Incavel permanecem. O distill só adiciona:

1. `map $host $ff_host_dir` com include do `hosts.map.conf` (allowlist; nunca `Host` cru)
2. `try_files` em `current/$ff_host_dir…` **antes** do `proxy_pass` existente

O plugin avisa “reload pendente”. Não executa `nginx -s reload`.

Sitemaps globais no vhost da LP (`/sitemap_index.xml`, `/*-sitemap*.xml`) → 301 `/sitemap.xml`.

## SEO no site mãe

`SeoGuards` tira os `post_id` mapeados do sitemap Yoast/Rank Math/core e emite canonical para a URL pública da LP.

## Forms

POST nunca é estático. O `action` do form continua no WP. Teste de aceite: envio do form da LP deve aparecer no plugin de formulário.

## Incremental

Editar o post da LP A enfileira só o host A. Flags (robots/sitemap/llm) disparam `scope=host_meta` sem LLM.
