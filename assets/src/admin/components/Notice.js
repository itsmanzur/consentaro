import { Notice as WPNotice } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';

const AUTO_DISMISS_MS = 4000;
const ANIM_MS = 200;

const prefersReducedMotion = () =>
	typeof window !== 'undefined' &&
	window.matchMedia &&
	window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

const Notice = ( { status, onRemove, children } ) => {
	const [ phase, setPhase ] = useState( 'entering' );
	const timers = useRef( [] );

	useEffect( () => {
		const track = ( id ) => timers.current.push( id );

		if ( prefersReducedMotion() ) {
			setPhase( 'visible' );
		} else {
			track( setTimeout( () => setPhase( 'visible' ), 10 ) );
		}

		if ( status === 'success' ) {
			track( setTimeout( startLeave, AUTO_DISMISS_MS ) );
		}

		return () => timers.current.forEach( clearTimeout );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const startLeave = () => {
		setPhase( 'leaving' );
		const delay = prefersReducedMotion() ? 0 : ANIM_MS;
		timers.current.push( setTimeout( onRemove, delay ) );
	};

	return (
		<div
			className={ `consentaro-admin__notice-anim consentaro-admin__notice-anim--${ phase }` }
		>
			<WPNotice status={ status } isDismissible onRemove={ startLeave }>
				{ children }
			</WPNotice>
		</div>
	);
};

export default Notice;
