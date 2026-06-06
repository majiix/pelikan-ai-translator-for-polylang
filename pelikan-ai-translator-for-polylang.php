<?php
/**
 * Plugin Name:       Pelikan AI Translator for Polylang
 * Plugin URI:        https://wordpress.org/plugins/pelikan-ai-translator-for-polylang
 * Description:       Adds automated translation capabilities to PolyLang string translation.
 * Version:           1.0.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            micromax
 * Text Domain:       pelikan-ai-translator-for-polylang
 * Domain Path:       /languages
 * License:           GPL-2.0+
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin version constant with the required prefix.
define( 'POLAI_VERSION', '1.0.1' );

class Polai_Translator {

	/**
	 * Constructor to initialize hooks.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		// Priority 99 ensures Polylang's 'mlang' menu exists before we add our submenu.
		add_action( 'admin_menu', array( $this, 'add_settings_page' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
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
	 * Render the settings page wrapper.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap polai-settings-wrapper">
			<div class="polai-settings-container">
				
				<!-- Header -->
				<div class="polai-header">
					<div class="polai-header-icon">
						<span class="dashicons dashicons-translation"></span>
					</div>
					<div class="polai-header-title">
						<h1>Pelikan Translator</h1>
						<p><?php esc_html_e( 'Premium Polylang String Translation', 'pelikan-ai-translator-for-polylang' ); ?></p>
					</div>
					<div class="polai-version-badge">v<?php echo POLAI_VERSION; ?></div>
				</div>

				<div class="polai-grid">
					<!-- Form Card -->
					<div class="polai-card polai-card-form">
						<form action="options.php" method="post">
							<?php settings_fields( 'polai_options_group' ); ?>
							
							<!-- API Key Field -->
							<div class="polai-form-group">
								<label for="polai_api_key" class="polai-label">
									<span class="dashicons dashicons-key"></span>
									<?php esc_html_e( 'OpenRouter API Key', 'pelikan-ai-translator-for-polylang' ); ?>
								</label>
								<div class="polai-input-wrapper">
									<input type="password" id="polai_api_key" name="polai_api_key" value="<?php echo esc_attr( get_option( 'polai_api_key', '' ) ); ?>" placeholder="sk-or-v1-..." class="polai-input" />
									<button type="button" class="polai-toggle-password" title="<?php esc_attr_e( 'Toggle Password Visibility', 'pelikan-ai-translator-for-polylang' ); ?>">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div>
								<p class="polai-help-text"><?php esc_html_e( 'Enter your OpenRouter API Key. Your credentials are securely processed.', 'pelikan-ai-translator-for-polylang' ); ?></p>
							</div>

							<!-- Model Selection Field -->
							<div class="polai-form-group">
								<label for="polai_model_select" class="polai-label">
									<span class="dashicons dashicons-admin-generic"></span>
									<?php esc_html_e( 'Translation Model', 'pelikan-ai-translator-for-polylang' ); ?>
								</label>
								<div class="polai-select-wrapper">
									<!-- Hidden input to store the actual model ID value which WP saves -->
									<input type="hidden" id="polai_model" name="polai_model" value="<?php echo esc_attr( get_option( 'polai_model', 'google/gemini-3-flash-preview' ) ); ?>" />
									
									<!-- Searchable Custom Dropdown -->
									<div class="polai-custom-select" id="polai_model_dropdown">
										<div class="polai-select-trigger">
											<span class="polai-selected-text"><?php echo esc_html( get_option( 'polai_model', 'google/gemini-3-flash-preview' ) ); ?></span>
											<span class="dashicons dashicons-arrow-down-alt2"></span>
										</div>
										<div class="polai-select-dropdown-panel">
											<div class="polai-select-search-box">
												<span class="dashicons dashicons-search"></span>
												<input type="text" id="polai_model_search" placeholder="<?php esc_attr_e( 'Search models...', 'pelikan-ai-translator-for-polylang' ); ?>" />
											</div>
											<div class="polai-select-options-list" id="polai_model_options">
												<!-- Skeletons go here -->
												<div class="polai-skeleton-item"></div>
												<div class="polai-skeleton-item"></div>
												<div class="polai-skeleton-item"></div>
											</div>
										</div>
									</div>
								</div>
								<p class="polai-help-text">
									<?php
									// translators: 1: Link tag, 2: Closing link tag.
									$desc = __( 'Select your translation model. You can view available models %1$shere%2$s.', 'pelikan-ai-translator-for-polylang' );
									printf(
										wp_kses_post( $desc ),
										'<a href="https://openrouter.ai/models" target="_blank" rel="noopener noreferrer">',
										'</a>'
									);
									?>
								</p>
							</div>

							<!-- Submit Button -->
							<div class="polai-form-actions">
								<button type="submit" name="submit" id="submit" class="polai-btn-primary">
									<span class="dashicons dashicons-saved"></span>
									<?php esc_html_e( 'Save Configuration', 'pelikan-ai-translator-for-polylang' ); ?>
								</button>
							</div>
						</form>
					</div>

					<!-- How to Use Card -->
					<div class="polai-card polai-card-info">
						<h2>
							<span class="dashicons dashicons-info"></span>
							<?php esc_html_e( 'How to Use', 'pelikan-ai-translator-for-polylang' ); ?>
						</h2>
						<div class="polai-steps">
							<div class="polai-step">
								<div class="polai-step-num">1</div>
								<div class="polai-step-content">
									<h3><?php esc_html_e( 'Setup Key & Model', 'pelikan-ai-translator-for-polylang' ); ?></h3>
									<p><?php esc_html_e( 'Enter your OpenRouter key, choose your preferred translation model, and save changes.', 'pelikan-ai-translator-for-polylang' ); ?></p>
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
									<p><?php esc_html_e( 'Click the new translation button next to any text field to automatically translate from English.', 'pelikan-ai-translator-for-polylang' ); ?></p>
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
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting( 'polai_options_group', 'polai_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		register_setting( 'polai_options_group', 'polai_model', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'google/gemini-3-flash-preview',
		) );

		add_settings_section(
			'polai_section',
			esc_html__( 'General Configuration', 'pelikan-ai-translator-for-polylang' ),
			'__return_empty_string',
			'polai-translator'
		);

		add_settings_field(
			'polai_api_key',
			esc_html__( 'API Key', 'pelikan-ai-translator-for-polylang' ),
			array( $this, 'render_api_key_field' ),
			'polai-translator',
			'polai_section'
		);

		add_settings_field(
			'polai_model',
			esc_html__( 'Model', 'pelikan-ai-translator-for-polylang' ),
			array( $this, 'render_model_field' ),
			'polai-translator',
			'polai_section'
		);
	}

	/**
	 * Render the API key input field.
	 */
	public function render_api_key_field() {
		$value = get_option( 'polai_api_key', '' );
		?>
		<input type="password" id="polai_api_key" name="polai_api_key" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<?php
	}

	/**
	 * Render the model selection as a Text Input.
	 */
	public function render_model_field() {
		$value = get_option( 'polai_model', 'google/gemini-3-flash-preview' );
		?>
		<input type="text" id="polai_model" name="polai_model" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<p class="description">
			<?php
			// translators: 1: HTML formatted example model ID, 2: Opening link tag, 3: Closing link tag.
			$description_text = __( 'Enter the model ID (e.g., %1$s). You can find available models %2$shere%3$s.', 'pelikan-ai-translator-for-polylang' );
			printf(
				wp_kses_post( $description_text ),
				'<code>google/gemini-3-flash-preview</code>',
				'<a href="https://openrouter.ai/models" target="_blank" rel="noopener noreferrer">',
				'</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Enqueue the translation scripts and styles on the PolyLang strings page.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		// Only run on the specific PolyLang strings page and settings page.
		if ( 'mlang_strings' !== $page && 'polai-translator' !== $page ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );

		// Enqueue the newly extracted CSS file.
		wp_enqueue_style(
			'polai-admin-style',
			plugins_url( 'assets/css/admin.css', __FILE__ ),
			array(),
			POLAI_VERSION
		);

		// Enqueue the external JS file.
		wp_enqueue_script(
			'polai-admin-script',
			plugins_url( 'assets/js/admin.js', __FILE__ ),
			array(),
			POLAI_VERSION,
			true
		);

		$api_key = get_option( 'polai_api_key', '' );
		$model   = get_option( 'polai_model', 'google/gemini-3-flash-preview' );

		// Fetch active language names dynamically from Polylang.
		$active_languages = array();
		if ( function_exists( 'pll_languages_list' ) ) {
			$polylang_names = pll_languages_list( array( 'fields' => 'name', 'hide_empty' => 0 ) );
			if ( is_array( $polylang_names ) ) {
				$active_languages = array_values( $polylang_names );
			}
		}

		// Ensure site name contains only ASCII characters to prevent JS fetch header errors.
		$raw_site_name  = wp_strip_all_tags( get_bloginfo( 'name' ) );
		$safe_site_name = preg_replace( '/[^\x20-\x7E]/', '', $raw_site_name );

		// Fallback if the entire site name was non-ASCII.
		if ( empty( trim( $safe_site_name ) ) ) {
			$safe_site_name = 'Polai Translator';
		}

		// Localize script to pass PHP variables to Javascript safely.
		wp_localize_script(
			'polai-admin-script',
			'polaiTranslatorData',
			array(
				'apiKey'          => $api_key,
				'model'           => $model,
				'endpoint'        => 'https://openrouter.ai/api/v1/chat/completions',
				'siteUrl'         => esc_url_raw( get_site_url() ),
				'siteName'        => $safe_site_name,
				'activeLanguages' => $active_languages,
				'i18n'            => array(
					'translateInto' => __( 'Translate into', 'pelikan-ai-translator-for-polylang' ),
					'noSource'      => __( 'No source text found.', 'pelikan-ai-translator-for-polylang' ),
					'failedEmpty'   => __( 'Translation failed or empty response.', 'pelikan-ai-translator-for-polylang' ),
					'failedConsole' => __( 'Translation failed. Check console for details.', 'pelikan-ai-translator-for-polylang' ),
				)
			)
		);
	}
}

// Initialize the plugin.
new Polai_Translator();