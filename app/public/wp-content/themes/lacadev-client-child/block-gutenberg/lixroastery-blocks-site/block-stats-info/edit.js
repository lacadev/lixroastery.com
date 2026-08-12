import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	RichText,
} from '@wordpress/block-editor';
import {
	PanelBody,
	Button,
	ColorPicker,
	RangeControl,
} from '@wordpress/components';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import { hexToRgba } from '../../utils/style';

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const blockProps = useBlockProps();

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Stats Info', 'laca' ) }
				title={ __( 'Chỉ số nổi bật', 'laca' ) }
				columns={ 4 }
			/>
		);
	}

	const {
		items,
		numberColor,
		labelColor,
		descriptionColor,
		bgColor,
		bgOpacity,
	} = attributes;

	const updateItem = ( index, field, value ) => {
		const next = [ ...items ];
		next[ index ] = { ...next[ index ], [ field ]: value };
		setAttributes( { items: next } );
	};

	const removeItem = ( index ) => {
		setAttributes( { items: items.filter( ( _, i ) => i !== index ) } );
	};

	const addItem = () => {
		setAttributes( {
			items: [ ...items, { number: '+10', label: '', description: '' } ],
		} );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Danh sách chỉ số', 'laca' ) }
					initialOpen={ true }
				>
					<p style={ { fontSize: 12, color: '#666' } }>
						{ __(
							'Nội dung từng chỉ số sửa trực tiếp trong khung soạn thảo. Ở đây chỉ để thêm/xóa mục.',
							'laca'
						) }
					</p>
					{ items.map( ( item, index ) => (
						<Button
							key={ index }
							variant="secondary"
							isDestructive
							style={ { marginBottom: 6, marginRight: 6 } }
							onClick={ () => removeItem( index ) }
						>
							{ __( 'Xóa mục', 'laca' ) } { index + 1 }
						</Button>
					) ) }
					<div>
						<Button variant="primary" onClick={ addItem }>
							{ __( '+ Thêm chỉ số', 'laca' ) }
						</Button>
					</div>
				</PanelBody>

				<PanelBody
					title={ __( 'Giao diện', 'laca' ) }
					initialOpen={ false }
				>
					<p
						style={ {
							fontSize: '0.8rem',
							fontWeight: 600,
							marginBottom: '0.5rem',
						} }
					>
						{ __( 'Màu số', 'laca' ) }
					</p>
					<ColorPicker
						color={ numberColor }
						onChange={ ( v ) =>
							setAttributes( { numberColor: v } )
						}
						enableAlpha={ false }
					/>
					<p
						style={ {
							fontSize: '0.8rem',
							fontWeight: 600,
							margin: '1rem 0 0.5rem',
						} }
					>
						{ __( 'Màu nhãn', 'laca' ) }
					</p>
					<ColorPicker
						color={ labelColor }
						onChange={ ( v ) => setAttributes( { labelColor: v } ) }
						enableAlpha={ false }
					/>
					<p
						style={ {
							fontSize: '0.8rem',
							fontWeight: 600,
							margin: '1rem 0 0.5rem',
						} }
					>
						{ __( 'Màu mô tả', 'laca' ) }
					</p>
					<ColorPicker
						color={ descriptionColor }
						onChange={ ( v ) =>
							setAttributes( { descriptionColor: v } )
						}
						enableAlpha={ false }
					/>
					<p
						style={ {
							fontSize: '0.8rem',
							fontWeight: 600,
							margin: '1rem 0 0.5rem',
						} }
					>
						{ __( 'Màu nền section', 'laca' ) }
					</p>
					<ColorPicker
						color={ bgColor }
						onChange={ ( v ) => setAttributes( { bgColor: v } ) }
						enableAlpha={ false }
					/>
					<RangeControl
						label={ __( 'Độ mờ nền (%)', 'laca' ) }
						value={ bgOpacity }
						min={ 0 }
						max={ 100 }
						step={ 5 }
						onChange={ ( v ) => setAttributes( { bgOpacity: v } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<section
				{ ...blockProps }
				style={ {
					...blockProps.style,
					background: hexToRgba( bgColor, bgOpacity ),
				} }
			>
				<div className="container">
					<div className="block-stats-info__grid">
						{ items.map( ( item, index ) => (
							<div
								className="block-stats-info__item"
								key={ index }
							>
								<div
									className="block-stats-info__number"
									style={ { color: numberColor } }
								>
									<RichText
										tagName="span"
										value={ item.number }
										onChange={ ( v ) =>
											updateItem( index, 'number', v )
										}
										placeholder="+10"
										allowedFormats={ [] }
									/>
								</div>
								<RichText
									tagName="div"
									className="block-stats-info__label"
									style={ { color: labelColor } }
									value={ item.label }
									onChange={ ( v ) =>
										updateItem( index, 'label', v )
									}
									placeholder={ __( 'Nhãn…', 'laca' ) }
									allowedFormats={ [] }
								/>
								<RichText
									tagName="p"
									className="block-stats-info__desc"
									style={ { color: descriptionColor } }
									value={ item.description }
									onChange={ ( v ) =>
										updateItem( index, 'description', v )
									}
									placeholder={ __( 'Mô tả…', 'laca' ) }
								/>
							</div>
						) ) }
					</div>
				</div>
			</section>
		</>
	);
}
