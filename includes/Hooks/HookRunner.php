<?php

namespace MediaWiki\Extension\CustomRedirect\Hooks;

use MediaWiki\HookContainer\HookContainer;
use Title;

/**
 * This is a hook runner class, see docs/Hooks.md in core.
 * @internal
 */
class HookRunner implements
	GetCustomRedirectHook
{
	private HookContainer $hookContainer;

	public function __construct(HookContainer $hookContainer)
	{
		$this->hookContainer = $hookContainer;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onGetCustomRedirect(Title &$title, bool &$changed)
	{
		return $this->hookContainer->run(
			'GetCustomRedirect',
			[&$title, &$changed]
		);
	}
}
