<?php

namespace MediaWiki\Extension\CustomRedirect;

use MediaWiki\MediaWikiServices;

/**
 * CustomRedirect wiring for MediaWiki services.
 */
return [
	'CustomRedirectLookup' => static function (MediaWikiServices $services) {

		return new CustomRedirectLookup(
			$services->getMainConfig(),
			$services->getHookContainer(),
			$services->getMainWANObjectCache(),
			$services->getRedirectLookup()
		);
	},
	'CustomRedirect.UrlShortener' => static function (MediaWikiServices $services) {

		return new UrlShortener(
			$services->getMainConfig(),
		);
	}
];
