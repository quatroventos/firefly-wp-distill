export function llmTxt(host: string, https: boolean, urls: string[], originSample: string): string {
  const scheme = https ? "https" : "http";
  const title = originSample.match(/<title[^>]*>([\s\S]*?)<\/title>/i)?.[1]?.replace(/<[^>]+>/g, "").trim() || host;
  const lines = [
    `# ${title}`,
    "",
    `Host: ${scheme}://${host}/`,
    "",
    "## URLs",
    ...urls.map((u) => `- ${u}`),
    "",
    "## Notas",
    "Landing page gerada pelo firefly-distill (interno Firefly). Contato e CTA conforme a página pública.",
  ];
  return lines.join("\n") + "\n";
}
