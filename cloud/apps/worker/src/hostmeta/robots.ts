export function robotsTxt(host: string, https: boolean, index: boolean): string {
  const scheme = https ? "https" : "http";
  if (!index) {
    return "User-agent: *\nDisallow: /\n";
  }
  return `User-agent: *\nAllow: /\nSitemap: ${scheme}://${host}/sitemap.xml\n`;
}
