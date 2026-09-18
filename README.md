# Generative UI with Neuron AI

<video src="https://github.com/neuron-core/generative-ui-demo/raw/main/public/generative-ui.mp4" controls muted width="100%"></video>

A demo of **Generative UI**: a Business Intelligence chat where the AI agent doesn't just reply with text, it decides which UI components to render — KPI cards, charts and tables — based on the data it pulls from the database.

Ask something like *"How did revenue go in the last 6 months?"* and the agent inspects the schema, runs read-only SQL queries against a sample ecommerce database, and answers with live Vue components streamed into the conversation.

## What is Neuron AI

[Neuron AI](https://docs.neuron-ai.dev) is the PHP agentic framework to build and orchestrate AI agents. It gives you agents, tools and toolkits, chat history, RAG, structured output, workflows, streaming and monitoring, so you can integrate AI into your existing PHP applications — Laravel, Symfony or any other framework — without leaving the PHP ecosystem.

📖 Documentation: **https://docs.neuron-ai.dev**

## How it works

- `app/Neuron/BIAgent.php` — the agent: Anthropic or OpenAI as the LLM, the MySQL toolkit in read-only mode, and chat history persisted with Eloquent.
- `app/Neuron/Tools/Render*Tool.php` — the render tools (`render_cards`, `render_chart`, `render_table`) the agent calls to pick a component and its data.
- `resources/js/components/chat` — the Vue components that draw those tool calls in the chat, connected to the agent through [CopilotKit](https://www.copilotkit.ai) and the AG-UI protocol.

The demo has two pages:

| Page | How the agent runs |
|---|---|
| `/chat` | Inside the HTTP request, streaming straight to the browser. |
| `/chat-background` | In a queued job, streaming to the browser through Redis Pub/Sub. |

Stack: Laravel 13, Inertia v3, Vue 3, Tailwind CSS v4.

## Requirements

- PHP 8.3+ with the `redis` extension (phpredis)
- Composer
- Node.js 20+ and npm
- Docker (for MySQL and Redis), or your own MySQL 8 and Redis servers
- An [Anthropic](https://console.anthropic.com) or [OpenAI](https://platform.openai.com) API key

## Installation

```bash
git clone <repository-url> app-demo
cd app-demo

# Start MySQL and Redis
docker compose up -d

# Install dependencies, create .env, generate the app key, migrate and build assets
composer setup
```

Open `.env` and set the API key of the provider you want to use (Anthropic is the default):

```dotenv
AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=sk-ant-...
```

Load the sample ecommerce data (customers, catalog and two years of sales):

```bash
php artisan db:seed
```

### Using OpenAI instead

The agent can run on Anthropic or OpenAI. Switch with `AI_PROVIDER`, no code changes needed:

```dotenv
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
```

| Variable | Default | |
|---|---|---|
| `AI_PROVIDER` | `anthropic` | `anthropic` or `openai` |
| `ANTHROPIC_MODEL` | `claude-sonnet-5` | Model used with Anthropic |
| `OPENAI_MODEL` | `gpt-5` | Model used with OpenAI |

The selection happens in `BIAgent::provider()`. Neuron AI ships many more providers (Gemini, Mistral, Ollama, ...) — see the [documentation](https://docs.neuron-ai.dev) to plug in another one. If you change `.env` while the app is running, restart `composer run dev` so the queue worker picks it up.

## Run it

```bash
composer run dev
```

This starts the Laravel server, the queue worker, the log viewer and Vite. Open http://localhost:8000 and log in with:

- **Email:** `test@example.com`
- **Password:** `password`

## Things to ask

- *Show me the headline KPIs for this month.*
- *Revenue trend by month over the last year.*
- *Top 10 products by revenue.*
- *Which categories are growing the fastest?*
