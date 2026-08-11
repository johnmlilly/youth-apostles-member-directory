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

		$members = array();
		foreach ( $results as $r ) {
			$members[] = array(
				'id'           => $r['id'],
				'display_name' => $r['display_name'],
				'first_name'   => $r['first_name'],
				'last_name'    => $r['last_name'],
				'job_title'    => $r['job_title'],
				'image_url'    => $r['image_URL'],
				'email'        => $r['Email.email'] ?? '',
				'phone'        => $r['Phone.phone'] ?? '',
				// 'chapter'   => $r['Membership_Info.Chapter'] ?? '',
			);
		}

		// Cache for 5 minutes so repeat page loads are fast and don't
		// hammer CiviCRM. Lower this while you're actively testing.
		set_transient( $cache_key, $members, 5 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $members );
	}
}
