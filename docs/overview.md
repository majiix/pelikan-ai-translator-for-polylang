# Overview

This plugin adds automated translation capabilities to the Polylang string translation interface. By integrating with OpenRouter, users can instantly translate their registered site strings with a single click.

## Tech Stack
- PHP 7.4+
- WordPress Core APIs (Settings API, HTTP API, AJAX API)
- Vanilla JavaScript
- Custom Modern CSS

## Dependencies
- Polylang Plugin (active)
- OpenRouter API account and key

## System Architecture
- **Settings Dashboard:** Custom styled dashboard using WordPress Settings API to manage and save configuration securely.
- **Client Side (JS):** Binds to Polylang translation interface, adding translation buttons. Sends secure AJAX calls to the local WordPress backend.
- **Server Side (PHP):** Binds to `wp_ajax_polai_translate`, checks user permissions, verifies nonce, queries the OpenRouter API securely without exposing keys to the client, and returns translation text.
