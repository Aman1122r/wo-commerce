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

	function revealMetaPanel() {
		if ( authPanel ) {
			authPanel.classList.add( 'is-hidden' );
			authPanel.setAttribute( 'aria-hidden', 'true' );
		}

		if ( metaPanel ) {
			metaPanel.classList.add( 'is-visible' );
			metaPanel.setAttribute( 'aria-hidden', 'false' );

			const iframeWrap = metaPanel.querySelector( '.ominiflow-onboarding__iframe-wrap' );
			const iframe = document.getElementById( 'facebook-commerce-iframe-enhanced' );

			if ( iframeWrap ) {
				iframeWrap.style.display = 'block';
			}

			if ( iframe ) {
				iframe.style.display = 'block';
			}
		}
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

				revealMetaPanel();
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
}( jQuery ) );
