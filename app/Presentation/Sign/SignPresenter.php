<?php

namespace App\Presentation\Sign;

use Nette;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use App\Presentation\BasePresenter;

final class SignPresenter extends BasePresenter
{     
    public function __construct()
    {
        parent::__construct();
    }

    public function actionOut(): void
    {
        $this->getUser()->logout();
        $this->flashMessage('Byl jste úspěšně odhlášen.');
        $this->redirect('Home:default');
    }

    public function createComponentSignInForm(): Form
    {
        $form = new Form;
        $form->addText('username', $this->translator->translate('messages.user.username'))
            ->setRequired($this->translator->translate('messages.user.username_required'));
        $form->addPassword('password', $this->translator->translate('messages.user.password'))
            ->setRequired($this->translator->translate('messages.user.password_required'));
        $form->addSubmit('send', $this->translator->translate('messages.user.signIn'));
        $form->onSuccess[] = [$this, 'signInFormSucceeded'];
        return $form;
    }

    public function signInFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $identity = $this->authenticator->authenticate($values->username, $values->password);
            $this->getUser()->login($identity);
            $this->flashMessage($this->translator->translate('messages.user.signIn_success'));
            $this->redirect('Home:default');
        } catch (Nette\Security\AuthenticationException $e) {
            $form->addError($this->translator->translate('messages.user.signIn_error'));
        }
    }

    public function createComponentSignUpForm(): Form
    {
        $form = new Form;
        $form->addText('name', $this->translator->translate('messages.user.name'))
            ->setRequired($this->translator->translate('messages.user.name_required'));
        $form->addText('surname', $this->translator->translate('messages.user.surname'))
            ->setRequired($this->translator->translate('messages.user.surname_required'));
        $form->addText('username', $this->translator->translate('messages.user.username'))
            ->setRequired($this->translator->translate('messages.user.username_required'));
        $form->addText('email', $this->translator->translate('messages.user.email'))
            ->setRequired($this->translator->translate('messages.user.email_required'));

        $form->addCheckbox('politicalAccount', $this->translator->translate('messages.user.politicalAccount'))
            ->setDefaultValue(false)
            ->setOption('description', $this->translator->translate('messages.user.politicalAccount_description'))
            ->addCondition($form::Equal, true)
            ->toggle('politicalPartyID');
                

        // Load political parties from DB
        $parties = $this->database->table('politicalParty')->fetchPairs('politicalParty_id', 'party_name');
        $form->addSelect('politicalParty', $this->translator->translate('messages.user.politicalParty'), $parties)
            ->setPrompt($this->translator->translate('messages.user.politicalParty_prompt'))
            ->setOption('id', 'politicalPartyID');

        $form->addCheckbox('publicIdentity', $this->translator->translate('messages.user.publicIdentity'))
            ->setDefaultValue(false)
            ->setOption('description', $this->translator->translate('messages.user.publicIdentity_description'));
        $form->addPassword('password', $this->translator->translate('messages.user.password'))
            ->setRequired($this->translator->translate('messages.user.password_required'));
        $form->addPassword('passwordVerify', $this->translator->translate('messages.user.passwordVerify'))
            ->setRequired($this->translator->translate('messages.user.passwordVerify_required'))
            ->addRule($form::EQUAL, $this->translator->translate('messages.user.passwordMismatch'), $form['password']);
        $form->addSubmit('send', $this->translator->translate('messages.user.signUp'));
        $form->onSuccess[] = [$this, 'signUpFormSucceeded'];
        return $form;
    }

    public function signUpFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            // If politicalAccount is checked, a party must be selected
            $politicalPartyId = null;
            if ($values->politicalAccount) {
                if (empty($values->politicalParty)) {
                    $form->addError($this->translator->translate('messages.user.politicalParty_required'));
                    return;
                }
                $politicalPartyId = $values->politicalParty;
            }

            $this->authenticator->register(
                $values->name,
                $values->surname,
                $values->username,
                $values->email,
                $values->password,
                $values->politicalAccount ?? false,
                $values->publicIdentity ?? false,
                $politicalPartyId
            );
            $this->flashMessage($this->translator->translate('messages.user.signUp_success'));
            $this->redirect('Sign:In');
        } catch (Nette\Security\AuthenticationException $e) {
            $form->addError($this->translator->translate('messages.user.signUp_error') . ': ' . $e->getMessage());
        }
    }

    public function actionProfile(): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage($this->translator->translate('messages.user.notLoggedIn'));
            $this->redirect('Sign:In');
        }
    }

    public function renderProfile(): void
    {
        $userId = $this->getUser()->getId();
        $roleRequests = $this->database->table('roleRequest')
            ->select('roleRequest.*, role.role_name')
            ->where('user_requestor_id', $userId)
            ->joinWhere('role', 'role.role_id = roleRequest.role_requested_id')
            ->order('roleRequest_createdTimestamp DESC')
            ->fetchAll();
        $this->template->roleRequests = $roleRequests;
    }

    /**
     * Form for role request
     */
    public function createComponentRoleRequestForm(): Form
    {
        $form = new Form;
        // Load available roles and translate their names
        $roles = [];
        foreach ($this->database->table('role')->where('role_id > ?', 3) as $role) {
            $roles[$role->role_id] = $this->translator->translate('messages.user.role.' . $role->role_name);
        }
        $form->addSelect('role', $this->translator->translate('messages.user.requestedRole'), $roles)
            ->setPrompt($this->translator->translate('messages.user.requestedRole_prompt'))
            ->setRequired($this->translator->translate('messages.user.requestedRole_required'))
            ->addCondition($form::EQUAL, 4)
            ->toggle('rolePoliticalPartyID');

        // Political parties
        $parties = $this->database->table('politicalParty')->fetchPairs('politicalParty_id', 'politicalParty_name');
        $form->addSelect('politicalParty', $this->translator->translate('messages.user.politicalParty'), $parties)
            ->setPrompt($this->translator->translate('messages.user.politicalParty_prompt'))
            ->setOption('id', 'rolePoliticalPartyID');

        $form->addSubmit('send', $this->translator->translate('messages.user.roleRequest_send'));
        $form->onSuccess[] = [$this, 'roleRequestFormSucceeded'];
        return $form;
    }

    public function roleRequestFormSucceeded(Form $form, \stdClass $values): void
    {
        $userId = $this->getUser()->getId();
        if (!$userId) {
            $this->flashMessage($this->translator->translate('messages.user.notLoggedIn'));
            $this->redirect('Sign:In');
            return;
        }

        // If the requested role is 'politician', a political party must be selected
        $roleName = $this->database->table('role')->get($values->role)->role_name ?? null;
        if ($roleName === 'politician' && empty($values->politicalParty)) {
            $form->addError($this->translator->translate('messages.user.politicalParty_required'));
            return;
        }

        // Check for duplicate request
        $existing = $this->database->table('roleRequest')
            ->where('user_requestor_id', $userId)
            ->where('role_requested_id', $values->role)
            ->fetch();
        if ($existing) {
            $form->addError($this->translator->translate('messages.user.roleRequest_duplicate'));
            return;
        }

        // Save the request and possibly update the user in a single transaction
        $this->database->beginTransaction();
        try {
            // If the requested role is 'politician', save the political party to the user
            if ($roleName === 'politician' && !empty($values->politicalParty)) {
                $this->database->table('user')->where('user_id', $userId)->update([
                    'user_politicalParty_id' => $values->politicalParty,
                ]);
            } else {
                // If the role is not 'politician', remove the political party from the user
                $this->database->table('user')->where('user_id', $userId)->update([
                    'user_politicalParty_id' => null,
                ]); 
            }


            // Save the request
            $this->database->table('roleRequest')->insert([
            'user_requestor_id' => $userId,
            'role_requested_id' => $values->role,
            'roleRequest_createdTimestamp' => new \DateTime(),
            ]);

            $this->database->commit();
        } catch (\Throwable $e) {
            $this->database->rollBack();
            $form->addError($this->translator->translate('messages.user.requests.roleRequest_error') . ' ' . $e->getMessage());
            return;
        }
        $this->flashMessage($this->translator->translate('messages.user.requests.roleRequest_success'));
        $this->redirect('this');
    }
}
