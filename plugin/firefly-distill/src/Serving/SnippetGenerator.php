<?php

namespace FireflyDistill\Serving;

final class SnippetGenerator
{
    public function nginxMapInclude(): string
    {
        $map = StoragePaths::mapFile();
        $root = StoragePaths::root();
        return <<<NGINX
# firefly-distill — contexto http
map \$host \$ff_host_dir {
    default "";
    include {$map};
}
map \$args \$ff_q_bypass {
    default 0;
    "~(^|&)firefly-distill=origin(&|\$)" 1;
}
map \$http_cookie \$ff_c_skip {
    default 0;
    "~*wordpress_logged_in_" 1;
    "~*firefly_distill_bypass=1" 1;
}
map "\$request_method:\$ff_q_bypass:\$ff_c_skip:\$ff_host_dir" \$ff_static {
    default 0;
    "~^(GET|HEAD):0:0:.+\$" 1;
}
NGINX;
    }

    public function nginxLocation(bool $reverseProxy = true): string
    {
        $root = StoragePaths::root();
        $proxy = $reverseProxy
            ? <<<NGINX
    location @wp {
        proxy_pass http://wp_origin;
        include /etc/nginx/snippets/ffd-proxy-headers.conf;
    }
NGINX
            : <<<NGINX
    location @wp {
        try_files \$uri \$uri/ /index.php?\$args;
    }
NGINX;
        $admin = $reverseProxy
            ? <<<NGINX
    location ~ ^/(wp-admin|wp-json|wp-login\\.php|xmlrpc\\.php|wp-cron\\.php) {
        proxy_pass http://wp_origin;
        include /etc/nginx/snippets/ffd-proxy-headers.conf;
    }
NGINX
            : <<<NGINX
    location ^~ /wp-admin/ { try_files \$uri \$uri/ /index.php?\$args; }
    location = /wp-login.php { try_files \$uri /index.php?\$args; }
    location ^~ /wp-json/ { try_files \$uri /index.php?\$args; }
NGINX;

        return <<<NGINX
# firefly-distill — colar ANTES do proxy_pass / index.php existente
set \$ff_root {$root};

{$admin}

    location ~ ^/(sitemap_index\\.xml|wp-sitemap.*\\.xml|[a-z0-9_-]+-sitemap[0-9]*\\.xml)\$ {
        return 301 https://\$host/sitemap.xml;
    }

    location / {
        error_page 418 = @wp;
        if (\$ff_static = 0) { return 418; }
        root \$ff_root;
        add_header X-FFD static always;
        try_files /current/\$ff_host_dir\$uri
                  /current/\$ff_host_dir\${uri}index.html
                  /current/\$ff_host_dir\$uri/index.html
                  @wp;
    }

{$proxy}
NGINX;
    }

    public function apache(): string
    {
        $root = StoragePaths::root();
        return <<<APACHE
# firefly-distill — colar ANTES do ProxyPass / regras WP
RewriteEngine On
RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$
RewriteCond %{QUERY_STRING} !(^|&)firefly-distill=origin(&|$)
RewriteCond %{HTTP_COOKIE} !(wordpress_logged_in_|firefly_distill_bypass=1)
RewriteCond %{REQUEST_URI} !^/(wp-admin|wp-json|wp-login\\.php|xmlrpc\\.php)
RewriteCond %{HTTP_HOST} ^([a-z0-9.-]+)$ [NC]
RewriteCond {$root}/current/%1%{REQUEST_URI}index.html -f
RewriteRule ^ {$root}/current/%1%{REQUEST_URI}index.html [L]
Header set X-FFD "static" env=REDIRECT_STATUS
APACHE;
    }
}
