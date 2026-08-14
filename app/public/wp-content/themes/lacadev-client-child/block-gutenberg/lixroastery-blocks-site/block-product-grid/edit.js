import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import {
	PanelBody,
	TextControl,
	SelectControl,
	RangeControl,
	CheckboxControl,
	RadioControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import ServerSideRender from '@wordpress/server-side-render';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import previewImage from './preview.png';

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const blockProps = useBlockProps();

	const {
		sectionTitle,
		viewAllText,
		viewAllLink,
		columns,
		mode,
		autoQuery,
		autoCount,
		selectedProducts,
	} = attributes;

	const [ productSearch, setProductSearch ] = useState( '' );

	// ── Thủ công: tìm sản phẩm theo tên (post type cố định 'product') ───────
	const manualProducts = useSelect(
		( select ) => {
			if ( mode !== 'manual' ) {
				return [];
			}
			return (
				select( 'core' ).getEntityRecords( 'postType', 'product', {
					per_page: 50,
					status: 'publish',
					search: productSearch || undefined,
				} ) || []
			);
		},
		[ mode, productSearch ]
	);

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Product Grid', 'laca' ) }
				title={ sectionTitle || __( 'Danh sách sản phẩm', 'laca' ) }
				columns={ columns || 4 }
				image={ previewImage }
			/>
		);
	}

	const toggleId = ( arr, id ) =>
		arr.includes( id ) ? arr.filter( ( x ) => x !== id ) : [ ...arr, id ];

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Hiển thị', 'laca' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'Tiêu đề section', 'laca' ) }
						value={ sectionTitle }
						onChange={ ( v ) => setAttributes( { sectionTitle: v } ) }
					/>
					<TextControl
						label={ __( 'Text "Xem tất cả"', 'laca' ) }
						value={ viewAllText }
						onChange={ ( v ) => setAttributes( { viewAllText: v } ) }
					/>
					<TextControl
						label={ __( 'Đường dẫn "Xem tất cả"', 'laca' ) }
						value={ viewAllLink }
						onChange={ ( v ) => setAttributes( { viewAllLink: v } ) }
						placeholder={ __( 'Để trống = trang Shop', 'laca' ) }
					/>
					<RangeControl
						label={ __( 'Số cột', 'laca' ) }
						value={ columns }
						min={ 2 }
						max={ 4 }
						onChange={ ( v ) => setAttributes( { columns: v } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Nguồn sản phẩm', 'laca' ) } initialOpen={ true }>
					<RadioControl
						label={ __( 'Chế độ', 'laca' ) }
						selected={ mode }
						options={ [
							{ label: __( 'Tự động', 'laca' ), value: 'auto' },
							{ label: __( 'Thủ công (tìm & chọn tay)', 'laca' ), value: 'manual' },
						] }
						onChange={ ( v ) => setAttributes( { mode: v } ) }
					/>

					{ mode === 'auto' && (
						<>
							<SelectControl
								label={ __( 'Kiểu tự động', 'laca' ) }
								value={ autoQuery }
								options={ [
									{ label: __( 'Mới nhất', 'laca' ), value: 'newest' },
									{ label: __( 'Bán chạy', 'laca' ), value: 'best_selling' },
									{ label: __( 'Đang giảm giá', 'laca' ), value: 'on_sale' },
									{ label: __( 'Nổi bật (Featured)', 'laca' ), value: 'featured' },
									{ label: __( 'Ngẫu nhiên', 'laca' ), value: 'random' },
								] }
								onChange={ ( v ) => setAttributes( { autoQuery: v } ) }
							/>
							<RangeControl
								label={ __( 'Số lượng sản phẩm', 'laca' ) }
								value={ autoCount }
								min={ 1 }
								max={ 20 }
								onChange={ ( v ) => setAttributes( { autoCount: v } ) }
							/>
						</>
					) }

					{ mode === 'manual' && (
						<>
							<p style={ { fontSize: '11px', color: '#666', margin: '4px 0 8px' } }>
								{ __( 'Đã chọn: ', 'laca' ) }
								<strong>{ selectedProducts.length }</strong>
							</p>
							<TextControl
								label={ __( 'Tìm sản phẩm', 'laca' ) }
								value={ productSearch }
								onChange={ setProductSearch }
								placeholder={ __( 'Nhập tên sản phẩm…', 'laca' ) }
							/>
							<div
								style={ {
									maxHeight: '240px',
									overflowY: 'auto',
									border: '1px solid #ddd',
									borderRadius: '4px',
									padding: '4px 8px',
								} }
							>
								{ manualProducts.map( ( product ) => (
									<CheckboxControl
										key={ product.id }
										label={ product.title?.rendered || `#${ product.id }` }
										checked={ selectedProducts.includes( product.id ) }
										onChange={ () =>
											setAttributes( {
												selectedProducts: toggleId( selectedProducts, product.id ),
											} )
										}
									/>
								) ) }
							</div>
						</>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<ServerSideRender block="lacadev/product-grid-block" attributes={ attributes } />
			</div>
		</>
	);
}
