function createPreviewSync( {
	delayMs = 150,
	schedule = ( callback, delay ) => window.setTimeout( callback, delay ),
	cancelSchedule = ( id ) => window.clearTimeout( id ),
} = {} ) {
	let timer = null;
	let sequence = 0;

	return {
		schedule( source, run ) {
			if ( timer !== null ) {
				cancelSchedule( timer );
			}

			const scheduledSequence = ++sequence;
			timer = schedule( () => {
				if ( scheduledSequence === sequence ) {
					timer = null;
					run( source );
				}
			}, delayMs );
		},
		cancel() {
			sequence += 1;
			if ( timer !== null ) {
				cancelSchedule( timer );
				timer = null;
			}
		},
	};
}

module.exports = { createPreviewSync };
