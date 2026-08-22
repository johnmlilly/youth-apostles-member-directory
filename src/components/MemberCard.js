import { useState } from 'react';
import ReactModal from 'react-modal';

// react-modal applies its own inline default styles for positioning
// unless overridden — blank them out so our own yamd-modal-* CSS classes
// control the look instead of fighting inline styles.
const MODAL_STYLE_OVERRIDE = { overlay: {}, content: {} };

/**
 * CiviCRM returns dates as "YYYY-MM-DD" (sometimes with a time suffix).
 * Parsed manually with the local Date constructor rather than
 * `new Date( dateStr )` to avoid a UTC-shift off-by-one — the native
 * date-only parser treats "YYYY-MM-DD" as midnight UTC, which can render
 * as the previous day in timezones behind UTC.
 */
function formatDate( dateStr ) {
	if ( ! dateStr ) {
		return '';
	}

	const [ year, month, day ] = dateStr.split( ' ' )[ 0 ].split( '-' ).map( Number );
	if ( ! year || ! month || ! day ) {
		return dateStr;
	}

	return new Date( year, month - 1, day ).toLocaleDateString( undefined, {
		year: 'numeric',
		month: 'long',
		day: 'numeric',
	} );
}

export default function MemberCard( { member } ) {
	// The REST controller may expose this as either key depending on how
	// the CiviCRM membership field is mapped — accept both.
	const membership = member.membership_type || member.membership || '';

	const [ isDetailsOpen, setIsDetailsOpen ] = useState( false );

	const address = member.address || {};
	const hasAddress = Object.values( address ).some( Boolean );
	const relationships = member.relationships || [];

	return (
		<div className="yamd-card">
			{ member.image_url ? (
				<img
					src={ member.image_url }
					alt={ member.display_name }
					className="yamd-avatar"
				/>
			) : (
				<div className="yamd-avatar yamd-avatar--placeholder" aria-hidden="true" />
			) }

			<h3 className="yamd-name">{ member.display_name }</h3>

			{ membership && (
				<p className="yamd-membership">{ membership }</p>
			) }

			<div className="yamd-contact">
				{ member.email && (
					<p className="yamd-email">
						<a href={ `mailto:${ member.email }` }>{ member.email }</a>
					</p>
				) }
				{ member.phone && (
					<p className="yamd-phone">
						<a href={ `tel:${ member.phone.replace( /[^\d+]/g, '' ) }` }>
							{ member.phone }
						</a>
					</p>
				) }
			</div>

			<button
				type="button"
				className="yamd-details-trigger"
				onClick={ () => setIsDetailsOpen( true ) }
			>
				View full details
			</button>

			<ReactModal
				isOpen={ isDetailsOpen }
				onRequestClose={ () => setIsDetailsOpen( false ) }
				contentLabel={ member.display_name }
				style={ MODAL_STYLE_OVERRIDE }
				overlayClassName="yamd-modal-backdrop"
				className="yamd-modal"
			>
				<div className="yamd-modal-header">
					<h2 className="yamd-modal-title">{ member.display_name }</h2>
					<button
						type="button"
						className="yamd-modal-close"
						onClick={ () => setIsDetailsOpen( false ) }
						aria-label="Close"
					>
						×
					</button>
				</div>

				<div className="yamd-modal-body">
					<div className="yamd-detail-section">
						<h3 className="yamd-detail-heading">Mailing Address</h3>
						{ hasAddress ? (
							<address className="yamd-address">
								{ address.street && <span>{ address.street }</span> }
								{ address.street2 && <span>{ address.street2 }</span> }
								<span>
									{ [ address.city, address.state, address.postal_code ]
										.filter( Boolean )
										.join( ', ' ) }
								</span>
								{ address.country && <span>{ address.country }</span> }
							</address>
						) : (
							<p className="yamd-detail-empty">No address on file.</p>
						) }
					</div>

					<div className="yamd-detail-section">
						<h3 className="yamd-detail-heading">Member Since</h3>
						<p>
							{ member.member_since
								? formatDate( member.member_since )
								: 'Unknown' }
						</p>
					</div>

					<div className="yamd-detail-section">
						<h3 className="yamd-detail-heading">Relationships</h3>
						{ relationships.length > 0 ? (
							<ul className="yamd-relationships">
								{ relationships.map( ( rel, i ) => (
									<li key={ i }>
										<span className="yamd-relationship-type">{ rel.type }</span>{ ' ' }
										<span className="yamd-relationship-name">{ rel.name }</span>
									</li>
								) ) }
							</ul>
						) : (
							<p className="yamd-detail-empty">No relationships on file.</p>
						) }
					</div>
				</div>
			</ReactModal>
		</div>
	);
}
