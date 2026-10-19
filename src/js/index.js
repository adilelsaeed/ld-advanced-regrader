/**
 * Entry point: mount the regrader app on the admin page.
 */
import { createRoot } from '@wordpress/element';

import App from './App';
import './style.scss';

const root = document.getElementById( 'ldar-app' );

if ( root ) {
	createRoot( root ).render( <App settings={ window.ldarSettings || {} } /> );
}
