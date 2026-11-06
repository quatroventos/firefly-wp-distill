import type { DesignTokens } from "../distill/impeccable.ts";

function stripJunk(html: string): string {
  return html
    .replace(/<script[\s\S]*?<\/script>/gi, "")
    .replace(/<style[\s\S]*?<\/style>/gi, "")
    .replace(/<!--[\s\S]*?-->/g, "");
}

function pick(html: string, re: RegExp, fallback: string): string {
  const m = html.match(re);
  return m?.[1]?.trim() || fallback;
}

function inner(html: string, tag: string): string {
  const re = new RegExp(`<${tag}[^>]*>([\\s\\S]*?)<\\/${tag}>`, "i");
  const m = html.match(re);
  return m?.[1]?.trim() || "";
}

export function generateStaticHtml(opts: {
  host: string;
  path: string;
  https: boolean;
  originHtml: string;
  tokens: DesignTokens;
}): string {
  const scheme = opts.https ? "https" : "http";
  const path = opts.path === "/" ? "/" : opts.path.replace(/\/?$/, "/");
  const canonical = `${scheme}://${opts.host}${path === "/" ? "/" : path}`;
  const cleaned = stripJunk(opts.originHtml);
  const title = pick(cleaned, /<title[^>]*>([\s\S]*?)<\/title>/i, opts.host).slice(0, 60);
  const desc = pick(cleaned, /<meta[^>]+name=["']description["'][^>]+content=["']([^"']+)/i, title).slice(0, 160);
  const h1 = inner(cleaned, "h1") || title;
  let main = inner(cleaned, "main") || inner(cleaned, "article") || inner(cleaned, "body");
  main = main.replace(/<header[\s\S]*?<\/header>/i, "").replace(/<footer[\s\S]*?<\/footer>/i, "");
  if (main.length > 20000) main = main.slice(0, 20000);
  const form = cleaned.match(/<form[\s\S]*?<\/form>/i)?.[0] || "";
  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "WebPage",
    name: title,
    url: canonical,
  };
  return `<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${escapeHtml(title)}</title>
  <meta name="description" content="${escapeHtml(desc)}">
  <link rel="canonical" href="${canonical}">
  <meta property="og:url" content="${canonical}">
  <meta property="og:title" content="${escapeHtml(title)}">
  <meta property="og:description" content="${escapeHtml(desc)}">
  <link rel="stylesheet" href="/assets/site.css">
  <script type="application/ld+json">${JSON.stringify(jsonLd)}</script>
</head>
<body>
  <header><a href="/">${escapeHtml(opts.host)}</a></header>
  <main>
    <h1>${escapeHtml(stripTags(h1))}</h1>
    ${main}
    ${form}
  </main>
  <footer><p>${escapeHtml(opts.host)}</p></footer>
</body>
</html>
`;
}

export function escapeHtml(s: string): string {
  return s.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c] || c));
}

function stripTags(s: string): string {
  return s.replace(/<[^>]+>/g, "").trim();
}
