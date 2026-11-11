import './index.scss';

// Gutenberg provides these modules as WordPress runtime externals.
/* eslint-disable import/no-unresolved, import/no-extraneous-dependencies */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Modal,
	Notice,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { PluginSidebar } from '@wordpress/edit-post';
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';
/* eslint-enable import/no-unresolved, import/no-extraneous-dependencies */
import { createPreviewRequest } from './Editor/preview-request';
import { createPreviewSync } from './Editor/preview-sync';
import {
	applyMarkdownAction,
	buildMarkdownImage,
	buildMarkdownLink,
	isSafeImageUrl,
	isSafeLinkUrl,
} from './Editor/markdown-toolbar';
import { isComplexContent } from './Editor/source-classification';

apiFetch.use( apiFetch.createRootURLMiddleware( window.umeSettings.root ) );
apiFetch.use( apiFetch.createNonceMiddleware( window.umeSettings.nonce ) );

const LOCK_NAME = 'ume-markdown-workspace';
const TOOLBAR_GROUPS = [
	[
		{
			action: 'heading',
			icon: 'heading',
			label: __( 'Heading', 'universal-markdown-editor' ),
		},
		{
			action: 'bold',
			icon: 'bold',
			label: __( 'Bold', 'universal-markdown-editor' ),
		},
		{
			action: 'italic',
			icon: 'italic',
			label: __( 'Italic', 'universal-markdown-editor' ),
		},
		{
			action: 'strikethrough',
			icon: 'strikethrough',
			label: __( 'Strikethrough', 'universal-markdown-editor' ),
		},
	],
	[
		{
			action: 'quote',
			icon: 'quote',
			label: __( 'Blockquote', 'universal-markdown-editor' ),
		},
		{
			action: 'unorderedList',
			icon: 'unorderedList',
			label: __( 'Bulleted list', 'universal-markdown-editor' ),
		},
		{
			action: 'orderedList',
			icon: 'orderedList',
			label: __( 'Numbered list', 'universal-markdown-editor' ),
		},
	],
	[
		{
			action: 'code',
			icon: 'code',
			label: __( 'Inline code', 'universal-markdown-editor' ),
		},
		{
			action: 'codeBlock',
			icon: 'codeBlock',
			label: __( 'Code block', 'universal-markdown-editor' ),
		},
		{
			action: 'table',
			icon: 'table',
			label: __( 'Table', 'universal-markdown-editor' ),
		},
	],
];

function ToolbarGlyph( { name } ) {
	const paths = {
		bold: 'M6.8 4h5.5c3 0 4.9 1.5 4.9 4 0 1.7-.9 3-2.4 3.5 1.9.5 3.1 2 3.1 4 0 2.8-2.1 4.5-5.3 4.5H6.8V4zm3 3.1v3.7h2.2c1.5 0 2.4-.7 2.4-1.9s-.8-1.8-2.4-1.8h-2.2zm0 6.6v4.1h2.5c1.7 0 2.7-.7 2.7-2.1 0-1.3-1-2-2.7-2h-2.5z',
		code: 'M8.6 7.4 3.9 12l4.7 4.6 1.4-1.4L6.7 12 10 8.8 8.6 7.4zm6.8 0-1.4 1.4 3.3 3.2-3.3 3.2 1.4 1.4 4.7-4.6-4.7-4.6z',
		codeBlock:
			'M4 3h16v18H4V3zm2 2v14h12V5H6zm2.2 3.3L6.8 9.7l1.4 1.4L9 10.4 7.8 9.2l1.2-1.1-1.4-1.4zm8.4 0-1.4 1.4 1.2 1.1-1.2 1.2 1.4 1.4 2.6-2.6-2.6-2.5zM8.5 14h7v1.8h-7V14z',
		heading: 'M5 4h3v6.4h8V4h3v16h-3v-6.8H8V20H5V4z',
		image: 'M4 5h16v14H4V5zm2 2v8.2l3.6-3.6 2.8 2.8 2.4-2.4 3.2 3.2V7H6zm8 1.3a1.4 1.4 0 1 0 0 2.8 1.4 1.4 0 0 0 0-2.8z',
		italic: 'M9.2 4H17v2.2h-2.5l-3.4 11.6H14V20H6.2v-2.2h2.5l3.4-11.6H9.2V4z',
		link: 'M9.8 7.4 8.4 8.8l-2 2a2 2 0 1 0 2.8 2.8l2-2 1.4 1.4-2 2a4 4 0 0 1-5.6-5.6l2-2 1.8-.4zm4.4 9.2 1.4-1.4 2-2a2 2 0 1 0-2.8-2.8l-2 2-1.4-1.4 2-2a4 4 0 0 1 5.6 5.6l-2 2-1.8.4-.1-.4z',
		orderedList:
			'M4 5h2v2H4V5zm0 4h2v2H4V9zm0 4h2v2H4v-2zm0 4h2v2H4v-2zM9 6h11v2H9V6zm0 4h11v2H9v-2zm0 4h11v2H9v-2zm0 4h11v2H9v-2z',
		quote: 'M5 6h9v5H8.8c0 1.3.8 2 2.2 2.2V16c-3.6-.1-5.4-2.1-5.4-5.6V6H5zm9 0h5v5h-2.7c0 1.3.8 2 2.2 2.2V16c-3.6-.1-5.4-2.1-5.4-5.6V6h.9z',
		strikethrough:
			'M5 11h14v2H5v-2zm3.2-2.6c0-2.1 1.6-3.5 4.2-3.5 2.1 0 3.8.8 4.7 2.3l-1.8 1.1c-.6-.9-1.6-1.4-2.9-1.4-1.4 0-2.2.5-2.2 1.4 0 .8.7 1.1 2 1.4l1.2.3c2.7.6 4 1.8 4 3.8 0 2.4-1.9 4-4.9 4-2.5 0-4.4-.9-5.5-2.7l1.9-1.2c.8 1.3 2 1.9 3.6 1.9 1.6 0 2.6-.6 2.6-1.7 0-.9-.7-1.4-2.2-1.7l-1.3-.3c-2.5-.6-3.4-1.7-3.4-3.7z',
		table: 'M4 5h16v14H4V5zm2 2v2h3V7H6zm5 0v2h3V7h-3zm5 0v2h2V7h-2zM6 11v2h3v-2H6zm5 0v2h3v-2h-3zm5 0v2h2v-2h-2zM6 15v2h3v-2H6zm5 0v2h3v-2h-3zm5 0v2h2v-2h-2z',
		unorderedList:
			'M5 6.5A1.5 1.5 0 1 0 5 9.5a1.5 1.5 0 0 0 0-3zm0 5A1.5 1.5 0 1 0 5 14.5a1.5 1.5 0 0 0 0-3zm0 5A1.5 1.5 0 1 0 5 19.5a1.5 1.5 0 0 0 0-3zM9 6h11v2H9V6zm0 5h11v2H9v-2zm0 5h11v2H9v-2z',
	};

	return (
		<svg
			aria-hidden="true"
			className="ume-toolbar-glyph"
			focusable="false"
			viewBox="0 0 24 24"
		>
			<path d={ paths[ name ] } />
		</svg>
	);
}

function PreviewFrame( { html } ) {
	const document = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{font:16px/1.65 system-ui,sans-serif;color:#202124;margin:20px;overflow-wrap:anywhere}img{max-width:100%;height:auto}pre{overflow:auto;padding:12px;background:#f3f4f5}table{border-collapse:collapse}th,td{border:1px solid #d0d4d8;padding:6px 10px;text-align:left}blockquote{border-left:3px solid #9aa0a6;margin-left:0;padding-left:14px;color:#5f6368}</style></head><body>${ html }</body></html>`;

	return (
		<iframe
			className="ume-preview-frame"
			title={ __( 'Markdown preview', 'universal-markdown-editor' ) }
			sandbox=""
			srcDoc={ document }
		/>
	);
}

function MarkdownWorkspace( { postId, initialContent, onClose } ) {
	const [ source, setSource ] = useState( '' );
	const [ baseline, setBaseline ] = useState( '' );
	const [ session, setSession ] = useState( null );
	const [ originalContent, setOriginalContent ] = useState( initialContent );
	const [ loadError, setLoadError ] = useState( '' );
	const [ saveError, setSaveError ] = useState( '' );
	const [ saveErrorCode, setSaveErrorCode ] = useState( '' );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ html, setHtml ] = useState( '' );
	const [ previewState, setPreviewState ] = useState( 'idle' );
	const [ previewError, setPreviewError ] = useState( '' );
	const [ mobileView, setMobileView ] = useState( 'editor' );
	const [ prompt, setPrompt ] = useState( '' );
	const [ confirmClose, setConfirmClose ] = useState( false );
	const [ linkDialog, setLinkDialog ] = useState( false );
	const [ linkText, setLinkText ] = useState( '' );
	const [ linkUrl, setLinkUrl ] = useState( '' );
	const [ linkError, setLinkError ] = useState( '' );
	const [ mediaError, setMediaError ] = useState( '' );
	const [ selection, setSelection ] = useState( { start: 0, end: 0 } );
	const textareaRef = useRef( null );
	const previewRequest = useMemo( () => createPreviewRequest(), [] );
	const previewSync = useMemo( () => createPreviewSync(), [] );
	const editorDispatch = useDispatch( 'core/editor' );
	const dirty =
		Boolean( session ) &&
		prompt === '' &&
		( ! session.managed || source !== baseline );

	const beginEditing = useCallback( ( content ) => {
		setSource( content );
		setBaseline( content );
		setHtml( '' );
		setPreviewError( '' );
		setPreviewState( 'idle' );
		setPrompt( '' );
	}, [] );

	useEffect( () => {
		let active = true;
		apiFetch( { path: `/ume/v1/posts/${ postId }/markdown` } )
			.then( ( result ) => {
				if ( ! active ) {
					return;
				}
				setSession( result );
				setOriginalContent( result.html || '' );
				if ( result.managed ) {
					beginEditing( result.source );
					return;
				}
				if ( result.html.trim() === '' ) {
					beginEditing( '' );
					return;
				}
				setPrompt(
					isComplexContent( result.html ) ? 'complex' : 'import'
				);
			} )
			.catch( ( error ) => {
				if ( active ) {
					setLoadError(
						error?.message ||
							__(
								'Could not load the Markdown session.',
								'universal-markdown-editor'
							)
					);
				}
			} );

		return () => {
			active = false;
		};
	}, [ beginEditing, postId ] );

	useEffect( () => {
		if ( typeof editorDispatch.lockPostSaving === 'function' ) {
			editorDispatch.lockPostSaving( LOCK_NAME );
		}
		if ( typeof editorDispatch.lockPostAutosaving === 'function' ) {
			editorDispatch.lockPostAutosaving( LOCK_NAME );
		}

		return () => {
			if ( typeof editorDispatch.unlockPostAutosaving === 'function' ) {
				editorDispatch.unlockPostAutosaving( LOCK_NAME );
			}
			if ( typeof editorDispatch.unlockPostSaving === 'function' ) {
				editorDispatch.unlockPostSaving( LOCK_NAME );
			}
		};
	}, [ editorDispatch ] );

	useEffect( () => {
		if ( prompt !== '' ) {
			return undefined;
		}

		previewSync.cancel();
		previewRequest.cancel();
		setPreviewError( '' );
		if ( source === '' ) {
			setHtml( '' );
			setPreviewState( 'idle' );
			return undefined;
		}

		setPreviewState( 'loading' );
		previewSync.schedule( source, ( value ) => {
			previewRequest.run(
				value,
				( nextSource, { signal } ) =>
					apiFetch( {
						path: `/ume/v1/posts/${ postId }/markdown/preview`,
						method: 'POST',
						data: { source: nextSource },
						signal,
					} ),
				( result ) => {
					setHtml( result.html );
					setPreviewState( 'ready' );
				},
				( error ) => {
					setPreviewError(
						error?.message ||
							__(
								'Preview is unavailable.',
								'universal-markdown-editor'
							)
					);
					setPreviewState( 'error' );
				}
			);
		} );

		return () => {
			previewSync.cancel();
			previewRequest.cancel();
		};
	}, [ postId, previewRequest, previewSync, prompt, source ] );

	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}

		const warnBeforeLeave = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warnBeforeLeave );

		return () =>
			window.removeEventListener( 'beforeunload', warnBeforeLeave );
	}, [ dirty ] );

	const requestClose = () => {
		if ( dirty ) {
			setConfirmClose( true );
			return;
		}
		onClose();
	};

	const replaceSelection = (
		replacement,
		selectedStart,
		selectedEnd,
		selectionStart,
		selectionEnd
	) => {
		const input = textareaRef.current;
		const start =
			selectionStart ??
			( input ? input.selectionStart : selection.start );
		const end =
			selectionEnd ?? ( input ? input.selectionEnd : selection.end );
		const nextSource =
			source.slice( 0, start ) + replacement + source.slice( end );
		setSource( nextSource );
		const caretStart = selectedStart ?? start + replacement.length;
		const caretEnd = selectedEnd ?? caretStart;
		window.requestAnimationFrame( () => {
			if ( textareaRef.current ) {
				textareaRef.current.focus();
				textareaRef.current.setSelectionRange( caretStart, caretEnd );
				setSelection( { start: caretStart, end: caretEnd } );
			}
		} );
	};

	const runToolbarAction = ( action ) => {
		const input = textareaRef.current;
		const start = input ? input.selectionStart : selection.start;
		const end = input ? input.selectionEnd : selection.end;
		const result = applyMarkdownAction( source, start, end, action );
		setSource( result.source );
		window.requestAnimationFrame( () => {
			if ( textareaRef.current ) {
				textareaRef.current.focus();
				textareaRef.current.setSelectionRange(
					result.selectionStart,
					result.selectionEnd
				);
				setSelection( {
					start: result.selectionStart,
					end: result.selectionEnd,
				} );
			}
		} );
	};

	const openLinkDialog = () => {
		const input = textareaRef.current;
		const start = input ? input.selectionStart : selection.start;
		const end = input ? input.selectionEnd : selection.end;
		setSelection( { start, end } );
		setLinkText( source.slice( start, end ) );
		setLinkUrl( '' );
		setLinkError( '' );
		setLinkDialog( true );
	};

	const insertLink = () => {
		if ( ! isSafeLinkUrl( linkUrl ) ) {
			setLinkError(
				__(
					'Enter a safe HTTP, HTTPS, mail, telephone, or relative URL.',
					'universal-markdown-editor'
				)
			);
			return;
		}
		const markdown = buildMarkdownLink( linkText || 'link text', linkUrl );
		const labelStart = 1;
		replaceSelection(
			markdown,
			selection.start + labelStart,
			selection.start + labelStart + ( linkText || 'link text' ).length,
			selection.start,
			selection.end
		);
		setLinkDialog( false );
	};

	const openMediaLibrary = () => {
		setMediaError( '' );
		if ( ! window.umeSettings.canUploadMedia ) {
			setMediaError(
				__(
					'Your account does not have permission to upload or select media.',
					'universal-markdown-editor'
				)
			);
			return;
		}
		if ( ! window.wp?.media ) {
			setMediaError(
				__(
					'The WordPress media library is unavailable. Reload the editor and try again.',
					'universal-markdown-editor'
				)
			);
			return;
		}

		try {
			const frame = window.wp.media( {
				title: __(
					'Select or upload an image',
					'universal-markdown-editor'
				),
				button: {
					text: __( 'Insert image', 'universal-markdown-editor' ),
				},
				library: { type: 'image' },
				multiple: false,
			} );
			frame.on( 'select', () => {
				const attachment = frame
					.state()
					.get( 'selection' )
					.first()
					?.toJSON();
				if (
					! attachment ||
					! isSafeImageUrl( attachment.url || '' )
				) {
					setMediaError(
						__(
							'The selected media did not provide a safe, permanent image URL.',
							'universal-markdown-editor'
						)
					);
					return;
				}
				const markdown = buildMarkdownImage(
					attachment.alt || attachment.title || '',
					attachment.url
				);
				const altText = attachment.alt || attachment.title || '';
				const input = textareaRef.current;
				const start = input ? input.selectionStart : selection.start;
				const end = input ? input.selectionEnd : selection.end;
				setSelection( { start, end } );
				replaceSelection(
					markdown,
					start + 2,
					start + 2 + altText.length,
					start,
					end
				);
			} );
			frame.open();
		} catch ( error ) {
			setMediaError(
				error?.message ||
					__(
						'The media library could not be opened.',
						'universal-markdown-editor'
					)
			);
		}
	};

	const saveMarkdown = async () => {
		if ( ! session || isSaving ) {
			return;
		}

		setIsSaving( true );
		setSaveError( '' );
		setSaveErrorCode( '' );
		try {
			const saved = await apiFetch( {
				path: `/ume/v1/posts/${ postId }/markdown`,
				method: 'PUT',
				data: {
					source,
					baseContentHash: session.contentHash,
					baseModifiedGmt: session.modifiedGmt,
					baseSourceHash: session.sourceHash,
				},
			} );
			setSession( saved );
			setBaseline( source );
			setSaveError( '' );
			window.setTimeout( () => window.location.reload(), 250 );
		} catch ( error ) {
			setSaveErrorCode( error?.code || '' );
			setSaveError(
				error?.message ||
					__(
						'Markdown could not be saved.',
						'universal-markdown-editor'
					)
			);
		} finally {
			setIsSaving( false );
		}
	};

	const downloadSource = () => {
		const blob = new Blob( [ source ], {
			type: 'text/markdown;charset=utf-8',
		} );
		const url = window.URL.createObjectURL( blob );
		const link = window.document.createElement( 'a' );
		link.href = url;
		link.download = `post-${ postId }.md`;
		link.click();
		window.URL.revokeObjectURL( url );
	};

	const saveUnavailableNotice = ! session?.revisionsEnabled && (
		<Notice status="warning" isDismissible={ false }>
			{ __(
				'WordPress revisions are disabled for this post type. Markdown can still be saved, but historical recovery is limited.',
				'universal-markdown-editor'
			) }
		</Notice>
	);

	if ( loadError ) {
		return (
			<Modal
				title={ __( 'Markdown editor', 'universal-markdown-editor' ) }
				onRequestClose={ onClose }
			>
				<Notice status="error" isDismissible={ false }>
					{ loadError }
				</Notice>
				<Button variant="secondary" onClick={ onClose }>
					{ __( 'Close', 'universal-markdown-editor' ) }
				</Button>
			</Modal>
		);
	}

	if ( ! session ) {
		return (
			<Modal
				title={ __( 'Markdown editor', 'universal-markdown-editor' ) }
				onRequestClose={ onClose }
			>
				<Spinner />
			</Modal>
		);
	}

	if ( prompt === 'complex' ) {
		return (
			<>
				<Modal
					title={ __(
						'Markdown editor',
						'universal-markdown-editor'
					) }
					onRequestClose={ onClose }
					isFullScreen
					className="ume-workspace-modal"
				>
					<div className="ume-import-prompt">
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'This post contains WordPress blocks, HTML, or shortcodes. It cannot be converted losslessly. The original content will remain unchanged.',
								'universal-markdown-editor'
							) }
						</Notice>
						<div className="ume-prompt-actions">
							<Button variant="secondary" onClick={ onClose }>
								{ __( 'Cancel', 'universal-markdown-editor' ) }
							</Button>
							<Button
								variant="primary"
								onClick={ () => beginEditing( '' ) }
							>
								{ __(
									'Start with empty Markdown',
									'universal-markdown-editor'
								) }
							</Button>
						</div>
					</div>
				</Modal>
			</>
		);
	}

	if ( prompt === 'import' ) {
		return (
			<Modal
				title={ __( 'Markdown editor', 'universal-markdown-editor' ) }
				onRequestClose={ onClose }
				isFullScreen
				className="ume-workspace-modal"
			>
				<div className="ume-import-prompt">
					<p>
						{ __(
							"Import this post's plain text as Markdown? No post content will be changed by opening or previewing it.",
							'universal-markdown-editor'
						) }
					</p>
					<div className="ume-prompt-actions">
						<Button variant="secondary" onClick={ onClose }>
							{ __( 'Cancel', 'universal-markdown-editor' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ () => beginEditing( '' ) }
						>
							{ __( 'Start empty', 'universal-markdown-editor' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ () => beginEditing( originalContent ) }
						>
							{ __(
								'Import current content',
								'universal-markdown-editor'
							) }
						</Button>
					</div>
				</div>
			</Modal>
		);
	}

	return (
		<>
			<Modal
				title={ __( 'Markdown editor', 'universal-markdown-editor' ) }
				onRequestClose={ requestClose }
				isFullScreen
				className="ume-workspace-modal"
			>
				<div className="ume-workspace">
					<div className="ume-workspace-header">
						<div
							className="ume-mobile-tabs"
							role="tablist"
							aria-label={ __(
								'Editor view',
								'universal-markdown-editor'
							) }
						>
							<Button
								role="tab"
								aria-selected={ mobileView === 'editor' }
								onClick={ () => setMobileView( 'editor' ) }
							>
								{ __( 'Source', 'universal-markdown-editor' ) }
							</Button>
							<Button
								role="tab"
								aria-selected={ mobileView === 'preview' }
								onClick={ () => setMobileView( 'preview' ) }
							>
								{ __( 'Preview', 'universal-markdown-editor' ) }
							</Button>
						</div>
						<div
							className="ume-format-toolbar"
							role="toolbar"
							aria-label={ __(
								'Markdown formatting',
								'universal-markdown-editor'
							) }
						>
							{ TOOLBAR_GROUPS.map( ( group ) => (
								<div
									className="ume-format-toolbar__group"
									key={ group
										.map( ( { action } ) => action )
										.join( '-' ) }
								>
									{ group.map(
										( { action, icon, label } ) => (
											<Button
												className="ume-toolbar-button"
												icon={
													<ToolbarGlyph
														name={ icon }
													/>
												}
												key={ action }
												label={ label }
												title={ label }
												variant="tertiary"
												disabled={
													prompt !== '' || isSaving
												}
												onMouseDown={ ( event ) =>
													event.preventDefault()
												}
												onClick={ () =>
													runToolbarAction( action )
												}
											/>
										)
									) }
								</div>
							) ) }
							<div className="ume-format-toolbar__group">
								<Button
									className="ume-toolbar-button"
									icon={ <ToolbarGlyph name="link" /> }
									label={ __(
										'Insert link',
										'universal-markdown-editor'
									) }
									title={ __(
										'Insert link',
										'universal-markdown-editor'
									) }
									variant="tertiary"
									disabled={ prompt !== '' || isSaving }
									onMouseDown={ ( event ) =>
										event.preventDefault()
									}
									onClick={ openLinkDialog }
								/>
								<Button
									className="ume-toolbar-button"
									icon={ <ToolbarGlyph name="image" /> }
									label={ __(
										'Insert image',
										'universal-markdown-editor'
									) }
									title={ __(
										'Insert image',
										'universal-markdown-editor'
									) }
									aria-describedby={
										! window.umeSettings.canUploadMedia
											? 'ume-media-permission-note'
											: undefined
									}
									variant="tertiary"
									disabled={
										prompt !== '' ||
										isSaving ||
										! window.umeSettings.canUploadMedia
									}
									onMouseDown={ ( event ) =>
										event.preventDefault()
									}
									onClick={ openMediaLibrary }
								/>
							</div>
							{ ! window.umeSettings.canUploadMedia && (
								<span
									id="ume-media-permission-note"
									className="ume-toolbar-help"
								>
									{ __(
										'Media access requires the WordPress upload_files capability.',
										'universal-markdown-editor'
									) }
								</span>
							) }
						</div>
					</div>
					{ mediaError && (
						<Notice
							status="error"
							isDismissible
							onRemove={ () => setMediaError( '' ) }
						>
							{ mediaError }
						</Notice>
					) }
					{ saveUnavailableNotice }
					{ saveError && (
						<Notice status="error" isDismissible={ false }>
							{ saveError }
							{ saveErrorCode === 'ume_edit_conflict' && (
								<div className="ume-prompt-actions">
									<Button
										variant="secondary"
										onClick={ downloadSource }
									>
										{ __(
											'Download Markdown',
											'universal-markdown-editor'
										) }
									</Button>
									<Button
										variant="secondary"
										onClick={ () =>
											window.location.reload()
										}
									>
										{ __(
											'Reload post',
											'universal-markdown-editor'
										) }
									</Button>
								</div>
							) }
						</Notice>
					) }
					{ previewError && (
						<Notice status="error" isDismissible={ false }>
							{ previewError }
						</Notice>
					) }
					<div
						className={ `ume-workspace-panels is-${ mobileView }` }
					>
						<section
							className="ume-source-panel"
							aria-label={ __(
								'Markdown source',
								'universal-markdown-editor'
							) }
						>
							<div className="ume-panel-heading">
								<label htmlFor="ume-markdown-source">
									{ __(
										'Markdown source',
										'universal-markdown-editor'
									) }
								</label>
							</div>
							<textarea
								id="ume-markdown-source"
								ref={ textareaRef }
								value={ source }
								onChange={ ( event ) => {
									setSource( event.target.value );
									setSelection( {
										start: event.target.selectionStart,
										end: event.target.selectionEnd,
									} );
								} }
								spellCheck="false"
								autoCapitalize="off"
								autoComplete="off"
								aria-label={ __(
									'Markdown source',
									'universal-markdown-editor'
								) }
							/>
						</section>
						<section
							className="ume-preview-panel"
							aria-label={ __(
								'Rendered preview',
								'universal-markdown-editor'
							) }
						>
							<div className="ume-panel-heading">
								<span>
									{ __(
										'Rendered preview',
										'universal-markdown-editor'
									) }
								</span>
							</div>
							<PreviewFrame html={ html } />
						</section>
					</div>
					<div className="ume-workspace-actions">
						<span
							className="ume-preview-status"
							role="status"
							aria-live="polite"
						>
							{ previewState === 'loading' && (
								<>
									<Spinner />
									{ __(
										'Updating preview…',
										'universal-markdown-editor'
									) }
								</>
							) }
							{ previewState === 'ready' &&
								__(
									'Preview updated',
									'universal-markdown-editor'
								) }
						</span>
						<div className="ume-workspace-actions__buttons">
							<Button
								variant="secondary"
								onClick={ requestClose }
							>
								{ __(
									'Exit Markdown mode',
									'universal-markdown-editor'
								) }
							</Button>
							<Button
								variant="primary"
								onClick={ saveMarkdown }
								disabled={
									isSaving ||
									( session.managed && source === baseline )
								}
							>
								{ isSaving
									? __(
											'Saving…',
											'universal-markdown-editor'
										)
									: __(
											'Save Markdown',
											'universal-markdown-editor'
										) }
							</Button>
						</div>
					</div>
				</div>
			</Modal>
			{ linkDialog && (
				<Modal
					title={ __( 'Insert link', 'universal-markdown-editor' ) }
					onRequestClose={ () => setLinkDialog( false ) }
				>
					<TextControl
						label={ __( 'Text', 'universal-markdown-editor' ) }
						value={ linkText }
						onChange={ setLinkText }
						autoComplete="off"
					/>
					<TextControl
						label={ __( 'URL', 'universal-markdown-editor' ) }
						value={ linkUrl }
						onChange={ ( value ) => {
							setLinkUrl( value );
							setLinkError( '' );
						} }
						autoComplete="url"
						autoCapitalize="off"
						spellCheck={ false }
						type="text"
					/>
					{ linkError && (
						<Notice status="error" isDismissible={ false }>
							{ linkError }
						</Notice>
					) }
					<div className="ume-prompt-actions">
						<Button
							variant="secondary"
							onClick={ () => setLinkDialog( false ) }
						>
							{ __( 'Cancel', 'universal-markdown-editor' ) }
						</Button>
						<Button variant="primary" onClick={ insertLink }>
							{ __( 'Insert link', 'universal-markdown-editor' ) }
						</Button>
					</div>
				</Modal>
			) }
			{ confirmClose && (
				<Modal
					title={ __(
						'Discard this Markdown session?',
						'universal-markdown-editor'
					) }
					onRequestClose={ () => setConfirmClose( false ) }
				>
					<p>
						{ __(
							'These edits are not saved. The original Gutenberg content remains unchanged, but this Markdown session will be discarded.',
							'universal-markdown-editor'
						) }
					</p>
					<div className="ume-prompt-actions">
						<Button
							variant="secondary"
							onClick={ () => setConfirmClose( false ) }
						>
							{ __(
								'Keep editing',
								'universal-markdown-editor'
							) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ onClose }
						>
							{ __(
								'Discard session',
								'universal-markdown-editor'
							) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
}

function MarkdownEditorPlugin() {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ sidebarNotice, setSidebarNotice ] = useState( '' );
	const editorState = useSelect( ( select ) => {
		const editor = select( 'core/editor' );

		return {
			isDirty: editor.isEditedPostDirty(),
			postId: editor.getCurrentPostId(),
			content: editor.getEditedPostContent() || '',
			status: editor.getCurrentPostAttribute( 'status' ),
		};
	}, [] );

	const openWorkspace = () => {
		setSidebarNotice( '' );
		if ( editorState.isDirty ) {
			setSidebarNotice(
				__(
					'Save your Gutenberg changes before opening Markdown mode.',
					'universal-markdown-editor'
				)
			);
			return;
		}
		if (
			! Number.isInteger( Number( editorState.postId ) ) ||
			Number( editorState.postId ) <= 0
		) {
			setSidebarNotice(
				__(
					'Save the post in Gutenberg before opening Markdown mode.',
					'universal-markdown-editor'
				)
			);
			return;
		}
		setIsOpen( true );
	};

	return (
		<>
			<PluginSidebar
				name="ume-markdown-sidebar"
				title={ __( 'Markdown editor', 'universal-markdown-editor' ) }
			>
				{ sidebarNotice && (
					<Notice status="warning" isDismissible={ false }>
						{ sidebarNotice }
					</Notice>
				) }
				<div className="ume-sidebar-content">
					<Button variant="primary" onClick={ openWorkspace }>
						{ __(
							'Open Markdown workspace',
							'universal-markdown-editor'
						) }
					</Button>
				</div>
			</PluginSidebar>
			{ isOpen && (
				<MarkdownWorkspace
					key={ editorState.postId }
					postId={ Number( editorState.postId ) }
					initialContent={ editorState.content }
					onClose={ () => setIsOpen( false ) }
				/>
			) }
		</>
	);
}

registerPlugin( 'ume-markdown-editor', { render: MarkdownEditorPlugin } );
