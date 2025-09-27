<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Services\DatabaseConsistencyChecker;
use Nette;
use App\Services\DisputoAuthenticator;
use App\Services\DisputoAuthorizator;
use Contributte\Translation\LocalesResolvers\Session;
use Nette\Security\Permission;
use Tracy\Debugger;

abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    protected Permission $acl;
    
    #[Nette\DI\Attributes\Inject]
    public DatabaseConsistencyChecker $dbChecker;

    #[Nette\DI\Attributes\Inject]
    public DisputoAuthenticator $authenticator;

    #[Nette\DI\Attributes\Inject]
    public DisputoAuthorizator $authorizator;

    #[Nette\DI\Attributes\Inject]
    public \Nette\Localization\Translator $translator;

    #[Nette\DI\Attributes\Inject]
    public \Nette\Database\Explorer $database;

    #[Nette\DI\Attributes\Inject]
    public Session $translatorSessionResolver;

    protected Tracy\ILogger $logger;

    protected function startup(): void
    {
        // Check database consistency
        parent::startup();
        $this->dbChecker->checkRoles();
        $this->dbChecker->checkResources();
        $this->dbChecker->checkResourceAccess();

        $this->template->user = $this->getUser();

        $this->translatorSessionResolver->setLocale('cs');

        // Initialize ACL and load roles
        $this->acl = new Permission();
        $this->authorizator->initializeAcl($this->acl);
    }

    public function handleChangeLocale(string $locale): void
    {
        $this->translatorSessionResolver->setLocale($locale);
        $this->redirect('this');
    }
}
