export default function MemberCard( { member } ) {
	return (
		<div className="yamd-card">
			{ member.image_url && (
				<img
					src={ member.image_url }
					alt={ member.display_name }
					className="yamd-avatar"
				/>
			) }
			<h3>{ member.display_name }</h3>
			{ member.job_title && <p className="yamd-title">{ member.job_title }</p> }
			{ member.chapter && <p className="yamd-chapter">{ member.chapter }</p> }
			{ member.email && (
				<p>
					<a href={ `mailto:${ member.email }` }>{ member.email }</a>
				</p>
			) }
			{ member.phone && <p className="yamd-phone">{ member.phone }</p> }
		</div>
	);
}
