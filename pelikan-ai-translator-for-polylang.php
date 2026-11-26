<?php
/**
 * Plugin Name:       Pelikan AI Translator for Polylang
 * Plugin URI:        https://wordpress.org/plugins/pelikan-ai-translator-for-polylang
 * Description:       Adds automated AI translation capabilities to Polylang string translation across multiple AI engines.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            micromax
 * Text Domain:       pelikan-ai-translator-for-polylang
 * Domain Path:       /languages
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'POLAI_VERSION', '1.1.0' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-polai-providers.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-polai-client.php';

/**
 * Main plugin controller for Pelikan AI Translator for Polylang.
 */
class Polai_Translator {

	/**
	 * Constructor to initialize hooks.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		// Priority 99 ensures Polylang's 'mlang' menu exists before we add our submenu.
		add_action( 'admin_menu', array( $this, 'add_settings_page' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_polai_translate', array( $this, 'ajax_translate' ) );
		add_action( 'wp_ajax_polai_fetch_models', array( $this, 'ajax_fetch_models' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Add the settings page to the admin menu under Polylang.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'mlang',
			esc_html__( 'Pelikan Translator Settings', 'pelikan-ai-translator-for-polylang' ),
			esc_html__( 'Pelikan Translator', 'pelikan-ai-translator-for-polylang' ),
			'manage_options',
			'polai-translator',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting(
			'polai_options_group',
			'polai_provider',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_provider' ),
				'default'           => 'openrouter',
			)
		);

		register_setting(
			'polai_options_group',
			'polai_provider_configs',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_provider_configs' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize the selected provider ID.
	 *
	 * @param string $value Raw provider ID.
	 * @return string
	 */
	public function sanitize_provider( $value ) {
		$provider_id = sanitize_key( $value );
		return Polai_Providers::exists( $provider_id ) ? $provider_id : 'openrouter';
	}

	/**
	 * Sanitize per-provider configurations.
	 *
	 * @param mixed $configs Raw configs array.
	 * @return array<string, array<string, string>>
	 */
	public function sanitize_provider_configs( $configs ) {
		if ( ! is_array( $configs ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $configs as $provider_id => $cfg ) {
			$pid = sanitize_key( $provider_id );
			if ( ! Polai_Providers::exists( $pid ) || ! is_array( $cfg ) ) {
				continue;
			}
			$sanitized[ $pid ] = array(
				'api_key'    => isset( $cfg['api_key'] ) ? sanitize_text_field( wp_unslash( $cfg['api_key'] ) ) : '',
				'model'      => isset( $cfg['model'] ) ? sanitize_text_field( wp_unslash( $cfg['model'] ) ) : '',
				'account_id' => isset( $cfg['account_id'] ) ? sanitize_text_field( wp_unslash( $cfg['account_id'] ) ) : '',
				'base_url'   => isset( $cfg['base_url'] ) ? esc_url_raw( trim( wp_unslash( $cfg['base_url'] ) ) ) : '',
			);
		}

		return $sanitized;
	}

	/**
	 * Get all saved provider configurations with legacy OpenRouter fallback.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function get_all_configs() {
		$configs = get_option( 'polai_provider_configs', array() );
		if ( ! is_array( $configs ) ) {
			$configs = array();
		}

		// Preserve legacy OpenRouter options if not yet migrated.
		if ( empty( $configs['openrouter'] ) ) {
			$legacy_key   = get_option( 'polai_api_key', '' );
			$legacy_model = get_option( 'polai_model', '' );
			if ( ! empty( $legacy_key ) || ! empty( $legacy_model ) ) {
				$configs['openrouter'] = array(
					'api_key'    => $legacy_key,
					'model'      => $legacy_model ? $legacy_model : 'google/gemini-3-flash-preview',
					'account_id' => '',
					'base_url'   => '',
				);
			}
		}

		return $configs;
	}

	/**
	 * Get the active provider ID.
	 *
	 * @return string
	 */
	private function get_active_provider_id() {
		$provider_id = get_option( 'polai_provider', 'openrouter' );
		return Polai_Providers::exists( $provider_id ) ? $provider_id : 'openrouter';
	}

	/**
	 * Get configuration for a given provider ID.
	 *
	 * @param string $provider_id Provider ID.
	 * @return array<string, string>
	 */
	private function get_provider_config( $provider_id ) {
		$all      = $this->get_all_configs();
		$provider = Polai_Providers::get( $provider_id );
		$saved    = isset( $all[ $provider_id ] ) && is_array( $all[ $provider_id ] ) ? $all[ $provider_id ] : array();

		return array(
			'api_key'    => isset( $saved['api_key'] ) ? $saved['api_key'] : '',
			'model'      => ! empty( $saved['model'] ) ? $saved['model'] : ( $provider ? $provider['default_model'] : '' ),
			'account_id' => isset( $saved['account_id'] ) ? $saved['account_id'] : '',
			'base_url'   => isset( $saved['base_url'] ) ? $saved['base_url'] : '',
		);
	}

	/**
	 * Check whether a provider has all required configuration fields.
	 *
	 * @param array<string, mixed>  $provider Provider metadata.
	 * @param array<string, string> $config   Provider configuration.
	 * @return bool
	 */
	private function is_provider_configured( $provider, $config ) {
		if ( empty( $config['model'] ) ) {
			return false;
		}
		if ( ! empty( $provider['requires_key'] ) && empty( $config['api_key'] ) ) {
			return false;
		}
		if ( ! empty( $provider['requires_account_id'] ) && empty( $config['account_id'] ) ) {
			return false;
		}
		if ( ! empty( $provider['requires_base_url'] ) && empty( $config['base_url'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Render the settings page wrapper.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_id   = $this->get_active_provider_id();
		$providers   = Polai_Providers::get_all();
		$active_meta = $providers[ $active_id ];
		$active_cfg  = $this->get_provider_config( $active_id );
		?>
		<div class="wrap polai-settings-wrapper">
			<div class="polai-settings-container">
				<div class="polai-header">
					<div class="polai-header-icon">
						<span class="dashicons dashicons-translation"></span>
					</div>
					<div class="polai-header-title">
						<h1>Pelikan Translator</h1>
						<p><?php esc_html_e( 'Multi-Engine Polylang String Translation', 'pelikan-ai-translator-for-polylang' ); ?></p>
					</div>
					<div class="polai-version-badge">v<?php echo esc_html( POLAI_VERSION ); ?></div>
				</div>

				<div class="polai-grid">
					<div class="polai-card polai-card-form">
						<form action="options.php" method="post" id="polai_settings_form">
							<?php settings_fields( 'polai_options_group' ); ?>
							<div id="polai_configs_hidden_store"></div>

							<!-- AI Engine Selection -->
							<div class="polai-form-group">
								<label for="polai_provider" class="polai-label">
									<span class="dashicons dashicons-cloud"></span>
									<?php esc_html_e( 'AI Engine', 'pelikan-ai-translator-for-polylang' ); ?>
								</label>
								<select id="polai_provider" name="polai_provider" class="polai-input polai-select-native">
									<?php foreach ( $providers as $pid => $pmeta ) : ?>
										<option value="<?php echo esc_attr( $pid ); ?>" <?php selected( $active_id, $pid ); ?>>
											<?php echo esc_html( $pmeta['name'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="polai-help-text"><?php esc_html_e( 'Each engine keeps its own saved API key and model selection.', 'pelikan-ai-translator-for-polylang' ); ?></p>
							</div>

							<!-- Custom Base URL (Custom / Ollama) -->
							<div class="polai-form-group" id="polai_base_url_group" <?php echo empty( $active_meta['requires_base_url'] ) ? 'style="display:none;"' : ''; ?>>
								<label for="polai_base_url" class="polai-label">
									<span class="dashicons dashicons-admin-links"></span>
									<?php esc_html_e( 'Endpoint Base URL', 'pelikan-ai-translator-for-polylang' ); ?>
								</label>
								<input type="url" id="polai_base_url" value="<?php echo esc_attr( $active_cfg['base_url'] ); ?>" placeholder="http://localhost:11434/v1" class="polai-input" />
								<p class="polai-help-text"><?php esc_html_e( 'Enter an HTTPS OpenAI-compatible base URL or local Ollama URL (e.g., http://localhost:11434/v1).', 'pelikan-ai-translator-for-polylang' ); ?></p>
							</div>

							<!-- Account / Workspace ID (Cloudflare, Liara) -->
							<div class="polai-form-group" id="polai_account_id_group" <?php echo empty( $active_meta['requires_account_id'] ) ? 'style="display:none;"' : ''; ?>>
								<label for="polai_account_id" class="polai-label">
									<span class="dashicons dashicons-id"></span>
									<span id="polai_account_id_label">
										<?php echo esc_html( $active_meta['account_id_label'] ? $active_meta['account_id_label'] : __( 'Account / Workspace ID', 'pelikan-ai-translator-for-polylang' ) ); ?>
									</span>
								</label>
								<input type="text" id="polai_account_id" value="<?php echo esc_attr( $active_cfg['account_id'] ); ?>" placeholder="<?php esc_attr_e( 'Enter your Account or Project ID...', 'pelikan-ai-translator-for-polylang' ); ?>" class="polai-input" />
							</div>

							<!-- API Key Field -->
							<div class="polai-form-group" id="polai_api_key_group">
								<label for="polai_api_key" class="polai-label">
									<span class="dashicons dashicons-key"></span>
									<span id="polai_api_key_label"><?php esc_html_e( 'API Key', 'pelikan-ai-translator-for-polylang' ); ?></span>
									<span id="polai_api_key_optional" class="polai-optional-badge" <?php echo ! empty( $active_meta['requires_key'] ) ? 'style="display:none;"' : ''; ?>>
										<?php esc_html_e( 'Optional', 'pelikan-ai-translator-for-polylang' ); ?>
									</span>
								</label>
								<div class="polai-input-wrapper">
									<input type="password" id="polai_api_key" value="<?php echo esc_attr( $active_cfg['api_key'] ); ?>" placeholder="sk-..." class="polai-input" />
									<button type="button" class="polai-toggle-password" title="<?php esc_attr_e( 'Toggle Password Visibility', 'pelikan-ai-translator-for-polylang' ); ?>">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div>
								<p class="polai-help-text"><?php esc_html_e( 'Your API key is stored in WordPress options and sent only from the server.', 'pelikan-ai-translator-for-polylang' ); ?></p>
							</div>

							<!-- Model Selection Field -->
							<div class="polai-form-group">
								<label for="polai_model_input" class="polai-label">
									<span class="dashicons dashicons-admin-generic"></span>
									<?php esc_html_e( 'Translation Model', 'pelikan-ai-translator-for-polylang' ); ?>
								</label>
								<div class="polai-model-controls">
									<input type="text" id="polai_model_input" value="<?php echo esc_attr( $active_cfg['model'] ); ?>" placeholder="<?php esc_attr_e( 'Enter or select a model ID...', 'pelikan-ai-translator-for-polylang' ); ?>" class="polai-input" />
									<button type="button" id="polai_fetch_models_btn" class="polai-btn-secondary">
										<span class="dashicons dashicons-update"></span>
										<?php esc_html_e( 'Fetch Models', 'pelikan-ai-translator-for-polylang' ); ?>
									</button>
								</div>
								<div class="polai-select-wrapper">
									<div class="polai-custom-select" id="polai_model_dropdown">
										<div class="polai-select-trigger">
											<span class="polai-selected-text"><?php echo esc_html( $active_cfg['model'] ); ?></span>
											<span class="dashicons dashicons-arrow-down-alt2"></span>
										</div>
										<div class="polai-select-dropdown-panel">
											<div class="polai-select-search-box">
												<span class="dashicons dashicons-search"></span>
												<input type="text" id="polai_model_search" placeholder="<?php esc_attr_e( 'Search preset or fetched models...', 'pelikan-ai-translator-for-polylang' ); ?>" />
											</div>
											<div class="polai-select-options-list" id="polai_model_options"></div>
										</div>
									</div>
								</div>
								<p class="polai-help-text" id="polai_model_help">
									<?php esc_html_e( 'Choose a preset, click Fetch Models for live models, or type any custom model ID above.', 'pelikan-ai-translator-for-polylang' ); ?>
								</p>
							</div>

							<div class="polai-form-actions">
								<button type="submit" name="submit" id="submit" class="polai-btn-primary">
									<span class="dashicons dashicons-saved"></span>
									<?php esc_html_e( 'Save Configuration', 'pelikan-ai-translator-for-polylang' ); ?>
								</button>
							</div>
						</form>
					</div>

					<div class="polai-card polai-card-info">
						<h2>
							<span class="dashicons dashicons-info"></span>
							<?php esc_html_e( 'How to Use', 'pelikan-ai-translator-for-polylang' ); ?>
						</h2>
						<div class="polai-steps">
							<div class="polai-step">
								<div class="polai-step-num">1</div>
								<div class="polai-step-content">
									<h3><?php esc_html_e( 'Choose Engine & Model', 'pelikan-ai-translator-for-polylang' ); ?></h3>
									<p><?php esc_html_e( 'Select your AI provider, enter credentials (or a local Ollama URL), choose a model, and save.', 'pelikan-ai-translator-for-polylang' ); ?></p>
								</div>
							</div>
							<div class="polai-step">
								<div class="polai-step-num">2</div>
								<div class="polai-step-content">
									<h3><?php esc_html_e( 'Go to Polylang Strings', 'pelikan-ai-translator-for-polylang' ); ?></h3>
									<p><?php esc_html_e( 'Navigate to Languages > Translations inside your WordPress admin dashboard.', 'pelikan-ai-translator-for-polylang' ); ?></p>
								</div>
							</div>
							<div class="polai-step">
								<div class="polai-step-num">3</div>
								<div class="polai-step-content">
									<h3><?php esc_html_e( 'One-Click Translation', 'pelikan-ai-translator-for-polylang' ); ?></h3>
									<p><?php esc_html_e( 'Click the translation button next to any text field to translate automatically.', 'pelikan-ai-translator-for-polylang' ); ?></p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue the translation scripts and styles on the Polylang strings and settings pages.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'mlang_strings' !== $page && 'polai-translator' !== $page ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'polai-admin-style', plugins_url( 'assets/css/admin.css', __FILE__ ), array(), POLAI_VERSION );
		wp_enqueue_script( 'polai-admin-script', plugins_url( 'assets/js/admin.js', __FILE__ ), array(), POLAI_VERSION, true );

		$active_id   = $this->get_active_provider_id();
		$provider    = Polai_Providers::get( $active_id );
		$config      = $this->get_provider_config( $active_id );
		$all_configs = array();

		if ( 'polai-translator' === $page ) {
			foreach ( array_keys( Polai_Providers::get_all() ) as $pid ) {
				$all_configs[ $pid ] = $this->get_provider_config( $pid );
			}
		}

		$active_languages = array();
		if ( function_exists( 'pll_languages_list' ) ) {
			$polylang_names = pll_languages_list( array( 'fields' => 'name', 'hide_empty' => 0 ) );
			$polylang_slugs = pll_languages_list( array( 'fields' => 'slug', 'hide_empty' => 0 ) );
			if ( is_array( $polylang_names ) ) {
				$active_languages = array_merge( $active_languages, array_values( $polylang_names ) );
			}
			if ( is_array( $polylang_slugs ) ) {
				$active_languages = array_merge( $active_languages, array_values( $polylang_slugs ) );
			}
		}

		wp_localize_script(
			'polai-admin-script',
			'polaiTranslatorData',
			array(
				'provider'         => $active_id,
				'isConfigured'     => $this->is_provider_configured( $provider, $config ),
				'model'            => $config['model'],
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'polai_translate_nonce' ),
				'fetchModelsNonce' => wp_create_nonce( 'polai_fetch_models_nonce' ),
				'activeLanguages'  => $active_languages,
				'providersMeta'    => ( 'polai-translator' === $page ) ? Polai_Providers::get_client_meta() : array(),
				'providerConfigs'  => $all_configs,
				'i18n'             => array(
					'translateInto' => __( 'Translate into', 'pelikan-ai-translator-for-polylang' ),
					'noSource'      => __( 'No source text found.', 'pelikan-ai-translator-for-polylang' ),
					'failedEmpty'   => __( 'Translation failed or empty response.', 'pelikan-ai-translator-for-polylang' ),
					'failedConsole' => __( 'Translation failed. Check console for details.', 'pelikan-ai-translator-for-polylang' ),
					'fetching'      => __( 'Fetching...', 'pelikan-ai-translator-for-polylang' ),
					'fetchModels'   => __( 'Fetch Models', 'pelikan-ai-translator-for-polylang' ),
				),
			)
		);
	}

	/**
	 * Display admin notices if Polylang is missing or active provider is not configured.
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! function_exists( 'pll_languages_list' ) ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<?php
					printf(
						/* translators: 1: Open strong tag, 2: Close strong tag. */
						esc_html__( '%1$sPelikan AI Translator for Polylang%2$s requires the Polylang plugin to be installed and active.', 'pelikan-ai-translator-for-polylang' ),
						'<strong>',
						'</strong>'
					);
					?>
				</p>
			</div>
			<?php
			return;
		}

		$active_id = $this->get_active_provider_id();
		$provider  = Polai_Providers::get( $active_id );
		$config    = $this->get_provider_config( $active_id );

		if ( ! $this->is_provider_configured( $provider, $config ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			$screen       = get_current_screen();
			if ( 'polai-translator' === $current_page || ( $screen && 'plugins' === $screen->id ) ) {
				$settings_url = admin_url( 'admin.php?page=polai-translator' );
				?>
				<div class="notice notice-info is-dismissible">
					<p>
						<?php
						printf(
							/* translators: 1: Open anchor tag, 2: Close anchor tag. */
							esc_html__( 'Pelikan AI Translator for Polylang is active but not configured yet. Please %1$sconfigure your AI engine%2$s.', 'pelikan-ai-translator-for-polylang' ),
							'<a href="' . esc_url( $settings_url ) . '">',
							'</a>'
						);
						?>
					</p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Handle AJAX model list fetching for the settings page.
	 */
	public function ajax_fetch_models() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pelikan-ai-translator-for-polylang' ) ), 403 );
		}

		if ( ! check_ajax_referer( 'polai_fetch_models_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'pelikan-ai-translator-for-polylang' ) ), 403 );
		}

		$provider_id = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : $this->get_active_provider_id();
		$provider    = Polai_Providers::get( $provider_id );
		if ( ! $provider ) {
			wp_send_json_error( array( 'message' => __( 'Unknown AI provider.', 'pelikan-ai-translator-for-polylang' ) ), 400 );
		}

		$saved  = $this->get_provider_config( $provider_id );
		$config = array(
			'api_key'    => isset( $_POST['api_key'] ) && '' !== $_POST['api_key'] ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : $saved['api_key'],
			'account_id' => isset( $_POST['account_id'] ) && '' !== $_POST['account_id'] ? sanitize_text_field( wp_unslash( $_POST['account_id'] ) ) : $saved['account_id'],
			'base_url'   => isset( $_POST['base_url'] ) && '' !== $_POST['base_url'] ? esc_url_raw( trim( wp_unslash( $_POST['base_url'] ) ) ) : $saved['base_url'],
			'model'      => $saved['model'],
		);

		$result = Polai_Client::fetch_models( $provider, $config );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'models' => $result ) );
	}

	/**
	 * Handle AJAX translation request.
	 */
	public function ajax_translate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pelikan-ai-translator-for-polylang' ) ), 403 );
		}

		if ( ! check_ajax_referer( 'polai_translate_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'pelikan-ai-translator-for-polylang' ) ), 403 );
		}

		$source_text = isset( $_POST['source_text'] ) ? wp_unslash( $_POST['source_text'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$target_lang = isset( $_POST['target_lang'] ) ? sanitize_text_field( wp_unslash( $_POST['target_lang'] ) ) : '';

		if ( empty( $source_text ) || empty( $target_lang ) ) {
			wp_send_json_error( array( 'message' => __( 'Source text or target language is missing.', 'pelikan-ai-translator-for-polylang' ) ), 400 );
		}

		$provider_id = $this->get_active_provider_id();
		$provider    = Polai_Providers::get( $provider_id );
		$config      = $this->get_provider_config( $provider_id );

		$result = Polai_Client::translate( $provider, $config, $source_text, $target_lang );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		wp_send_json_success( array( 'translation' => $result ) );
	}
}

new Polai_Translator();
