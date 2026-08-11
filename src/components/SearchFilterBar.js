export default function SearchFilterBar( {
	search,
	onSearchChange,
	chapters,
	chapterFilter,
	onChapterChange,
	sortBy,
	onSortChange,
} ) {
	return (
		<div className="yamd-filter-bar">
			<input
				type="text"
				placeholder="Search by name or title…"
				value={ search }
				onChange={ ( e ) => onSearchChange( e.target.value ) }
			/>

			{ /* This dropdown only shows options once members have a
			     'chapter' field — see README for adding custom fields. */ }
			{ chapters.length > 0 && (
				<select
					value={ chapterFilter }
					onChange={ ( e ) => onChapterChange( e.target.value ) }
				>
					<option value="">All chapters</option>
					{ chapters.map( ( c ) => (
						<option key={ c } value={ c }>
							{ c }
						</option>
					) ) }
				</select>
			) }

			<select value={ sortBy } onChange={ ( e ) => onSortChange( e.target.value ) }>
				<option value="last_name">Sort: Last name</option>
				<option value="first_name">Sort: First name</option>
				<option value="chapter">Sort: Chapter</option>
			</select>
		</div>
	);
}
