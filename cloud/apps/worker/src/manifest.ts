import { createHash } from "node:crypto";

export type ManifestPage = {
  path: string;
  post_id: number;
  source_url: string;
  output_relpath: string;
  origin_hash: string;
  output_hash: string;
  post_modified_gmt: string | null;
};

export type Manifest = {
  host: string;
  build_id: string;
  created_at: string;
  pages: ManifestPage[];
  hostmeta: { robots: boolean; sitemap: boolean; llm_txt: boolean };
};

export function sha256(input: string | Buffer): string {
  return createHash("sha256").update(input).digest("hex");
}

export function buildManifest(input: Omit<Manifest, "created_at"> & { created_at?: string }): Manifest {
  return {
    ...input,
    created_at: input.created_at ?? new Date().toISOString(),
  };
}
