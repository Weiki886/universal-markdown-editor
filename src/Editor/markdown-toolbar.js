const INLINE_ACTIONS = {
	bold: { before: '**', after: '**', placeholder: 'bold text' },
	italic: { before: '*', after: '*', placeholder: 'italic text' },
	strikethrough: { before: '~~', after: '~~', placeholder: 'deleted text' },
	code: { before: '`', after: '`', placeholder: 'code' },
	link: {
		before: '[',
		after: '](https://example.com)',
		placeholder: 'link text',
	},
	image: {
		before: '![',
		after: '](https://example.com/image.jpg)',
		placeholder: 'alt text',
	},
};

const BLOCK_ACTIONS = {
	heading: { prefix: '## ' },
	unorderedList: { prefix: '- ' },
	orderedList: { prefix: '1. ' },
	quote: { prefix: '> ' },
	codeBlock: { before: '```\n', after: '\n```', placeholder: 'code' },
	table: {
		before: '',
		after: '',
		placeholder:
			'| Column 1 | Column 2 |\n| --- | --- |\n| Value | Value |',
	},
};

function applyMarkdownAction( source, selectionStart, selectionEnd, action ) {
	const selected = source.slice( selectionStart, selectionEnd );
	const inline = INLINE_ACTIONS[ action ];
	if ( inline ) {
		const value = selected || inline.placeholder;
		const replacement = `${ inline.before }${ value }${ inline.after }`;
		const start = selectionStart + inline.before.length;
		return {
			source:
				source.slice( 0, selectionStart ) +
				replacement +
				source.slice( selectionEnd ),
			selectionStart: start,
			selectionEnd: start + value.length,
		};
	}

	const block = BLOCK_ACTIONS[ action ];
	if ( ! block ) {
		return { source, selectionStart, selectionEnd };
	}

	if ( block.prefix ) {
		const startOfLine = source.lastIndexOf( '\n', selectionStart - 1 ) + 1;
		const endOfLine = source.indexOf( '\n', selectionEnd );
		const end = endOfLine === -1 ? source.length : endOfLine;
		const lines = source.slice( startOfLine, end );
		const replacement = lines
			.split( '\n' )
			.map( ( line ) => block.prefix + line )
			.join( '\n' );
		return {
			source:
				source.slice( 0, startOfLine ) +
				replacement +
				source.slice( end ),
			selectionStart: selectionStart + block.prefix.length,
			selectionEnd:
				selectionEnd + block.prefix.length * lines.split( '\n' ).length,
		};
	}

	const value = selected || block.placeholder;
	const before = block.before || '';
	const after = block.after || '';
	const replacement = `${ before }${ value }${ after }`;
	const start = selectionStart + before.length;
	return {
		source:
			source.slice( 0, selectionStart ) +
			replacement +
			source.slice( selectionEnd ),
		selectionStart: start,
		selectionEnd: start + value.length,
	};
}

function isSafeLinkUrl( value ) {
	const url = value.trim();
	if (
		! url ||
		/[\\\u0000-\u001f\u007f]/.test( url ) ||
		url.startsWith( '//' )
	) {
		return false;
	}

	const scheme = url.match( /^([a-z][a-z0-9+.-]*):/i );
	return scheme
		? [ 'http', 'https', 'mailto', 'tel' ].includes(
				scheme[ 1 ].toLowerCase()
			)
		: true;
}

function isSafeImageUrl( value ) {
	try {
		const url = new URL( value );
		return [ 'http:', 'https:' ].includes( url.protocol );
	} catch {
		return false;
	}
}

function buildMarkdownLink( text, url ) {
	const label = escapeMarkdownLabel( text );
	const destination = encodeMarkdownDestination( url );
	return `[${ label }](${ destination })`;
}

function buildMarkdownImage( alt, url ) {
	const label = escapeMarkdownLabel( alt );
	const destination = encodeMarkdownDestination( url );
	return `![${ label }](${ destination })`;
}

function escapeMarkdownLabel( value ) {
	return value.replace( /([\\[\]])/g, '\\$1' ).replace( /\r?\n/g, ' ' );
}

function encodeMarkdownDestination( url ) {
	return url
		.trim()
		.replace(
			/[ ()]/g,
			( character ) =>
				( { ' ': '%20', '(': '%28', ')': '%29' } )[ character ]
		);
}

module.exports = {
	applyMarkdownAction,
	buildMarkdownImage,
	buildMarkdownLink,
	isSafeImageUrl,
	isSafeLinkUrl,
};
