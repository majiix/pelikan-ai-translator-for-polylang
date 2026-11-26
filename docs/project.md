# Pelikan AI Translator for Polylang

## Overview
WordPress plugin that adds one-click AI translation buttons to Polylang's **Strings translations** table (`mlang_strings`), supporting 23 chat/text-generation AI engines with per-provider credential and model storage.

## Tech Stack & Dependencies
- **Platform:** WordPress 5.8+ (tested up to 7.1), PHP 7.4+
- **Required Plugin:** Polylang (`pll_languages_list`)
- **Frontend:** Vanilla JavaScript (`assets/js/admin.js`), CSS (`assets/css/admin.css`), WordPress Dashicons

## Architecture
- `pelikan-ai-translator-for-polylang.php`: `Polai_Translator` bootstrap, settings registration (`polai_provider`, `polai_provider_configs`), legacy OpenRouter fallback (`polai_api_key`, `polai_model`), admin UI, notices, and AJAX endpoints (`polai_translate`, `polai_fetch_models`).
- `includes/class-polai-providers.php`: `Polai_Providers` static registry of 23 supported chat/text-generation providers, endpoints, required fields (`requires_key`, `requires_account_id`, `requires_base_url`), and curated model presets.
- `includes/class-polai-client.php`: `Polai_Client` server-side HTTP client handling OpenAI-compatible and Google Gemini `generateContent` translation requests, model listing, and SSRF-safe custom/Ollama URL validation.
- `uninstall.php`: Removes `polai_provider`, `polai_provider_configs`, `polai_api_key`, and `polai_model` on plugin deletion.

## Current Features
- 23 supported AI engines (OpenRouter, OpenAI Direct, Google Gemini Direct, Cohere, Groq, AvalAI, GapGPT, NVIDIA NIM, Mistral AI, Together AI, Fireworks AI, xAI, Alibaba Cloud Model Studio, Cloudflare Workers AI, ArvanCloud AI, MetisAI, Liara AI, HeroAI, TalkBot, CupAI, Hooshang, Hormouz, and Custom OpenAI-Compatible / Self-Hosted Ollama).
- Per-provider credential and model persistence.
- Searchable model dropdown with curated presets, server-side live model fetching, and free-text custom model input.
- Server-side AJAX translation preserving HTML tags inside strings.

## Verification Commands
- Check git status and diff: `rtk git status`
- Check for forbidden em dashes across plugin files: `rg -n "—" pelikan-ai-translator-for-polylang.php includes/ assets/ readme.txt uninstall.php`
- Full runtime verification requires a WordPress + Polylang instance (PHP is not installed in the container).
