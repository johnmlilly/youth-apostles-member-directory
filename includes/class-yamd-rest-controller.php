<?php
/**
 * ---------------------------------------------------------------------
 * WHAT THIS CLASS DOES
 *
 * It defines one REST API endpoint:
 *   GET /wp-json/yamd/v1/members
 *
 * When your React app requests that URL, WordPress runs get_members()
 * below, which:
 *   1. Checks the visitor is logged in.
 *   2. Checks a short-lived cache (so we don't hit CiviCRM on every load).
 *   3. Calls CiviCRM's PHP API (APIv4) directly — since CiviCRM runs as
 *      a plugin in the SAME WordPress install, we don't need an API key
 *      or an HTTP call. We call the PHP function directly, in-process.
 *   4. Formats the result into simple JSON and returns it.
 * ---------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YAMD_REST_Controller {

	const NAMESPACE_ = 'yamd/v1';

	/**
	 * TODO: Replace this with your actual CiviCRM "Members" Group ID.
	 * Find it in Wordpress Admin > CiviCRM > Contacts > Manage Groups —
	 * hover the group name, the ID is in the URL (gid=123).
	 */
	const MEMBERS_GROUP_ID = 27;

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/members',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_members' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Only logged-in WordPress users can hit this endpoint.
	 * Because WordPress's REST API automatically checks the X-WP-Nonce
	 * header against the visitor's login cookie, is_user_logged_in()
	 * here correctly reflects "is this a real logged-in session."
	 */
	public function check_permission() {
		return is_user_logged_in();
	}

	public function get_members( WP_REST_Request $request ) {
		$cache_key = 'yamd_members_cache';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		if ( ! function_exists( 'civicrm_initialize' ) ) {
			return new WP_Error(
				'civicrm_missing',
				'CiviCRM does not appear to be active on this site.',
				array( 'status' => 500 )
			);
		}

		// Boots up CiviCRM so its API classes/functions are available.
		civicrm_initialize();

		try {
			/**
			 * CiviCRM APIv4 query.
			 *
			 * Tip: CiviCRM has a built-in "API4 Explorer" tool
			 * (CiviCRM menu > Support > API4 Explorer) that lets you
			 * visually build a query like this, test it, and copy the
			 * generated PHP code — very useful for finding your exact
			 * custom field names below.
			 */
			$results = \Civi\Api4\Contact::get( TRUE )
				->addSelect(
					'id',
					'display_name',
					'first_name',
					'last_name',
					'image_URL',
					'Email.email',
					'Phone.phone',
					'Address.street_address',
					'Address.supplemental_address_1',
					'Address.city',
					'Address.state_province_id:label',
					'Address.postal_code',
					'Address.country_id:label'
					// Example custom field select — uncomment and rename
					// once you know your actual custom group/field names:
					// 'Membership_Info.Chapter',
				)
				->addJoin( 'Email AS email', 'LEFT', array( 'email.is_primary', '=', TRUE ) )
				->addJoin( 'Phone AS phone', 'LEFT', array( 'phone.is_primary', '=', TRUE ) )
				->addJoin( 'Address AS address', 'LEFT', array( 'address.is_primary', '=', TRUE ) )
				->addWhere( 'is_deleted', '=', FALSE )
				->addWhere( 'contact_type', '=', 'Individual' )
				->addWhere( 'groups', 'IN', array( self::MEMBERS_GROUP_ID ) )
				->addOrderBy( 'last_name', 'ASC' )
				->setLimit( 0 ) // 0 = no limit
				->execute();
		} catch ( \Exception $e ) {
			return new WP_Error(
				'civicrm_query_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}

		$members     = array();
		$contact_ids = array();

		foreach ( $results as $r ) {
			$contact_ids[] = $r['id'];

			$members[] = array(
				'id'              => $r['id'],
				'display_name'    => $r['display_name'],
				'first_name'      => $r['first_name'],
				'last_name'       => $r['last_name'],
				'image_url'       => $r['image_URL'],
				'email'           => $r['Email.email'] ?? '',
				'phone'           => $r['Phone.phone'] ?? '',
				'membership_type' => '',
				'member_since'    => '',
				'address'         => array(
					'street'      => $r['Address.street_address'] ?? '',
					'street2'     => $r['Address.supplemental_address_1'] ?? '',
					'city'        => $r['Address.city'] ?? '',
					'state'       => $r['Address.state_province_id:label'] ?? '',
					'postal_code' => $r['Address.postal_code'] ?? '',
					'country'     => $r['Address.country_id:label'] ?? '',
				),
				'relationships'   => array(),
				// 'chapter'      => $r['Membership_Info.Chapter'] ?? '',
			);
		}

		// Membership and relationships live on their own CiviCRM entities,
		// not on Contact, so they each need their own query. One extra
		// query per entity for the whole list (rather than one per member)
		// keeps this fast.
		$membership_data = $this->get_membership_data( $contact_ids );
		$relationships   = $this->get_relationships( $contact_ids );

		foreach ( $members as &$member ) {
			$data                      = $membership_data[ $member['id'] ] ?? array();
			$member['membership_type'] = $data['type'] ?? '';
			$member['member_since']    = $data['join_date'] ?? '';
			$member['relationships']   = $relationships[ $member['id'] ] ?? array();
		}
		unset( $member ); // Break the reference from the loop above.

		// Cache for 5 minutes so repeat page loads are fast and don't
		// hammer CiviCRM. Lower this while you're actively testing.
		set_transient( $cache_key, $members, 5 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $members );
	}

	/**
	 * Looks up the current membership type + join date for a set of contacts.
	 *
	 * A contact can hold several memberships (past ones, or more than one
	 * type at once), so we only consider active-ish statuses and take the
	 * one furthest in the future — that's the membership worth showing.
	 *
	 * @param int[] $contact_ids Contact IDs to look up.
	 * @return array<int,array{type:string,join_date:string}> contact_id => membership data.
	 */
	private function get_membership_data( array $contact_ids ) {
		if ( empty( $contact_ids ) ) {
			return array();
		}

		try {
			$results = \Civi\Api4\Membership::get( TRUE )
				->addSelect( 'contact_id', 'membership_type_id:label', 'join_date', 'end_date' )
				->addWhere( 'contact_id', 'IN', $contact_ids )
				// Statuses that count as "currently a member". Adjust to match
				// the statuses configured in CiviCRM > Administer >
				// CiviMember > Membership Status Rules.
				->addWhere( 'status_id:name', 'IN', array( 'New', 'Current', 'Grace' ) )
				->addWhere( 'is_test', '=', FALSE )
				->setLimit( 0 )
				->execute();
		} catch ( \Exception $e ) {
			// A missing/disabled CiviMember component shouldn't break the
			// whole directory — just show members without a membership type.
			return array();
		}

		// Pick the "best" membership per contact in PHP rather than relying
		// on SQL ORDER BY: a NULL end_date (lifetime membership) should
		// always win, and NULL sorts inconsistently across DB engines/
		// directions (e.g. MySQL puts NULLs last under ORDER BY ... DESC,
		// not first), so sorting in SQL alone can silently pick the wrong row.
		$map = array();
		foreach ( $results as $m ) {
			$contact_id = $m['contact_id'];
			$end_date   = $m['end_date'] ?? null;

			$is_better = ! isset( $map[ $contact_id ] )
				|| ( null === $end_date && null !== $map[ $contact_id ]['end_date'] )
				|| ( null !== $end_date && null !== $map[ $contact_id ]['end_date'] && $end_date > $map[ $contact_id ]['end_date'] );

			if ( $is_better ) {
				$map[ $contact_id ] = array(
					'type'      => $m['membership_type_id:label'] ?? '',
					'join_date' => $m['join_date'] ?? '',
					'end_date'  => $end_date,
				);
			}
		}

		// Drop the internal end_date now that comparisons are done — callers
		// only expect 'type' and 'join_date'.
		foreach ( $map as &$data ) {
			unset( $data['end_date'] );
		}
		unset( $data );

		return $map;
	}

	/**
	 * Looks up active relationships (spouse, parent, etc.) for a set of
	 * contacts, from the contact's own perspective.
	 *
	 * CiviCRM relationships are directional rows shared by two contacts
	 * (contact_id_a / contact_id_b), so a contact in our list can appear on
	 * either side of a given row. relationship_type_id.label_a_b always
	 * describes contact_id_a's role relative to contact_id_b (e.g. "Parent
	 * of"), so which label we use depends on which side our member is on.
	 *
	 * @param int[] $contact_ids Contact IDs to look up.
	 * @return array<int,array<int,array{type:string,name:string}>> contact_id => list of relationships.
	 */
	private function get_relationships( array $contact_ids ) {
		if ( empty( $contact_ids ) ) {
			return array();
		}

		try {
			$results = \Civi\Api4\Relationship::get( TRUE )
				->addSelect(
					'contact_id_a',
					'contact_id_b',
					'relationship_type_id.label_a_b',
					'relationship_type_id.label_b_a',
					'is_active',
					'end_date'
				)
				->addClause( 'OR', array( 'contact_id_a', 'IN', $contact_ids ), array( 'contact_id_b', 'IN', $contact_ids ) )
				->addWhere( 'is_active', '=', TRUE )
				->setLimit( 0 )
				->execute();
		} catch ( \Exception $e ) {
			// A missing/misconfigured Relationship setup shouldn't break the
			// whole directory — just show members without relationships.
			return array();
		}

		$today              = current_time( 'Y-m-d' );
		$contact_id_lookup  = array_flip( $contact_ids );
		$by_member          = array();
		$related_contact_id = array();

		foreach ( $results as $rel ) {
			// is_active alone doesn't guarantee "not expired" unless a
			// CiviCRM scheduled job has run recently — also check end_date.
			if ( ! empty( $rel['end_date'] ) && $rel['end_date'] < $today ) {
				continue;
			}

			$a = $rel['contact_id_a'];
			$b = $rel['contact_id_b'];

			if ( isset( $contact_id_lookup[ $a ] ) ) {
				$by_member[ $a ][] = array(
					'type'               => $rel['relationship_type_id.label_a_b'] ?? '',
					'related_contact_id' => $b,
				);
				$related_contact_id[] = $b;
			}

			if ( isset( $contact_id_lookup[ $b ] ) ) {
				$by_member[ $b ][] = array(
					'type'               => $rel['relationship_type_id.label_b_a'] ?? '',
					'related_contact_id' => $a,
				);
				$related_contact_id[] = $a;
			}
		}

		$names = $this->get_contact_names( array_unique( $related_contact_id ) );

		foreach ( $by_member as &$rels ) {
			foreach ( $rels as &$rel ) {
				$rel['name'] = $names[ $rel['related_contact_id'] ] ?? '';
				unset( $rel['related_contact_id'] );
			}
			unset( $rel );
		}
		unset( $rels );

		return $by_member;
	}

	/**
	 * Batched contact_id => display_name lookup, used to resolve the
	 * "other side" of each relationship without an N+1 query per member.
	 *
	 * Unlike the main member query, this deliberately does NOT filter to
	 * contact_type = 'Individual' — a relationship's other side is often an
	 * Organization or Household (e.g. "Employee of"), and excluding those
	 * would silently drop valid relationship names.
	 *
	 * @param int[] $contact_ids Contact IDs to look up.
	 * @return array<int,string> contact_id => display name.
	 */
	private function get_contact_names( array $contact_ids ) {
		if ( empty( $contact_ids ) ) {
			return array();
		}

		try {
			$results = \Civi\Api4\Contact::get( TRUE )
				->addSelect( 'id', 'display_name' )
				->addWhere( 'id', 'IN', $contact_ids )
				->addWhere( 'is_deleted', '=', FALSE )
				->setLimit( 0 )
				->execute();
		} catch ( \Exception $e ) {
			return array();
		}

		$map = array();
		foreach ( $results as $c ) {
			$map[ $c['id'] ] = $c['display_name'];
		}

		return $map;
	}
}
