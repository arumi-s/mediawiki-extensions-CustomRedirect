<?php

namespace MediaWiki\Extension\CustomRedirect;

use MediaWiki\MediaWikiServices;
use Parser;
use Title;

class ParserHooks implements \MediaWiki\Hook\ParserFirstCallInitHook
{
	/**
	 * {@inheritDoc}
	 * @param Parser $parser
	 * @return bool|void
	 */
	public function onParserFirstCallInit($parser)
	{
		$parser->setFunctionHook('redirect', [ParserHooks::class, 'redirect']);
	}

	/**
	 * @param Parser $parser
	 * @param string $text
	 * @param string $separator
	 * @param string $join
	 * @return string
	 */
	public static function redirect($parser, $text = '', $separator = '', $join = '|')
	{
		$separator = str_replace('\n', "\n", $parser->getStripState()->unstripNoWiki($separator));
		if ($separator === '') {
			$titles = [$text];
		} else {
			$join = str_replace('\n', "\n", $parser->getStripState()->unstripNoWiki($join));
			$titles = explode($separator, $text);
		}

		/** @var CustomRedirectLookup */
		$customRedirectLookup = MediaWikiServices::getInstance()->get('CustomRedirectLookup');

		$output = [];
		foreach ($titles as $text) {
			$title = $customRedirectLookup->getRedirectTarget(Title::newFromText($text));
			if ($title !== null) {
				$output[] = $title->getFullText();
			}
		}

		return implode($join, array_unique($output));
	}
}
