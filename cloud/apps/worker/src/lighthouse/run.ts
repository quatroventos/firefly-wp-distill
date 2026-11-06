import { scoresFromHtml } from "../seo/onpage.ts";

export async function runLighthouse(url: string, html: string) {
  const scores = scoresFromHtml(html);
  if (process.env.FFD_SKIP_LIGHTHOUSE === "1") {
    return { url, ...scores, source: "heuristic" as const };
  }
  try {
    const mod = await import("lighthouse").catch(() => null);
    const chromeLauncher = await import("chrome-launcher").catch(() => null);
    if (!mod || !chromeLauncher) {
      return { url, ...scores, source: "heuristic" as const };
    }
    return { url, ...scores, source: "heuristic" as const };
  } catch {
    return { url, ...scores, source: "heuristic" as const };
  }
}
