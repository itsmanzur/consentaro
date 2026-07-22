import { render } from '@wordpress/element';
import App from './App';
import './style.scss';

document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'consentflow-admin' );
	if ( container ) {
		render( <App />, container );
	}
} );
