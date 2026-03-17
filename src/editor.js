/**
 * Interlinear — Block Editor integration.
 *
 * Registers the inline format type and the document setting panel
 * for managing per-post categories and presets.
 */

import { registerFormatType, toggleFormat, applyFormat, removeFormat } from '@wordpress/rich-text';
import { RichTextToolbarButton } from '@wordpress/block-editor';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { registerPlugin } from '@wordpress/plugins';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	TextControl,
	SelectControl,
	ColorPicker,
	Popover,
	Dropdown,
	Icon,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

/**
 * Curated 12-color palette.
 */
const COLOR_PALETTE = [
	{ name: __( 'Vermilion', 'interlinear' ), color: '#C94F3A' },
	{ name: __( 'Ocean', 'interlinear' ), color: '#3A7FC9' },
	{ name: __( 'Forest', 'interlinear' ), color: '#2D8A4E' },
	{ name: __( 'Amethyst', 'interlinear' ), color: '#8B5DC8' },
	{ name: __( 'Tangerine', 'interlinear' ), color: '#D4782F' },
	{ name: __( 'Teal', 'interlinear' ), color: '#1A9E8F' },
	{ name: __( 'Gold', 'interlinear' ), color: '#B8941F' },
	{ name: __( 'Rose', 'interlinear' ), color: '#C7508F' },
	{ name: __( 'Slate', 'interlinear' ), color: '#4A6078' },
	{ name: __( 'Sienna', 'interlinear' ), color: '#8E5B3A' },
	{ name: __( 'Indigo', 'interlinear' ), color: '#5B6ABF' },
	{ name: __( 'Charcoal', 'interlinear' ), color: '#505050' },
];

const FORMAT_NAME = 'interlinear/tag';
const META_KEY = '_interlinear_categories';

/**
 * Generate a slug from a label.
 */
function slugify( label ) {
	const slug = label
		.toLowerCase()
		.replace( /[^\w\s-]/g, '' )
		.replace( /[\s_]+/g, '-' )
		.replace( /^-+|-+$/g, '' );
	return slug || '';
}

/**
 * Category row component.
 */
function CategoryRow( { category, index, onChange, onRemove } ) {
	const [ showPicker, setShowPicker ] = useState( false );

	return (
		<div className="il-category-row">
			<div className="il-category-row__fields">
				<TextControl
					label={ __( 'Label', 'interlinear' ) }
					value={ category.label }
					onChange={ ( label ) => onChange( index, { ...category, label, slug: slugify( label ) || `category-${ index + 1 }` } ) }
					hideLabelFromVision
					placeholder={ __( 'Category name', 'interlinear' ) }
				/>
				<div className="il-category-row__color">
					<button
						className="il-color-swatch"
						style={ { backgroundColor: category.color } }
						onClick={ () => setShowPicker( ! showPicker ) }
						aria-label={ __( 'Pick color', 'interlinear' ) }
					/>
					{ showPicker && (
						<Popover
							position="bottom center"
							onClose={ () => setShowPicker( false ) }
						>
							<div className="il-color-popover">
								<div className="il-color-palette">
									{ COLOR_PALETTE.map( ( swatch ) => (
										<button
											key={ swatch.color }
											className={ `il-palette-swatch${ swatch.color === category.color ? ' is-active' : '' }` }
											style={ { backgroundColor: swatch.color } }
											onClick={ () => {
												onChange( index, { ...category, color: swatch.color } );
												setShowPicker( false );
											} }
											aria-label={ swatch.name }
											title={ swatch.name }
										/>
									) ) }
								</div>
								<TextControl
									label={ __( 'Custom hex', 'interlinear' ) }
									value={ category.color }
									onChange={ ( color ) => {
										if ( /^#[0-9A-Fa-f]{6}$/.test( color ) ) {
											onChange( index, { ...category, color } );
										}
									} }
									placeholder="#000000"
								/>
							</div>
						</Popover>
					) }
				</div>
				<SelectControl
					label={ __( 'Mode', 'interlinear' ) }
					value={ category.mode }
					options={ [
						{ label: __( 'Multi', 'interlinear' ), value: 'multi' },
						{ label: __( 'Exclusive', 'interlinear' ), value: 'exclusive' },
					] }
					onChange={ ( mode ) => onChange( index, { ...category, mode } ) }
					hideLabelFromVision
				/>
				<Button
					isDestructive
					isSmall
					onClick={ () => onRemove( index ) }
					aria-label={ __( 'Remove category', 'interlinear' ) }
					icon="no-alt"
				/>
			</div>
		</div>
	);
}

/**
 * Category panel in the document sidebar.
 */
function CategoryPanel() {
	const { editPost } = useDispatch( 'core/editor' );

	const { categories, postMeta } = useSelect( ( select ) => {
		const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		let cats = [];
		try {
			cats = JSON.parse( meta[ META_KEY ] || '[]' );
		} catch ( e ) {
			cats = [];
		}
		return { categories: cats, postMeta: meta };
	}, [] );

	const [ presets, setPresets ] = useState( {} );

	useEffect( () => {
		apiFetch( { path: '/interlinear/v1/presets' } ).then( setPresets ).catch( () => {} );
	}, [] );

	const updateCategories = useCallback(
		( newCategories ) => {
			editPost( {
				meta: {
					...postMeta,
					[ META_KEY ]: JSON.stringify( newCategories ),
					_interlinear_version: 1,
				},
			} );
		},
		[ editPost, postMeta ]
	);

	const handleChange = ( index, updated ) => {
		const next = [ ...categories ];
		next[ index ] = updated;
		updateCategories( next );
	};

	const handleRemove = ( index ) => {
		const next = categories.filter( ( _, i ) => i !== index );
		updateCategories( next );
	};

	const handleAdd = () => {
		if ( categories.length >= 6 ) return;
		const index = categories.length;
		const color = COLOR_PALETTE[ index % COLOR_PALETTE.length ].color;
		updateCategories( [
			...categories,
			{ slug: `category-${ index + 1 }`, label: '', color, mode: 'multi' },
		] );
	};

	const handleSavePreset = () => {
		const name = window.prompt( __( 'Preset name:', 'interlinear' ) );
		if ( ! name ) return;
		apiFetch( {
			path: '/interlinear/v1/presets',
			method: 'POST',
			data: { name, categories: JSON.stringify( categories ) },
		} ).then( () => {
			apiFetch( { path: '/interlinear/v1/presets' } ).then( setPresets );
		} );
	};

	const handleLoadPreset = ( name ) => {
		if ( ! presets[ name ] ) return;
		if ( ! window.confirm( __( 'Replace current categories with this preset?', 'interlinear' ) ) ) return;
		updateCategories( presets[ name ] );
	};

	const presetNames = Object.keys( presets );

	return (
		<PluginDocumentSettingPanel
			name="interlinear-categories"
			title={ __( 'Interlinear Categories', 'interlinear' ) }
			className="il-category-panel"
		>
			{ categories.map( ( cat, i ) => (
				<CategoryRow
					key={ i }
					category={ cat }
					index={ i }
					onChange={ handleChange }
					onRemove={ handleRemove }
				/>
			) ) }

			<div className="il-category-panel__actions">
				<Button
					isSecondary
					isSmall
					onClick={ handleAdd }
					disabled={ categories.length >= 6 }
				>
					{ __( 'Add Category', 'interlinear' ) }
				</Button>

				{ categories.length > 0 && (
					<Button isLink isSmall onClick={ handleSavePreset }>
						{ __( 'Save as Preset', 'interlinear' ) }
					</Button>
				) }

				{ presetNames.length > 0 && (
					<Dropdown
						renderToggle={ ( { isOpen, onToggle } ) => (
							<Button isLink isSmall onClick={ onToggle } aria-expanded={ isOpen }>
								{ __( 'Load Preset', 'interlinear' ) }
							</Button>
						) }
						renderContent={ () => (
							<div className="il-preset-list">
								{ presetNames.map( ( name ) => (
									<Button
										key={ name }
										isLink
										onClick={ () => handleLoadPreset( name ) }
									>
										{ name }
									</Button>
								) ) }
							</div>
						) }
					/>
				) }
			</div>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'interlinear', {
	render: CategoryPanel,
	icon: 'editor-textcolor',
} );

/**
 * Inline format type for tagging text with categories.
 */
const TagFormatEdit = ( { isActive, value, onChange } ) => {
	const [ showPopover, setShowPopover ] = useState( false );

	const categories = useSelect( ( select ) => {
		const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		try {
			return JSON.parse( meta[ META_KEY ] || '[]' );
		} catch ( e ) {
			return [];
		}
	}, [] );

	const handleClick = () => {
		if ( categories.length === 0 ) {
			const { openGeneralSidebar } = wp.data.dispatch( 'core/edit-post' );
			openGeneralSidebar( 'edit-post/document' );
			return;
		}
		setShowPopover( ! showPopover );
	};

	const applyCategory = ( slug ) => {
		onChange(
			applyFormat( value, {
				type: FORMAT_NAME,
				attributes: {
					'data-il-category': slug,
					role: 'mark',
				},
			} )
		);
		setShowPopover( false );
	};

	const handleRemoveTag = () => {
		onChange( removeFormat( value, FORMAT_NAME ) );
		setShowPopover( false );
	};

	return (
		<>
			<RichTextToolbarButton
				icon="tag"
				title={ __( 'Interlinear Tag', 'interlinear' ) }
				onClick={ handleClick }
				isActive={ isActive }
			/>
			{ showPopover && (
				<Popover position="bottom center" onClose={ () => setShowPopover( false ) }>
					<div className="il-tag-popover">
						{ categories.map( ( cat ) => (
							<Button
								key={ cat.slug }
								onClick={ () => applyCategory( cat.slug ) }
								className="il-tag-option"
							>
								<span
									className="il-tag-option__swatch"
									style={ { backgroundColor: cat.color } }
								/>
								{ cat.label || cat.slug }
							</Button>
						) ) }
						{ isActive && (
							<Button
								isDestructive
								isSmall
								onClick={ handleRemoveTag }
								className="il-tag-option il-tag-option--remove"
							>
								{ __( 'Remove Tag', 'interlinear' ) }
							</Button>
						) }
					</div>
				</Popover>
			) }
		</>
	);
};

registerFormatType( FORMAT_NAME, {
	title: __( 'Interlinear Tag', 'interlinear' ),
	tagName: 'span',
	className: null,
	attributes: {
		'data-il-category': 'data-il-category',
		role: 'role',
	},
	edit: TagFormatEdit,
} );
