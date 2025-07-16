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

    protected function startup(): void
    {
        // Check database consistency
        parent::startup();
        $this->dbChecker->checkRoles();
        $this->template->user = $this->getUser();
    }
}
