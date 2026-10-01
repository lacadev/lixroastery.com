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
import {
	ResponsiveSelectControl,
	ResponsiveRangeControl,
} from '../../utils/inspector-panels';
import previewImage from './preview.png';

const ALIGN_OPTIONS = [
	{ label: __( 'Trái', 'laca' ), value: 'left' },
	{ label: __( 'Giữa', 'laca' ), value: 'center' },
	{ label: __( 'Phải', 'laca' ), value: 'right' },
	{ label: __( 'Căn đều (Justify)', 'laca' ), value: 'justify' },
];

const MARGIN_MAP = {
	left: '0 auto 0 0',
	center: '0 auto',
	right: '0 0 0 auto',
};

// Map device → tên attribute tương ứng — tránh viết ternary lồng nhau trong
// onChange (eslint no-nested-ternary).
const TITLE_ALIGN_KEYS = {
	pc: 'titleAlign',
	tablet: 'titleAlignTablet',
	mobile: 'titleAlignMobile',
};
const DESCRIPTION_ALIGN_KEYS = {
	pc: 'descriptionAlign',
	tablet: 'descriptionAlignTablet',
	mobile: 'descriptionAlignMobile',
};
const MAX_WIDTH_KEYS = {
	pc: 'maxWidth',
	tablet: 'maxWidthTablet',
	mobile: 'maxWidthMobile',
};

const CONTENT_ALIGN_KEYS = {
	pc: 'contentAlign',
	tablet: 'contentAlignTablet',
	mobile: 'contentAlignMobile',
};

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const blockProps = useBlockProps();

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Stats Info', 'laca' ) }
				title={ __( 'Chỉ số nổi bật', 'laca' ) }
				columns={ 4 }
				image={ previewImage }
			/>
		);
	}

	const {
		maxWidth,
		maxWidthTablet,
		maxWidthMobile,
		contentAlign,
		contentAlignTablet,
		contentAlignMobile,
		items,
		columns,
		titleAlign,
		titleAlignTablet,
		titleAlignMobile,
		descriptionAlign,
		descriptionAlignTablet,
		descriptionAlignMobile,
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

	const maxWidthStyle = {
		'--mw-pc': `${ maxWidth }%`,
		'--mw-tablet': `${ maxWidthTablet }%`,
		'--mw-mobile': `${ maxWidthMobile }%`,
		'--align-margin-pc': MARGIN_MAP[ contentAlign ] || MARGIN_MAP.center,
		'--align-margin-tablet':
			MARGIN_MAP[ contentAlignTablet ] || MARGIN_MAP.center,
		'--align-margin-mobile':
			MARGIN_MAP[ contentAlignMobile ] || MARGIN_MAP.center,
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Bố cục', 'laca' ) }
					initialOpen={ true }
				>
					<ResponsiveRangeControl
						label={ __( 'Kích thước tối đa (%)', 'laca' ) }
						help={ __(
							'Mobile mặc định full width — chỉnh riêng nếu muốn khác.',
							'laca'
						) }
						min={ 10 }
						max={ 100 }
						valuesByDevice={ {
							pc: maxWidth,
							tablet: maxWidthTablet,
							mobile: maxWidthMobile,
						} }
						onChange={ ( device, v ) =>
							setAttributes( { [ MAX_WIDTH_KEYS[ device ] ]: v } )
						}
					/>
					<RangeControl
						label={ __( 'Số lượng / hàng', 'laca' ) }
						value={ columns }
						min={ 1 }
						max={ 6 }
						onChange={ ( v ) => setAttributes( { columns: v } ) }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Căn lề', 'laca' ) }
					initialOpen={ true }
				>
					<ResponsiveSelectControl
						label={ __( 'Vị trí khung', 'laca' ) }
						options={ [
							{ label: __( 'Trái', 'laca' ), value: 'left' },
							{ label: __( 'Giữa', 'laca' ), value: 'center' },
							{ label: __( 'Phải', 'laca' ), value: 'right' },
						] }
						valuesByDevice={ {
							pc: contentAlign,
							tablet: contentAlignTablet,
							mobile: contentAlignMobile,
						} }
						onChange={ ( device, v ) =>
							setAttributes( {
								[ CONTENT_ALIGN_KEYS[ device ] ]: v,
							} )
						}
					/>
					<ResponsiveSelectControl
						label={ __( 'Căn lề tiêu đề (số + nhãn)', 'laca' ) }
						options={ ALIGN_OPTIONS }
						valuesByDevice={ {
							pc: titleAlign,
							tablet: titleAlignTablet,
							mobile: titleAlignMobile,
						} }
						onChange={ ( device, v ) =>
							setAttributes( {
								[ TITLE_ALIGN_KEYS[ device ] ]: v,
							} )
						}
					/>
					<ResponsiveSelectControl
						label={ __( 'Căn lề mô tả', 'laca' ) }
						options={ ALIGN_OPTIONS }
						valuesByDevice={ {
							pc: descriptionAlign,
							tablet: descriptionAlignTablet,
							mobile: descriptionAlignMobile,
						} }
						onChange={ ( device, v ) =>
							setAttributes( {
								[ DESCRIPTION_ALIGN_KEYS[ device ] ]: v,
							} )
						}
					/>
				</PanelBody>

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
				<div className="container-fluid">
					<div
						className="block-stats-info__maxwidth"
						style={ maxWidthStyle }
					>
						<div
							className="block-stats-info__grid"
							style={ {
								'--stats-columns': columns,
								'--stats-title-align-pc': titleAlign,
								'--stats-title-align-tablet': titleAlignTablet,
								'--stats-title-align-mobile': titleAlignMobile,
								'--stats-desc-align-pc': descriptionAlign,
								'--stats-desc-align-tablet':
									descriptionAlignTablet,
								'--stats-desc-align-mobile':
									descriptionAlignMobile,
							} }
						>
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
											updateItem(
												index,
												'description',
												v
											)
										}
										placeholder={ __( 'Mô tả…', 'laca' ) }
									/>
								</div>
							) ) }
						</div>
					</div>
				</div>
			</section>
		</>
	);
}
