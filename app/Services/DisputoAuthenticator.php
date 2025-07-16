<?php
declare(strict_types=1);
namespace App\Services;

use Nette\Security\SimpleIdentity;
use Nette\Security\IIdentity;
use Nette\Security\Authenticator;
use Nette\Security\IdentityHandler;
use Nette\Database\Explorer;
use Nette\Security\Passwords;

final class DisputoAuthenticator implements Authenticator, IdentityHandler
{
	public function __construct(
		private Explorer $database,
		private Passwords $passwords,
	) {
	}

	public function authenticate(string $username, string $password): SimpleIdentity
	{
		$row = $this->database->table('user')
			->where('user_username', $username)
			->fetch();

		if (!$row) 
		{
			throw new Nette\Security\AuthenticationException('User not found.');
		}

		if (!$this->passwords->verify($password, $row->user_password)) 
		{
			throw new Nette\Security\AuthenticationException('Invalid password.');
		}

		// Generování a uložení authtokenu
		$authtoken = bin2hex(random_bytes(32));
		$this->database->table('user')->where('user_id', $row->user_id)->update([
			'user_authtoken' => $authtoken
		]);

		return new SimpleIdentity(
			$row->user_id,
			$row->user_role_id, // nebo pole více rolí
			[
				'username' => $row->user_username,
				'name' => $row->user_name,
				'surname' => $row->user_surname,
				'authtoken' => $authtoken,
			],
		);
	}

    public function sleepIdentity(\Nette\Security\IIdentity $identity): \Nette\Security\IIdentity
	{
		// vrátíme zástupnou identitu, kde v ID bude authtoken
		return new SimpleIdentity($identity->getData()['authtoken'] ?? null);
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

	public function register(
        string $name,
        string $surname,
        string $username,
        string $email,
        string $password,
        bool $politicalAccount = false,
        bool $publicIdentity = false
    ): void
    {
        $row = $this->database->table('user')->where('user_email', $email)->fetch();
        if ($row) 
        {
            throw new \Exception('Uživatel s tímto emailem již existuje.');
        }

        $rowUsername = $this->database->table('user')->where('user_username', $username)->fetch();
        if ($rowUsername) 
        {
            throw new \Exception('Uživatel s tímto uživatelským jménem již existuje.');
        }
        
        $result = $this->database->table('user')->insert([
            'user_name' => $name,
            'user_surname' => $surname,
            'user_username' => $username,
            'user_email' => $email,
            'user_password' => $this->passwords->hash($password),
            'user_role_id' => 2, // unapproved user role
            'user_deleted' => 0,
            'user_approved' => 0,
            'user_politicalAccount' => $politicalAccount ? 1 : 0,
            'user_publicIdentity' => $publicIdentity ? 1 : 0,
        ]);
        
        \Tracy\Debugger::barDump($result);
    }
}