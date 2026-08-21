import { useState, useEffect, useMemo } from 'react';
import SearchFilterBar from './components/SearchFilterBar';
import MemberCard from './components/MemberCard';

// yamdData is the global object PHP creates via wp_localize_script.
// (See the wp_localize_script call in youth-apostles-member-directory.php.)
const { apiUrl, nonce } = window.yamdData || {};

export default function App() {
	const [ members, setMembers ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const [ search, setSearch ] = useState( '' );
	const [ membershipFilter, setMembershipFilter ] = useState( '' );
	const [ sortBy, setSortBy ] = useState( 'last_name' );

	// Fetch the member list once, when the component first mounts.
	useEffect( () => {
		if ( ! apiUrl ) {
			setError( 'Directory is not configured correctly (missing API URL).' );
			setLoading( false );
			return;
		}

		fetch( apiUrl, {
			headers: {
				// This header is required for WordPress to recognize the
				// request as coming from a logged-in browser session.
				'X-WP-Nonce': nonce,
			},
		} )
			.then( ( res ) => {
				if ( ! res.ok ) {
					throw new Error( 'Could not load the member directory.' );
				}
				return res.json();
			} )
			.then( ( data ) => {
				setMembers( data );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err.message );
				setLoading( false );
			} );
	}, [] );

	// Build the list of unique membership types for the filter dropdown.
	// Populated from whatever the REST endpoint returns — no separate wiring.
	const membershipTypes = useMemo( () => {
		const set = new Set(
			members.map( ( m ) => m.membership_type ).filter( Boolean )
		);
		return Array.from( set ).sort();
	}, [ members ] );

	// All the actual filtering/sorting logic lives here. useMemo means
	// this only re-runs when members/search/membershipFilter/sortBy change,
	// not on every render.
	const filteredMembers = useMemo( () => {
		let result = members;

		if ( search ) {
			const term = search.toLowerCase();
			result = result.filter( ( m ) =>
				`${ m.first_name } ${ m.last_name } ${ m.membership_type || '' }`
					.toLowerCase()
					.includes( term )
			);
		}

		if ( membershipFilter ) {
			result = result.filter(
				( m ) => m.membership_type === membershipFilter
			);
		}

		result = [ ...result ].sort( ( a, b ) =>
			( a[ sortBy ] || '' ).localeCompare( b[ sortBy ] || '' )
		);

		return result;
	}, [ members, search, membershipFilter, sortBy ] );

	if ( loading ) {
		return <p className="yamd-status">Loading directory…</p>;
	}

	if ( error ) {
		return <p className="yamd-status yamd-error">{ error }</p>;
	}

	return (
		<div className="youth-apostles-member-directory">
			<SearchFilterBar
				search={ search }
				onSearchChange={ setSearch }
				membershipTypes={ membershipTypes }
				membershipFilter={ membershipFilter }
				onMembershipChange={ setMembershipFilter }
				sortBy={ sortBy }
				onSortChange={ setSortBy }
			/>
			<p className="yamd-count">{ filteredMembers.length } members</p>
			<div className="yamd-grid">
				{ filteredMembers.map( ( m ) => (
					<MemberCard key={ m.id } member={ m } />
				) ) }
			</div>
		</div>
	);
}
