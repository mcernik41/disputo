<?php

use Nette;
use Nette\Security\SimpleIdentity;

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
			->where('username', $user_username)
			->fetch();

		if (!$row) {
			throw new Nette\Security\AuthenticationException('User not found.');
		}

		if (!$this->passwords->verify($password, $row->user_password)) {
			throw new Nette\Security\AuthenticationException('Invalid password.');
		}

		return new SimpleIdentity(
			$row->user_id,
			$row->role_role_id, // nebo pole více rolí
			['name' => $row->username, 'name' => $row->user_name, 'surname' => $row->user_surname],
		);
	}

    public function sleepIdentity(IIdentity $identity): SimpleIdentity
	{
		// vrátíme zástupnou identitu, kde v ID bude authtoken
		return new SimpleIdentity($identity->authtoken);
	}

	public function wakeupIdentity(IIdentity $identity): ?SimpleIdentity
	{
		// zástupnou identitu nahradíme plnou identitou, jako v authenticate()
        $row = $this->database->table('user')
			->where('user_authtoken', $identity->getId())
			->fetch();
            
		return $row
			? new SimpleIdentity(
                $row->id, 
                $row->role_role_id, 
                ['name' => $row->username, 'name' => $row->user_name, 'surname' => $row->user_surname]
            ) : null;
	}
}