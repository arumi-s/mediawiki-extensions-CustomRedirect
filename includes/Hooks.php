<?php

namespace MediaWiki\Extension\CustomRedirect;

use Config;
use WebRequest;
use MediaWiki\Linker\LinkTarget;
use Parser;
use Title;
use Article;
use OutputPage;
use Skin;
use MediaWiki\Revision\RevisionRecord;
use HtmlArmor;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\RevisionLookup;
use IDBAccessObject;
use ISearchResultSet;
use Html;

class Hooks implements
	\MediaWiki\Hook\InitializeArticleMaybeRedirectHook,
	\MediaWiki\Hook\BeforeParserFetchTemplateRevisionRecordHook,
	\MediaWiki\Hook\BeforeParserFetchFileAndTitleHook,
	\MediaWiki\Linker\Hook\HtmlPageLinkRendererBeginHook,
	\MediaWiki\Hook\SpecialSearchResultsHook,
	\MediaWiki\Api\Hook\ApiOpenSearchSuggestHook
{
	/** @var Config */
	private $config;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var UrlShortener */
	private $urlShortener;

	/** @var CustomRedirectLookup */
	private $customRedirectLookup;

	/**
	 * @param Config $config
	 * @param RevisionLookup $revisionLookup
	 * @param UrlShortener $urlShortener
	 * @param CustomRedirectLookup $customRedirectLookup
	 */
	public function __construct(
		Config $config,
		RevisionLookup $revisionLookup,
		UrlShortener $urlShortener,
		CustomRedirectLookup $customRedirectLookup
	) {
		$this->config = $config;
		$this->revisionLookup = $revisionLookup;
		$this->urlShortener = $urlShortener;
		$this->customRedirectLookup = $customRedirectLookup;
	}

	/**
	 * {@inheritDoc}
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return void
	 */
	public function onBeforePageDisplay($out, $skin): void
	{
		if (!$this->config->get('CustomRedirectShowShortUrl')) {
			return;
		}

		$request = $out->getRequest();
		$id = $out->getTitle()->getArticleID();
		if ($id > 1 && in_array($out->getActionName(), ['view'])) {
			$query = $request->getQueryValues();
			unset($query['title']);
			$out->setIndicators(
				[
					Html::rawElement(
						'a',
						[
							'rel' => 'nofollow',
							'href' => $this->urlShortener->forPageId($id, $query)
						],
						$out->msg('shorturl')
					)
				]
			);
		}
	}

	/**
	 * Perform regex redirect on web requests.
	 * {@inheritDoc}
	 * @param Title $title
	 * @param WebRequest $request
	 * @param bool &$ignoreRedirect
	 * @param Title|string &$target
	 * @param Article &$article
	 * @return bool|void
	 */
	public function onInitializeArticleMaybeRedirect($title, $request, &$ignoreRedirect, &$target, &$article)
	{
		if ($ignoreRedirect || $target) {
			return;
		}

		if ($this->config->get('CustomRedirectUseShortUrl')) {
			$path = trim($request->getVal('title', ''));
			$resolved = $this->urlShortener->resolve($path);
			if ($resolved !== null) {
				// in case $wgTitle will be used somewhere
				global $wgTitle;
				$wgTitle = $resolved;
				$article = Article::newFromTitle($resolved, $article->getContext());
				return;
			}
		}

		if ($title instanceof Title && $title->isRedirect()) {
			return;
		}

		$changed = $this->doRedirect($title);
		if ($changed) {
			// in case $wgTitle will be used somewhere
			global $wgTitle;
			$wgTitle = $title;
			$article = Article::newFromTitle($title, $article->getContext());
		}
	}

	/**
	 * Perform regex redirect on template calls in parser.
	 * {@inheritDoc}
	 * @param ?LinkTarget $contextTitle
	 * @param LinkTarget $title
	 * @param bool &$skip
	 * @param ?RevisionRecord &$revRecord
	 * @return bool|void
	 */
	public function onBeforeParserFetchTemplateRevisionRecord(?LinkTarget $contextTitle, LinkTarget $title, bool &$skip, ?RevisionRecord &$revRecord)
	{
		if (!$this->doRedirect($title)) {
			return;
		}

		$revisionRecord = $this->revisionLookup->getRevisionByTitle($title, 0, IDBAccessObject::READ_NORMAL);
		if ($revisionRecord === null) {
			return;
		}

		$revRecord = $revisionRecord;
	}

	/**
	 * Perform regex redirect on file fetches.
	 * {@inheritDoc}
	 * @param Parser $parser
	 * @param Title &$title
	 * @param array &$options
	 * @param string &$descQuery
	 * @return bool|void
	 */
	public function onBeforeParserFetchFileAndTitle($parser, &$title, &$options, &$descQuery)
	{
		$this->doRedirect($title);
	}

	/**
	 * Makes possible redirect links known.
	 * {@inheritDoc}
	 * @param LinkRenderer $linkRenderer
	 * @param LinkTarget $target
	 * @param string|HtmlArmor|null &$text
	 * @param string[] &$customAttribs
	 * @param string[] &$query
	 * @param string &$ret
	 * @return bool|void
	 */
	public function onHtmlPageLinkRendererBegin($linkRenderer, $target, &$text, &$customAttribs, &$query, &$ret)
	{
		if (isset($extraAttribs['unred']) || (isset($query['redirect']) && $query['redirect'] === 'no')) {
			return;
		}

		if ($target instanceof Title && $target->isRedirect()) {
			return;
		}

		$redirectTarget = $this->customRedirectLookup->getRedirectTarget($target);
		if ($redirectTarget === null || $redirectTarget->isSameLinkAs($target)) {
			return;
		}

		$extraAttribs['unred'] = '';
		$ret = $linkRenderer->makeLink($redirectTarget, $text, $extraAttribs, $query);

		return false;
	}

	/**
	 * Show suggestion text on search page
	 * {@inheritDoc}
	 * @param string $term
	 * @param ?ISearchResultSet &$titleMatches
	 * @param ?ISearchResultSet &$textMatches
	 * @return bool|void 
	 */
	public function onSpecialSearchResults($text, &$titleMatches, &$textMatches)
	{
		$suggestion = $this->customRedirectLookup->getSearchSuggestionText($text);
		if (!$suggestion) {
			return;
		}

		global $wgOut;
		$wgOut->addWikiTextAsInterface(Html::rawElement('p', [], $suggestion));
	}

	/**
	 * {@inheritDoc}
	 * @param array[] &$results
	 * @return bool|void
	 */
	public function onApiOpenSearchSuggest(&$results)
	{
		foreach ($results as &$row) {
			$target = $this->customRedirectLookup->getRedirectTarget($row['title']);
			if ($target !== null && !$target->isSameLinkAs($row['title'])) {
				$row['redirect from'] = $row['title'];
				$row['title'] = $target;
			}
		}

		return true;
	}

	/**
	 * @param Title &$title
	 * @return bool
	 */
	private function doRedirect(&$title)
	{
		$target = $this->customRedirectLookup->getRedirectTarget($title);

		if ($target->equals($title)) {
			return false;
		}

		$title = $target;
		return true;
	}
}
