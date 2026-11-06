import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import Ajv from "ajv";
import addFormats from "ajv-formats";
import { describe, expect, it } from "vitest";
import { buildManifest, sha256 } from "../src/manifest.ts";

const root = join(dirname(fileURLToPath(import.meta.url)), "../../../packages/shared/schemas/manifest.schema.json");

describe("manifest schema", () => {
  it("aceita um manifesto válido", () => {
    const schema = JSON.parse(readFileSync(root, "utf8"));
    const ajv = new Ajv({ allErrors: true, strict: false });
    addFormats(ajv);
    const validate = ajv.compile(schema);
    const hash = sha256("x");
    const manifest = buildManifest({
      host: "lp.example.test",
      build_id: "20261106T133000Z-a1b2",
      pages: [
        {
          path: "/",
          post_id: 12,
          source_url: "https://lp.example.test/",
          output_relpath: "index.html",
          origin_hash: hash,
          output_hash: hash,
          post_modified_gmt: "2026-11-06 12:00:00",
        },
      ],
      hostmeta: { robots: true, sitemap: true, llm_txt: true },
    });
    expect(validate(manifest)).toBe(true);
  });
});
