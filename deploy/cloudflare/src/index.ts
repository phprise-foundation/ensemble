import { DurableObject } from "cloudflare:workers";

const HEALTH_PATH = "/api/health";
const CONTAINER_PORT = 80;
const INSTANCE_TYPE = "standard-2";
const CONTAINER_ENV_KEYS = [
  "DATABASE_URL",
  "APP_ENCRYPTION_KEY",
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
    return port.fetch(forwarded);
  }

  private async ensureRunning(): Promise<void> {
    const container = this.ctx.container;
    if (container === undefined) {
      throw new Error("no container is bound to this durable object");
    }
    if (!container.running) {
      container.start({
        image: container.images.base,
        instance: INSTANCE_TYPE,
        enableInternet: true,
        env: this.containerEnv(),
      });
    }

    const port = container.getTcpPort(CONTAINER_PORT);
    if (port === undefined) {
      throw new Error(`the container exposes no TCP port ${CONTAINER_PORT}`);
    }
    let lastError: unknown;
    for (let attempt = 0; attempt < 100; attempt++) {
      try {
        const response = await port.fetch(`http://container${HEALTH_PATH}`, {
          signal: AbortSignal.timeout(1000),
        });
        await response.body?.cancel();
        if (!response.ok) {
          throw new Error(`health check returned ${response.status}`);
        }
        return;
      } catch (error) {
        lastError = error;
        await scheduler.wait(500);
      }
    }
    throw new Error("the Ensemble container did not become ready on port 80", {
      cause: lastError,
    });
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
  fetch(request, env) {
    return env.ENSEMBLE.getByName("ensemble").fetch(request);
  },

  async scheduled(_event, env) {
    await env.ENSEMBLE.getByName("ensemble").fetch(`https://container${HEALTH_PATH}`);
  },
} satisfies ExportedHandler<Env>;
