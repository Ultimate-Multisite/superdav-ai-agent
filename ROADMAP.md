# Superdav AI Agent Roadmap

## LLM Provider Research (March 2026)

### Goal

Find an LLM model that is good at agentic tasks (tool calling, multi-step reasoning), very inexpensive, and can be resold with markup for the Superdav AI Agent product. Must be compatible with OpenAI chat completions API format.

### Recommended Models

#### Default Tier: GPT-4.1-nano (OpenAI)

| | |
|---|---|
| Input | $0.10 / 1M tokens |
| Output | $0.40 / 1M tokens |
| Context | 1M tokens |
| Tool calling | Excellent — specifically optimized for function calling |
| API format | Native OpenAI chat completions |
| ToS | Commercial apps allowed, output ownership assigned to builder |

Best overall value. Native OpenAI format means existing `send_prompt_direct()` works unchanged. At $0.10 input, can charge $0.50-$1.00/M and still be affordable.

#### Premium Tier: GPT-4.1-mini (OpenAI)

| | |
|---|---|
| Input | $0.40 / 1M tokens |
| Output | $1.60 / 1M tokens |
| Context | 1M tokens |
| Tool calling | Very strong (0.76-0.85 tool selection scores) |
| API format | Native OpenAI chat completions |

4x the cost of nano but significantly better multi-step reasoning. Good as a premium option for complex workflows.

#### Alternative: Gemini 2.5 Flash (Google)

| | |
|---|---|
| Input | $0.30 / 1M tokens |
| Output | $2.50 / 1M tokens |
| Context | 1M tokens |
| Tool calling | Good, with controllable thinking budget |
| Caveat | Not natively OpenAI-compatible — needs adapter (OpenRouter/LiteLLM) |
| ToS | Commercial apps allowed. Must use paid tier to avoid training data sharing |

#### Budget: Mistral Small 3.1 / Devstral Small 2

| | |
|---|---|
| Input | $0.10 / 1M tokens |
| Output | $0.30 / 1M tokens |
| Context | 128K tokens |
| Tool calling | Decent for the price |
| License | Apache 2.0 — self-hostable at scale with no restrictions |

### Cost Per Agent Session

Assuming ~5K input + ~2K output tokens per iteration, 5 iterations:

| Model | Raw cost | With 5x markup |
|-------|----------|----------------|
| GPT-4.1-nano | $0.0065 | $0.033 |
| GPT-4.1-mini | $0.026 | $0.13 |
| Gemini 2.5 Flash | $0.033 | $0.16 |
| Claude 3.5 Haiku | $0.060 | $0.30 |

### Full Pricing Reference

| Model | Provider | Input/1M | Output/1M | Context |
|-------|----------|----------|-----------|---------|
| GPT-4.1-nano | OpenAI | $0.10 | $0.40 | 1M |
| GPT-4.1-mini | OpenAI | $0.40 | $1.60 | 1M |
| GPT-4.1 | OpenAI | $2.00 | $8.00 | 1M |
| Gemini 2.5 Flash Lite | Google | $0.10 | $0.40 | 1M |
| Gemini 2.5 Flash | Google | $0.30 | $2.50 | 1M |
| Devstral Small 2 | Mistral | $0.10 | $0.30 | 128K |
| Mistral Small 3.1 | Mistral | $0.10 | $0.30 | 128K |
| DeepSeek V3 | DeepSeek | $0.14 | $0.28 | 128K |
| DeepSeek V3.2 | DeepSeek | $0.28 | $0.42 | 128K |
| Claude 3.5 Haiku | Anthropic | $0.80 | $4.00 | 200K |
| Llama 3.1 8B | Groq | $0.05 | $0.08 | 128K |
| Llama 3.3 70B | Groq | $0.59 | $0.79 | 128K |

### Models to Avoid for Agentic Resale

- **DeepSeek** — 81.5% tool calling accuracy vs 96%+ for OpenAI, no indemnification, capacity/availability concerns
- **Llama 8B models** (any provider) — too weak for reliable tool calling
- **Gemini Flash Lite** — thinking disabled, complex tool orchestration suffers
- **Anthropic Haiku** — ambiguous ToS for "wrapper" apps, 4-8x more expensive than nano

### Terms of Service Summary

All major providers allow building commercial products on their APIs. None allow reselling raw API access. OpenAI has the clearest, most permissive language: you own outputs, can build "Customer Applications," and serve end users. Anthropic's terms are the most restrictive and ambiguous for wrapper/resale products.

### Decision

Start with **GPT-4.1-nano** as default, offer **GPT-4.1-mini** as premium tier. Both use native OpenAI chat completions format — no adapter needed. 1M token context window at $0.10/M input is exceptional value.

### Implementation Notes

- Both models work with the existing OpenAI-compatible proxy in `send_prompt_direct()`
- No code changes needed beyond configuring the endpoint URL and model ID
- Discovery mode (tool discovery feature) reduces tool definition tokens from ~77K to ~3K, making nano even more viable since it has less capacity for large tool sets
- Consider tiered pricing: nano for standard users, mini for power users, full GPT-4.1 for enterprise
