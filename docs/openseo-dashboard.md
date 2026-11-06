# Dashboard OpenSEO

`packages/openseo-client` é a única porta. Sem `OPENSEO_URL`/`OPENSEO_TOKEN` o Laravel usa `FakeOpenSeoClient`.

`openseo_project_id` no host, senão no site. Cruzamento por `(host, path)`. Sync pós-job e diário (`schedule:work`). Oportunidades viram `suggestions` com `source=openseo` e `dedupe_hash`.
