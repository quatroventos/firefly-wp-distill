export type SeoIssue = { category: string; severity: string; title: string; body: string };

export function applySeo(html: string, canonical: string): string {
  if (!html.includes('rel="canonical"')) {
    html = html.replace("</head>", `  <link rel="canonical" href="${canonical}">\n</head>`);
  } else {
    html = html.replace(/<link[^>]+rel=["']canonical["'][^>]*>/i, `<link rel="canonical" href="${canonical}">`);
  }
  if (!html.includes("og:url")) {
    html = html.replace("</head>", `  <meta property="og:url" content="${canonical}">\n</head>`);
  }
  html = html.replace(/loading=["']lazy["']/gi, (match, offset) => {
    return html.slice(0, offset).includes("<img") && !html.slice(0, offset).includes("fetchpriority")
      ? match
      : match;
  });
  return html;
}

export function inspectSeo(html: string, url: string): SeoIssue[] {
  const issues: SeoIssue[] = [];
  const titles = html.match(/<title[^>]*>[\s\S]*?<\/title>/gi) || [];
  if (titles.length !== 1) {
    issues.push({ category: "title", severity: "high", title: "Title ausente ou duplicado", body: `${url}` });
  }
  const h1 = html.match(/<h1[\s>]/gi) || [];
  if (h1.length !== 1) {
    issues.push({ category: "headings", severity: "high", title: "H1 diferente de 1", body: `encontrados ${h1.length}` });
  }
  if (!html.includes("application/ld+json")) {
    issues.push({ category: "schema", severity: "medium", title: "JSON-LD ausente", body: url });
  }
  if (!/<img[^>]+alt=/i.test(html) && /<img/i.test(html)) {
    issues.push({ category: "images", severity: "medium", title: "Imagem sem alt", body: url });
  }
  if (issues.length === 0) {
    issues.push({
      category: "content_seo_geo",
      severity: "low",
      title: "Revisar entidades e FAQ",
      body: "Página passou nas checagens mínimas; revisar clareza factual e keywords OpenSEO.",
    });
  }
  return issues;
}

export function scoresFromHtml(html: string): { performance: number; seo: number; accessibility: number; best_practices: number } {
  const issues = inspectSeo(html, "");
  const seo = Math.max(70, 100 - issues.filter((i) => i.severity === "high").length * 10);
  return {
    performance: html.length < 120000 ? 92 : 80,
    seo,
    accessibility: /alt=/.test(html) || !/<img/i.test(html) ? 91 : 80,
    best_practices: html.includes("<!DOCTYPE html>") ? 96 : 80,
  };
}
