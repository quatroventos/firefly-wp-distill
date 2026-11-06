import { chromium, type Browser } from "playwright";
import { sha256 } from "../manifest.ts";

export type CrawlResult = {
  url: string;
  html: string;
  title: string;
  screenshot?: Buffer;
  hash: string;
};

function originUrl(signed: { url: string; ff_sig: string; ff_exp: string }): string {
  const u = new URL(signed.url);
  u.searchParams.set("firefly-distill", "origin");
  u.searchParams.set("ff_sig", signed.ff_sig);
  u.searchParams.set("ff_exp", signed.ff_exp);
  return u.toString();
}

export async function crawlOrigin(
  signed: { url: string; ff_sig: string; ff_exp: string; path: string },
  originIp?: string | null
): Promise<CrawlResult> {
  const target = originUrl(signed);
  const host = new URL(signed.url).host;
  const args = ["--disable-dev-shm-usage"];
  if (originIp) {
    args.push(`--host-resolver-rules=MAP ${host} ${originIp}`);
  }
  const browser: Browser = await chromium.launch({ headless: true, args });
  try {
    const page = await browser.newPage();
    await page.goto(target, { waitUntil: "networkidle", timeout: 60000 });
    const html = await page.content();
    const title = await page.title();
    return { url: signed.url, html, title, hash: sha256(html) };
  } finally {
    await browser.close();
  }
}
