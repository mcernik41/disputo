<?php

class MyAuthorizator implements Nette\Security\Authorizator
{
	public function isAllowed($role, $resource, $operation): bool
	{
		if ($role === 'admin') {
			return true;
		}
		if ($role === 'user' && $resource === 'article') {
			return true;
		}

		// ...

		return false;
	}
}