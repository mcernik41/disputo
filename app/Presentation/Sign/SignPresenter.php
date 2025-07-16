<?php

namespace App\Presentation\Sign;

use Nette;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use App\Services\DisputoAuthenticator;

final class SignPresenter extends Presenter
{
    /** @var \App\Services\DisputoAuthenticator */
    private $authenticator;

    public function __construct(DisputoAuthenticator $authenticator)
    {
        parent::__construct();
        $this->authenticator = $authenticator;
    }

    public function actionLogout(): void
    {
        $this->getUser()->logout();
        $this->flashMessage('Byl jste úspěšně odhlášen.');
        $this->redirect('Sign:In');
    }

    public function createComponentSignInForm(): Form
    {
        $form = new Form;
        $form->addText('username', 'Uživatelské jméno:')
            ->setRequired('Zadejte uživatelské jméno.');
        $form->addPassword('password', 'Heslo:')
            ->setRequired('Zadejte heslo.');
        $form->addSubmit('send', 'Přihlásit se');
        $form->onSuccess[] = [$this, 'signInFormSucceeded'];
        return $form;
    }

    public function signInFormSucceeded(Form $form, \stdClass $values): void
    {
        try 
        {
            $identity = $this->authenticator->authenticate($values->username, $values->password);
            $this->getUser()->login($identity);
            $this->flashMessage('Přihlášení bylo úspěšné.');
            $this->redirect('Home:default');
        } 
        catch (Nette\Security\AuthenticationException $e) 
        {
            $form->addError('Neplatné přihlašovací údaje.');
        }
    }

    public function createComponentSignUpForm(): Form
    {
        $form = new Form;
        $form->addText('name', 'Jméno:')
            ->setRequired('Zadejte jméno.');
        $form->addText('surname', 'Příjmení:')
            ->setRequired('Zadejte příjmení.');
        $form->addText('username', 'Uživatelské jméno:')
            ->setRequired('Zadejte uživatelské jméno.');
        $form->addText('email', 'Email:')
            ->setRequired('Zadejte email.');
        $form->addCheckbox('politicalAccount', 'Politický účet')
            ->setDefaultValue(false)
            ->setOption('description', 'Ostatní uživatelé uvidí, že jste politik');
        $form->addCheckbox('publicIdentity', 'Veřejná identita')
            ->setDefaultValue(false)
            ->setOption('description', 'Ostatní uživatelé uvidí vaši identitu (jméno, příjmení, uživatelské jméno)');
        $form->addPassword('password', 'Heslo:')
            ->setRequired('Zadejte heslo.');
        $form->addPassword('passwordVerify', 'Heslo znovu:')
            ->setRequired('Zadejte heslo znovu.')
            ->addRule($form::EQUAL, 'Hesla se neshodují.', $form['password']);
        $form->addSubmit('send', 'Registrovat se');
        $form->onSuccess[] = [$this, 'signUpFormSucceeded'];
        return $form;
    }

    public function signUpFormSucceeded(Form $form, \stdClass $values): void
    {
        try 
        {
            $this->authenticator->register(
                $values->name,
                $values->surname,
                $values->username,
                $values->email,
                $values->password,
                $values->politicalAccount ?? false,
                $values->publicIdentity ?? false
            );
            $this->flashMessage('Registrace byla úspěšná. Nyní se můžete přihlásit.');
            $this->redirect('Sign:In');
        } 
        catch (\Exception $e) 
        {
            $form->addError('Registrace se nezdařila: ' . $e->getMessage());
        }
    }
}
