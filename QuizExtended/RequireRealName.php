<?php
use MediaWiki\MediaWikiServices;

class RequireRealName {
	public static function beforePageDisplay( &$out, &$skin ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'QuizExtended' );
		if ($config->get( 'RequireRealName' )) { // this adds a little javascript to the login page to force the use of a real name
			$title = $out->getTitle();

			if ( $title->isSpecial( 'CreateAccount' ) ) {
				$out->addModules( 'ext.createAccount' );
			}
		}
	}
}