# Rewrite Apache

Ver [infra/apache/lp-vhost.conf.example](../infra/apache/lp-vhost.conf.example).

Colocar **antes** das regras padrão do WordPress / ProxyPass. Preferir RewriteMap com a allowlist gerada pelo plugin quando o painel permitir. Host forjado fora de `^[a-z0-9.-]+$` não casa.
