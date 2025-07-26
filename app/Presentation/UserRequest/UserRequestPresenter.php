<?php

namespace App\Presentation\UserRequest;

use Nette;
use App\Presentation\BasePresenter;

final class UserRequestPresenter extends BasePresenter
{

    public function checkRequirements($element): void
    {
        parent::checkRequirements($element);
        if (!$this->getUser()->isAllowed('userRequest', 'view')) {
            $this->flashMessage($this->translator->translate('messages.user.requests.notAllowed'), 'error');
            $this->redirect(':Home:default');
        }
    }

    private function getBaseSelectColumns(): string
    {
        return '
            roleRequest.*, role.role_name, 
            requestor.user_name AS requestor_name, 
            requestor.user_name AS requestor_userName, 
            requestor.user_surname AS requestor_userSurname, 
            requestor.user_id AS requestor_userId, 
            currentRole.role_name AS currentRoleName
        ';
    }

    private function getBaseFromJoin(): string
    {
        return '
            FROM roleRequest
            JOIN role ON role.role_id = roleRequest.role_requested_id
            JOIN user AS requestor ON requestor.user_id = roleRequest.user_requestor_id
            JOIN role AS currentRole ON currentRole.role_id = requestor.user_role_id
        ';
    }

    public function renderDefault(): void
    {
        // Nevyřešené požadavky
        $sql = 'SELECT ' . $this->getBaseSelectColumns() . $this->getBaseFromJoin() . ' WHERE roleRequest.user_approval_id IS NULL';
        $this->template->requests = $this->database->getConnection()->query($sql)->fetchAll();

        // Vyřešené požadavky (schválené i zamítnuté)
        $resolvedSelect = 'SELECT ' . $this->getBaseSelectColumns() . ',
                CASE WHEN roleRequest.roleRequest_isApproved = 1 THEN 1 ELSE 0 END AS isApproved
            ' . $this->getBaseFromJoin() . '
            WHERE roleRequest.user_approval_id IS NOT NULL
            ORDER BY roleRequest.roleRequest_approvedTimestamp DESC
        ';
        $this->template->resolvedRequests = $this->database->getConnection()->query($resolvedSelect)->fetchAll();
    }


    public function handleApprove(int $id): void
    {
        // Kontrola oprávnění
        if (!$this->getUser()->isAllowed('userRequest', 'approve')) {
            $this->flashMessage($this->translator->translate('messages.user.requests.notAllowed'), 'error');
            $this->redirect('this');
            return;
        }

        // Získání požadavku
        $request = $this->database->table('roleRequest')->get($id);
        if (!$request) {
            $this->flashMessage($this->translator->translate('messages.user.requests.notFound'), 'error');
            $this->redirect('this');
            return;
        }


        // Obě změny v jedné transakci
        $this->database->getConnection()->beginTransaction();
        try {
            $request->update([
                'roleRequest_isApproved' => true,
                'roleRequest_approvedTimestamp' => new \DateTime(),
                'user_approval_id' => $this->getUser()->getId(),
            ]);
            $this->database->table('user')->get($request->user_requestor_id)->update([
                'user_role_id' => $request->role_requested_id,
            ]);
            $this->database->getConnection()->commit();
        } catch (\Throwable $e) {
            $this->database->getConnection()->rollBack();
            $this->flashMessage($this->translator->translate('messages.user.requests.approveError') . $e->getMessage(), 'error');
            $this->redirect('this');
            return;
        }

        $this->flashMessage($this->translator->translate('messages.user.requests.approved'), 'success');
        $this->redirect('this');
    }

    public function handleReject(int $id): void
    {
        // Kontrola oprávnění
        if (!$this->getUser()->isAllowed('userRequest', 'approve')) {
            $this->flashMessage($this->translator->translate('messages.user.requests.notAllowed'), 'error');
            $this->redirect('this');
            return;
        }

        // Získání požadavku
        $request = $this->database->table('roleRequest')->get($id);
        if (!$request) {
            $this->flashMessage($this->translator->translate('messages.user.requests.notFound'), 'error');
            $this->redirect('this');
            return;
        }

        // Nastavení zamítnutí a času
        $request->update([
            'roleRequest_isApproved' => false,
            'roleRequest_approvedTimestamp' => new \DateTime(),
        ]);

        $this->flashMessage($this->translator->translate('messages.user.requests.rejected'), 'info');
        $this->redirect('this');
    }
}
