function createPreviewRequest() {
	let sequence = 0;
	let activeController = null;

	return {
		async run( source, send, onSuccess, onError ) {
			const requestSequence = ++sequence;
			if ( activeController ) {
				activeController.abort();
			}

			const controller = new AbortController();
			activeController = controller;

			try {
				const result = await send( source, {
					signal: controller.signal,
				} );
				if ( requestSequence === sequence ) {
					onSuccess( result );
				}
			} catch ( error ) {
				if (
					requestSequence === sequence &&
					error?.name !== 'AbortError'
				) {
					onError( error );
				}
			} finally {
				if ( requestSequence === sequence ) {
					activeController = null;
				}
			}
		},
		cancel() {
			sequence += 1;
			if ( activeController ) {
				activeController.abort();
				activeController = null;
			}
		},
	};
}

module.exports = { createPreviewRequest };
