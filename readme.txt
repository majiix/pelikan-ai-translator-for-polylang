=== Pelikan AI Translator for Polylang ===
Contributors: micromax2
Tags: polylang, translation, automated, openrouter, multilingual
Requires at least: 5.8
Tested up to: 7.1
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds automated multi-engine AI translation capabilities directly into the Polylang string translation interface.

== Description ==

Pelikan AI Translator for Polylang streamlines your localization workflow by integrating automated translation directly into the Polylang strings translation interface. Choose from 23 supported AI engines, including OpenRouter, OpenAI Direct, Google Gemini Direct, Groq, Mistral AI, xAI (Grok), regional providers, or your own Custom OpenAI-Compatible / Self-Hosted Ollama endpoint, and translate strings with a single click.

Features:
* Seamless integration with the Polylang Strings translation dashboard.
* One-click translation buttons next to each configured language input.
* Multi-engine support with per-provider API key and model storage so switching engines preserves your credentials.
* Curated model presets, optional server-side live model fetching, and free-text custom model ID input.
* Support for self-hosted Ollama and custom OpenAI-compatible HTTPS endpoints.
* Automatically maps and identifies your active Polylang languages dynamically.

== External Services ==

This plugin connects to the third-party AI service you select in the settings page to perform automated string translation and optional model listing. You must supply your own API key (except for local/unauthenticated custom or Ollama endpoints).

* OpenRouter (https://openrouter.ai) - Terms: https://openrouter.ai/terms, Privacy: https://openrouter.ai/privacy
* OpenAI Direct (https://api.openai.com) - Terms: https://openai.com/policies/terms-of-use, Privacy: https://openai.com/policies/privacy-policy
* Google Gemini Direct (https://generativelanguage.googleapis.com) - Terms: https://ai.google.dev/terms, Privacy: https://policies.google.com/privacy
* Cohere (https://api.cohere.com) - Terms: https://cohere.com/terms-of-use, Privacy: https://cohere.com/privacy
* Groq (https://api.groq.com) - Terms: https://groq.com/terms-of-use/, Privacy: https://groq.com/privacy-policy/
* AvalAI (https://api.avalai.ir) - Website: https://avalai.ir/
* GapGPT (https://api.gapgpt.app) - Website: https://gapgpt.app/
* NVIDIA NIM (https://integrate.api.nvidia.com) - Website: https://build.nvidia.com/
* Mistral AI (https://api.mistral.ai) - Terms: https://mistral.ai/terms/
* Together AI (https://api.together.xyz) - Website: https://www.together.ai/
* Fireworks AI (https://api.fireworks.ai) - Website: https://fireworks.ai/
* xAI (Grok) (https://api.x.ai) - Website: https://x.ai/
* Alibaba Cloud Model Studio (Qwen DashScope) (https://dashscope-intl.aliyuncs.com) - Website: https://www.alibabacloud.com/
* Cloudflare Workers AI (https://api.cloudflare.com) - Website: https://www.cloudflare.com/
* ArvanCloud AI (https://api.arvancloudai.ir) - Website: https://www.arvancloud.ir/
* MetisAI (https://api.metisai.ir) - Website: https://metisai.ir/
* Liara AI (https://ai.liara.ir) - Website: https://liara.ir/
* HeroAI (https://api.heroai.ir) - Website: https://heroai.ir/
* TalkBot (https://api.talkbot.ir) - Website: https://talkbot.ir/
* CupAI (https://api.cupai.ir) - Website: https://cupai.ir/
* Hooshang (https://hushang.cloud) - Website: https://hushang.cloud/
* Hormouz (https://api.hormouz.net) - Website: https://api.hormouz.net/
* Custom OpenAI-Compatible Endpoint / Self-Hosted Ollama - Sends requests only to the HTTPS (or localhost Ollama) Base URL you explicitly configure.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/pelikan-ai-translator-for-polylang` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Ensure the core "Polylang" plugin is also installed and activated.
4. Navigate to Languages > Pelikan Translator in your WordPress admin menu.
5. Choose your preferred AI Engine, enter your API key (or Base URL for Custom/Ollama), and select or type a model ID.
6. Go to Languages > Translations to start translating your strings automatically.

== Frequently Asked Questions ==

= What AI engines are supported? =

The plugin supports 23 chat/text-generation engines including OpenRouter, OpenAI Direct, Google Gemini Direct, Cohere, Groq, Mistral AI, xAI (Grok), Together AI, Fireworks AI, NVIDIA NIM, Alibaba Cloud Model Studio, Cloudflare Workers AI, regional providers, and Custom OpenAI-Compatible / Self-Hosted Ollama endpoints.

= Will switching engines erase my saved API keys? =

No. Credentials and model selections are stored per provider, so you can switch between engines without re-entering keys.

= Does this translate my posts and pages automatically? =

No. Currently, this plugin focuses exclusively on the "Strings translations" section of Polylang, allowing you to quickly localize common theme strings, widget texts, and plugin options.

== Changelog ==

= 1.1.0 =
* Added multi-engine AI provider support (23 chat/text-generation providers including OpenRouter, OpenAI Direct, Google Gemini Direct, Groq, Mistral, xAI, Cloudflare Workers AI, and Custom OpenAI-Compatible / Self-Hosted Ollama).
* Added per-provider API key, model, and endpoint configuration storage.
* Added server-side live model fetching, curated model presets, and free-text custom model ID input.
* Bumped plugin version to 1.1.0.

= 1.0.5 =
* Fixed "No source text found" error by ensuring full compatibility with primary column table header tags (th.column-string).
* Cleaned extracted source text by stripping WordPress table actions and toggle buttons.
* Enhanced language code and slug matching dynamically across all Polylang versions.
* Bumped plugin version to 1.0.5.
= 1.0.4 =
* Verified compatibility with WordPress 7.1.0 and updated Tested up to header.
* Bumped plugin version to 1.0.4.

= 1.0.3 =
* Hardened AJAX translation pipeline against JSON decoding warning/notice vectors.
* Added fallback default Gemini model logic when configuration values are missing.
* Added dismissible dashboard notices for missing Polylang plugin and unconfigured API keys.
* Enhanced admin.js to safely check for localization payload parameters and prevent crashes on other dashboard pages.
* Cleaned up unused site name parsing logic on core scripts enqueuing.

= 1.0.2 =
* Securely routed translation API requests through server-side WordPress AJAX.
* Added missing License URI header to comply with WordPress.org guidelines.
* Added uninstall.php to clean up database options on plugin deletion.
* Initialized documentation structure.

= 1.0.1 =
* Bumped version to 1.0.1.
* Tested up to WordPress 7.0.
* Removed unneeded references and updated documentation.
* General code cleanup and stability improvements.

= 1.0.0 =
* Initial release.
