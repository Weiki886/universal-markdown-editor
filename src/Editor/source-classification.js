function isComplexContent( content ) {
	return (
		/<!--\s*wp:/i.test( content ) ||
		/<\/?[a-z][\w:-]*(?:\s+[^<>]*)?\s*\/?>/i.test( content ) ||
		/\[[a-z][\w-]*(?:\s+[^\]]*)?\](?!\s*\()/i.test( content )
	);
}

module.exports = { isComplexContent };
