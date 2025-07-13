<?php

declare(strict_types=1);

namespace App\Presentation\Home;

use Nette;
use Nette\Database\Explorer;
use Contributte\Translation\Translator;
use Contributte\Translation\LocalesResolvers\Session;
use App\Presentation\BasePresenter;

final class HomePresenter extends BasePresenter
{
    #[Nette\DI\Attributes\Inject]
    public Explorer $database;

    #[Nette\DI\Attributes\Inject]
    public Translator $translator;

    #[Nette\DI\Attributes\Inject]
    public Session $translatorSessionResolver;

    public function handleChangeLocale(string $locale): void
    {
        $this->translatorSessionResolver->setLocale($locale);
        $this->redirect('this');
    }

    public function renderDefault(): void
    {
        $prefixedTranslator = $this->translator->createPrefixedTranslator('messages');
        $this->translatorSessionResolver->setLocale('cs');
    }
}
