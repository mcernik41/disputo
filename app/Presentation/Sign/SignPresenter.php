<?php

namespace App\Presentation\Sign;

use Nette;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use App\Services\DisputoAuthenticator;

final class SignPresenter extends Presenter
{
    #[Nette\DI\Attributes\Inject]
    public DisputoAuthenticator $authenticator;

    #[Nette\DI\Attributes\Inject]
    public \Nette\Localization\Translator $translator;

    #[Nette\DI\Attributes\Inject]
    public \Nette\Database\Explorer $database;
     
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
        try 
        {
            $identity = $this->authenticator->authenticate($values->username, $values->password);
            $this->getUser()->login($identity);
            $this->flashMessage($this->translator->translate('messages.user.signIn_success'));
            $this->redirect('Home:default');
        } 
        catch (Nette\Security\AuthenticationException $e) 
        {
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
                

        // Načtení politických stran z DB
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
        try 
        {
            // Pokud je politicalAccount zaškrtnuté, musí být vybrána strana
            $politicalPartyId = null;
            if ($values->politicalAccount) 
            {
                if (empty($values->politicalParty)) 
                {
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
        } 
        catch (Nette\Security\AuthenticationException $e) 
        {
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
        dump($this->getUser()->getIdentity());
        $userId = $this->getUser()->getId();
        $roleRequests = $this->database->table('roleRequest')
            ->select('roleRequest.*, role.role_name')
            ->where('user_requestor_id', $userId)
            ->joinWhere('role', 'role.role_id = roleRequest.role_requested_id')
            ->fetchAll();
        $this->template->roleRequests = $roleRequests;
    }
}
