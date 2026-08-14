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
import { useState, useEffect } from '@wordpress/element';
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
		postType,
		taxonomy,
		selectedTerms,
		mode,
		orderBy,
		order,
		postsCount,
		selectedPosts,
		columns,
	} = attributes;

	const [ postSearch, setPostSearch ] = useState( '' );

	// ── Post types ─────────────────────────────────────────────────────────
	const postTypes = useSelect( ( select ) => {
		const types = select( 'core' ).getPostTypes( { per_page: -1 } );
		if ( ! types ) {
			return [];
		}
		return types
			.filter(
				( t ) =>
					t.viewable &&
					! [
						'attachment',
						'wp_block',
						'wp_template',
						'wp_template_part',
						'wp_navigation',
						'wp_font_family',
						'wp_font_face',
					].includes( t.slug )
			)
			.map( ( t ) => ( { label: t.name, value: t.slug } ) );
	}, [] );

	// ── Taxonomies theo postType ───────────────────────────────────────────
	const taxonomies = useSelect(
		( select ) => {
			const types = select( 'core' ).getPostTypes( { per_page: -1 } );
			if ( ! types ) {
				return [];
			}
			const current = types.find( ( t ) => t.slug === postType );
			if ( ! current ) {
				return [];
			}
			return ( current.taxonomies || [] ).map( ( slug ) => {
				const tax = select( 'core' ).getTaxonomy( slug );
				return { label: tax ? tax.name : slug, value: slug };
			} );
		},
		[ postType ]
	);

	// ── Terms theo taxonomy ──────────────────────────────────────────────
	const terms = useSelect(
		( select ) => {
			if ( ! taxonomy ) {
				return [];
			}
			return (
				select( 'core' ).getEntityRecords( 'taxonomy', taxonomy, {
					per_page: 50,
				} ) || []
			);
		},
		[ taxonomy ]
	);

	// ── Manual: all posts (checkbox list) ─────────────────────────────────
	const manualPosts = useSelect(
		( select ) => {
			if ( mode !== 'manual' ) {
				return [];
			}
			return (
				select( 'core' ).getEntityRecords( 'postType', postType, {
					per_page: 50,
					status: 'publish',
					search: postSearch || undefined,
				} ) || []
			);
		},
		[ mode, postType, postSearch ]
	);

	useEffect( () => {
		setAttributes( { selectedTerms: [], taxonomy: '' } );
	}, [ postType ] );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Journal Grid', 'laca' ) }
				title={ sectionTitle || __( 'Bài viết nổi bật', 'laca' ) }
				columns={ columns || 2 }
				image={ previewImage }
			/>
		);
	}

	const toggleId = ( arr, id ) =>
		arr.includes( id ) ? arr.filter( ( x ) => x !== id ) : [ ...arr, id ];

	const taxonomyOptions = [
		{ label: __( '— Không lọc —', 'laca' ), value: '' },
		...taxonomies.map( ( tx ) => ( { label: tx.label, value: tx.value } ) ),
	];

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
						placeholder="https://…"
					/>
					<RangeControl
						label={ __( 'Số cột', 'laca' ) }
						value={ columns }
						min={ 1 }
						max={ 2 }
						onChange={ ( v ) => setAttributes( { columns: v } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Nguồn bài viết', 'laca' ) } initialOpen={ true }>
					<RadioControl
						label={ __( 'Chế độ', 'laca' ) }
						selected={ mode }
						options={ [
							{ label: __( 'Tự động (query)', 'laca' ), value: 'auto' },
							{ label: __( 'Thủ công (chọn tay)', 'laca' ), value: 'manual' },
						] }
						onChange={ ( v ) => setAttributes( { mode: v } ) }
					/>

					{ postTypes.length > 0 && (
						<SelectControl
							label={ __( 'Loại bài viết (Post Type)', 'laca' ) }
							value={ postType }
							options={ postTypes }
							onChange={ ( v ) =>
								setAttributes( { postType: v, selectedTerms: [], selectedPosts: [] } )
							}
						/>
					) }

					{ mode === 'auto' && (
						<>
							{ taxonomyOptions.length > 1 && (
								<SelectControl
									label={ __( 'Taxonomy', 'laca' ) }
									value={ taxonomy }
									options={ taxonomyOptions }
									onChange={ ( v ) => setAttributes( { taxonomy: v, selectedTerms: [] } ) }
								/>
							) }

							{ taxonomy && terms.length > 0 && (
								<>
									<p style={ { fontSize: '11px', fontWeight: 600, marginBottom: '6px' } }>
										{ __( 'Chọn danh mục', 'laca' ) }
									</p>
									<div
										style={ {
											maxHeight: '200px',
											overflowY: 'auto',
											border: '1px solid #ddd',
											borderRadius: '4px',
											padding: '4px 8px',
										} }
									>
										{ terms.map( ( term ) => (
											<CheckboxControl
												key={ term.id }
												label={ `${ term.name } (${ term.count })` }
												checked={ selectedTerms.includes( term.id ) }
												onChange={ () =>
													setAttributes( {
														selectedTerms: toggleId( selectedTerms, term.id ),
													} )
												}
											/>
										) ) }
									</div>
								</>
							) }

							<RangeControl
								label={ __( 'Số bài viết', 'laca' ) }
								value={ postsCount }
								min={ 3 }
								max={ 20 }
								onChange={ ( v ) => setAttributes( { postsCount: v } ) }
							/>

							<SelectControl
								label={ __( 'Sắp xếp theo', 'laca' ) }
								value={ orderBy }
								options={ [
									{ label: __( 'Ngày đăng', 'laca' ), value: 'date' },
									{ label: __( 'Tiêu đề', 'laca' ), value: 'title' },
									{ label: __( 'Menu Order', 'laca' ), value: 'menu_order' },
								] }
								onChange={ ( v ) => setAttributes( { orderBy: v } ) }
							/>

							<SelectControl
								label={ __( 'Thứ tự', 'laca' ) }
								value={ order }
								options={ [
									{ label: __( 'Mới nhất (DESC)', 'laca' ), value: 'DESC' },
									{ label: __( 'Cũ nhất (ASC)', 'laca' ), value: 'ASC' },
								] }
								onChange={ ( v ) => setAttributes( { order: v } ) }
							/>
						</>
					) }

					{ mode === 'manual' && (
						<>
							<p style={ { fontSize: '11px', color: '#666', margin: '4px 0 8px' } }>
								{ __( 'Đã chọn: ', 'laca' ) }
								<strong>{ selectedPosts.length }</strong>
							</p>
							<TextControl
								label={ __( 'Tìm bài viết', 'laca' ) }
								value={ postSearch }
								onChange={ setPostSearch }
								placeholder={ __( 'Nhập tên bài…', 'laca' ) }
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
								{ manualPosts.map( ( post ) => (
									<CheckboxControl
										key={ post.id }
										label={ post.title?.rendered || `#${ post.id }` }
										checked={ selectedPosts.includes( post.id ) }
										onChange={ () =>
											setAttributes( {
												selectedPosts: toggleId( selectedPosts, post.id ),
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
				<ServerSideRender block="lacadev/journal-grid-block" attributes={ attributes } />
			</div>
		</>
	);
}
