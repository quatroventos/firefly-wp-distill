import { sha256 } from "../manifest.ts";

export type PackedFile = { path: string; bytes: Buffer };

const apiUrl = () => (process.env.API_URL || "http://api:8000").replace(/\/$/, "");
const token = () => process.env.WORKER_TOKEN || "";

export async function deliverRest(opts: {
  jobId: number;
  host: string;
  buildId: string;
  manifest: unknown;
  files: PackedFile[];
}): Promise<void> {
  const files = opts.files.map((f) => ({
    path: f.path,
    sha256: sha256(f.bytes),
    content_base64: f.bytes.toString("base64"),
  }));
  const res = await fetch(`${apiUrl()}/api/v1/worker/jobs/${opts.jobId}/push-build`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      Authorization: `Bearer ${token()}`,
      "X-FFD-Worker-Token": token(),
    },
    body: JSON.stringify({
      host: opts.host,
      build_id: opts.buildId,
      manifest: opts.manifest,
      files,
    }),
  });
  if (!res.ok) {
    throw new Error(`push-build ${res.status}: ${await res.text()}`);
  }
}
