import { inspectSeo, type SeoIssue } from "../seo/onpage.ts";

export function suggestForPage(host: string, url: string, originHtml: string, distilledHtml: string): Array<SeoIssue & { host: string; url: string; source: string; status: string }> {
  const issues = inspectSeo(distilledHtml, url);
  return issues.map((i) => ({
    ...i,
    host,
    url,
    source: "agent",
    status: "open",
    body: `${i.body}\n\nOrigem ${originHtml.length} bytes → destilado ${distilledHtml.length} bytes.`,
  }));
}
