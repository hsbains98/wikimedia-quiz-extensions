<?php

use MediaWiki\Auth\AbstractSecondaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\UserDataAuthenticationRequest;
use MediaWiki\MediaWikiServices;

class TeacherSignupPageSecondaryAuthenticationProvider extends AbstractSecondaryAuthenticationProvider {

	/**
	 * @param array $params
	 */
	public function __construct( $params = [] ) {
	}

	public function getAuthenticationRequests( $action, array $options ) {
		if ( $action === AuthManager::ACTION_CREATE ) {
			return [ new TeacherSignupPageAuthenticationRequest(
				$this->manager->getRequest()
			) ];
		}

		return [];
	}

	public function beginSecondaryAuthentication( $user, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}

	public function beginSecondaryAccountCreation( $user, $creator, array $reqs ) {
		$req = AuthenticationRequest::getRequestByClass(
			$reqs, TeacherSignupPageAuthenticationRequest::class
		);
		
		if ($req->teacher) { // add teacher to database
			$db = wfGetDB( DB_MASTER );
			$db->insert(
				'qz_teacher',
				['tc_id' => $user->getId()]
			);
		}
		return AuthenticationResponse::newPass();
	}
}