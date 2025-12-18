/* global wp */
( function ( blocks, blockEditor, components, element, i18n ) {
	var registerBlockType = blocks.registerBlockType;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var Fragment = element.Fragment;
	var el = element.createElement;
	var __ = i18n.__;

	var LABELS = {
		price: __( '価格', 'okivaluelab-child' ),
		yield: __( '表面利回り', 'okivaluelab-child' ),
	};

	registerBlockType( 'okivaluelab/property-meta', {
		title: __( 'Property Meta', 'okivaluelab-child' ),
		description: __( 'Displays property price or yield information inside property loops.', 'okivaluelab-child' ),
		category: 'widgets',
		icon: 'chart-bar',
		keywords: [ 'property', 'meta', 'price', 'yield' ],
		supports: {
			html: false,
			align: false,
		},
		attributes: {
			variant: {
				type: 'string',
				default: 'price',
			},
			label: {
				type: 'string',
				default: '',
			},
			yieldFields: {
				type: 'string',
				default: 'yield_gross,yield_surface,yield_real,yield_actual',
			},
		},
		edit: function ( props ) {
			var attributes = props.attributes || {};
			var variant = attributes.variant === 'yield' ? 'yield' : 'price';
			var placeholderLabel = LABELS[ variant ];
			var label = attributes.label || placeholderLabel;
			var blockProps = useBlockProps( {
				className: 'property-card__meta property-card__meta--' + variant,
			} );

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{
							title: __( 'Display settings', 'okivaluelab-child' ),
							initialOpen: true,
						},
						el( SelectControl, {
							label: __( 'Variant', 'okivaluelab-child' ),
							value: variant,
							options: [
								{ label: __( 'Price', 'okivaluelab-child' ), value: 'price' },
								{ label: __( 'Yield', 'okivaluelab-child' ), value: 'yield' },
							],
							onChange: function ( value ) {
								props.setAttributes( { variant: value } );
							},
						} ),
						el( TextControl, {
							label: __( 'Label', 'okivaluelab-child' ),
							value: attributes.label || '',
							placeholder: placeholderLabel,
							onChange: function ( value ) {
								props.setAttributes( { label: value } );
							},
							help: __( '空欄ならデフォルトの見出しを使用します。', 'okivaluelab-child' ),
						} ),
						variant === 'yield' &&
							el( TextControl, {
								label: __( 'Yield meta keys', 'okivaluelab-child' ),
								value: attributes.yieldFields || '',
								onChange: function ( value ) {
									props.setAttributes( { yieldFields: value } );
								},
								help: __( '優先順位順でカンマ区切り入力 (例: yield_gross,yield_surface,...)', 'okivaluelab-child' ),
							} )
					)
				),
				el(
					'p',
					blockProps,
					el( 'span', { className: 'property-card__meta-label' }, label ),
					el(
						'span',
						{ className: 'property-card__meta-value' },
						variant === 'price'
							? __( 'ログインで表示', 'okivaluelab-child' )
							: __( '— (会員向け)', 'okivaluelab-child' )
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.element,
	window.wp.i18n
);
