import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { PanelBody, SelectControl } from '@wordpress/components';
import { useState, useEffect, useRef, Fragment } from '@wordpress/element';
import { useInserterPreview, BlockPreviewMock } from '../../utils/preview';
import { ResponsiveRangeControl } from '../../utils/inspector-panels';
import previewImage from './preview.png';

const ALLOWED_BLOCKS = [ 'lacadev/tab-panel-block' ];

// Section ngoài LUÔN container-fluid — độ rộng khối tabs điều chỉnh riêng qua
// maxWidth (%) + contentAlign (giống hệt block Container).
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

// Không đặt sẵn heading — nội dung tab thường dùng block đã có tiêu đề
// riêng (CTA Section, Partnership Grid…), thêm heading riêng sẽ bị trùng.
const TEMPLATE = [
	[
		'lacadev/tab-panel-block',
		{ tabTitle: 'THE ORIGIN' },
		[ [ 'core/paragraph', {} ] ],
	],
	[
		'lacadev/tab-panel-block',
		{ tabTitle: 'THE LAB' },
		[ [ 'core/paragraph', {} ] ],
	],
	[
		'lacadev/tab-panel-block',
		{ tabTitle: 'THE ROASTERY' },
		[ [ 'core/paragraph', {} ] ],
	],
];

export default function Edit( { attributes, setAttributes, clientId } ) {
	const isPreview = useInserterPreview( attributes );
	const { maxWidth, maxWidthTablet, maxWidthMobile, contentAlign } =
		attributes;
	const maxWidthStyle = {
		'--mw-pc': `${ maxWidth }%`,
		'--mw-tablet': `${ maxWidthTablet }%`,
		'--mw-mobile': `${ maxWidthMobile }%`,
		margin: MARGIN_MAP[ contentAlign ] || MARGIN_MAP.center,
	};
	// Không gộp class "container-fluid" vào cùng div này — ".tabs-block" đã có
	// padding shorthand (padding: 3rem 0) trong style.scss, gộp chung sẽ zero-out
	// padding-left/right của container-fluid (đúng cảnh báo ở skill lacadev-theme
	// mục 2.3). Ngoài trang thật, "container-fluid" nằm ở div con
	// ".tabs-block__inner" riêng (xem render.php) nên không bị lỗi này.
	const blockProps = useBlockProps( { className: 'tabs-block' } );

	// ── Hiển thị như frontend: 1 thanh tab bấm được + chỉ panel đang chọn mới
	// hiện, thay vì xếp chồng hết tất cả để soạn (cách làm cũ) ─────────────────
	const [ activeTab, setActiveTab ] = useState( 0 );
	const panelsRef = useRef( null );

	const { innerBlocks, selectedClientId } = useSelect(
		( select ) => {
			const editor = select( 'core/block-editor' );
			return {
				innerBlocks: editor.getBlocks( clientId ),
				selectedClientId: editor.getSelectedBlockClientId(),
			};
		},
		[ clientId ]
	);
	const { selectBlock, insertBlock } = useDispatch( 'core/block-editor' );

	// Xoá bớt tab mà đang đứng ở tab cuối cùng (giờ không còn tồn tại) thì lùi
	// về tab cuối cùng còn lại — không được để activeTab trỏ ra ngoài mảng.
	useEffect( () => {
		if ( activeTab > innerBlocks.length - 1 ) {
			setActiveTab( Math.max( 0, innerBlocks.length - 1 ) );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ innerBlocks.length ] );

	// Chọn 1 Tab Panel (hay 1 block cháu bên trong nó, vd bấm vào đoạn văn
	// trong tab) qua List View hoặc click trực tiếp thì thanh tab tự chuyển
	// theo cho khớp, không cần bấm lại nút tab.
	useEffect( () => {
		if ( ! selectedClientId ) {
			return;
		}
		const idx = innerBlocks.findIndex(
			( block ) => block.clientId === selectedClientId
		);
		if ( idx !== -1 && idx !== activeTab ) {
			setActiveTab( idx );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ selectedClientId ] );

	// Ẩn/hiện panel con theo tab đang chọn, giống hệt hành vi ngoài frontend —
	// InnerBlocks tự render children, không có prop để set style riêng từng
	// con nên phải thao tác DOM trực tiếp qua ref (chỉ ảnh hưởng editor, xem
	// ghi chú "KHÔNG đặt display:none mặc định" ở style.scss).
	useEffect( () => {
		const wrap = panelsRef.current;
		if ( ! wrap ) {
			return;
		}
		Array.from( wrap.children ).forEach( ( child, index ) => {
			// Bỏ qua nút "+" thêm block mặc định của InnerBlocks nếu còn sót —
			// đã tắt qua renderAppender: false nên thường không có, phòng hờ.
			if ( ! child.classList.contains( 'tab-panel' ) ) {
				return;
			}
			child.style.display = index === activeTab ? '' : 'none';
		} );
	}, [ activeTab, innerBlocks.length ] );

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'tabs-block__editor-panels', ref: panelsRef },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			templateLock: false,
			orientation: 'horizontal',
			renderAppender: false,
		}
	);

	const handleAddTab = () => {
		const newBlock = createBlock( 'lacadev/tab-panel-block', {
			tabTitle:
				__( 'Tab mới', 'laca' ) + ' ' + ( innerBlocks.length + 1 ),
		} );
		insertBlock( newBlock, innerBlocks.length, clientId );
		setActiveTab( innerBlocks.length );
		selectBlock( newBlock.clientId );
	};

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Tabs', 'laca' ) }
				title={ __( 'The Origin | The Lab | The Roastery', 'laca' ) }
				columns={ 1 }
				image={ previewImage }
			/>
		);
	}

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
					title={ __( 'Căn lề', 'laca' ) }
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
			</InspectorControls>

			<div { ...blockProps }>
				<div className="tabs-block__maxwidth" style={ maxWidthStyle }>
					<nav
						className="tabs-block__nav tabs-block__nav--editor"
						aria-label={ __(
							'Chuyển tab (chế độ soạn thảo)',
							'laca'
						) }
					>
						{ innerBlocks.map( ( block, index ) => (
							<Fragment key={ block.clientId }>
								<button
									type="button"
									className={
										'tabs-block__nav-link' +
										( index === activeTab
											? ' is-active'
											: '' )
									}
									onClick={ () => {
										setActiveTab( index );
										selectBlock( block.clientId );
									} }
								>
									{ block.attributes.tabTitle ||
										__( 'Tab', 'laca' ) +
											' ' +
											( index + 1 ) }
								</button>
								<span className="tabs-block__nav-sep">|</span>
							</Fragment>
						) ) }
						<button
							type="button"
							className="tabs-block__editor-add-tab"
							onClick={ handleAddTab }
							aria-label={ __( 'Thêm tab mới', 'laca' ) }
						>
							+
						</button>
					</nav>
					<div { ...innerBlocksProps } />
				</div>
			</div>
		</>
	);
}
