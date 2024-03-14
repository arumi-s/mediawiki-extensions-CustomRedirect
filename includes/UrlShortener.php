<?php

namespace MediaWiki\Extension\CustomRedirect;

use MediaWiki\Linker\LinkTarget;
use MediaWiki\MainConfigNames;
use Config;
use Title;

class UrlShortener
{
	/** @var Config */
	private $config;

	/** @var string */
	private $articlePath;

	/** @var string */
	private $shortUrlPath = '-/';

	/**
	 * @param Config $config
	 */
	public function __construct(
		Config $config
	) {
		$this->config = $config;
		$this->articlePath = $this->config->get(MainConfigNames::ArticlePath);
	}

	/**
	 * @return string
	 */
	public function getShortUrlPath()
	{
		return $this->shortUrlPath;
	}

	/**
	 * Resolves the short url to a page
	 * @param string $path Url path only, without the first forward slash
	 * @return ?Title The resolved page title or null if unsuccessful
	 */
	public function resolve(string $path)
	{
		if (substr($path, 0, strlen($this->shortUrlPath)) === $this->shortUrlPath) {
			$title = Title::newFromID((int) base_convert(substr($path, 2), 32, 10));
			if ($title !== null) {
				return $title;
			}
		}

		return null;
	}

	/**
	 * Gets short url for a page
	 * @param LinkTarget $page
	 * @param ?array $query Optional url query as an array
	 * @return ?string
	 */
	public function forPage(LinkTarget $page, ?array $query = null)
	{
		if (!($page instanceof Title) || $page->isExternal()) {
			return null;
		}

		return $this->forPageId($page->getArticleID(), $query);
	}

	/**
	 * Gets short url for a known page id
	 * @param int $pageId
	 * @param ?array $query Optional url query as an array
	 * @return ?string
	 */
	public function forPageId(int $pageId, ?array $query = null)
	{
		return $pageId > 0 ?
			str_replace('$1', $this->shortUrlPath . base_convert($pageId, 10, 32), $this->articlePath) . (empty($query) ? '' : '?' . http_build_query($query)) :
			null;
	}
}
