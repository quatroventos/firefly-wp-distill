# Rewrite Nginx

Ver [infra/nginx/ffd-maps.conf](../infra/nginx/ffd-maps.conf) e [infra/nginx/lp-vhost.conf.example](../infra/nginx/lp-vhost.conf.example).

WP único (sem reverse proxy): o `@wp` vira `try_files $uri $uri/ /index.php?$args;` + `location ~ \.php$` padrão. O plugin copia os dois snippets na tela Hosts / Mapeamento.

`$arg_firefly-distill` não existe (hífen). Usar regex em `$args`.
