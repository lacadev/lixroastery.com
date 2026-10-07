/**
 * Panel "Dịch bằng AI" — tự động gắn vào InspectorControls của MỌI block
 * custom có khai báo attribute "translatable": true trong block.json (xem
 * lacadev_get_translatable_block_attrs() ở theme/setup/gutenberg-blocks.php
 * và AITranslationParser::getTranslatableMap()). Không cần sửa code riêng
 * từng block — tạo block mới chỉ cần đánh dấu đúng attribute là tự động có
 * panel này.
 *
 * Ngôn ngữ đích lấy từ Polylang thật của site (window.lacaAITranslate.langs,
 * xem AITranslationManager::localizeBlockEditorScript()): 0 ngôn ngữ (site
 * 1 ngôn ngữ/Polylang tắt) → ẩn hẳn panel; 1 ngôn ngữ → nút dịch thẳng,
 * không cần chọn; từ 2 ngôn ngữ trở lên → dropdown chọn ngôn ngữ đích.
 */
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, PanelRow, SelectControl, Button, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';

const AITranslatePanel = ( { clientId, name, attributes } ) => {
	const aiData = window.lacaAITranslate || null;
	const targetLangs = aiData?.langs || [];

	const [ targetLang, setTargetLang ] = useState( targetLangs[ 0 ]?.value || '' );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ statusMsg, setStatusMsg ] = useState( '' );
	const [ isError, setIsError ] = useState( false );

	const { updateBlockAttributes } = useDispatch( 'core/block-editor' );

	if ( ! aiData || ! targetLangs.length ) {
		return null;
	}

	const handleTranslate = () => {
		if ( ! targetLang ) {
			return;
		}

		setIsLoading( true );
		setStatusMsg( '' );
		setIsError( false );

		const formData = new URLSearchParams( {
			action: 'lacadev_ai_translate_block',
			nonce: aiData.nonce,
			source_lang: 'auto',
			target_lang: targetLang,
			block_data: JSON.stringify( { blockName: name, attrs: attributes, innerHTML: '' } ),
		} );

		window
			.fetch( aiData.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: formData,
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

	const currentLabel = targetLangs.find( ( l ) => l.value === targetLang )?.label || '';

	return (
		<InspectorControls>
			<PanelBody title={ __( '✨ Dịch bằng AI', 'laca' ) } initialOpen={ false }>
				{ targetLangs.length > 1 && (
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
				) }

				{ statusMsg && (
					<PanelRow>
						<p
							style={ {
								color: isError ? '#cc1818' : '#1a7a1a',
								margin: '0 0 8px',
								fontSize: '12px',
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
						disabled={ isLoading || ! targetLang }
						onClick={ handleTranslate }
						style={ { width: '100%', justifyContent: 'center' } }
					>
						{ isLoading ? (
							<>
								<Spinner />
								&nbsp;{ __( 'Đang dịch…', 'laca' ) }
							</>
						) : targetLangs.length === 1 ? (
							sprintf( __( '✨ Dịch sang %s', 'laca' ), currentLabel )
						) : (
							__( '✨ Dịch ngay', 'laca' )
						) }
					</Button>
				</PanelRow>
			</PanelBody>
		</InspectorControls>
	);
};

const withAITranslate = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const translatableBlocks = window.lacaAITranslate?.translatableBlocks || [];

		if ( ! translatableBlocks.includes( props.name ) ) {
			return <BlockEdit { ...props } />;
		}

		return (
			<>
				<BlockEdit { ...props } />
				<AITranslatePanel clientId={ props.clientId } name={ props.name } attributes={ props.attributes } />
			</>
		);
	};
}, 'withAITranslate' );

addFilter( 'editor.BlockEdit', 'laca-ai-translate/with-ai-translate', withAITranslate );
