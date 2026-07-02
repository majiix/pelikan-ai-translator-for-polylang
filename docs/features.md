# Features

## Polylang Strings Translation
- Binds to Polylang string translation tables.
- Adds translation action buttons next to input fields.
- Translates source text into targeted languages dynamically.

## Service Integration
- Integrates with OpenRouter API.
- Supports customizable translation model selection.
- Searchable model selection dropdown menu.

## Security
- Server-side translation request proxy prevents leaking API credentials in the HTML/JS source code.
- Capabilities checks (`manage_options`) and nonce verification on translation requests.

## Settings Management
- Modernized configuration page nested under Languages admin page.
- Password visibility toggle.

## System Cleanup
- Automatic database option cleanup on plugin uninstallation.

## System Notices & Diagnostics
- Auto-detects if Polylang is missing or inactive and displays a warning notice in the WP dashboard.
- Displays configuration warnings if the OpenRouter API Key is empty.

## Fault Tolerance
- Fallback logic to a default model if options are empty.
- Defensive JSON parsing and validation to prevent PHP warnings/notices and server-side 500 errors.
- Graceful client-side scripts execution guards to prevent errors on non-translation admin screens.
