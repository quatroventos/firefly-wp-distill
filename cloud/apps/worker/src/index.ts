import { nextJob, event, postAudits, postSuggestions, postDelivered } from "./api/client.ts";
import { crawlOrigin } from "./crawl/origin.ts";
import { distillTokens, tokensToCss } from "./distill/impeccable.ts";
import { generateWithLlm } from "./generate/llm.ts";
import { applySeo, scoresFromHtml } from "./seo/onpage.ts";
import { robotsTxt } from "./hostmeta/robots.ts";
import { sitemapXml } from "./hostmeta/sitemap.ts";
import { llmTxt } from "./hostmeta/llmtxt.ts";
import { runLighthouse } from "./lighthouse/run.ts";
import { deliverRest, type PackedFile } from "./deliver/rest.ts";
import { suggestForPage } from "./suggest/agent.ts";
import { buildManifest, sha256, type ManifestPage } from "./manifest.ts";

const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));

async function runJob(job: any): Promise<void> {
  const host = job.host.host as string;
  const https = Boolean(job.host.https);
  const scheme = https ? "https" : "http";
  const flags = job.host.flags;
  const buildId = job.build_id as string;
  const signedUrls: any[] = job.signed_urls || [];
  const files: PackedFile[] = [];
  const pages: ManifestPage[] = [];
  const audits: any[] = [];
  const suggestions: any[] = [];
  let sampleHtml = "";

  await event(job.id, job.scope === "host_meta" ? "hostmeta" : "crawling");

  const tokensHtml: string[] = [];
  if (job.scope !== "host_meta") {
    for (const signed of signedUrls) {
      let origin;
      try {
        origin = await crawlOrigin(signed, job.host.origin_ip);
      } catch (err) {
        await event(job.id, "failed", { error: String(err) });
        throw err;
      }
      tokensHtml.push(origin.html);
      sampleHtml = origin.html;
      const tokens = distillTokens(origin.html);
      await event(job.id, "generating");
      let html = await generateWithLlm({
        host,
        path: signed.path,
        https,
        originHtml: origin.html,
        tokens,
      });
      const canonical = `${scheme}://${host}${signed.path === "/" ? "/" : String(signed.path).replace(/\/?$/, "/")}`;
      html = applySeo(html, canonical);
      const rel = signed.path === "/" ? "index.html" : `${String(signed.path).replace(/^\/|\/$/g, "")}/index.html`;
      files.push({ path: rel, bytes: Buffer.from(html, "utf8") });
      pages.push({
        path: signed.path,
        post_id: 0,
        source_url: signed.url,
        output_relpath: rel,
        origin_hash: origin.hash,
        output_hash: sha256(html),
        post_modified_gmt: null,
      });
      const lh = await runLighthouse(canonical, html);
      const scores = scoresFromHtml(html);
      audits.push({
        host,
        path: signed.path,
        url: canonical,
        phase: "origin",
        ...scores,
      });
      audits.push({
        host,
        path: signed.path,
        url: canonical,
        phase: "build",
        performance: lh.performance,
        seo: lh.seo,
        accessibility: lh.accessibility,
        best_practices: lh.best_practices,
      });
      suggestions.push(...suggestForPage(host, canonical, origin.html, html));
    }
    const css = tokensToCss(distillTokens(tokensHtml[0] || ""));
    files.push({ path: "assets/site.css", bytes: Buffer.from(css, "utf8") });
    files.push({
      path: "design-tokens.json",
      bytes: Buffer.from(JSON.stringify(distillTokens(tokensHtml[0] || ""), null, 2), "utf8"),
    });
  }

  await event(job.id, "hostmeta");
  const publicUrls = (job.paths || ["/"]).map((p: string) => `${scheme}://${host}${p === "/" ? "/" : p}`);
  if (flags.robots_index !== undefined) {
    files.push({ path: "robots.txt", bytes: Buffer.from(robotsTxt(host, https, Boolean(flags.robots_index)), "utf8") });
  }
  if (flags.sitemap) {
    files.push({
      path: "sitemap.xml",
      bytes: Buffer.from(
        sitemapXml(
          (job.paths || ["/"]).map((p: string) => ({
            loc: `${scheme}://${host}${p === "/" ? "/" : p}`,
          }))
        ),
        "utf8"
      ),
    });
  }
  if (flags.llm_txt) {
    files.push({ path: "llm.txt", bytes: Buffer.from(llmTxt(host, https, publicUrls, sampleHtml), "utf8") });
    files.push({ path: "llms.txt", bytes: Buffer.from(llmTxt(host, https, publicUrls, sampleHtml), "utf8") });
  }

  const manifest = buildManifest({
    host,
    build_id: buildId,
    pages,
    hostmeta: {
      robots: Boolean(flags.robots_index),
      sitemap: Boolean(flags.sitemap),
      llm_txt: Boolean(flags.llm_txt),
    },
  });
  files.push({ path: "manifest.json", bytes: Buffer.from(JSON.stringify(manifest, null, 2), "utf8") });

  await event(job.id, "delivering");
  await deliverRest({ jobId: job.id, host, buildId, manifest, files });
  await postDelivered(job.id, buildId, sha256(JSON.stringify(manifest)));
  if (audits.length) await postAudits(job.id, audits);
  if (suggestions.length) await postSuggestions(job.id, suggestions);

  const warn = audits.some(
    (a) => a.phase === "build" && (a.performance < 90 || a.seo < 95 || a.accessibility < 90 || a.best_practices < 95)
  );
  await event(job.id, warn ? "completed_with_warnings" : "completed");
}

async function loop(): Promise<void> {
  console.log("firefly-distill worker up");
  for (;;) {
    try {
      const job = await nextJob();
      if (!job) {
        await sleep(Number(process.env.WORKER_POLL_MS || 5000));
        continue;
      }
      console.log("job", job.id, job.host?.host, job.scope);
      await runJob(job);
    } catch (err) {
      console.error(err);
      await sleep(3000);
    }
  }
}

loop();
