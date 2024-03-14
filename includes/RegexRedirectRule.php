<?php

namespace MediaWiki\Extension\CustomRedirect;

class RegexRedirectRule
{
	/**
	 * Matcher regex
	 * @var string
	 */
	private $regex;

	/**
	 * Target title text
	 * @var string
	 */
	private $target;

	/**
	 * Search suggestion text
	 * @var string|null
	 */
	private $search;

	function __construct(
		string $regex,
		string $target,
		?string $search = null
	) {
		$this->regex = $regex;
		$this->target = $target;
		$this->search = $search;
	}

	/**
	 * Creates a RegexRedirectRule from a regex-redirect definition
	 * 
	 * @param string $text
	 * @return RegexRedirectRule|null Rule or null if unsuccessful
	 */
	public static function newFromText(string $text)
	{
		$text = trim($text);

		if ($text === '') {
			return null;
		}

		// use the first character as delimiter
		$delimiter = mb_substr($text, 0, 1);

		if ($delimiter === '') {
			return null;
		}

		$parts = preg_split('/((?<!\\\\)(?:\\\\\\\\)*)' . preg_quote($delimiter, '/') . '/u', $text, 4);
		if (count($parts) >= 3) {
			return new RegexRedirectRule(
				$delimiter . $parts[1] . $delimiter . 'u',
				$parts[2],
				empty($parts[3]) ? null : $parts[3]
			);
		}

		return null;
	}

	/**
	 * @return bool
	 */
	public function hasSearch(): bool
	{
		return $this->search !== null;
	}

	/**
	 * @param string &$text
	 * @return bool
	 */
	public function replaceTarget(string &$text): bool
	{
		$result = @preg_replace($this->regex, $this->target, $text, 1, $count);
		if ($count === 1) {
			$text = $result;
			return true;
		}
		return false;
	}

	/**
	 * @param string &$text
	 * @return bool
	 */
	public function replaceSearch(string &$text): bool
	{
		if (!$this->hasSearch()) {
			return false;
		}

		$result = @preg_replace($this->regex, $this->search, $text, 1, $count);
		if ($count === 1) {
			$text = $result;
			return true;
		}
		return false;
	}

	/**
	 * @param string $text
	 * @return string|null
	 */
	public function matchTarget(string $text): ?string
	{
		$result = @preg_replace($this->regex, $this->target, $text, 1, $count);
		return $count === 1 ? $result : null;
	}

	/**
	 * @param string $text
	 * @return string|null
	 */
	public function matchSearch(string $text): ?string
	{
		if (!$this->hasSearch()) {
			return null;
		}

		$result = @preg_replace($this->regex, $this->search, $text, 1, $count);
		return $count === 1 ? $result : null;
	}
}

