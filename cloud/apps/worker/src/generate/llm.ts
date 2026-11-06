import { generateStaticHtml } from "./html.ts";
import type { DesignTokens } from "../distill/impeccable.ts";

export async function generateWithLlm(opts: {
  host: string;
  path: string;
  https: boolean;
  originHtml: string;
  tokens: DesignTokens;
}): Promise<string> {
  const provider = (process.env.LLM_PROVIDER || "none").toLowerCase();
  const fallback = generateStaticHtml(opts);
  if (provider === "none" || !process.env.LLM_API_KEY) {
    return fallback;
  }
  const prompt = `Gere HTML5 semântico + CSS já linkado em /assets/site.css para a landing ${opts.host}${opts.path}.
Regras: 1 H1, title ≤60, meta description 150-160, canonical ${opts.https ? "https" : "http"}://${opts.host}${opts.path},
sem jQuery, sem page builder, forms POST preservados, lang=pt-BR.
HTML de origem (recorte):\n${opts.originHtml.slice(0, 12000)}`;
  try {
    if (provider === "openai") {
      const res = await fetch("https://api.openai.com/v1/chat/completions", {
        method: "POST",
        headers: {
          Authorization: `Bearer ${process.env.LLM_API_KEY}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          model: process.env.LLM_MODEL || "gpt-4o-mini",
          messages: [
            { role: "system", content: "Você devolve apenas HTML completo, sem markdown." },
            { role: "user", content: prompt },
          ],
        }),
      });
      const data = (await res.json()) as any;
      const html = data?.choices?.[0]?.message?.content;
      if (typeof html === "string" && html.includes("<html")) return html;
    }
  } catch {
    return fallback;
  }
  return fallback;
}
