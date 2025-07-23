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
    #[Nette\DI\Attributes\Inject]
    public \Nette\Localization\Translator $translator;

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
			throw new Nette\Security\AuthenticationException($this->translator->translate('user.exceptions.notFound'));
		}

		if (!$this->passwords->verify($password, $row->user_password)) 
		{
			throw new Nette\Security\AuthenticationException($this->translator->translate('user.exceptions.invalidCredentials'));
		}

		// Generate and store the auth token
		$authtoken = bin2hex(random_bytes(32));
		$this->database->table('user')->where('user_id', $row->user_id)->update([
			'user_authtoken' => $authtoken
		]);

		return new SimpleIdentity(
			$row->user_id,
			$row->user_role_id,
			[
				'username' => $row->user_username,
				'name' => $row->user_name,
				'surname' => $row->user_surname,
				'authtoken' => $authtoken,
				'email' => $row->user_email,
				'roleName' => $this->database->table('role')->where('role_id', $row->user_role_id)->fetch()->role_name,
			],
		);
	}

    public function sleepIdentity(\Nette\Security\IIdentity $identity): \Nette\Security\IIdentity
	{
		// returns a placeholder identity, where the ID will be the auth token
		return new SimpleIdentity($identity->getData()['authtoken'] ?? null);
	}

	public function wakeupIdentity(\Nette\Security\IIdentity $identity): ?\Nette\Security\IIdentity
	{
		// replaces the placeholder identity with a full identity, as in authenticate()
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
			throw new \Exception($this->translator->translate('user.alreadyExists_email.alreadyExists_email'));
        }

        $rowUsername = $this->database->table('user')->where('user_username', $username)->fetch();
        if ($rowUsername) 
        {
            throw new \Exception($this->translator->translate('user.alreadyExists.alreadyExists'));
        }
        
		$this->database->beginTransaction();
		try {
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

			// generates request for approval
			$this->database->table('roleRequest')->insert([
			'user_requestor_id' => $result->user_id,
			'role_requested_id' => 3,
			'roleRequest_created' => new \Nette\Utils\DateTime()
			]);

			$this->database->commit();
		} 
		catch (\Throwable $e) 
		{
			$this->database->rollBack();
			throw $e;
		}
        
        \Tracy\Debugger::barDump($result);
    }
}