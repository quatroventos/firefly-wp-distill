export type DesignTokens = {
  color: Record<string, string>;
  font: Record<string, string>;
  text: Record<string, string>;
  space: Record<string, string>;
  radius: Record<string, string>;
  shadow: Record<string, string>;
};

const DEFAULT_TOKENS: DesignTokens = {
  color: {
    primary: "#1d4ed8",
    text: "#111827",
    muted: "#4b5563",
    bg: "#ffffff",
    surface: "#f9fafb",
    border: "#e5e7eb",
    accent: "#0f766e",
  },
  font: {
    sans: 'Inter, system-ui, "Segoe UI", sans-serif',
  },
  text: {
    xs: "0.75rem",
    sm: "0.875rem",
    md: "1rem",
    lg: "1.25rem",
    xl: "1.75rem",
    display: "2.25rem",
  },
  space: {
    1: "0.25rem",
    2: "0.5rem",
    3: "0.75rem",
    4: "1rem",
    6: "1.5rem",
    8: "2rem",
    12: "3rem",
  },
  radius: { sm: "4px", md: "8px" },
  shadow: { sm: "0 1px 2px rgb(0 0 0 / 0.06)" },
};

export function distillTokens(html: string): DesignTokens {
  const tokens = structuredClone(DEFAULT_TOKENS);
  const hex = html.match(/#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})\b/g) || [];
  if (hex[0]) tokens.color.primary = hex[0];
  if (hex[1]) tokens.color.accent = hex[1];
  const family = html.match(/font-family:\s*([^;}{]+)/i);
  if (family) tokens.font.sans = family[1].trim();
  return tokens;
}

export function tokensToCss(tokens: DesignTokens): string {
  const lines: string[] = [":root {"];
  for (const [k, v] of Object.entries(tokens.color)) lines.push(`  --color-${k}: ${v};`);
  for (const [k, v] of Object.entries(tokens.font)) lines.push(`  --font-${k}: ${v};`);
  for (const [k, v] of Object.entries(tokens.text)) lines.push(`  --text-${k}: ${v};`);
  for (const [k, v] of Object.entries(tokens.space)) lines.push(`  --space-${k}: ${v};`);
  for (const [k, v] of Object.entries(tokens.radius)) lines.push(`  --radius-${k}: ${v};`);
  for (const [k, v] of Object.entries(tokens.shadow)) lines.push(`  --shadow-${k}: ${v};`);
  lines.push("}");
  lines.push(`html,body{margin:0;padding:0;background:var(--color-bg);color:var(--color-text);font-family:var(--font-sans);line-height:1.5;}`);
  lines.push(`img{max-width:100%;height:auto;} a{color:var(--color-primary);} main{max-width:72rem;margin:0 auto;padding:var(--space-8);}`);
  lines.push(`h1{font-size:var(--text-display);line-height:1.15;margin:0 0 var(--space-4);} header,footer{padding:var(--space-4) var(--space-8);}`);
  lines.push(`button,.btn{display:inline-block;background:var(--color-primary);color:#fff;border:0;border-radius:var(--radius-sm);padding:var(--space-2) var(--space-4);text-decoration:none;}`);
  lines.push(`form{display:grid;gap:var(--space-3);} input,textarea,select{padding:var(--space-2);border:1px solid var(--color-border);border-radius:var(--radius-sm);}`);
  return lines.join("\n");
}
