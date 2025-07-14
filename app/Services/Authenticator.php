<?php

use Nette\Security\SimpleIdentity;
use Nette\Security\IIdentity;

final class Authenticator implements Nette\Security\Authenticator, Nette\Security\IdentityHandler
{
	public function __construct(
		private Nette\Database\Explorer $database,
		private Nette\Security\Passwords $passwords,
	) {
	}

	public function authenticate(string $username, string $password): SimpleIdentity
	{
		$row = $this->database->table('user')
			->where('user_username', $username)
			->fetch();

		if (!$row) {
			throw new Nette\Security\AuthenticationException('User not found.');
		}

		if (!$this->passwords->verify($password, $row->user_password)) {
			throw new Nette\Security\AuthenticationException('Invalid password.');
		}

		return new SimpleIdentity(
			$row->user_id,
			$row->user_role_id, // nebo pole více rolí
			['username' => $row->user_username, 'name' => $row->user_name, 'surname' => $row->user_surname],
		);
	}

    public function sleepIdentity(\Nette\Security\IIdentity $identity): \Nette\Security\IIdentity
	{
		// vrátíme zástupnou identitu, kde v ID bude authtoken
		return new SimpleIdentity($identity->authtoken);
	}

	public function wakeupIdentity(\Nette\Security\IIdentity $identity): ?\Nette\Security\IIdentity
	{
		// zástupnou identitu nahradíme plnou identitou, jako v authenticate()
        $row = $this->database->table('user')
			->where('user_authtoken', $identity->getId())
			->fetch();
			
		return $row
			? new SimpleIdentity(
                $row->user_id, 
                $row->user_role_id, 
                ['username' => $row->user_username, 'name' => $row->user_name, 'surname' => $row->user_surname]
            ) : null;
	}
}