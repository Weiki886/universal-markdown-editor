export default function MarkdownBrandIcon() {
	return (
		<svg
			aria-hidden="true"
			className="ume-brand-icon"
			fill="none"
			focusable="false"
			height="24"
			viewBox="0 0 24 24"
			width="24"
			xmlns="http://www.w3.org/2000/svg"
		>
			<g
				fill="none"
				stroke="currentColor"
				strokeLinecap="round"
				strokeLinejoin="round"
				strokeWidth="1.8"
			>
				{ /* Keep paths unfilled when WordPress sets fill on toolbar SVGs. */ }
				<path
					d="M5 5h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"
					fill="none"
				/>
				<path d="M6 15V9l2.5 3L11 9v6" fill="none" />
				<path d="M16.5 9v6m-2-2 2 2 2-2" fill="none" />
			</g>
		</svg>
	);
}
