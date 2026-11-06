# Bypass de origem

O worker nunca lê `current/`. Sempre pede a página no **host público** com query assinada:

```
https://{host}{path}?firefly-distill=origin&ff_sig=…&ff_exp=…
```

- `ff_sig = hmac_sha256(secret, host + "\n" + path + "\n" + ff_exp)`
- `ff_exp` ≤ 2h
- Path normalizado com barra inicial e final (`/`, `/obrigado/`)
- Token de um host não vale em outro
- Parâmetro presente e inválido → 403
- Rewrite vê `firefly-distill=origin` e não serve estático
- Plugin responde `X-FFD: origin`

Cookie alternativo: `firefly_distill_bypass=1`.

Fallback se o DNS da LP ainda não aponta: Chromium `--host-resolver-rules=MAP {host} {ip}`.
