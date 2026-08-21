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
					'job_title',
					'image_URL',
					'Email.email',
					'Phone.phone'
					// Example custom field select — uncomment and rename
					// once you know your actual custom group/field names:
					// 'Membership_Info.Chapter',
				)
				->addJoin( 'Email AS email', 'LEFT', array( 'email.is_primary', '=', TRUE ) )
				->addJoin( 'Phone AS phone', 'LEFT', array( 'phone.is_primary', '=', TRUE ) )
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
				'job_title'       => $r['job_title'],
				'image_url'       => $r['image_URL'],
				'email'           => $r['Email.email'] ?? '',
				'phone'           => $r['Phone.phone'] ?? '',
				'membership_type' => '',
				// 'chapter'      => $r['Membership_Info.Chapter'] ?? '',
			);
		}

		// Membership lives on CiviCRM's Membership entity, not on Contact,
		// so it needs its own query. One extra query for the whole list
		// (rather than one per member) keeps this fast.
		$memberships = $this->get_membership_types( $contact_ids );

		foreach ( $members as &$member ) {
			$member['membership_type'] = $memberships[ $member['id'] ] ?? '';
		}
		unset( $member ); // Break the reference from the loop above.

		// Cache for 5 minutes so repeat page loads are fast and don't
		// hammer CiviCRM. Lower this while you're actively testing.
		set_transient( $cache_key, $members, 5 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $members );
	}

	/**
	 * Looks up the current membership type label for a set of contacts.
	 *
	 * A contact can hold several memberships (past ones, or more than one
	 * type at once), so we only consider active-ish statuses and take the
	 * one furthest in the future — that's the membership worth showing.
	 *
	 * @param int[] $contact_ids Contact IDs to look up.
	 * @return array<int,string> contact_id => membership type label.
	 */
	private function get_membership_types( array $contact_ids ) {
		if ( empty( $contact_ids ) ) {
			return array();
		}

		try {
			$results = \Civi\Api4\Membership::get( TRUE )
				->addSelect( 'contact_id', 'membership_type_id:label', 'end_date' )
				->addWhere( 'contact_id', 'IN', $contact_ids )
				// Statuses that count as "currently a member". Adjust to match
				// the statuses configured in CiviCRM > Administer >
				// CiviMember > Membership Status Rules.
				->addWhere( 'status_id:name', 'IN', array( 'New', 'Current', 'Grace' ) )
				->addWhere( 'is_test', '=', FALSE )
				// NULL end_date means a lifetime membership, so sort those first.
				->addOrderBy( 'end_date', 'DESC' )
				->setLimit( 0 )
				->execute();
		} catch ( \Exception $e ) {
			// A missing/disabled CiviMember component shouldn't break the
			// whole directory — just show members without a membership type.
			return array();
		}

		$map = array();
		foreach ( $results as $m ) {
			$contact_id = $m['contact_id'];

			// First row wins: results are already ordered by end_date DESC.
			if ( ! isset( $map[ $contact_id ] ) ) {
				$map[ $contact_id ] = $m['membership_type_id:label'] ?? '';
			}
		}

		return $map;
	}
}
