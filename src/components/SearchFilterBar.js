export default function SearchFilterBar( {
	search,
	onSearchChange,
	membershipTypes,
	membershipFilter,
	onMembershipChange,
	sortBy,
	onSortChange,
} ) {
	return (
		<div className="yamd-filter-bar">
			<input
				type="text"
				placeholder="Search by name or membership…"
				value={ search }
				onChange={ ( e ) => onSearchChange( e.target.value ) }
			/>

			{ /* Options come from the membership types actually present in
			     the results, so this stays empty until CiviCRM returns them. */ }
			{ membershipTypes.length > 0 && (
				<select
					value={ membershipFilter }
					onChange={ ( e ) => onMembershipChange( e.target.value ) }
				>
					<option value="">All memberships</option>
					{ membershipTypes.map( ( type ) => (
						<option key={ type } value={ type }>
							{ type }
						</option>
					) ) }
				</select>
			) }

			<select value={ sortBy } onChange={ ( e ) => onSortChange( e.target.value ) }>
				<option value="last_name">Sort: Last name</option>
				<option value="first_name">Sort: First name</option>
				<option value="membership_type">Sort: Membership</option>
			</select>
		</div>
	);
}
