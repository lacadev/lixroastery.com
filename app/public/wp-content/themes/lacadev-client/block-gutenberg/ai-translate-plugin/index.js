/**
 * LaCa AI Translate Plugin (Block Extension)
 *
 * Extends supported Gutenberg blocks to include an AI Translate panel
 * inside the InspectorControls (sidebar).
 *
 * @requires window.lacaAITranslate
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	PanelRow,
	SelectControl,
	Button,
	Spinner,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/** Blocks that support per-block AI translation */
const TRANSLATABLE_BLOCKS = [
	// LaCa custom blocks
	'lacadev/slogan-block',
	'lacadev/about-laca-block',
	'lacadev/service-block',
	'lacadev/button-block',
	'lacadev/statement-block',
	'lacadev/blog-block',
	'lacadev/staggered-blog-block',
	'lacadev/process-block',
	'lacadev/project-block',
	'lacadev/marquee-block',
	'lacadev/tech-list-block',
	'lacadev/workflow-block',
	// WordPress core text blocks
	'core/paragraph',
	'core/heading',
	'core/list',
	'core/list-item',
	'core/quote',
	'core/table',
	'core/button',
	'core/image',
	'core/freeform',
];

/**
 * AI Translate Sidebar Panel Component
 */
const AITranslatePanel = ( { clientId, name, attributes } ) => {
	const [ sourceLang, setSourceLang ] = useState( 'auto' );
	const [ targetLang, setTargetLang ] = useState( 'en' );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ statusMsg, setStatusMsg ] = useState( '' );
	const [ isError, setIsError ] = useState( false );

	const { updateBlockAttributes } = useDispatch( 'core/block-editor' );

	const aiData = window.lacaAITranslate || null;
	if ( ! aiData ) {
		return null;
	}

	const allLangs    = aiData.langs || [];
	const targetLangs = allLangs.filter( ( l ) => l.value !== 'auto' );

	const handleTranslate = () => {
		setIsLoading( true );
		setStatusMsg( '' );
		setIsError( false );

		const blockPayload = {
			blockName: name,
			attrs:     attributes,
			innerHTML: ''
		};

		const formData = new URLSearchParams( {
			action:      'lacadev_ai_translate_block',
			nonce:       aiData.nonce,
			source_lang: sourceLang,
			target_lang: targetLang,
			block_data:  JSON.stringify( blockPayload ),
		} );

		window
			.fetch( aiData.ajaxUrl, {
				method:  'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body:    formData,
			} )
			.then( ( res ) => res.json() )
			.then( ( json ) => {
				setIsLoading( false );

				if ( ! json.success ) {
					setIsError( true );
					setStatusMsg( json.data || __( 'Lỗi không xác định.', 'laca' ) );
					return;
				}

				const { attrs } = json.data;

				if ( attrs && Object.keys( attrs ).length > 0 ) {
					updateBlockAttributes( clientId, attrs );
				}

				setStatusMsg( __( '✅ Đã dịch thành công!', 'laca' ) );
			} )
			.catch( () => {
				setIsLoading( false );
				setIsError( true );
				setStatusMsg( __( 'Lỗi kết nối. Vui lòng thử lại.', 'laca' ) );
			} );
	};

	return (
		<InspectorControls>
			<PanelBody
				title={ __( '✨ Dịch bằng AI', 'laca' ) }
				initialOpen={ false }
			>
				<PanelRow>
					<SelectControl
						label={ __( 'Ngôn ngữ gốc', 'laca' ) }
						value={ sourceLang }
						options={ allLangs }
						onChange={ ( val ) => {
							setSourceLang( val );
							setStatusMsg( '' );
						} }
					/>
				</PanelRow>

				<PanelRow>
					<SelectControl
						label={ __( 'Ngôn ngữ đích', 'laca' ) }
						value={ targetLang }
						options={ targetLangs }
						onChange={ ( val ) => {
							setTargetLang( val );
							setStatusMsg( '' );
						} }
					/>
				</PanelRow>

				{ statusMsg && (
					<PanelRow>
						<p
							style={ {
								color:      isError ? '#cc1818' : '#1a7a1a',
								margin:     '0 0 8px',
								fontSize:   '12px',
								lineHeight: '1.5',
							} }
						>
							{ statusMsg }
						</p>
					</PanelRow>
				) }

				<PanelRow>
					<Button
						variant="primary"
						isBusy={ isLoading }
						disabled={ isLoading }
						onClick={ handleTranslate }
						style={ { width: '100%', justifyContent: 'center' } }
					>
						{ isLoading ? (
							<>
								<Spinner />
								&nbsp;{ __( 'Đang dịch…', 'laca' ) }
							</>
						) : (
							__( '✨ Dịch ngay', 'laca' )
						) }
					</Button>
				</PanelRow>
			</PanelBody>
		</InspectorControls>
	);
};

/**
 * Filter to wrap blocks and add InspectorControls
 */
const withAITranslate = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		// If block isn't supported, just return the normal BlockEdit
		if ( ! TRANSLATABLE_BLOCKS.includes( props.name ) ) {
			return <BlockEdit { ...props } />;
		}

		return (
			<>
				<BlockEdit { ...props } />
				<AITranslatePanel
					clientId={ props.clientId }
					name={ props.name }
					attributes={ props.attributes }
				/>
			</>
		);
	};
}, 'withAITranslate' );

addFilter(
	'editor.BlockEdit',
	'laca-ai-translate/with-ai-translate',
	withAITranslate
);
