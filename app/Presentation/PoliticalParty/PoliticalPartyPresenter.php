<?php

namespace App\Presentation\PoliticalParty;

use Nette;
use Nette\Application\UI\Form;
use App\Presentation\BasePresenter;
use Nette\Application\UI\Presenter;

final class PoliticalPartyPresenter extends BasePresenter
{
    public function __construct()
    {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        $this->template->approvedParties = $this->database->table('politicalParty')
            ->where('politicalParty_user_approval IS NOT NULL')
            ->order('politicalParty_name')
            ->fetchAll();
        $this->template->unapprovedParties = $this->database->table('politicalParty')
            ->where('politicalParty_user_approval IS NULL')
            ->order('politicalParty_name')
            ->fetchAll();
    }

    protected function createComponentAddPartyForm(): Form
    {
        $form = new Form;
        $form->addText('acronyme', $this->translator->translate('messages.party.acronyme'))
            ->setRequired($this->translator->translate('messages.party.acronyme_required'));
        $form->addText('name', $this->translator->translate('messages.party.name'))
            ->setRequired($this->translator->translate('messages.party.name_required'));
        $form->addSubmit('send', $this->translator->translate('messages.party.add'));
        $form->onSuccess[] = [$this, 'addPartyFormSucceeded'];
        return $form;
    }

    public function addPartyFormSucceeded(Form $form, \stdClass $values): void
    {
        $exists = $this->database->table('politicalParty')->where('politicalParty_name', $values->name)->fetch();
        if ($exists) 
        {
            $form->addError($this->translator->translate('messages.party.exists'));
            return;
        }

        $this->database->table('politicalParty')->insert([
            'politicalParty_acronyme' => $values->acronyme,
            'politicalParty_name' => $values->name,
            'politicalParty_user_creating' => $this->getUser()->getId(),
        ]);
        $this->flashMessage($this->translator->translate('messages.party.added'), 'success');
        $this->redirect('this');
    }

    public function actionApprove(int $id): void
    {
        if (!$this->getUser()->isAllowed('politicalParty', 'approve')) {
            $this->flashMessage($this->translator->translate('messages.party.no_permission'), 'error');
            $this->redirect('default');
        }

        $party = $this->database->table('politicalParty')->get($id);
        if (!$party) {
            $this->flashMessage($this->translator->translate('messages.party.not_found'), 'error');
            $this->redirect('default');
        }

        $party->update([
            'politicalParty_user_approval' => $this->getUser()->getId(),
        ]);
        $this->flashMessage($this->translator->translate('messages.party.approve_success'), 'success');
        $this->redirect('default');
    }
}
