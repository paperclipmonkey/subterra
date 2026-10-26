# Assistant evals

A lightweight loop for baselining and iterating on the AI assistant.

## How the loop works

1. `dataset.json` holds a fixed list of test prompts, each with the things to look for.
2. Run `php artisan assistant:eval` — it sends each prompt through `AssistantService`,
   captures the response and tool calls, and writes a markdown report into `results/`.
3. Open the report, tick the boxes (`[ ]` → `[x]`), set a rating, and add notes.
4. Share the rated file back so the system prompt and tool definitions can be tuned
   to the failure modes you saw.

The same dataset re-runs on every iteration, so it's easy to tell whether a prompt
change actually helped.

## Running

Inside the dev container:

```bash
docker exec -it subterra-laravel.test-1 php artisan assistant:eval
```

Useful flags:

```bash
# Run only the accommodation prompts
... assistant:eval --filter=accommodation

# Smoke-test with the first 3 prompts
... assistant:eval --limit=3

# Run as a specific user (default: first platform_admin)
... assistant:eval --user=42

# Use a custom dataset / output path
... assistant:eval --dataset=tests/AssistantEval/dataset.json --output=/tmp/eval.md
```

Each run needs `OPENROUTER_API_KEY` set and burns real model tokens.

## Choosing a faster provider

The `provider` block in `config/assistant.php` lets you pin the upstream provider.
Set `ASSISTANT_PROVIDER_ORDER` in `.env` to a comma-separated priority list:

```
# Pin to Anthropic direct (lower latency for Claude models)
ASSISTANT_PROVIDER_ORDER=Anthropic

# Use the fastest open-weights providers (works with e.g. meta-llama/llama-3.3-70b-instruct)
ASSISTANT_PROVIDER_ORDER=Cerebras,Groq,Together
```

Leave it blank to let OpenRouter pick the cheapest option.

## Adding prompts

Edit `dataset.json`. Each entry needs:

- `id` — short slug used in the report and for filtering
- `category` — groups prompts (e.g. `accommodation`, `recommendations`, `guardrails`)
- `prompt` — the user message
- `checks` — bullet list of expectations rendered as checkboxes in the report

Keep the dataset broad but small (10–20 prompts) so a full run finishes in
under a minute or two and a human can reasonably rate it in one sitting.
