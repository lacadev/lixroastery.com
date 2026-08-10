import { __ } from '@wordpress/i18n';
import {
	Button,
	ButtonGroup,
	PanelBody,
	SelectControl,
	TextControl,
	ColorPicker,
	RangeControl,
} from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';

const SPACING_SIDES = [ 'top', 'left', 'bottom', 'right' ];
const SPACING_DEVICES = [ 'desktop', 'tablet', 'mobile' ];
const SPACING_UNITS = [ 'px', 'rem', 'em', '%', 'vw', 'vh' ];

// @wordpress/i18n yêu cầu chuỗi + textdomain literal để công cụ extract
// hoạt động — nên các nhãn theo key động (side/device) phải tra qua map
// literal thay vì gọi __( side, textdomain ) trực tiếp.
const SIDE_LABELS = {
	top: __( 'top', 'laca' ),
	left: __( 'left', 'laca' ),
	bottom: __( 'bottom', 'laca' ),
	right: __( 'right', 'laca' ),
};

const DEVICE_LABELS = {
	desktop: __( 'Desktop', 'laca' ),
	tablet: __( 'Tablet', 'laca' ),
	mobile: __( 'Mobile', 'laca' ),
};

function createEmptySpacing() {
	return {
		desktop: {
			margin: { top: '', left: '', bottom: '', right: '' },
			padding: { top: '', left: '', bottom: '', right: '' },
		},
		tablet: {
			margin: { top: '', left: '', bottom: '', right: '' },
			padding: { top: '', left: '', bottom: '', right: '' },
		},
		mobile: {
			margin: { top: '', left: '', bottom: '', right: '' },
			padding: { top: '', left: '', bottom: '', right: '' },
		},
	};
}

function parseSpacingInput( value ) {
	const normalized = `${ value ?? '' }`.trim();
	if ( ! normalized ) {
		return { amount: '', unit: 'px' };
	}

	const withUnit = normalized.match(
		/^(-?\d+(?:\.\d+)?)(px|rem|em|%|vw|vh)$/
	);
	if ( withUnit ) {
		return { amount: withUnit[ 1 ], unit: withUnit[ 2 ] };
	}

	if ( /^-?\d+(\.\d+)?$/.test( normalized ) ) {
		return { amount: normalized, unit: 'px' };
	}

	// Fallback for unexpected legacy values; keep editable amount and default unit.
	return { amount: normalized, unit: 'px' };
}

function mergeSpacing( spacing ) {
	const fallback = createEmptySpacing();
	if ( ! spacing || typeof spacing !== 'object' ) {
		return fallback;
	}

	return SPACING_DEVICES.reduce( ( devicesAcc, device ) => {
		const deviceSpacing = spacing[ device ] || {};
		devicesAcc[ device ] = {
			margin: SPACING_SIDES.reduce( ( sideAcc, side ) => {
				sideAcc[ side ] = `${
					deviceSpacing?.margin?.[ side ] ??
					fallback[ device ].margin[ side ]
				}`;
				return sideAcc;
			}, {} ),
			padding: SPACING_SIDES.reduce( ( sideAcc, side ) => {
				sideAcc[ side ] = `${
					deviceSpacing?.padding?.[ side ] ??
					fallback[ device ].padding[ side ]
				}`;
				return sideAcc;
			}, {} ),
		};
		return devicesAcc;
	}, {} );
}

function ResponsiveSpacingControls( { textdomain, spacing, onChangeSpacing } ) {
	const [ activeDevice, setActiveDevice ] = useState( 'desktop' );

	const resolvedSpacing = useMemo(
		() => mergeSpacing( spacing ),
		[ spacing ]
	);
	const deviceSpacing = resolvedSpacing[ activeDevice ];

	const updateSpacing = ( type, side, { amount, unit } ) => {
		const nextSpacing = mergeSpacing( resolvedSpacing );
		const normalizedAmount = `${ amount ?? '' }`.trim();
		nextSpacing[ activeDevice ][ type ][ side ] = normalizedAmount
			? `${ normalizedAmount }${ unit || 'px' }`
			: '';
		onChangeSpacing( nextSpacing );
	};

	return (
		<>
			<p style={ { marginBottom: '8px', fontWeight: 600 } }>
				{ __( 'Responsive', 'laca' ) }
			</p>
			<ButtonGroup style={ { marginBottom: '12px' } }>
				{ SPACING_DEVICES.map( ( device ) => (
					<Button
						key={ device }
						isPrimary={ activeDevice === device }
						isSecondary={ activeDevice !== device }
						onClick={ () => setActiveDevice( device ) }
					>
						{ DEVICE_LABELS[ device ] }
					</Button>
				) ) }
			</ButtonGroup>

			<p style={ { marginBottom: '6px', fontWeight: 600 } }>
				{ __( 'Margin', 'laca' ) }
			</p>
			<div
				style={ {
					display: 'grid',
					gridTemplateColumns: 'repeat(4, minmax(0, 1fr))',
					gap: '8px',
					marginBottom: '12px',
				} }
			>
				{ SPACING_SIDES.map( ( side ) => {
					const parsed = parseSpacingInput(
						deviceSpacing.margin[ side ]
					);
					return (
						<div key={ `margin-${ side }` }>
							<TextControl
								label={ SIDE_LABELS[ side ] }
								value={ parsed.amount }
								placeholder=""
								onChange={ ( value ) =>
									updateSpacing( 'margin', side, {
										amount: value,
										unit: parsed.unit,
									} )
								}
							/>
							<SelectControl
								label=""
								value={ parsed.unit }
								options={ SPACING_UNITS.map( ( unit ) => ( {
									label: unit,
									value: unit,
								} ) ) }
								onChange={ ( value ) =>
									updateSpacing( 'margin', side, {
										amount: parsed.amount,
										unit: value,
									} )
								}
							/>
						</div>
					);
				} ) }
			</div>

			<p style={ { marginBottom: '6px', fontWeight: 600 } }>
				{ __( 'Padding', 'laca' ) }
			</p>
			<div
				style={ {
					display: 'grid',
					gridTemplateColumns: 'repeat(4, minmax(0, 1fr))',
					gap: '8px',
				} }
			>
				{ SPACING_SIDES.map( ( side ) => {
					const parsed = parseSpacingInput(
						deviceSpacing.padding[ side ]
					);
					return (
						<div key={ `padding-${ side }` }>
							<TextControl
								label={ SIDE_LABELS[ side ] }
								value={ parsed.amount }
								placeholder=""
								onChange={ ( value ) =>
									updateSpacing( 'padding', side, {
										amount: value,
										unit: parsed.unit,
									} )
								}
							/>
							<SelectControl
								label=""
								value={ parsed.unit }
								options={ SPACING_UNITS.map( ( unit ) => ( {
									label: unit,
									value: unit,
								} ) ) }
								onChange={ ( value ) =>
									updateSpacing( 'padding', side, {
										amount: parsed.amount,
										unit: value,
									} )
								}
							/>
						</div>
					);
				} ) }
			</div>
		</>
	);
}

/**
 * Shared inspector panel for block-specific settings.
 *
 * @param {Object}  props             Component props.
 * @param {string}  props.title       Panel title.
 * @param {string}  props.textdomain  Translation domain.
 * @param {boolean} props.initialOpen Initial open state.
 * @param {*}       props.children    Custom block controls.
 * @return {JSX.Element} Panel body.
 */
export function BlockConfigPanel( {
	title = 'Cấu hình block',
	textdomain = 'laca',
	initialOpen = true,
	children,
} ) {
	return (
		<PanelBody
			// `title` là chuỗi đã dịch sẵn do từng block truyền vào (component
			// dùng chung cho nhiều block) — không thể literal hoá.
			// eslint-disable-next-line @wordpress/i18n-text-domain, @wordpress/i18n-no-variables
			title={ __( title, textdomain ) }
			initialOpen={ initialOpen }
		>
			{ children }
		</PanelBody>
	);
}

/**
 * Nhóm field dùng chung cho giao diện + spacing.
 * Tách riêng để tái sử dụng trong nhiều panel mà không lặp code.
 *
 * @param {Object}   props               Component props.
 * @param {string}   props.textdomain    Textdomain để dịch.
 * @param {string}   props.bgColor       Màu nền hiện tại.
 * @param {number}   props.bgOpacity     Độ mờ nền hiện tại.
 * @param {Object}   props.spacing       Cấu hình spacing responsive.
 * @param {Function} props.setAttributes Hàm setAttributes của block.
 * @param {string}   props.attributeKey  Tên attribute spacing.
 * @return {JSX.Element} Nhóm field UI.
 */
function AppearanceAndSpacingFields( {
	textdomain,
	bgColor,
	bgOpacity,
	spacing,
	setAttributes,
	attributeKey = 'spacing',
} ) {
	return (
		<>
			<p
				style={ {
					fontSize: '0.8rem',
					fontWeight: 600,
					marginBottom: '0.5rem',
				} }
			>
				{ __( 'Background color', 'laca' ) }
			</p>
			<ColorPicker
				color={ bgColor || 'transparent' }
				onChange={ ( value ) => setAttributes( { bgColor: value } ) }
				enableAlpha={ false }
				defaultValue="transparent"
			/>

			<RangeControl
				label={ __( 'Opacity (0 = transparent)', 'laca' ) }
				value={ typeof bgOpacity === 'number' ? bgOpacity : 100 }
				min={ 0 }
				max={ 100 }
				step={ 5 }
				onChange={ ( value ) => setAttributes( { bgOpacity: value } ) }
			/>

			<div style={ { marginTop: '12px' } }>
				<ResponsiveSpacingControls
					textdomain={ textdomain }
					spacing={ spacing }
					onChangeSpacing={ ( nextSpacing ) =>
						setAttributes( { [ attributeKey ]: nextSpacing } )
					}
				/>
			</div>
		</>
	);
}

/**
 * Shared title panel.
 *
 * @param {Object}   props             Component props.
 * @param {string}   props.value       Current title value.
 * @param {Function} props.onChange    Change handler.
 * @param {string}   props.textdomain  Translation domain.
 * @param {string}   props.label       Input label.
 * @param {string}   props.placeholder Input placeholder.
 * @return {JSX.Element} Panel body.
 */
export function TitlePanel( {
	value,
	onChange,
	textdomain = 'laca',
	label = 'Tiêu đề',
	placeholder = '',
} ) {
	return (
		<PanelBody title={ __( 'Tiêu đề', 'laca' ) } initialOpen={ false }>
			<TextControl
				// `label` là chuỗi đã dịch sẵn do từng block truyền vào
				// eslint-disable-next-line @wordpress/i18n-text-domain, @wordpress/i18n-no-variables
				label={ __( label, textdomain ) }
				value={ value || '' }
				onChange={ onChange }
				placeholder={ placeholder }
			/>
		</PanelBody>
	);
}

/**
 * Shared subtitle panel.
 *
 * @param {Object}   props             Component props.
 * @param {string}   props.value       Current subtitle value.
 * @param {Function} props.onChange    Change handler.
 * @param {string}   props.textdomain  Translation domain.
 * @param {string}   props.label       Input label.
 * @param {string}   props.placeholder Input placeholder.
 * @return {JSX.Element} Panel body.
 */
export function SubtitlePanel( {
	value,
	onChange,
	textdomain = 'laca',
	label = 'Phụ đề',
	placeholder = '',
} ) {
	return (
		<PanelBody title={ __( 'Phụ đề', 'laca' ) } initialOpen={ false }>
			<TextControl
				// `label` là chuỗi đã dịch sẵn do từng block truyền vào
				// eslint-disable-next-line @wordpress/i18n-text-domain, @wordpress/i18n-no-variables
				label={ __( label, textdomain ) }
				value={ value || '' }
				onChange={ onChange }
				placeholder={ placeholder }
			/>
		</PanelBody>
	);
}

/**
 * Shared appearance panel for background color and opacity.
 *
 * @param {Object}   props               Component props.
 * @param {string}   props.textdomain    Translation domain.
 * @param {string}   props.bgColor       Current background color.
 * @param {number}   props.bgOpacity     Current background opacity.
 * @param {Function} props.setAttributes Gutenberg setAttributes function.
 * @param            props.spacing
 * @param            props.attributeKey
 * @return {JSX.Element} Panel body.
 */
export function AppearancePanel( {
	textdomain = 'laca',
	bgColor = 'transparent',
	bgOpacity = 100,
	spacing,
	attributeKey = 'spacing',
	setAttributes,
} ) {
	return (
		<PanelBody
			title={ __( 'Cấu hình block', 'laca' ) }
			initialOpen={ true }
		>
			<AppearanceAndSpacingFields
				textdomain={ textdomain }
				bgColor={ bgColor }
				bgOpacity={ bgOpacity }
				spacing={ spacing }
				setAttributes={ setAttributes }
				attributeKey={ attributeKey }
			/>
		</PanelBody>
	);
}

/**
 * Bộ panel chuẩn dùng chung cho tất cả block hiện tại và tương lai.
 * - "Cấu hình block" luôn ở đầu.
 * - Chứa sẵn nền + spacing responsive.
 * - Cho phép gắn thêm controls riêng theo từng block qua `configChildren`.
 *
 * @param {Object}   props                     Component props.
 * @param {Object}   props.attributes          Toàn bộ attributes của block.
 * @param {Function} props.setAttributes       Hàm setAttributes.
 * @param {string}   props.textdomain          Textdomain để dịch.
 * @param {boolean}  props.showTitle           Bật/tắt panel tiêu đề.
 * @param {boolean}  props.showSubtitle        Bật/tắt panel phụ đề.
 * @param {string}   props.titleLabel          Nhãn input tiêu đề.
 * @param {string}   props.subtitleLabel       Nhãn input phụ đề.
 * @param {string}   props.titlePlaceholder    Placeholder tiêu đề.
 * @param {string}   props.subtitlePlaceholder Placeholder phụ đề.
 * @param {*}        props.configChildren      Controls riêng của block đặt trong panel cấu hình.
 * @param {string}   props.spacingAttributeKey Tên attribute spacing.
 * @return {JSX.Element} Cụm panel Inspector chuẩn hoá.
 */
export function BlockBasePanels( {
	attributes,
	setAttributes,
	textdomain = 'laca',
	showTitle = true,
	showSubtitle = true,
	titleLabel = 'Tiêu đề section',
	subtitleLabel = 'Phụ đề section',
	titlePlaceholder = '',
	subtitlePlaceholder = '',
	configChildren = null,
	spacingAttributeKey = 'spacing',
} ) {
	const {
		heading = '',
		subheading = '',
		bgColor = 'transparent',
		bgOpacity = 100,
		spacing = createEmptySpacing(),
	} = attributes || {};

	return (
		<>
			<PanelBody
				title={ __( 'Cấu hình block', 'laca' ) }
				initialOpen={ true }
			>
				{ configChildren }
				<AppearanceAndSpacingFields
					textdomain={ textdomain }
					bgColor={ bgColor }
					bgOpacity={ bgOpacity }
					spacing={ spacing }
					setAttributes={ setAttributes }
					attributeKey={ spacingAttributeKey }
				/>
			</PanelBody>

			{ showTitle ? (
				<TitlePanel
					textdomain={ textdomain }
					value={ heading }
					onChange={ ( value ) =>
						setAttributes( { heading: value } )
					}
					label={ titleLabel }
					placeholder={ titlePlaceholder }
				/>
			) : null }

			{ showSubtitle ? (
				<SubtitlePanel
					textdomain={ textdomain }
					value={ subheading }
					onChange={ ( value ) =>
						setAttributes( { subheading: value } )
					}
					label={ subtitleLabel }
					placeholder={ subtitlePlaceholder }
				/>
			) : null }
		</>
	);
}

/**
 * Shared spacing panel for section spacing.
 *
 * @param {Object}   props                 Component props.
 * @param {string}   props.textdomain      Translation domain.
 * @param {number}   props.marginTop       Margin top in px.
 * @param {number}   props.marginBottom    Margin bottom in px.
 * @param {number}   props.paddingTop      Padding top in px.
 * @param {number}   props.paddingBottom   Padding bottom in px.
 * @param {Function} props.setAttributes   Gutenberg setAttributes function.
 * @param            props.spacing
 * @param            props.onChangeSpacing
 * @param            props.attributeKey
 * @return {JSX.Element} Panel body.
 */
export function SpacingPanel( {
	textdomain = 'laca',
	spacing,
	onChangeSpacing,
	attributeKey = 'spacing',
	marginTop = 0,
	marginBottom = 0,
	paddingTop = 60,
	paddingBottom = 55,
	setAttributes,
} ) {
	if ( spacing && typeof onChangeSpacing === 'function' ) {
		return (
			<PanelBody title={ __( 'Spacing', 'laca' ) } initialOpen={ false }>
				<ResponsiveSpacingControls
					textdomain={ textdomain }
					spacing={ spacing }
					onChangeSpacing={ onChangeSpacing }
				/>
			</PanelBody>
		);
	}

	if ( spacing && typeof setAttributes === 'function' ) {
		return (
			<PanelBody title={ __( 'Spacing', 'laca' ) } initialOpen={ false }>
				<ResponsiveSpacingControls
					textdomain={ textdomain }
					spacing={ spacing }
					onChangeSpacing={ ( nextSpacing ) =>
						setAttributes( { [ attributeKey ]: nextSpacing } )
					}
				/>
			</PanelBody>
		);
	}

	return (
		<PanelBody title={ __( 'Spacing', 'laca' ) } initialOpen={ false }>
			<RangeControl
				label={ __( 'Margin top (px)', 'laca' ) }
				value={ marginTop }
				min={ -200 }
				max={ 300 }
				step={ 5 }
				onChange={ ( value ) => setAttributes( { marginTop: value } ) }
			/>
			<RangeControl
				label={ __( 'Margin bottom (px)', 'laca' ) }
				value={ marginBottom }
				min={ -200 }
				max={ 300 }
				step={ 5 }
				onChange={ ( value ) =>
					setAttributes( { marginBottom: value } )
				}
			/>
			<RangeControl
				label={ __( 'Padding top (px)', 'laca' ) }
				value={ paddingTop }
				min={ 0 }
				max={ 300 }
				step={ 5 }
				onChange={ ( value ) => setAttributes( { paddingTop: value } ) }
			/>
			<RangeControl
				label={ __( 'Padding bottom (px)', 'laca' ) }
				value={ paddingBottom }
				min={ 0 }
				max={ 300 }
				step={ 5 }
				onChange={ ( value ) =>
					setAttributes( { paddingBottom: value } )
				}
			/>
		</PanelBody>
	);
}
