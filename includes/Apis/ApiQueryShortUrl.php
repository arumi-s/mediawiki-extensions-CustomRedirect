<?php

namespace MediaWiki\Extension\CustomRedirect\Apis;

use ApiQuery;
use ApiQueryBase;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Extension\CustomRedirect\UrlShortener;

class ApiQueryShortUrl extends ApiQueryBase
{
	/** @var UrlShortener */
	private $urlShortener;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @param UrlShortener $urlShortener
	 */
	public function __construct(
		ApiQuery $query,
		$moduleName,
		UrlShortener $urlShortener
	) {
		parent::__construct($query, $moduleName, 'pi');
		$this->urlShortener = $urlShortener;
	}

	/**
	 * @return PageIdentity[]
	 */
	protected function getTitles()
	{
		$pageSet = $this->getPageSet();
		$titles = $pageSet->getGoodPages();

		return $titles;
	}

	public function execute()
	{
		$titles = $this->getTitles();

		if (count($titles) === 0) {
			return;
		}

		$result = $this->getResult();

		foreach ($titles as $title) {
			$id = $title->getArticleID();
			$result->addValue(
				['query', 'pages'],
				$id,
				['shorturl' => $this->urlShortener->forPageId($id)]
			);
		}
	}

	public function getCacheMode($params)
	{
		return 'public';
	}
}
