
=== Pelikan AI Translator for Polylang ===
Contributors: micromax2
Tags: polylang, translation, automated, openrouter, multilingual
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds automated translation capabilities directly into the PolyLang string translation interface.

== Description ==

Pelikan Translator for Polylang streamlines your localization workflow by integrating automated translation seamlessly into the Polylang strings translation interface. By utilizing the OpenRouter API, you can instantly translate your site strings with a single click.

Features:
* Seamless integration with the Polylang Strings translation dashboard.
* One-click translation buttons next to each configured language input.
* Customizable OpenRouter model selection.
* Automatically maps and identifies your active Polylang languages dynamically.

**🧠 Why OpenRouter?**

OpenRouter is an aggregator that offers significant benefits for WordPress users:

**Cheaper:** Many high-quality translation models are incredibly affordable.

**Flexible:** Switch models instantly. If a new model is released tomorrow, you can simply paste its ID and use it immediately.

**No Middleman:** You use your own API key.

== External Services ==

This plugin relies on OpenRouter as a third-party service to provide automated translation capabilities.

Service Name: OpenRouter
Service URL: https://openrouter.ai/
Terms of Service: OpenRouter Terms https://openrouter.ai/terms
Privacy Policy: OpenRouter Privacy Policy https://openrouter.ai/privacy

**Note:** You must obtain your own API key from OpenRouter to use this plugin.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/pelikan-ai-translator-for-polylang` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Ensure the core "Polylang" plugin is also installed and activated.
4. Navigate to Languages > Pelikan Translator in your WordPress admin menu.
5. Enter your valid OpenRouter API key and preferred model ID.
6. Go to Languages > Translations to start translating your strings automatically.

== Frequently Asked Questions ==

= Where do I get an API key? =

You can obtain an API key by signing up for an account at OpenRouter and generating a new key from your dashboard.

= What models are supported? =

You can use any text generation model available on OpenRouter by entering its exact model ID (e.g., `meta-llama/llama-3-8b-instruct`) in the settings page.

= Does this translate my posts and pages automatically? =

No. Currently, this plugin focuses exclusively on the "Strings translations" section of Polylang, allowing you to quickly localize common theme strings, widget texts, and plugin options.

== Changelog ==

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