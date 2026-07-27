( function ( $ ) {
	'use strict';

	if ( typeof wc_ominiflow_onboarding === 'undefined' ) {
		return;
	}

	const config = wc_ominiflow_onboarding;
	const root = document.getElementById( 'ominiflow-onboarding-root' );

	if ( ! root ) {
		return;
	}

	const authPanel = document.getElementById( 'ominiflow-auth-panel' );
	const metaPanel = document.getElementById( 'ominiflow-meta-panel' );
	const signupForm = document.getElementById( 'ominiflow-signup-form' );
	const loginForm = document.getElementById( 'ominiflow-login-form' );
	const connectPanel = document.getElementById( 'ominiflow-connect-ominiflow-panel' );
	const connectMessage = document.getElementById( 'ominiflow-connect-ominiflow-message' );
	const connectLink = document.getElementById( 'ominiflow-connect-ominiflow-link' );
	const context = config.context || root.getAttribute( 'data-ominiflow-context' ) || 'meta';

	function switchView( view ) {
		root.querySelectorAll( '[data-ominiflow-view]' ).forEach( function ( tab ) {
			const isActive = tab.getAttribute( 'data-ominiflow-view' ) === view;
			tab.classList.toggle( 'is-active', isActive );
			tab.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
		} );

		root.querySelectorAll( '[data-ominiflow-panel]' ).forEach( function ( panel ) {
			const isActive = panel.getAttribute( 'data-ominiflow-panel' ) === view;
			panel.classList.toggle( 'is-active', isActive );
			panel.hidden = ! isActive;
		} );
	}

	function showError( elementId, message ) {
		const errorEl = document.getElementById( elementId );
		if ( ! errorEl ) {
			return;
		}

		errorEl.textContent = message;
		errorEl.hidden = ! message;
	}

	function hideConnectPanel() {
		if ( connectPanel ) {
			connectPanel.hidden = true;
		}
	}

	function showConnectPanel( message, url ) {
		revealMetaPanel();

		if ( connectMessage ) {
			connectMessage.textContent = message || config.i18n.connect_on_ominiflow;
		}

		if ( connectLink ) {
			connectLink.href = url || config.connect_meta_url || '#';
		}

		if ( connectPanel ) {
			connectPanel.hidden = false;
		}

		const iframeWrap = metaPanel ? metaPanel.querySelector( '.ominiflow-onboarding__iframe-wrap' ) : null;
		if ( iframeWrap ) {
			iframeWrap.style.display = 'none';
		}
	}

	function revealMetaPanel() {
		if ( authPanel ) {
			authPanel.classList.add( 'is-hidden' );
			authPanel.setAttribute( 'aria-hidden', 'true' );
		}

		if ( metaPanel ) {
			metaPanel.classList.add( 'is-visible' );
			metaPanel.setAttribute( 'aria-hidden', 'false' );

			const iframeWrap = metaPanel.querySelector( '.ominiflow-onboarding__iframe-wrap' );
			const iframe = metaPanel.querySelector( 'iframe' );

			if ( iframeWrap ) {
				iframeWrap.style.display = 'block';
			}

			if ( iframe ) {
				iframe.style.display = 'block';
			}
		}
	}

	function isCompleteForContext( syncData ) {
		if ( context === 'whatsapp' ) {
			return !! syncData.whatsapp_complete;
		}

		return !! syncData.meta_complete;
	}

	function syncCredentials() {
		if ( ! config.credential_sync_enabled || ! config.sync_action ) {
			return Promise.resolve( { use_meta_iframe: true } );
		}

		const formData = new FormData();
		formData.append( 'action', config.sync_action );
		formData.append( 'nonce', config.nonce );

		return fetch( config.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( function ( response ) {
				return response.json().then( function ( body ) {
					return {
						ok: response.ok,
						body: body,
					};
				} );
			} )
			.then( function ( result ) {
				if ( ! result.ok || ! result.body.success ) {
					const message =
						( result.body && result.body.data && result.body.data.message ) ||
						config.i18n.generic_error;
					throw new Error( message );
				}

				return result.body.data || {};
			} );
	}

	function handlePostAuthSuccess( submitButton, loadingText ) {
		if ( submitButton ) {
			submitButton.disabled = true;
			submitButton.textContent = config.i18n.syncing_credentials || loadingText;
		}

		return syncCredentials()
			.then( function ( syncData ) {
				if ( syncData.use_meta_iframe ) {
					hideConnectPanel();
					revealMetaPanel();
					return;
				}

				if ( isCompleteForContext( syncData ) || syncData.whatsapp_connected || syncData.meta_connected ) {
					window.location.reload();
					return;
				}

				if ( syncData.meta_connected === false && syncData.whatsapp_connected === false ) {
					showConnectPanel( syncData.message, syncData.connect_url );
					return;
				}

				hideConnectPanel();
				showConnectPanel(
					config.i18n.connect_on_ominiflow,
					config.connect_meta_url
				);
			} )
			.catch( function ( error ) {
				showError(
					context === 'whatsapp' ? 'ominiflow-login-error' : 'ominiflow-login-error',
					error.message || config.i18n.generic_error
				);
			} )
			.finally( function () {
				if ( submitButton ) {
					submitButton.disabled = false;
					submitButton.textContent = submitButton.dataset.originalText;
				}
			} );
	}

	function postAuth( action, formData, errorElementId, loadingText, submitButton ) {
		if ( ! config.auth_configured ) {
			showError( errorElementId, config.i18n.auth_not_configured );
			return Promise.resolve();
		}

		if ( submitButton ) {
			submitButton.disabled = true;
			submitButton.dataset.originalText = submitButton.textContent;
			submitButton.textContent = loadingText;
		}

		showError( errorElementId, '' );

		formData.append( 'action', action );
		formData.append( 'nonce', config.nonce );

		return fetch( config.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( function ( response ) {
				return response.json().then( function ( body ) {
					return {
						ok: response.ok,
						body: body,
					};
				} );
			} )
			.then( function ( result ) {
				if ( ! result.ok || ! result.body.success ) {
					const message =
						( result.body && result.body.data && result.body.data.message ) ||
						config.i18n.generic_error;
					throw new Error( message );
				}

				return handlePostAuthSuccess( submitButton, loadingText );
			} )
			.catch( function ( error ) {
				showError( errorElementId, error.message || config.i18n.generic_error );
			} )
			.finally( function () {
				if ( submitButton ) {
					submitButton.disabled = false;
					submitButton.textContent = submitButton.dataset.originalText;
				}
			} );
	}

	root.querySelectorAll( '[data-ominiflow-view], [data-ominiflow-switch]' ).forEach( function ( control ) {
		control.addEventListener( 'click', function () {
			const view = control.getAttribute( 'data-ominiflow-view' ) || control.getAttribute( 'data-ominiflow-switch' );
			if ( view ) {
				switchView( view );
			}
		} );
	} );

	if ( signupForm ) {
		signupForm.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			const formData = new FormData( signupForm );
			const submitButton = signupForm.querySelector( 'button[type="submit"]' );
			formData.set( 'terms_accepted', signupForm.querySelector( '#ominiflow-signup-terms' ).checked ? '1' : '0' );

			postAuth( config.signup_action, formData, 'ominiflow-signup-error', config.i18n.creating_account, submitButton );
		} );
	}

	if ( loginForm ) {
		loginForm.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			const formData = new FormData( loginForm );
			const submitButton = loginForm.querySelector( 'button[type="submit"]' );
			formData.set( 'remember_me', loginForm.querySelector( '#ominiflow-login-remember' ).checked ? '1' : '0' );

			postAuth( config.login_action, formData, 'ominiflow-login-error', config.i18n.signing_in, submitButton );
		} );
	}

	if ( config.credential_sync_enabled && config.sync_action && metaPanel ) {
		const connectPanelVisible = connectPanel && ! connectPanel.hidden;
		const iframeWrap = metaPanel.querySelector( '.ominiflow-onboarding__iframe-wrap' );
		const iframeVisible = iframeWrap && iframeWrap.style.display !== 'none';

		if ( ! connectPanelVisible && iframeVisible ) {
			syncCredentials()
				.then( function ( syncData ) {
					if ( syncData.use_meta_iframe ) {
						return;
					}

					if ( isCompleteForContext( syncData ) || syncData.whatsapp_connected || syncData.meta_connected ) {
						window.location.reload();
						return;
					}

					if ( syncData.meta_connected === false && syncData.whatsapp_connected === false ) {
						showConnectPanel( syncData.message, syncData.connect_url );
					}
				} )
				.catch( function () {
					// Keep existing UI; server-side render may already show connect notice.
				} );
		}
	}
}( jQuery ) );
