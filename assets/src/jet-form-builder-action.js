const { addFilter } = wp.hooks;
const { __ } = wp.i18n;
const {
		  ToggleControl,
		  RadioControl,
		  BaseControl,
		  __experimentalNumberControl,
	  } = wp.components;

let { NumberControl } = wp.components;

if ( typeof NumberControl === 'undefined' ) {
	NumberControl = __experimentalNumberControl;
}

addFilter( 'jet.fb.render.action.insert_post', 'jet-form-builder', function( emptyComponent, props ) {
	const { settings, onChangeSetting } = props;

	return <>
		<ToggleControl
			label={ __( 'Enable expiration period', 'jet-engine-post-expiration-period' ) }
			checked={ settings.enable_expiration_period }
			onChange={ newValue => onChangeSetting( newValue, 'enable_expiration_period' ) }
		/>
		{ settings.enable_expiration_period && <>
			<BaseControl
				key={ 'expiration_period' }
			>
				<NumberControl
					label={ __( 'Expiration period', 'jet-engine-post-expiration-period' ) }
					key='step'
					labelPosition='side'
					min={ 1 }
					value={ settings.expiration_period }
					onChange={ newValue => onChangeSetting( Number( newValue ), 'expiration_period' ) }
				/>
			</BaseControl>
			<BaseControl
				label={ __( 'Expiration action', 'jet-engine-post-expiration-period' ) }
			>
				<div className="jet-user-fields-map__list jet-control-clear-full">
					<RadioControl
						className='jet-inline-radio'
						selected={ settings.expiration_action }
						options={ [
							{ label: 'Draft', value: 'draft' },
							{ label: 'Trash', value: 'trash' },
						] }
						onChange={ newValue => onChangeSetting( newValue, 'expiration_action' ) }
					/>
				</div>
			</BaseControl>
		</> }
	</>;
} )