const apiUrl = () => (process.env.API_URL || "http://api:8000").replace(/\/$/, "");
const token = () => process.env.WORKER_TOKEN || "";

async function request(method: string, path: string, body?: unknown): Promise<any> {
  const res = await fetch(apiUrl() + path, {
    method,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: `Bearer ${token()}`,
      "X-FFD-Worker-Token": token(),
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  if (!res.ok) {
    throw new Error(`API ${method} ${path} → ${res.status}`);
  }
  return res.json();
}

export async function nextJob(): Promise<any | null> {
  const data = await request("GET", "/api/v1/worker/jobs/next");
  return data.job ?? null;
}

export async function event(jobId: number, status: string, extra: Record<string, unknown> = {}) {
  return request("POST", `/api/v1/worker/jobs/${jobId}/events`, { status, ...extra });
}

export async function postAudits(jobId: number, audits: unknown[]) {
  return request("POST", `/api/v1/worker/jobs/${jobId}/audits`, { audits });
}

export async function postSuggestions(jobId: number, suggestions: unknown[]) {
  return request("POST", `/api/v1/worker/jobs/${jobId}/suggestions`, { suggestions });
}

export async function postDelivered(jobId: number, buildId: string, checksum: string) {
  return request("POST", `/api/v1/worker/jobs/${jobId}/delivered`, { build_id: buildId, checksum });
}
