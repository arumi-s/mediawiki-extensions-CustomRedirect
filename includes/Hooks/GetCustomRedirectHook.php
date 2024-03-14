<?php

namespace MediaWiki\Extension\CustomRedirect\Hooks;

use Title;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "CustomRedirect" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface GetCustomRedirectHook
{
	/**
	 * @param Title &$title Title
	 * @param bool &$changed True if the title was changed
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onGetCustomRedirect(Title &$title, bool &$changed);
}
