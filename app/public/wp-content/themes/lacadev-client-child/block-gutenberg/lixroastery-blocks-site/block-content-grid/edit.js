import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	RichText,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl, Button } from '@wordpress/components';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import {
	ResponsiveRangeControl,
	ResponsiveSelectControl,
} from '../../utils/inspector-panels';
import previewImage from './preview.png';

// Section ngoài LUÔN container-fluid — độ rộng NỘI DUNG điều chỉnh riêng qua
// maxWidth (%) + contentAlign, giống hệt block Container (block-container).
const MARGIN_MAP = {
	left: '0 auto 0 0',
	center: '0 auto',
	right: '0 0 0 auto',
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
	const blockProps = useBlockProps( { className: 'container-fluid' } );

	const {
		columns,
		maxWidth,
		maxWidthTablet,
		maxWidthMobile,
		contentAlign,
		contentAlignTablet,
		contentAlignMobile,
		items,
	} = attributes;

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

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Content Grid', 'laca' ) }
				title={ __( 'Danh sách nội dung', 'laca' ) }
				columns={ columns || 2 }
				image={ previewImage }
			/>
		);
	}

	const updateItem = ( index, field, value ) => {
		const next = [ ...items ];
		next[ index ] = { ...next[ index ], [ field ]: value };
		setAttributes( { items: next } );
	};

	const removeItem = ( index ) => {
		setAttributes( { items: items.filter( ( _, i ) => i !== index ) } );
	};

	const addItem = () => {
		setAttributes( { items: [ ...items, { title: '', desc: '' } ] } );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Bố cục', 'laca' ) }
					initialOpen={ true }
				>
					<RangeControl
						label={ __( 'Số cột', 'laca' ) }
						value={ columns }
						min={ 1 }
						max={ 4 }
						onChange={ ( v ) => setAttributes( { columns: v } ) }
					/>
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
				</PanelBody>

				<PanelBody
					title={ __( 'Vị trí khung', 'laca' ) }
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
				</PanelBody>

				<PanelBody
					title={ __( 'Danh sách mục', 'laca' ) }
					initialOpen={ true }
				>
					<p
						style={ {
							fontSize: '11px',
							color: '#666',
							margin: '4px 0 8px',
						} }
					>
						{ __(
							'Tiêu đề và mô tả sửa trực tiếp trong khung soạn thảo.',
							'laca'
						) }
					</p>
					{ items.map( ( item, index ) => (
						<Button
							key={ index }
							variant="secondary"
							isDestructive
							style={ { marginBottom: 8, marginRight: 8 } }
							onClick={ () => removeItem( index ) }
						>
							{ __( 'Xóa mục', 'laca' ) } { index + 1 }
						</Button>
					) ) }
					<Button variant="primary" onClick={ addItem }>
						{ __( '+ Thêm mục', 'laca' ) }
					</Button>
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps }>
				<div
					className="block-content-grid__maxwidth"
					style={ maxWidthStyle }
				>
					<div
						className="block-content-grid__grid"
						style={ { '--ctg-columns': columns } }
					>
						{ items.map( ( item, index ) => (
							<div
								className="block-content-grid__item"
								key={ index }
							>
								<hr className="block-content-grid__rule" />
								<RichText
									tagName="h3"
									className="block-content-grid__title"
									value={ item.title }
									onChange={ ( v ) =>
										updateItem( index, 'title', v )
									}
									placeholder={ __( 'Tiêu đề…', 'laca' ) }
									allowedFormats={ [] }
								/>
								<RichText
									tagName="p"
									className="block-content-grid__desc"
									value={ item.desc }
									onChange={ ( v ) =>
										updateItem( index, 'desc', v )
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
