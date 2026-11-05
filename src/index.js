import './index.scss';

// Gutenberg provides these modules as WordPress runtime externals.
/* eslint-disable import/no-unresolved, import/no-extraneous-dependencies */
import apiFetch from '@wordpress/api-fetch';
import { Button, Modal, Notice, Spinner } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { PluginSidebar } from '@wordpress/edit-post';
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';
/* eslint-enable import/no-unresolved, import/no-extraneous-dependencies */
import { createPreviewRequest } from './Editor/preview-request';
import { isComplexContent } from './Editor/source-classification';

apiFetch.use( apiFetch.createRootURLMiddleware( window.umeSettings.root ) );
apiFetch.use( apiFetch.createNonceMiddleware( window.umeSettings.nonce ) );

const PREVIEW_DEBOUNCE_MS = 400;
const LOCK_NAME = 'ume-markdown-workspace';

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

function MarkdownWorkspace( {
	postId,
	initialContent,
	initialPrompt,
	onClose,
} ) {
	const [ source, setSource ] = useState( '' );
	const [ baseline, setBaseline ] = useState( '' );
	const [ html, setHtml ] = useState( '' );
	const [ previewState, setPreviewState ] = useState( 'idle' );
	const [ previewError, setPreviewError ] = useState( '' );
	const [ mobileView, setMobileView ] = useState( 'editor' );
	const [ prompt, setPrompt ] = useState( initialPrompt );
	const [ confirmClose, setConfirmClose ] = useState( false );
	const previewRequest = useMemo( () => createPreviewRequest(), [] );
	const editorDispatch = useDispatch( 'core/editor' );
	const dirty = source !== baseline;

	const beginEditing = ( content ) => {
		setSource( content );
		setBaseline( content );
		setHtml( '' );
		setPreviewError( '' );
		setPreviewState( 'idle' );
		setPrompt( '' );
	};

	useEffect( () => {
		if ( prompt !== '' ) {
			return undefined;
		}

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
	}, [ editorDispatch, prompt ] );

	useEffect( () => {
		if ( prompt !== '' ) {
			return undefined;
		}

		previewRequest.cancel();
		setPreviewError( '' );
		if ( source === '' ) {
			setHtml( '' );
			setPreviewState( 'idle' );
			return undefined;
		}

		setPreviewState( 'loading' );
		const timer = window.setTimeout( () => {
			previewRequest.run(
				source,
				( value, { signal } ) =>
					apiFetch( {
						path: `/ume/v1/posts/${ postId }/markdown/preview`,
						method: 'POST',
						data: { source: value },
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
		}, PREVIEW_DEBOUNCE_MS );

		return () => {
			window.clearTimeout( timer );
			previewRequest.cancel();
		};
	}, [ postId, previewRequest, prompt, source ] );

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

	const saveUnavailableNotice = (
		<Notice status="warning" isDismissible={ false }>
			{ __(
				'Markdown changes are kept only for this editing session. Closing the workspace discards them; the original Gutenberg content remains unchanged.',
				'universal-markdown-editor'
			) }
		</Notice>
	);

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
							onClick={ () => beginEditing( initialContent ) }
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
					<div className="ume-workspace-toolbar">
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
						<Button variant="secondary" onClick={ requestClose }>
							{ __(
								'Exit Markdown mode',
								'universal-markdown-editor'
							) }
						</Button>
					</div>
					{ saveUnavailableNotice }
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
							<label htmlFor="ume-markdown-source">
								{ __(
									'Markdown source',
									'universal-markdown-editor'
								) }
							</label>
							<textarea
								id="ume-markdown-source"
								value={ source }
								onChange={ ( event ) =>
									setSource( event.target.value )
								}
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
							<PreviewFrame html={ html } />
						</section>
					</div>
				</div>
			</Modal>
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

	let initialPrompt = '';
	if (
		isOpen &&
		editorState.status !== 'auto-draft' &&
		editorState.content.trim() !== ''
	) {
		initialPrompt = isComplexContent( editorState.content )
			? 'complex'
			: 'import';
	}

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
					initialPrompt={ initialPrompt }
					onClose={ () => setIsOpen( false ) }
				/>
			) }
		</>
	);
}

registerPlugin( 'ume-markdown-editor', { render: MarkdownEditorPlugin } );
