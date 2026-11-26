<?php
/**
 * HTTP Client for Pelikan AI Translator for Polylang.
 *
 * @package Pelikan_AI_Translator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles translation and model listing requests across AI providers.
 */
class Polai_Client {

	/**
	 * Translate source text into a target language using the configured provider.
	 *
	 * @param array<string, mixed>  $provider    Provider metadata from Polai_Providers.
	 * @param array<string, string> $config      Saved provider configuration.
	 * @param string                $source_text Text to translate.
	 * @param string                $target_lang Target language name or slug.
	 * @return string|WP_Error
	 */
	public static function translate( $provider, $config, $source_text, $target_lang ) {
		$model = ! empty( $config['model'] ) ? $config['model'] : $provider['default_model'];
		if ( empty( $model ) ) {
			return new WP_Error( 'polai_missing_model', __( 'Model is not configured.', 'pelikan-ai-translator-for-polylang' ) );
		}

		if ( ! empty( $provider['requires_key'] ) && empty( $config['api_key'] ) ) {
			return new WP_Error( 'polai_missing_key', __( 'API Key is not configured.', 'pelikan-ai-translator-for-polylang' ) );
		}

		if ( ! empty( $provider['requires_account_id'] ) && empty( $config['account_id'] ) ) {
			return new WP_Error( 'polai_missing_account', __( 'Account or Workspace ID is not configured.', 'pelikan-ai-translator-for-polylang' ) );
		}

		$endpoint = self::resolve_chat_endpoint( $provider, $config, $model );
		if ( is_wp_error( $endpoint ) ) {
			return $endpoint;
		}

		$prompt = 'You are a helpful translation assistant. Translate the following text into '
			. $target_lang
			. '. Respond only with the translated text and nothing else and if there are html elements inside text do not remove them and also Never use mdash in any text and use a regular hyphen or alternative wording instead: '
			. $source_text;

		if ( 'gemini' === $provider['format'] ) {
			return self::request_gemini( $endpoint, $config, $prompt );
		}

		return self::request_openai_compatible( $provider['id'], $endpoint, $config, $model, $prompt );
	}

	/**
	 * Fetch available models for a provider.
	 *
	 * @param array<string, mixed>  $provider Provider metadata.
	 * @param array<string, string> $config   Provider configuration.
	 * @return array<int, array<string, string>>|WP_Error
	 */
	public static function fetch_models( $provider, $config ) {
		$models_url = self::resolve_models_url( $provider, $config );
		if ( is_wp_error( $models_url ) ) {
			return $models_url;
		}

		if ( empty( $models_url ) ) {
			return $provider['preset_models'];
		}

		$headers = array(
			'Accept' => 'application/json',
		);

		$api_key = isset( $config['api_key'] ) ? $config['api_key'] : '';
		if ( 'gemini' === $provider['format'] ) {
			if ( empty( $api_key ) ) {
				return new WP_Error( 'polai_missing_key', __( 'Enter an API Key first to fetch live models.', 'pelikan-ai-translator-for-polylang' ) );
			}
			$headers['x-goog-api-key'] = $api_key;
		} elseif ( empty( $provider['public_models'] ) ) {
			if ( ! empty( $provider['requires_key'] ) && empty( $api_key ) ) {
				return new WP_Error( 'polai_missing_key', __( 'Enter an API Key first to fetch live models.', 'pelikan-ai-translator-for-polylang' ) );
			}
			if ( ! empty( $api_key ) ) {
				$headers['Authorization'] = 'Bearer ' . $api_key;
			}
		}

		$response = wp_remote_get(
			$models_url,
			array(
				'timeout' => 15,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			return new WP_Error( 'polai_models_http', self::extract_error_message( $data, $code ) );
		}

		return self::normalize_models_response( $provider['format'], $data );
	}

	/**
	 * Validate a custom or self-hosted Ollama base URL.
	 *
	 * Allows HTTPS URLs, and HTTP only for loopback hosts (localhost, 127.0.0.1, ::1).
	 *
	 * @param string $raw_url Raw user-supplied URL.
	 * @return string|WP_Error Normalized URL without trailing slash, or WP_Error.
	 */
	public static function validate_custom_base_url( $raw_url ) {
		$url = trim( $raw_url );
		if ( empty( $url ) ) {
			return new WP_Error( 'polai_missing_url', __( 'Base URL is required for custom endpoints.', 'pelikan-ai-translator-for-polylang' ) );
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return new WP_Error( 'polai_invalid_url', __( 'Please enter a valid Base URL.', 'pelikan-ai-translator-for-polylang' ) );
		}

		$scheme = strtolower( $parts['scheme'] );
		$host   = strtolower( trim( $parts['host'], '[]' ) );

		// Block cloud metadata endpoints.
		if ( '169.254.169.254' === $host || 'metadata.google.internal' === $host ) {
			return new WP_Error( 'polai_blocked_host', __( 'This host is not allowed.', 'pelikan-ai-translator-for-polylang' ) );
		}

		$is_loopback = in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );
		if ( 'https' !== $scheme && ! ( 'http' === $scheme && $is_loopback ) ) {
			return new WP_Error(
				'polai_insecure_url',
				__( 'Custom endpoints must use HTTPS (HTTP is allowed only for localhost / 127.0.0.1 Ollama).', 'pelikan-ai-translator-for-polylang' )
			);
		}

		return untrailingslashit( esc_url_raw( $url ) );
	}

	/**
	 * Resolve the chat completion endpoint URL for a provider.
	 *
	 * @param array<string, mixed>  $provider Provider metadata.
	 * @param array<string, string> $config   Saved provider configuration.
	 * @param string                $model    Model ID.
	 * @return string|WP_Error
	 */
	private static function resolve_chat_endpoint( $provider, $config, $model ) {
		if ( ! empty( $provider['requires_base_url'] ) ) {
			$base = self::validate_custom_base_url( isset( $config['base_url'] ) ? $config['base_url'] : '' );
			if ( is_wp_error( $base ) ) {
				return $base;
			}
			if ( substr( $base, -17 ) === '/chat/completions' ) {
				return $base;
			}
			return $base . '/chat/completions';
		}

		$endpoint = $provider['endpoint'];
		if ( false !== strpos( $endpoint, '{account_id}' ) ) {
			$endpoint = str_replace( '{account_id}', rawurlencode( $config['account_id'] ), $endpoint );
		}
		if ( false !== strpos( $endpoint, '{model}' ) ) {
			$clean_model = preg_replace( '#^models/#', '', $model );
			$endpoint    = str_replace( '{model}', rawurlencode( $clean_model ), $endpoint );
		}

		return $endpoint;
	}

	/**
	 * Resolve the models listing URL for a provider.
	 *
	 * @param array<string, mixed>  $provider Provider metadata.
	 * @param array<string, string> $config   Saved provider configuration.
	 * @return string|WP_Error
	 */
	private static function resolve_models_url( $provider, $config ) {
		if ( ! empty( $provider['requires_base_url'] ) ) {
			$base = self::validate_custom_base_url( isset( $config['base_url'] ) ? $config['base_url'] : '' );
			if ( is_wp_error( $base ) ) {
				return $base;
			}
			$base = preg_replace( '#/chat/completions$#', '', $base );
			return untrailingslashit( $base ) . '/models';
		}

		return isset( $provider['models_url'] ) ? $provider['models_url'] : '';
	}

	/**
	 * Send a translation request to an OpenAI-compatible chat completions endpoint.
	 *
	 * @param string                $provider_id Provider ID.
	 * @param string                $endpoint    Target URL.
	 * @param array<string, string> $config      Provider configuration.
	 * @param string                $model       Model ID.
	 * @param string                $prompt      Translation prompt.
	 * @return string|WP_Error
	 */
	private static function request_openai_compatible( $provider_id, $endpoint, $config, $model, $prompt ) {
		$headers = array(
			'Content-Type' => 'application/json',
		);

		if ( ! empty( $config['api_key'] ) ) {
			$headers['Authorization'] = 'Bearer ' . $config['api_key'];
		}

		if ( 'openrouter' === $provider_id ) {
			$headers['HTTP-Referer'] = esc_url_raw( get_site_url() );
			$headers['X-Title']      = self::get_ascii_site_name();
		}

		$body_data = wp_json_encode(
			array(
				'model'    => $model,
				'messages' => array(
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
			)
		);

		if ( false === $body_data ) {
			return new WP_Error( 'polai_encode_error', __( 'Failed to encode request data.', 'pelikan-ai-translator-for-polylang' ) );
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => 30,
				'redirection' => 5,
				'blocking'    => true,
				'headers'     => $headers,
				'body'        => $body_data,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ( is_array( $data ) && isset( $data['error'] ) ) ) {
			return new WP_Error( 'polai_api_error', self::extract_error_message( $data, $code ) );
		}

		$translated = '';
		if ( is_array( $data ) && isset( $data['choices'][0]['message']['content'] ) ) {
			$translated = $data['choices'][0]['message']['content'];
		}

		if ( empty( $translated ) ) {
			return new WP_Error( 'polai_empty_response', __( 'Translation failed or empty response.', 'pelikan-ai-translator-for-polylang' ) );
		}

		return preg_replace( '/^"|"$/', '', trim( $translated ) );
	}

	/**
	 * Send a translation request to Google Gemini's native generateContent API.
	 *
	 * @param string                $endpoint Target URL.
	 * @param array<string, string> $config   Provider configuration.
	 * @param string                $prompt   Translation prompt.
	 * @return string|WP_Error
	 */
	private static function request_gemini( $endpoint, $config, $prompt ) {
		$body_data = wp_json_encode(
			array(
				'contents' => array(
					array(
						'parts' => array(
							array( 'text' => $prompt ),
						),
					),
				),
			)
		);

		if ( false === $body_data ) {
			return new WP_Error( 'polai_encode_error', __( 'Failed to encode request data.', 'pelikan-ai-translator-for-polylang' ) );
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => 30,
				'redirection' => 5,
				'blocking'    => true,
				'headers'     => array(
					'Content-Type'   => 'application/json',
					'x-goog-api-key' => $config['api_key'],
				),
				'body'        => $body_data,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ( is_array( $data ) && isset( $data['error'] ) ) ) {
			return new WP_Error( 'polai_api_error', self::extract_error_message( $data, $code ) );
		}

		$translated = '';
		if ( is_array( $data ) && isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$translated = $data['candidates'][0]['content']['parts'][0]['text'];
		}

		if ( empty( $translated ) ) {
			return new WP_Error( 'polai_empty_response', __( 'Translation failed or empty response.', 'pelikan-ai-translator-for-polylang' ) );
		}

		return preg_replace( '/^"|"$/', '', trim( $translated ) );
	}

	/**
	 * Normalize model list responses into {id, name} pairs.
	 *
	 * @param string $format Provider format ('openai' or 'gemini').
	 * @param mixed  $data   Decoded JSON response.
	 * @return array<int, array<string, string>>
	 */
	private static function normalize_models_response( $format, $data ) {
		$models = array();
		if ( ! is_array( $data ) ) {
			return $models;
		}

		if ( 'gemini' === $format && isset( $data['models'] ) && is_array( $data['models'] ) ) {
			foreach ( $data['models'] as $item ) {
				if ( empty( $item['name'] ) ) {
					continue;
				}
				$id       = preg_replace( '#^models/#', '', $item['name'] );
				$models[] = array(
					'id'   => sanitize_text_field( $id ),
					'name' => sanitize_text_field( ! empty( $item['displayName'] ) ? $item['displayName'] : $id ),
				);
			}
			return $models;
		}

		$list = array();
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$list = $data['data'];
		} elseif ( isset( $data['models'] ) && is_array( $data['models'] ) ) {
			$list = $data['models'];
		}

		foreach ( $list as $item ) {
			if ( empty( $item['id'] ) ) {
				continue;
			}
			$models[] = array(
				'id'   => sanitize_text_field( $item['id'] ),
				'name' => sanitize_text_field( ! empty( $item['name'] ) ? $item['name'] : $item['id'] ),
			);
		}

		return $models;
	}

	/**
	 * Extract a safe error message from a decoded API response.
	 *
	 * @param mixed $data Decoded JSON response.
	 * @param int   $code HTTP status code.
	 * @return string
	 */
	private static function extract_error_message( $data, $code ) {
		if ( is_array( $data ) && isset( $data['error']['message'] ) ) {
			return sanitize_text_field( $data['error']['message'] );
		}
		if ( is_array( $data ) && isset( $data['error'] ) && is_string( $data['error'] ) ) {
			return sanitize_text_field( $data['error'] );
		}
		if ( is_array( $data ) && isset( $data['message'] ) && is_string( $data['message'] ) ) {
			return sanitize_text_field( $data['message'] );
		}

		return sprintf(
			/* translators: %d: HTTP status code. */
			__( 'API request failed with status code %d.', 'pelikan-ai-translator-for-polylang' ),
			$code
		);
	}

	/**
	 * Build an ASCII-safe site title for OpenRouter X-Title header.
	 *
	 * @return string
	 */
	private static function get_ascii_site_name() {
		$raw_site_name  = wp_strip_all_tags( get_bloginfo( 'name' ) );
		$safe_site_name = preg_replace( '/[^\x20-\x7E]/', '', $raw_site_name );
		if ( empty( trim( $safe_site_name ) ) ) {
			return 'Polai Translator';
		}
		return $safe_site_name;
	}
}
