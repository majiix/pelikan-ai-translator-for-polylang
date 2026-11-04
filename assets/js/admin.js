document.addEventListener( 'DOMContentLoaded', function() {
	// Guard against undefined localized data (e.g. if loaded on other pages or aggregated).
	if ( typeof polaiTranslatorData === 'undefined' ) {
		return;
	}

	// Access localized data provided by PHP utilizing the new polai prefix with safe fallbacks
	const { hasApiKey, model, ajaxUrl, nonce, activeLanguages = [], i18n = {} } = polaiTranslatorData;

	// --- Settings Page Custom Dropdown and AJAX Model Load ---
	const dropdown = document.getElementById( 'polai_model_dropdown' );
	if ( dropdown ) {
		const trigger = dropdown.querySelector( '.polai-select-trigger' );
		const selectedText = dropdown.querySelector( '.polai-selected-text' );
		const searchInput = document.getElementById( 'polai_model_search' );
		const optionsContainer = document.getElementById( 'polai_model_options' );
		const hiddenInput = document.getElementById( 'polai_model' );
		const togglePasswordBtn = document.querySelector( '.polai-toggle-password' );
		const apiKeyInput = document.getElementById( 'polai_api_key' );

		let modelsList = [];
		let currentSelection = hiddenInput.value || 'google/gemini-3-flash-preview';

		// Toggle password visibility
		if ( togglePasswordBtn && apiKeyInput ) {
			togglePasswordBtn.addEventListener( 'click', function() {
				const isPassword = apiKeyInput.type === 'password';
				apiKeyInput.type = isPassword ? 'text' : 'password';
				const icon = togglePasswordBtn.querySelector( '.dashicons' );
				if ( icon ) {
					if ( isPassword ) {
						icon.classList.remove( 'dashicons-visibility' );
						icon.classList.add( 'dashicons-hidden' );
					} else {
						icon.classList.remove( 'dashicons-hidden' );
						icon.classList.add( 'dashicons-visibility' );
					}
				}
			} );
		}

		// Toggle dropdown
		trigger.addEventListener( 'click', function( e ) {
			e.stopPropagation();
			dropdown.classList.toggle( 'open' );
			if ( dropdown.classList.contains( 'open' ) ) {
				searchInput.focus();
			}
		} );

		// Close on clicking outside
		document.addEventListener( 'click', function( e ) {
			if ( ! dropdown.contains( e.target ) ) {
				dropdown.classList.remove( 'open' );
			}
		} );

		// Local fallback list of models in case of fetch failure
		const fallbackModels = [
			{ id: 'google/gemini-3-flash-preview', name: 'Google: Gemini 3 Flash Preview' },
			{ id: 'google/gemini-3.5-flash', name: 'Google: Gemini 3.5 Flash' },
			{ id: 'meta-llama/llama-3-8b-instruct', name: 'Meta: Llama 3 8B Instruct' },
			{ id: 'openai/gpt-4o-mini', name: 'OpenAI: GPT-4o Mini' },
			{ id: 'mistralai/mistral-7b-instruct', name: 'Mistral: Mistral 7B Instruct' }
		];

		// Render options list helper
		function renderOptions( list ) {
			optionsContainer.innerHTML = '';
			if ( list.length === 0 ) {
				const noResults = document.createElement( 'div' );
				noResults.className = 'polai-select-no-results';
				noResults.textContent = 'No matching models found';
				optionsContainer.appendChild( noResults );
				return;
			}

			list.forEach( function( m ) {
				const isSelected = m.id === currentSelection;

				const option = document.createElement( 'div' );
				option.className = 'polai-select-option' + ( isSelected ? ' selected' : '' );
				option.setAttribute( 'data-value', m.id );

				const nameSpan = document.createElement( 'span' );
				nameSpan.className = 'polai-option-name';
				nameSpan.textContent = m.name || m.id;

				const idSpan = document.createElement( 'span' );
				idSpan.className = 'polai-option-id';
				idSpan.textContent = m.id;

				option.appendChild( nameSpan );
				option.appendChild( idSpan );

				if ( isSelected ) {
					selectedText.textContent = m.name || m.id;
				}

				option.addEventListener( 'click', function( e ) {
					e.stopPropagation();
					currentSelection = m.id;
					hiddenInput.value = m.id;
					selectedText.textContent = m.name || m.id;

					// Update option classes
					dropdown.querySelectorAll( '.polai-select-option' ).forEach( function( opt ) {
						opt.classList.remove( 'selected' );
					} );
					option.classList.add( 'selected' );
					dropdown.classList.remove( 'open' );
				} );

				optionsContainer.appendChild( option );
			} );

			// If current selection is not represented in the list (e.g. initial load with custom saved model),
			// update the trigger text manually to show the value.
			const foundSelected = list.find( function( m ) {
				return m.id === currentSelection;
			} );
			if ( ! foundSelected ) {
				selectedText.textContent = currentSelection;
			}
		}

		// Fetch models via AJAX
		async function fetchModels() {
			try {
				const response = await fetch( 'https://openrouter.ai/api/v1/models' );
				if ( ! response.ok ) {
					throw new Error( 'Network error fetching models' );
				}
				const data = await response.json();
				if ( data && Array.isArray( data.data ) ) {
					modelsList = data.data.map( function( m ) {
						return { id: m.id, name: m.name };
					} );
				} else {
					throw new Error( 'Invalid data format' );
				}
			} catch ( err ) {
				console.warn( 'Pelikan Translator: Failed to load models via API. Using local fallbacks.', err );
				modelsList = fallbackModels;
			} finally {
				renderOptions( modelsList );
			}
		}

		// Initial load
		fetchModels();

		// Search filter
		searchInput.addEventListener( 'input', function() {
			const query = searchInput.value.toLowerCase().trim();
			const filtered = modelsList.filter( function( m ) {
				return m.id.toLowerCase().includes( query ) || m.name.toLowerCase().includes( query );
			} );
			renderOptions( filtered );
		} );
	}

	// --- Polylang Strings Translation Engine ---
	const translationCells = document.querySelectorAll( '.column-translations' );

	/**
	 * Extract source string text from a table row.
	 *
	 * Polylang uses WP_List_Table where the string column is the primary column,
	 * rendered as a `<th class="string column-string...">` or `<td class="column-string">`.
	 * Extraneous elements like toggle buttons, row actions, and screen reader text are stripped.
	 *
	 * @param {HTMLTableRowElement} row
	 * @returns {string}
	 */
	function getRowSourceText( row ) {
		if ( ! row ) {
			return '';
		}

		const sourceCell = row.querySelector( '.column-string, th.string, td.string' );
		if ( sourceCell ) {
			const cellClone = sourceCell.cloneNode( true );
			const unwanted = cellClone.querySelectorAll( '.toggle-row, .row-actions, .screen-reader-text, button' );
			unwanted.forEach( function( el ) {
				el.remove();
			} );

			const text = cellClone.textContent.trim();
			if ( text ) {
				return text;
			}
		}

		// Fallback to accessibility label in checkbox column: "Select <String>"
		const cbLabel = row.querySelector( 'td.check-column label.screen-reader-text' );
		if ( cbLabel && cbLabel.textContent ) {
			const cbText = cbLabel.textContent.trim();
			const prefix = 'Select ';
			if ( cbText.startsWith( prefix ) ) {
				return cbText.substring( prefix.length ).trim();
			}
		}

		return '';
	}

	if ( hasApiKey && model && translationCells.length > 0 ) {
		// Normalize active languages to lowercase for robust matching
		const validLanguages = ( activeLanguages || [] ).map( function( lang ) {
			return lang.toLowerCase();
		});

		translationCells.forEach( function( cell ) {
			const translations = cell.querySelectorAll( '.translation' );

			translations.forEach( function( translationDiv ) {
				const label = translationDiv.querySelector( 'label' );
				const input = translationDiv.querySelector( 'textarea, input[type="text"]' );

				if ( ! label || ! input ) {
					return;
				}

				// Clone label to safely get language text without any inserted button text
				const labelClone = label.cloneNode( true );
				const existingBtn = labelClone.querySelector( '.polai-icon-button' );
				if ( existingBtn ) {
					existingBtn.remove();
				}
				const langText = labelClone.textContent.trim();
				const langKey = langText.toLowerCase();

				// Extract language slug if available from name (e.g. translation[ar][...]) or id (e.g. ar-...)
				let langSlug = '';
				const nameMatch = input.name && input.name.match( /translation\[([^\]]+)\]/ );
				if ( nameMatch ) {
					langSlug = nameMatch[1].toLowerCase();
				} else if ( input.id ) {
					const idParts = input.id.split( '-' );
					if ( idParts.length > 1 ) {
						langSlug = idParts[0].toLowerCase();
					}
				}

				// Dynamic check against Polylang's active languages if configured
				if ( validLanguages.length > 0 ) {
					const matchesName = validLanguages.includes( langKey );
					const matchesSlug = langSlug && validLanguages.includes( langSlug );
					if ( ! matchesName && ! matchesSlug ) {
						return;
					}
				}

				// Prevent duplicate buttons using the new CSS class prefix
				if ( label.querySelector( '.polai-icon-button' ) ) {
					return;
				}

				const iconWrapper = document.createElement( 'span' );
				iconWrapper.className = 'polai-icon-button';
				iconWrapper.title = ( i18n.translateInto || 'Translate into' ) + ' ' + langText;

				const icon = document.createElement( 'span' );
				icon.className = 'dashicons dashicons-translation';
				iconWrapper.appendChild( icon );

				label.appendChild( iconWrapper );

				iconWrapper.addEventListener( 'click', async function( e ) {
					e.preventDefault();
					e.stopPropagation();

					const row = cell.closest( 'tr' );
					if ( ! row ) {
						return;
					}

					const sourceText = getRowSourceText( row );

					if ( ! sourceText ) {
						alert( i18n.noSource || 'No source text found.' );
						return;
					}

					iconWrapper.classList.add( 'loading' );

					try {
						// Create form data payload for WordPress AJAX.
						const formData = new URLSearchParams();
						formData.append( 'action', 'polai_translate' );
						formData.append( 'nonce', nonce );
						formData.append( 'source_text', sourceText );
						formData.append( 'target_lang', langText );

						const response = await fetch( ajaxUrl, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded'
							},
							body: formData.toString()
						} );

						if ( ! response.ok ) {
							const errText = await response.text();
							throw new Error( 'Network response was not ok: ' + errText );
						}

						const data = await response.json();

						if ( data.success && data.data && data.data.translation ) {
							input.value = data.data.translation;
							input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
							input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
						} else {
							const errorMsg = data.data && data.data.message ? data.data.message : ( i18n.failedEmpty || 'Translation failed or empty response.' );
							alert( errorMsg );
						}
					} catch ( err ) {
						console.error( 'API error:', err );
						alert( i18n.failedConsole || 'Translation failed. Check console for details.' );
					} finally {
						iconWrapper.classList.remove( 'loading' );
					}
				} );
			} );
		} );
	} else if ( ! hasApiKey || ! model ) {
		console.warn( 'Pelikan Translator: API Key or Model is empty. Translation features are disabled. Please configure them in the settings.' );
	}
} );