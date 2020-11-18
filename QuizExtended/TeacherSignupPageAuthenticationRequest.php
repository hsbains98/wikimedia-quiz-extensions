<?php

use MediaWiki\Auth\AuthenticationRequest;

class TeacherSignupPageAuthenticationRequest extends AuthenticationRequest {
	public $required = self::REQUIRED;

	public $teacher;

	/**
	 * @param WebRequest $request
	 */
	public function __construct( $request ) {
		$this->request = $request;
	}

	public function getFieldInfo() {
		return [ // this adds the teacher checkbox to the sign up page
			'teacher' => [
				'type' => 'checkbox',
				'label' => wfMessage('quiz-teacher-account-creation')
			]
		];
	}

	public function loadFromSubmission( array $data ) {
		// We always want to use this request, so ignore parent's return value.
		parent::loadFromSubmission( $data );

		return true;
	}
}