import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const ITEM_META = {
	gtm: {
		label: __( 'Add a valid GTM container ID', 'consentaro' ),
		doneLabel: __( 'Google Tag Manager step done', 'consentaro' ),
		tab: 'general',
	},
	text: {
		label: __( 'Review your banner text', 'consentaro' ),
		doneLabel: __( 'Banner text reviewed', 'consentaro' ),
		tab: 'design',
	},
	privacy: {
		label: __( 'Link your privacy policy', 'consentaro' ),
		doneLabel: __( 'Privacy policy linked', 'consentaro' ),
		tab: 'design',
	},
	test: {
		label: __( 'Test the banner in a private window', 'consentaro' ),
		doneLabel: __( 'Banner tested', 'consentaro' ),
		tab: null,
	},
};

/**
 * Settings-driven setup checklist. Status comes from the server (derived
 * from saved settings), so it cannot drift from what is actually configured.
 */
const SetupChecklist = ( {
	setup,
	onGoToTab,
	onSkipGtm,
	onConfirmText,
	onMarkTested,
	homeUrl,
	compact = false,
} ) => {
	if ( ! setup || ! Array.isArray( setup.items ) ) {
		return null;
	}

	const percent = Math.round( ( setup.done / setup.total ) * 100 );

	const renderAction = ( item ) => {
		if ( item.done ) {
			return null;
		}
		switch ( item.id ) {
			case 'gtm':
				return (
					<>
						<Button variant="link" onClick={ () => onGoToTab( 'general' ) }>
							{ __( 'Add ID', 'consentaro' ) }
						</Button>
						<Button variant="link" onClick={ onSkipGtm }>
							{ __( 'I don’t use GTM', 'consentaro' ) }
						</Button>
					</>
				);
			case 'text':
				return (
					<>
						<Button variant="link" onClick={ () => onGoToTab( 'design' ) }>
							{ __( 'Edit', 'consentaro' ) }
						</Button>
						<Button variant="link" onClick={ onConfirmText }>
							{ __( 'Looks good as is', 'consentaro' ) }
						</Button>
					</>
				);
			case 'privacy':
				return (
					<Button variant="link" onClick={ () => onGoToTab( 'design' ) }>
						{ __( 'Choose page', 'consentaro' ) }
					</Button>
				);
			case 'test':
				return (
					<>
						<a
							className="consentaro-setup__link"
							href={ homeUrl }
							target="_blank"
							rel="noopener noreferrer"
							onClick={ onMarkTested }
						>
							{ __( 'Open site in a new tab', 'consentaro' ) }
						</a>
						<Button variant="link" onClick={ onMarkTested }>
							{ __( 'Mark as tested', 'consentaro' ) }
						</Button>
					</>
				);
			default:
				return null;
		}
	};

	return (
		<section
			className={ `consentaro-setup ${ compact ? 'consentaro-setup--compact' : '' } ${
				setup.complete ? 'is-complete' : ''
			}` }
			aria-label={ __( 'Setup checklist', 'consentaro' ) }
		>
			<div className="consentaro-setup__head">
				<strong>
					{ setup.complete
						? __( 'Setup complete', 'consentaro' )
						: __( 'Finish setting up', 'consentaro' ) }
				</strong>
				<span className="consentaro-setup__count">
					{ setup.done } / { setup.total }
				</span>
			</div>
			<div
				className="consentaro-setup__bar"
				role="progressbar"
				aria-valuemin={ 0 }
				aria-valuemax={ 100 }
				aria-valuenow={ percent }
				aria-label={ __( 'Setup progress', 'consentaro' ) }
			>
				<span style={ { width: `${ percent }%` } } />
			</div>
			<ul className="consentaro-setup__list">
				{ setup.items.map( ( item ) => {
					const meta = ITEM_META[ item.id ];
					if ( ! meta ) {
						return null;
					}
					return (
						<li key={ item.id } className={ item.done ? 'is-done' : '' }>
							<span className="consentaro-setup__mark" aria-hidden="true">
								{ item.done ? '✓' : '' }
							</span>
							<span className="consentaro-setup__label">
								{ item.done ? meta.doneLabel : meta.label }
								<span className="screen-reader-text">
									{ item.done
										? __( ' (done)', 'consentaro' )
										: __( ' (to do)', 'consentaro' ) }
								</span>
							</span>
							<span className="consentaro-setup__actions">
								{ renderAction( item ) }
							</span>
						</li>
					);
				} ) }
			</ul>
		</section>
	);
};

export default SetupChecklist;
