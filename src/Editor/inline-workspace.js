const INLINE_WORKSPACE_HOST_CLASS = 'ume-inline-workspace-host';
const INLINE_WORKSPACE_HOST_SELECTORS = [
	'.interface-interface-skeleton__content',
	'.editor-visual-editor',
];

function findInlineWorkspaceHost( documentNode ) {
	if ( ! documentNode || typeof documentNode.querySelector !== 'function' ) {
		return null;
	}

	for ( const selector of INLINE_WORKSPACE_HOST_SELECTORS ) {
		const host = documentNode.querySelector( selector );
		if ( host ) {
			return host;
		}
	}

	return null;
}

function activateInlineWorkspaceHost( host ) {
	if ( ! host?.classList ) {
		return () => {};
	}

	const wasActive = host.classList.contains( INLINE_WORKSPACE_HOST_CLASS );
	host.classList.add( INLINE_WORKSPACE_HOST_CLASS );

	return () => {
		if ( ! wasActive ) {
			host.classList.remove( INLINE_WORKSPACE_HOST_CLASS );
		}
	};
}

module.exports = {
	activateInlineWorkspaceHost,
	findInlineWorkspaceHost,
};
