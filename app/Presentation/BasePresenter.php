<?php

declare(strict_types=1);

namespace App\Presentation;

use Nette;

abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    /**
     * Zde můžete přidat společné proměnné, metody, práci s uživateli atd.
     */
    protected function startup(): void
    {
        parent::startup();
        // Například: předání identity do šablony
        $this->template->user = $this->getUser();
    }
}
