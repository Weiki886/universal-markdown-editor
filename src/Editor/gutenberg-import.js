function decodeHtmlText( value ) {
	return value
		.replace( /&amp;/g, '&' )
		.replace( /&lt;/g, '<' )
		.replace( /&gt;/g, '>' )
		.replace( /&quot;/g, '"' )
		.replace( /&#39;|&apos;/g, "'" )
		.replace( /&#(\d+);/g, ( match, code ) =>
			String.fromCodePoint( Number( code ) )
		)
		.replace( /&#x([\da-f]+);/gi, ( match, code ) =>
			String.fromCodePoint( parseInt( code, 16 ) )
		);
}

function plainElementText( html, tag, allowedAttributes = '' ) {
	const pattern = new RegExp(
		`^<${ tag }${ allowedAttributes }>([\\s\\S]*)<\\/${ tag }>$`
	);
	const match = html.trim().match( pattern );
	if ( ! match || /<[^>]+>/.test( match[ 1 ] ) ) {
		return null;
	}
	return decodeHtmlText( match[ 1 ] );
}

function supportedMarkdown( block ) {
	if ( block.innerBlocks.length ) {
		return null;
	}
	const attributes = block.attrs || {};

	if ( block.blockName === 'core/paragraph' ) {
		if ( Object.keys( attributes ).length ) {
			return null;
		}
		return plainElementText( block.innerHTML, 'p' );
	}

	if ( block.blockName === 'core/heading' ) {
		const match = block.innerHTML.trim().match( /^<h([1-6])(?: |>)/ );
		if (
			! match ||
			Object.keys( attributes ).some( ( key ) => key !== 'level' )
		) {
			return null;
		}
		const level = Number( match[ 1 ] );
		if (
			attributes.level !== undefined &&
			attributes.level !== null &&
			Number( attributes.level ) !== level
		) {
			return null;
		}
		const text = plainElementText(
			block.innerHTML,
			`h${ level }`,
			'(?: class="wp-block-heading")?'
		);
		return text === null ? null : `${ '#'.repeat( level ) } ${ text }`;
	}

	return null;
}

function importBlocksToMarkdown( blocks ) {
	if ( ! Array.isArray( blocks ) ) {
		throw new TypeError( 'Gutenberg blocks must be an array.' );
	}
	let preservedCount = 0;
	const parts = [];

	for ( const block of blocks ) {
		if (
			! block ||
			! Object.prototype.hasOwnProperty.call( block, 'blockName' ) ||
			( block.blockName !== null &&
				typeof block.blockName !== 'string' ) ||
			typeof block.innerHTML !== 'string' ||
			! Array.isArray( block.innerBlocks )
		) {
			throw new TypeError(
				'Gutenberg blocks must use the raw parser format.'
			);
		}
		if ( block.blockName === null && block.innerHTML.trim() === '' ) {
			continue;
		}

		const markdown = supportedMarkdown( block );
		if ( markdown === null ) {
			parts.push( `<!-- ume-preserved-block:${ preservedCount++ } -->` );
		} else {
			parts.push( markdown );
		}
	}

	return { source: parts.join( '\n\n' ), preservedCount };
}

function prepareGutenbergImport( blocks, expectedSource ) {
	const imported = importBlocksToMarkdown( blocks );
	if ( imported.source !== expectedSource ) {
		throw new Error( 'Gutenberg import results did not match.' );
	}
	return imported;
}

module.exports = { importBlocksToMarkdown, prepareGutenbergImport };
