import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	RichText,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, Button } from '@wordpress/components';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import { ResponsiveRangeControl } from '../../utils/inspector-panels';
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

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const blockProps = useBlockProps( { className: 'container-fluid' } );

	const { maxWidth, maxWidthTablet, maxWidthMobile, contentAlign, items } =
		attributes;

	const maxWidthStyle = {
		'--mw-pc': `${ maxWidth }%`,
		'--mw-tablet': `${ maxWidthTablet }%`,
		'--mw-mobile': `${ maxWidthMobile }%`,
		margin: MARGIN_MAP[ contentAlign ] || MARGIN_MAP.center,
	};

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Timeline', 'laca' ) }
				title={ __( 'Cột mốc thời gian', 'laca' ) }
				columns={ 1 }
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
		setAttributes( {
			items: [ ...items, { year: '', title: '', desc: '', content: '' } ],
		} );
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
				</PanelBody>

				<PanelBody
					title={ __( 'Vị trí khung', 'laca' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Vị trí khung', 'laca' ) }
						value={ contentAlign }
						options={ [
							{ label: __( 'Trái', 'laca' ), value: 'left' },
							{ label: __( 'Giữa', 'laca' ), value: 'center' },
							{ label: __( 'Phải', 'laca' ), value: 'right' },
						] }
						onChange={ ( v ) =>
							setAttributes( { contentAlign: v } )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Các mốc thời gian', 'laca' ) }
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
							'Nhập trực tiếp trong khung soạn thảo. Các mốc cùng năm liên tiếp sẽ tự động chỉ hiện năm ở mốc đầu tiên, không cần ẩn tay.',
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
							{ __( 'Xóa mốc', 'laca' ) } { index + 1 }
						</Button>
					) ) }
					<Button variant="primary" onClick={ addItem }>
						{ __( '+ Thêm mốc', 'laca' ) }
					</Button>
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps }>
				<div
					className="block-timeline__maxwidth"
					style={ maxWidthStyle }
				>
					<div className="block-timeline__list">
						{ items.map( ( item, index ) => {
							const prevYear =
								index > 0 ? items[ index - 1 ].year : null;
							const isRepeatYear =
								item.year !== '' && item.year === prevYear;
							return (
								<div
									className="block-timeline__row"
									key={ index }
								>
									<div
										className={
											'block-timeline__year' +
											( isRepeatYear
												? ' block-timeline__year--muted'
												: '' )
										}
										title={
											isRepeatYear
												? __(
														'Trùng năm với mốc trước — sẽ tự ẩn ở frontend',
														'laca'
												  )
												: ''
										}
									>
										<RichText
											tagName="span"
											value={ item.year }
											onChange={ ( v ) =>
												updateItem( index, 'year', v )
											}
											placeholder={ __( 'Năm…', 'laca' ) }
											allowedFormats={ [] }
										/>
									</div>
									<div className="block-timeline__meta">
										<RichText
											tagName="h3"
											className="block-timeline__title"
											value={ item.title }
											onChange={ ( v ) =>
												updateItem( index, 'title', v )
											}
											placeholder={ __(
												'Tiêu đề…',
												'laca'
											) }
											allowedFormats={ [] }
										/>
										<RichText
											tagName="p"
											className="block-timeline__desc"
											value={ item.desc }
											onChange={ ( v ) =>
												updateItem( index, 'desc', v )
											}
											placeholder={ __(
												'Mô tả ngắn…',
												'laca'
											) }
										/>
									</div>
									<div className="block-timeline__content">
										<RichText
											tagName="p"
											value={ item.content }
											onChange={ ( v ) =>
												updateItem(
													index,
													'content',
													v
												)
											}
											placeholder={ __(
												'Nội dung chi tiết…',
												'laca'
											) }
										/>
									</div>
								</div>
							);
						} ) }
					</div>
				</div>
			</section>
		</>
	);
}
