<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>firefly-distill</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 24px; color: #1d2327; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 24px; }
        th, td { border: 1px solid #dcdcde; padding: 6px 8px; text-align: left; font-size: 13px; }
        th { background: #f6f7f7; }
        code { font-size: 12px; }
        h1, h2 { margin-bottom: 8px; }
    </style>
</head>
<body>
<h1>firefly-distill</h1>
<form method="get">
    <label>Host
        <select name="host" onchange="this.form.submit()">
            <option value="">todos</option>
            @foreach ($hosts as $h)
                <option value="{{ $h->host }}" @selected($hostFilter === $h->host)>{{ $h->host }}</option>
            @endforeach
        </select>
    </label>
</form>

<h2>Sites</h2>
<table>
    <thead><tr><th>Cliente</th><th>Home</th><th>Key</th><th>Hosts</th></tr></thead>
    <tbody>
    @foreach ($sites as $site)
        <tr>
            <td>{{ $site->client_label }}</td>
            <td>{{ $site->home_url }}</td>
            <td><code>{{ $site->site_key_id }}</code></td>
            <td>{{ $site->hosts->pluck('host')->join(', ') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<h2>Jobs</h2>
<table>
    <thead><tr><th>ID</th><th>Host</th><th>Scope</th><th>Status</th><th>Atualizado</th></tr></thead>
    <tbody>
    @foreach ($jobs as $job)
        <tr>
            <td>{{ $job->id }}</td>
            <td>{{ $job->host?->host }}</td>
            <td>{{ $job->scope }}</td>
            <td>{{ $job->status }}</td>
            <td>{{ $job->updated_at }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<h2>Audits</h2>
<table>
    <thead><tr><th>URL</th><th>Fase</th><th>Perf</th><th>SEO</th><th>A11y</th><th>BP</th></tr></thead>
    <tbody>
    @foreach ($audits as $a)
        <tr>
            <td>{{ $a->url }}</td>
            <td>{{ $a->phase }}</td>
            <td>{{ $a->performance }}</td>
            <td>{{ $a->seo }}</td>
            <td>{{ $a->accessibility }}</td>
            <td>{{ $a->best_practices }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<h2>Sugestões</h2>
<table>
    <thead><tr><th>URL</th><th>Título</th><th>Sev</th><th>Status</th><th>Source</th></tr></thead>
    <tbody>
    @foreach ($suggestions as $s)
        <tr>
            <td>{{ $s->url }}</td>
            <td>{{ $s->title }}</td>
            <td>{{ $s->severity }}</td>
            <td>{{ $s->status }}</td>
            <td>{{ $s->source }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
