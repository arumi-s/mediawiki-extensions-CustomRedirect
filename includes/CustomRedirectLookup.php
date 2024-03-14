<?php

namespace MediaWiki\Extension\CustomRedirect;

use MediaWiki\HookContainer\HookContainer;
use Config;
use WANObjectCache;
use MediaWiki\Page\RedirectLookup;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Extension\CustomRedirect\Hooks\HookRunner;
use Title;
use Message;
use Html;

class CustomRedirectLookup
{
	/** @var Config */
	private $config;

	/** @var HookContainer */
	private $hookContainer;

	/** @var WANObjectCache */
	private $cache;

	/** @var RedirectLookup */
	private $redirectLookup;

	/** @var RegexRedirectRule[] */
	private $regexRedirectRules = null;

	/**
	 * @param Config $config
	 * @param HookContainer $hookContainer
	 * @param WANObjectCache $cache
	 * @param RedirectLookup $redirectLookup
	 */
	public function __construct(
		Config $config,
		HookContainer $hookContainer,
		WANObjectCache $cache,
		RedirectLookup $redirectLookup
	) {
		$this->config = $config;
		$this->hookContainer = $hookContainer;
		$this->cache = $cache;
		$this->redirectLookup = $redirectLookup;
	}

	/**
	 * @param ?LinkTarget $title
	 * @return bool
	 */
	public function canRedirect(?LinkTarget $title)
	{
		return $title !== null &&
			$title->canExist() &&
			!$title->inNamespaces(
				NS_USER,
				NS_USER_TALK,
				NS_CATEGORY,
				NS_CATEGORY_TALK,
				NS_MEDIAWIKI,
				NS_MEDIAWIKI_TALK
			);
	}

	/**
	 * Assumes title is already permitted by {@see CustomRedirectLookup::canRedirect()}
	 * @param ?LinkTarget $title
	 * @return bool
	 */
	public function canCustomRedirect(?LinkTarget $title)
	{
		return $title->inNamespaces($this->config->get('CustomRedirectEnabledNamespaces')) && !$title->exists();
	}

	/**
	 * Assumes title is already permitted by {@see CustomRedirectLookup::canRedirect()} and {@see CustomRedirectLookup::canCustomRedirect()}
	 * @param ?LinkTarget $title
	 * @return bool
	 */
	public function canRegexRedirect(?LinkTarget $title)
	{
		return $title->inNamespaces($this->config->get('CustomRedirectRegexEnabledNamespaces'));
	}

	/**
	 * @param ?Title &$title
	 * @return ?Title
	 */
	public function getRedirectTarget($title)
	{
		static $cache = [];

		if (
			!$this->canRedirect($title) ||
			$this->doStockRedirect($title) ||
			!$this->canCustomRedirect($title)
		) {
			return $title;
		}

		$key = $title->getPrefixedText();

		if (isset($cache[$key])) {
			return Title::newFromText($cache[$key]);
		}

		if ($this->doCustomRedirect($title)) {
			$this->doStockRedirect($title);
		}

		$cache[$key] = $title->getFullText();
		return $title;
	}

	/**
	 * @param Title &$title
	 * @return bool
	 */
	private function doCustomRedirect(&$title): bool
	{
		$changed = false;

		$hookRunner = new HookRunner($this->hookContainer);
		$hookRunner->onGetCustomRedirect($title, $changed);

		if (!$changed && $this->canRegexRedirect($title)) {
			$changed = $this->doRegexRedirect($title);
		}

		return $changed;
	}

	/**
	 * @param Title &$title
	 * @return bool
	 */
	private function doRegexRedirect(&$title)
	{
		$text = rtrim(ltrim($title->getPrefixedText(), '/'), '/');
		$rules = $this->getRegexRedirectRules();
		foreach ($rules as $rule) {
			if ($rule->replaceTarget($text)) {
				$title = Title::newFromText($text);
				return true;
			}
		}

		return false;
	}

	/**
	 * @param Title &$title
	 * @return bool
	 */
	private function doStockRedirect(&$title): bool
	{
		$target = Title::castFromLinkTarget($this->redirectLookup->getRedirectTarget($title));

		if ($target === null) {
			return false;
		}

		$title = $target;
		return true;
	}

	/**
	 * @return RegexRedirectRule[]
	 */
	private function getRegexRedirectRules()
	{
		if ($this->regexRedirectRules === null) {
			$message = wfMessage('regex-redirect');
			if (!$message->exists() || ($text = trim($message->plain())) === '') {
				return $this->regexRedirectRules = [];
			}

			$line = strtok($text, "\n");
			while ($line !== false) {
				$rule = RegexRedirectRule::newFromText($line);
				if ($rule !== null) {
					$this->regexRedirectRules[] = $rule;
				}
				$line = strtok("\n");
			}
		}

		return $this->regexRedirectRules;
	}

	/**
	 * @param string $text
	 * @return string|null
	 */
	public function getSearchSuggestionText($text)
	{
		$rules = $this->getRegexRedirectRules();

		foreach ($rules as $rule) {
			if ($rule->hasSearch()) {
				if ($rule->replaceSearch($text)) {
					return $text;
				}
			} else {
				if ($rule->replaceTarget($text)) {
					return Html::rawElement(
						'p',
						['class' => 'mw-search-exists'],
						wfMessage(
							'searchmenu-exists',
							[
								wfEscapeWikiText($text),
								Message::numParam(0)
							]
						)->plain()
					);
				}
			}
		}
		return null;
	}
}
