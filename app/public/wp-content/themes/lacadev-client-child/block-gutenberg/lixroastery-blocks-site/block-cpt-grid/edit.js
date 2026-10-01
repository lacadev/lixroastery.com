import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RadioControl,
	RangeControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import ServerSideRender from '@wordpress/server-side-render';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import {
	ResponsiveRangeControl,
	ResponsiveSelectControl,
} from '../../utils/inspector-panels';

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

const EXCLUDED_POST_TYPES = [
	'attachment',
	'wp_block',
	'wp_template',
	'wp_template_part',
	'wp_navigation',
	'wp_font_family',
	'wp_font_face',
	'nav_menu_item',
];

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const blockProps = useBlockProps();

	const {
		maxWidth,
		maxWidthTablet,
		maxWidthMobile,
		contentAlign,
		contentAlignTablet,
		contentAlignMobile,
		postType,
		taxonomy,
		columns,
		paginationMode,
		perPagePC,
		perPageMobile,
		minCount,
	} = attributes;

	const postTypes = useSelect( ( select ) => {
		const types = select( 'core' ).getPostTypes( { per_page: -1 } );
		if ( ! types ) {
			return [];
		}
		return types.filter(
			( type ) =>
				type.viewable && ! EXCLUDED_POST_TYPES.includes( type.slug )
		);
	}, [] );

	const taxonomies = useSelect(
		( select ) => {
			if ( ! postType ) {
				return [];
			}
			const taxes = select( 'core' ).getTaxonomies( {
				type: postType,
				per_page: -1,
			} );
			return taxes || [];
		},
		[ postType ]
	);

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'CPT Grid', 'laca' ) }
				title={ __( 'Danh sách bài viết theo Post Type', 'laca' ) }
				columns={ 3 }
			/>
		);
	}

	const postTypeOptions = postTypes.map( ( type ) => ( {
		label: type.name,
		value: type.slug,
	} ) );

	const taxonomyOptions = [
		{ label: __( '— Không dùng tab lọc —', 'laca' ), value: '' },
		...taxonomies.map( ( tax ) => ( {
			label: tax.name,
			value: tax.slug,
		} ) ),
	];

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
						label={ __( 'Số cột', 'laca' ) }
						value={ columns }
						onChange={ ( v ) => setAttributes( { columns: v } ) }
						min={ 1 }
						max={ 4 }
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
					title={ __( 'Nguồn dữ liệu', 'laca' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Post Type', 'laca' ) }
						value={ postType }
						options={
							postTypeOptions.length
								? postTypeOptions
								: [
										{
											label: __( 'Đang tải…', 'laca' ),
											value: postType,
										},
								  ]
						}
						onChange={ ( v ) =>
							setAttributes( { postType: v, taxonomy: '' } )
						}
					/>
					<SelectControl
						label={ __( 'Taxonomy hiện tab lọc', 'laca' ) }
						help={ __(
							'Sẽ hiện tất cả danh mục thuộc taxonomy này dạng tab (ALL + từng danh mục).',
							'laca'
						) }
						value={ taxonomy }
						options={ taxonomyOptions }
						onChange={ ( v ) => setAttributes( { taxonomy: v } ) }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Phân trang', 'laca' ) }
					initialOpen={ true }
				>
					<RadioControl
						label={ __( 'Kiểu phân trang', 'laca' ) }
						selected={ paginationMode }
						options={ [
							{
								label: __( 'Đánh số trang', 'laca' ),
								value: 'numbered',
							},
							{
								label: __(
									'Tự tải thêm khi scroll (load more)',
									'laca'
								),
								value: 'load-more',
							},
						] }
						onChange={ ( v ) =>
							setAttributes( { paginationMode: v } )
						}
					/>

					{ paginationMode === 'numbered' && (
						<>
							<TextControl
								type="number"
								label={ __( 'Số bài / trang (PC)', 'laca' ) }
								value={ perPagePC }
								min={ 1 }
								onChange={ ( v ) =>
									setAttributes( {
										perPagePC: parseInt( v, 10 ) || 1,
									} )
								}
							/>
							<TextControl
								type="number"
								label={ __(
									'Số bài / trang (Mobile)',
									'laca'
								) }
								help={ __(
									'Để trống hoặc 0 = dùng chung số của PC.',
									'laca'
								) }
								value={ perPageMobile || '' }
								min={ 0 }
								onChange={ ( v ) =>
									setAttributes( {
										perPageMobile: parseInt( v, 10 ) || 0,
									} )
								}
							/>
						</>
					) }

					{ paginationMode === 'load-more' && (
						<TextControl
							type="number"
							label={ __(
								'Số bài tối thiểu (ban đầu + mỗi lần tải thêm)',
								'laca'
							) }
							value={ minCount }
							min={ 1 }
							onChange={ ( v ) =>
								setAttributes( {
									minCount: parseInt( v, 10 ) || 1,
								} )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<ServerSideRender
					block="lacadev/cpt-grid-block"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
