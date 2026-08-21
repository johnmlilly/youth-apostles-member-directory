/**
 * Builds the two-letter fallback shown when a member has no photo,
 * e.g. "Ada Lovelace" -> "AL". Falls back to "?" if we have no name.
 */
function getInitials( member ) {
	const first = ( member.first_name || '' ).trim();
	const last = ( member.last_name || '' ).trim();

	if ( first || last ) {
		return `${ first.charAt( 0 ) }${ last.charAt( 0 ) }`.toUpperCase();
	}

	const display = ( member.display_name || '' ).trim();
	return display ? display.charAt( 0 ).toUpperCase() : '?';
}

export default function MemberCard( { member } ) {
	// The REST controller may expose this as either key depending on how
	// the CiviCRM membership field is mapped — accept both.
	const membership = member.membership_type || member.membership || '';

	return (
		<div className="yamd-card">
			{ member.image_url ? (
				<img
					src={ member.image_url }
					alt={ member.display_name }
					className="yamd-avatar"
				/>
			) : (
				<div className="yamd-avatar yamd-avatar--placeholder" aria-hidden="true">
					{ getInitials( member ) }
				</div>
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
		</div>
	);
}
