document.addEventListener( 'DOMContentLoaded', function() {
	if ( typeof polaiTranslatorData === 'undefined' ) {
		return;
	}

	const {
		provider: initialProvider = 'openrouter',
		isConfigured = false,
		model = '',
		ajaxUrl,
		nonce,
		fetchModelsNonce = '',
		activeLanguages = [],
		providersMeta = {},
		providerConfigs = {},
		i18n = {}
	} = polaiTranslatorData;

	// --- Settings Page Multi-Engine & Model Selector ---
	const settingsForm = document.getElementById( 'polai_settings_form' );
	if ( settingsForm ) {
		const providerSelect = document.getElementById( 'polai_provider' );
		const baseUrlGroup = document.getElementById( 'polai_base_url_group' );
		const baseUrlInput = document.getElementById( 'polai_base_url' );
		const accountIdGroup = document.getElementById( 'polai_account_id_group' );
		const accountIdLabel = document.getElementById( 'polai_account_id_label' );
		const accountIdInput = document.getElementById( 'polai_account_id' );
		const apiKeyInput = document.getElementById( 'polai_api_key' );
		const apiKeyOptional = document.getElementById( 'polai_api_key_optional' );
		const togglePasswordBtn = document.querySelector( '.polai-toggle-password' );
		const modelInput = document.getElementById( 'polai_model_input' );
		const fetchModelsBtn = document.getElementById( 'polai_fetch_models_btn' );
		const dropdown = document.getElementById( 'polai_model_dropdown' );
		const trigger = dropdown.querySelector( '.polai-select-trigger' );
		const selectedText = dropdown.querySelector( '.polai-selected-text' );
		const searchInput = document.getElementById( 'polai_model_search' );
		const optionsContainer = document.getElementById( 'polai_model_options' );
		const hiddenStore = document.getElementById( 'polai_configs_hidden_store' );

		const configsState = JSON.parse( JSON.stringify( providerConfigs || {} ) );
		let currentProvider = providerSelect.value || initialProvider;
		let modelsList = [];

		function saveActiveFieldsToState() {
			configsState[ currentProvider ] = {
				api_key: apiKeyInput.value,
				model: modelInput.value.trim(),
				account_id: accountIdInput.value.trim(),
				base_url: baseUrlInput.value.trim()
			};
		}

		function renderOptions( list ) {
			optionsContainer.innerHTML = '';
			const currentModel = modelInput.value.trim();

			if ( list.length === 0 ) {
				const noResults = document.createElement( 'div' );
				noResults.className = 'polai-select-no-results';
				noResults.textContent = 'No matching models found';
				optionsContainer.appendChild( noResults );
				return;
			}

			list.forEach( function( m ) {
				const isSelected = m.id === currentModel;
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
					modelInput.value = m.id;
					selectedText.textContent = m.name || m.id;
					dropdown.querySelectorAll( '.polai-select-option' ).forEach( function( opt ) {
						opt.classList.remove( 'selected' );
					} );
					option.classList.add( 'selected' );
					dropdown.classList.remove( 'open' );
				} );

				optionsContainer.appendChild( option );
			} );

			const found = list.find( function( m ) {
				return m.id === currentModel;
			} );
			if ( ! found ) {
				selectedText.textContent = currentModel || 'Select a preset model...';
			}
		}

		async function fetchLiveModels( showErrorAlert ) {
			const meta = providersMeta[ currentProvider ];
			if ( ! meta || ! meta.can_fetch_models ) {
				return;
			}

			fetchModelsBtn.disabled = true;
			fetchModelsBtn.classList.add( 'loading' );

			try {
				const formData = new URLSearchParams();
				formData.append( 'action', 'polai_fetch_models' );
				formData.append( 'nonce', fetchModelsNonce );
				formData.append( 'provider', currentProvider );
				formData.append( 'api_key', apiKeyInput.value );
				formData.append( 'account_id', accountIdInput.value.trim() );
				formData.append( 'base_url', baseUrlInput.value.trim() );

				const response = await fetch( ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: formData.toString()
				} );
				const data = await response.json();

				if ( data.success && data.data && Array.isArray( data.data.models ) && data.data.models.length > 0 ) {
					modelsList = data.data.models;
					renderOptions( modelsList );
				} else if ( showErrorAlert ) {
					const msg = data.data && data.data.message ? data.data.message : 'Could not fetch models.';
					alert( msg );
				}
			} catch ( err ) {
				if ( showErrorAlert ) {
					alert( 'Failed to fetch models. Check console for details.' );
				}
				console.warn( 'Pelikan Translator: Model fetch failed, using presets.', err );
			} finally {
				fetchModelsBtn.disabled = false;
				fetchModelsBtn.classList.remove( 'loading' );
			}
		}

		function applyProviderToForm( pid ) {
			currentProvider = pid;
			const meta = providersMeta[ pid ] || {};
			const cfg = configsState[ pid ] || {};

			apiKeyInput.value = cfg.api_key || '';
			accountIdInput.value = cfg.account_id || '';
			baseUrlInput.value = cfg.base_url || '';
			modelInput.value = cfg.model || meta.default_model || '';

			baseUrlGroup.style.display = meta.requires_base_url ? '' : 'none';
			accountIdGroup.style.display = meta.requires_account_id ? '' : 'none';
			if ( meta.account_id_label ) {
				accountIdLabel.textContent = meta.account_id_label;
			}
			apiKeyOptional.style.display = meta.requires_key ? 'none' : '';
			fetchModelsBtn.style.display = meta.can_fetch_models ? '' : 'none';

			modelsList = Array.isArray( meta.preset_models ) ? meta.preset_models.slice() : [];
			searchInput.value = '';
			renderOptions( modelsList );

			if ( meta.public_models ) {
				fetchLiveModels( false );
			}
		}

		if ( togglePasswordBtn && apiKeyInput ) {
			togglePasswordBtn.addEventListener( 'click', function() {
				const isPassword = apiKeyInput.type === 'password';
				apiKeyInput.type = isPassword ? 'text' : 'password';
				const icon = togglePasswordBtn.querySelector( '.dashicons' );
				if ( icon ) {
					icon.classList.toggle( 'dashicons-visibility', ! isPassword );
					icon.classList.toggle( 'dashicons-hidden', isPassword );
				}
			} );
		}

		providerSelect.addEventListener( 'change', function() {
			saveActiveFieldsToState();
			applyProviderToForm( providerSelect.value );
		} );

		modelInput.addEventListener( 'input', function() {
			selectedText.textContent = modelInput.value.trim() || 'Select a preset model...';
		} );

		fetchModelsBtn.addEventListener( 'click', function() {
			fetchLiveModels( true );
		} );

		trigger.addEventListener( 'click', function( e ) {
			e.stopPropagation();
			dropdown.classList.toggle( 'open' );
			if ( dropdown.classList.contains( 'open' ) ) {
				searchInput.focus();
			}
		} );

		document.addEventListener( 'click', function( e ) {
			if ( ! dropdown.contains( e.target ) ) {
				dropdown.classList.remove( 'open' );
			}
		} );

		searchInput.addEventListener( 'input', function() {
			const query = searchInput.value.toLowerCase().trim();
			const filtered = modelsList.filter( function( m ) {
				return m.id.toLowerCase().includes( query ) || ( m.name && m.name.toLowerCase().includes( query ) );
			} );
			renderOptions( filtered );
		} );

		settingsForm.addEventListener( 'submit', function() {
			saveActiveFieldsToState();
			hiddenStore.innerHTML = '';

			Object.keys( configsState ).forEach( function( pid ) {
				const cfg = configsState[ pid ] || {};
				[ 'api_key', 'model', 'account_id', 'base_url' ].forEach( function( field ) {
					const input = document.createElement( 'input' );
					input.type = 'hidden';
					input.name = 'polai_provider_configs[' + pid + '][' + field + ']';
					input.value = cfg[ field ] || '';
					hiddenStore.appendChild( input );
				} );
			} );
		} );

		// Initialize view on load.
		applyProviderToForm( currentProvider );
	}

	// --- Polylang Strings Translation Engine ---
	const translationCells = document.querySelectorAll( '.column-translations' );

	/**
	 * Extract source string text from a table row.
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

	if ( isConfigured && model && translationCells.length > 0 ) {
		const validLanguages = ( activeLanguages || [] ).map( function( lang ) {
			return lang.toLowerCase();
		} );

		translationCells.forEach( function( cell ) {
			const translations = cell.querySelectorAll( '.translation' );

			translations.forEach( function( translationDiv ) {
				const label = translationDiv.querySelector( 'label' );
				const input = translationDiv.querySelector( 'textarea, input[type="text"]' );

				if ( ! label || ! input ) {
					return;
				}

				const labelClone = label.cloneNode( true );
				const existingBtn = labelClone.querySelector( '.polai-icon-button' );
				if ( existingBtn ) {
					existingBtn.remove();
				}
				const langText = labelClone.textContent.trim();
				const langKey = langText.toLowerCase();

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

				if ( validLanguages.length > 0 ) {
					const matchesName = validLanguages.includes( langKey );
					const matchesSlug = langSlug && validLanguages.includes( langSlug );
					if ( ! matchesName && ! matchesSlug ) {
						return;
					}
				}

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
						const formData = new URLSearchParams();
						formData.append( 'action', 'polai_translate' );
						formData.append( 'nonce', nonce );
						formData.append( 'source_text', sourceText );
						formData.append( 'target_lang', langText );

						const response = await fetch( ajaxUrl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
	} else if ( ! isConfigured || ! model ) {
		console.warn( 'Pelikan Translator: Active AI engine is not configured. Translation features are disabled.' );
	}
} );
