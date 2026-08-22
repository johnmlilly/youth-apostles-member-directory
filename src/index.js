import { createRoot } from 'react-dom/client';
import ReactModal from 'react-modal';
import App from './App';
import './style.css';

/**
 * WordPress loads this script in the footer (see wp_enqueue_script in
 * youth-apostles-member-directory.php), so by the time this runs, the #yamd-directory-root
 * div from our shortcode already exists in the page HTML.
 */
document.addEventListener( 'DOMContentLoaded', () => {
	const rootEl = document.getElementById( 'yamd-directory-root' );
	if ( rootEl ) {
		// Tells react-modal which element is the "app root" so it can mark
		// everything else aria-hidden while a modal is open. Without this it
		// logs a console warning and skips that accessibility behavior.
		ReactModal.setAppElement( rootEl );

		const root = createRoot( rootEl );
		root.render( <App /> );
	}
} );
