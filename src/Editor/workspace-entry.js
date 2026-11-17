function activateWorkspace( {
	active,
	isOpen,
	isDirty,
	postId,
	closeSidebar,
	openWorkspace,
	warn,
} ) {
	if ( ! active ) {
		return;
	}

	closeSidebar();
	if ( isOpen ) {
		return;
	}
	if ( isDirty ) {
		warn( 'dirty' );
		return;
	}
	if ( ! Number.isInteger( Number( postId ) ) || Number( postId ) <= 0 ) {
		warn( 'missing-post' );
		return;
	}
	openWorkspace();
}

module.exports = { activateWorkspace };
