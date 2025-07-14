<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Services\DatabaseConsistencyChecker;
use Nette;

abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    /**
     * @inject
     * @var DatabaseConsistencyChecker
     */
    public DatabaseConsistencyChecker $dbChecker;

    /**
     * Zde můžete přidat společné proměnné, metody, práci s uživateli atd.
     */
    protected function startup(): void
    {
        parent::startup();
        $this->dbChecker->checkRoles();
        // Například: předání identity do šablony
        $this->template->user = $this->getUser();
    }
}
