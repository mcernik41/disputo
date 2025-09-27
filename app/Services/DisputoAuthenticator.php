<?php
declare(strict_types=1);
namespace App\Services;

use Nette\Security\SimpleIdentity;
use Nette\Security\IIdentity;
use Nette\Security\Authenticator;
use Nette\Security\IdentityHandler;
use Nette\Database\Explorer;
use Nette\Security\Passwords;
use App\Exceptions\AuthenticationException;

final class DisputoAuthenticator implements Authenticator, IdentityHandler
{
	public function __construct(
		private Explorer $database,
		private Passwords $passwords,
		private \Nette\Localization\Translator $translator,
	) {
	}

	public function authenticate(string $username, string $password): SimpleIdentity
	{
		$row = $this->database->table('user')
			->where('user_username', $username)
			->fetch();

		if (!$row) {
			throw new AuthenticationException($this->translator->translate('messages.user.exceptions.notFound'));
		}

		if (!$this->passwords->verify($password, $row->user_password)) {
			throw new AuthenticationException($this->translator->translate('messages.user.exceptions.invalidCredentials'));
		}

		// Generate and store the auth token
		$authtoken = bin2hex(random_bytes(32));
		$this->database->table('user')->where('user_id', $row->user_id)->update([
			'user_authtoken' => $authtoken
		]);

	   return $this->createIdentityFromUserRow($row, $authtoken);
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

		if (!$row) {
		   return null;
		}
	   return $this->createIdentityFromUserRow($row, $row->user_authtoken);
	}

	public function register(
		string $name,
		string $surname,
		string $username,
		string $email,
		string $password,
		bool $politicalAccount = false,
		bool $publicIdentity = false,
		?int $politicalPartyId = null
	): void
	{
		$row = $this->database->table('user')->where('user_email', $email)->fetch();
		if ($row) {
			throw new \Exception($this->translator->translate('messages.user.alreadyExists_email.alreadyExists_email'));
		}

		$rowUsername = $this->database->table('user')->where('user_username', $username)->fetch();
		if ($rowUsername) {
			throw new \Exception($this->translator->translate('messages.user.alreadyExists.alreadyExists'));
		}
		
		$this->database->beginTransaction();
		try {
			$insertData = [
				'user_name' => $name,
				'user_surname' => $surname,
				'user_username' => $username,
				'user_email' => $email,
				'user_password' => $this->passwords->hash($password),
				'user_role_id' => 2, // unapproved user role
				'user_deleted' => 0,
				'user_politicalAccount' => $politicalAccount ? 1 : 0,
				'user_publicIdentity' => $publicIdentity ? 1 : 0,
			];

			if ($politicalAccount && $politicalPartyId) {
				$insertData['user_politicalParty_id'] = $politicalPartyId;
			}
			$result = $this->database->table('user')->insert($insertData);

			// generates request for approval
			$this->database->table('roleRequest')->insert([
				'user_requestor_id' => $result->user_id,
				'role_requested_id' => $politicalAccount ? 4 : 3,
				'roleRequest_createdTimestamp' => new \Nette\Utils\DateTime()
			]);

			$this->database->commit();
		} catch (\Throwable $e) {
			$this->database->rollBack();
			throw $e;
		}
		
		\Tracy\Debugger::barDump($result);
	}
	/**
	 * Creates a SimpleIdentity from a user database row and auth token.
	 *
	 * @param \Nette\Database\Table\ActiveRow $row
	 * @param string $authtoken
	 * @return SimpleIdentity
	 */
	private function createIdentityFromUserRow(\Nette\Database\Table\ActiveRow $row, string $authtoken): SimpleIdentity
	{
		$roleRow = $this->database->table('role')->where('role_id', $row->user_role_id)->fetch();
		$roleName = $roleRow ? $roleRow->role_name : null;
		return new SimpleIdentity(
			$row->user_id,
			$roleName,
			[
				'username' => $row->user_username,
				'name' => $row->user_name,
				'surname' => $row->user_surname,
				'authtoken' => $authtoken,
				'email' => $row->user_email,
				'roleName' => $roleName,
			],
		);
	}
}