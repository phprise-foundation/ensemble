import { DurableObject } from "cloudflare:workers";

const HEALTH_PATH = "/api/health";
const CONTAINER_PORT = 80;
const INSTANCE_TYPE = "standard-2";
const READY_ATTEMPTS = 30;
const READY_DELAY_MS = 500;
const CONTAINER_ENV_KEYS = [
  "DATABASE_URL",
  "APP_ENCRYPTION_KEY",
  "APP_ENV",
  "ENSEMBLE_WORKDIR_ROOT",
  "BOOTSTRAP_ADMIN_LOGIN",
  "BOOTSTRAP_ADMIN_PASSWORD",
  "DEEPSEEK_API_KEY",
  "SILICONFLOW_API_KEY",
  "OPENROUTER_API_KEY",
  "ANTHROPIC_API_KEY",
  "OPENAI_API_KEY",
] as const;

interface Env {
  ENSEMBLE: DurableObjectNamespace<EnsembleContainer>;
  DATABASE_URL?: string;
  APP_ENCRYPTION_KEY?: string;
  APP_ENV?: string;
  ENSEMBLE_WORKDIR_ROOT?: string;
  BOOTSTRAP_ADMIN_LOGIN?: string;
  BOOTSTRAP_ADMIN_PASSWORD?: string;
  DEEPSEEK_API_KEY?: string;
  SILICONFLOW_API_KEY?: string;
  OPENROUTER_API_KEY?: string;
  ANTHROPIC_API_KEY?: string;
  OPENAI_API_KEY?: string;
}

export class EnsembleContainer extends DurableObject<Env> {
  override async fetch(request: Request): Promise<Response> {
    try {
      await this.ensureRunning();
      const container = this.ctx.container;
      if (container === undefined) {
        throw new Error("no container is bound to this durable object");
      }
      const url = new URL(request.url);
      url.protocol = "http:";
      url.host = "container";
      const forwarded = new Request(url, request);
      forwarded.headers.delete("host");
      const port = container.getTcpPort(CONTAINER_PORT);
      if (port === undefined) {
        throw new Error(`the container exposes no TCP port ${CONTAINER_PORT}`);
      }
      const response = await port.fetch(forwarded);
      return this.publicLocation(response, request);
    } catch (error) {
      console.error("ensemble request failed", error);
      return this.failureResponse(request, error);
    }
  }

  private publicLocation(response: Response, request: Request): Response {
    const location = response.headers.get("location");
    if (location === null) {
      return response;
    }
    let target: URL;
    try {
      target = new URL(location, request.url);
    } catch {
      return response;
    }
    if (target.hostname !== "container") {
      return response;
    }
    const publicUrl = new URL(request.url);
    target.protocol = publicUrl.protocol;
    target.host = publicUrl.host;
    const headers = new Headers(response.headers);
    headers.set("location", target.toString());
    return new Response(response.body, { status: response.status, headers });
  }

  private failureResponse(request: Request, error: unknown): Response {
    const path = new URL(request.url).pathname;
    return new Response(
      JSON.stringify({
        ok: false,
        error: "the Ensemble container is not available",
        detail: this.describe(error),
        container: this.ctx.container?.running === true ? "running" : "stopped",
        path,
        forwardedEnv: Object.keys(this.containerEnv()),
      }),
      {
        status: 503,
        headers: { "content-type": "application/json; charset=utf-8" },
      },
    );
  }

  private describe(error: unknown): string {
    if (error instanceof Error) {
      const cause = error.cause instanceof Error ? `: ${error.cause.message}` : "";
      return `${error.message}${cause}`;
    }
    return String(error);
  }

  private async ensureRunning(): Promise<void> {
    const container = this.ctx.container;
    if (container === undefined) {
      throw new Error("no container is bound to this durable object");
    }
    if (container.running) {
      return;
    }
    try {
      container.start({
        image: container.images.base,
        instance: INSTANCE_TYPE,
        enableInternet: true,
        env: this.containerEnv(),
      });
    } catch (error) {
      console.error("container start failed", error);
    }

    const port = container.getTcpPort(CONTAINER_PORT);
    if (port === undefined) {
      throw new Error(`the container exposes no TCP port ${CONTAINER_PORT}`);
    }
    let lastError: unknown;
    for (let attempt = 0; attempt < READY_ATTEMPTS; attempt++) {
      if (!container.running) {
        throw new Error("the container stopped while starting", {
          cause: lastError instanceof Error ? lastError : undefined,
        });
      }
      try {
        const response = await port.fetch(`http://container${HEALTH_PATH}`, {
          signal: AbortSignal.timeout(1000),
        });
        await response.body?.cancel();
        if (response.ok) {
          return;
        }
        lastError = new Error(`health check returned ${response.status}`);
      } catch (error) {
        lastError = error;
      }
      await scheduler.wait(READY_DELAY_MS);
    }
    throw new Error(
      `the Ensemble container is still starting on port ${CONTAINER_PORT}`,
      { cause: lastError instanceof Error ? lastError : undefined },
    );
  }

  private containerEnv(): Record<string, string> {
    const env: Record<string, string> = {};
    const self = this.env as unknown as Record<string, string | undefined>;
    for (const key of CONTAINER_ENV_KEYS) {
      const value = self[key];
      if (value !== undefined && value !== "") {
        env[key] = value;
      }
    }
    return env;
  }
}

export default {
  async fetch(request, env) {
    try {
      return await env.ENSEMBLE.getByName("ensemble").fetch(request);
    } catch (error) {
      console.error("ensemble worker failed", error);
      return new Response(
        JSON.stringify({
          ok: false,
          error: "the Ensemble worker failed before reaching the container",
          detail: error instanceof Error ? error.message : String(error),
        }),
        {
          status: 503,
          headers: { "content-type": "application/json; charset=utf-8" },
        },
      );
    }
  },

  async scheduled(_event, env) {
    try {
      await env.ENSEMBLE.getByName("ensemble").fetch(`https://container${HEALTH_PATH}`);
    } catch (error) {
      console.error("ensemble keep-alive failed", error);
    }
  },
} satisfies ExportedHandler<Env>;
