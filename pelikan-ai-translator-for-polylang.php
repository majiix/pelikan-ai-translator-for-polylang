<?php
/**
 * Plugin Name:       Pelikan AI Translator for Polylang
 * Plugin URI:        https://wordpress.org/plugins/pelikan-ai-translator-for-polylang
 * Description:       Adds automated translation capabilities to PolyLang string translation.
 * Version:           1.0.4
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

// Define plugin version constant with the required prefix.
define( 'POLAI_VERSION', '1.0.4' );

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
					<div class="polai-version-badge">v<?php echo esc_html( POLAI_VERSION ); ?></div>
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

		// Localize script to pass PHP variables to Javascript safely.
		wp_localize_script(
			'polai-admin-script',
			'polaiTranslatorData',
			array(
				'hasApiKey'       => ! empty( $api_key ),
				'model'           => $model,
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'polai_translate_nonce' ),
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

	/**
	 * Display admin notices if Polylang is missing or API credentials are not set.
	 */
	public function admin_notices() {
		// Only show notices to users who can manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if Polylang is active.
		if ( ! function_exists( 'pll_languages_list' ) ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<?php
					printf(
						// translators: 1: Open strong tag, 2: Close strong tag.
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

		// Check if API key is set.
		$api_key = get_option( 'polai_api_key', '' );
		if ( empty( $api_key ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			$screen       = get_current_screen();
			if ( 'polai-translator' === $current_page || ( $screen && 'plugins' === $screen->id ) ) {
				?>
				<div class="notice notice-info is-dismissible">
					<p>
						<?php
						$settings_url = admin_url( 'admin.php?page=polai-translator' );
						printf(
							// translators: 1: Open anchor tag, 2: Close anchor tag.
							esc_html__( 'Pelikan AI Translator for Polylang is active but not configured yet. Please %1$sconfigure your API Key%2$s.', 'pelikan-ai-translator-for-polylang' ),
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
	 * Handle AJAX translation request.
	 */
	public function ajax_translate() {
		// Verify capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pelikan-ai-translator-for-polylang' ) ), 403 );
		}

		// Verify nonce.
		if ( ! check_ajax_referer( 'polai_translate_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'pelikan-ai-translator-for-polylang' ) ), 403 );
		}

		// Retrieve inputs.
		$source_text = isset( $_POST['source_text'] ) ? wp_unslash( $_POST['source_text'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$target_lang = isset( $_POST['target_lang'] ) ? sanitize_text_field( wp_unslash( $_POST['target_lang'] ) ) : '';

		if ( empty( $source_text ) || empty( $target_lang ) ) {
			wp_send_json_error( array( 'message' => __( 'Source text or target language is missing.', 'pelikan-ai-translator-for-polylang' ) ), 400 );
		}

		$api_key = get_option( 'polai_api_key', '' );
		$model   = get_option( 'polai_model', 'google/gemini-3-flash-preview' );

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'API Key is not configured.', 'pelikan-ai-translator-for-polylang' ) ), 400 );
		}

		// Fallback if the saved model option is somehow empty.
		if ( empty( $model ) ) {
			$model = 'google/gemini-3-flash-preview';
		}

		// Ensure site name contains only ASCII characters.
		$raw_site_name  = wp_strip_all_tags( get_bloginfo( 'name' ) );
		$safe_site_name = preg_replace( '/[^\x20-\x7E]/', '', $raw_site_name );
		if ( empty( trim( $safe_site_name ) ) ) {
			$safe_site_name = 'Polai Translator';
		}

		$prompt = 'You are a helpful translation assistant. Translate the following English phrase into ' . $target_lang . '. Respond only with the translated text and nothing else and if there are html elements inside text do not remove them and also Never use mdash in any text and use a regular hyphen or alternative wording instead: ' . $source_text;

		$body_data = wp_json_encode( array(
			'model'    => $model,
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		) );

		if ( false === $body_data ) {
			wp_send_json_error( array( 'message' => __( 'Failed to encode request data.', 'pelikan-ai-translator-for-polylang' ) ), 400 );
		}

		$response = wp_remote_post(
			'https://openrouter.ai/api/v1/chat/completions',
			array(
				'timeout'     => 30,
				'redirection' => 5,
				'blocking'    => true,
				'headers'     => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
					'HTTP-Referer'  => esc_url_raw( get_site_url() ),
					'X-Title'       => $safe_site_name,
				),
				'body'        => $body_data,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => sanitize_text_field( $response->get_error_message() ) ), 500 );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );

		// Parse JSON response.
		$data = json_decode( $body, true );

		if ( 200 !== $response_code ) {
			$error_message = '';
			if ( is_array( $data ) && isset( $data['error']['message'] ) ) {
				$error_message = sanitize_text_field( $data['error']['message'] );
			} elseif ( is_array( $data ) && isset( $data['error'] ) && is_string( $data['error'] ) ) {
				$error_message = sanitize_text_field( $data['error'] );
			} else {
				// translators: %d: HTTP status code.
				$error_message = sprintf( __( 'API request failed with status code %d.', 'pelikan-ai-translator-for-polylang' ), $response_code );
			}
			wp_send_json_error( array( 'message' => $error_message ), 500 );
		}

		// Check for error in the payload even for 200 responses.
		if ( is_array( $data ) && isset( $data['error']['message'] ) ) {
			wp_send_json_error( array( 'message' => sanitize_text_field( $data['error']['message'] ) ), 500 );
		}

		$translated_text = '';
		if ( is_array( $data ) && isset( $data['choices'][0]['message']['content'] ) ) {
			$translated_text = $data['choices'][0]['message']['content'];
		}

		if ( ! empty( $translated_text ) ) {
			// Remove surrounding quotes if they were added unnecessarily.
			$clean_text = preg_replace( '/^"|"$/', '', trim( $translated_text ) );
			wp_send_json_success( array( 'translation' => $clean_text ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Translation failed or empty response.', 'pelikan-ai-translator-for-polylang' ) ), 500 );
		}
	}
}

// Initialize the plugin.
new Polai_Translator();