<?php

declare(strict_types=1);

namespace App\Presentation\Shared;

use App\Presentation\BasePresenter;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;

/**
 * Abstraktní presenter pro jednoduché hierarchické entity (např. území, okruhy,...)
 *
 * Konvence databáze:
 *  - Tabulka ($tableName) obsahuje alespoň sloupce:
 *      - primární klíč ($idColumn)
 *      - název ($nameColumn)
 *      - rodič ($parentColumn) – NULL pro kořenovou položku
 *      - volitelně <tableName>_user_creating (ID uživatele zakládajícího záznam)
 *  - Sloupce jsou definovány v konkrétním presenteru.
 *
 * Oprávnění (ACL):
 *  - resource = $resourceName
 *  - právo 'view' pro zobrazení stránky
 *  - právo 'add' pro přidání nové položky
 *
 * Překlady:
 *  - sekce messages.$translationSection.* musí obsahovat klíče:
 *      name, name_required, parent, parent_prompt, add, added, exists, no_permission_add
 */
abstract class AbstractHierarchicalEntityPresenter extends BasePresenter
{
    /** Název DB tabulky (musí být nastaveno v potomkovi) */
    protected string $tableName;
    /** Primární klíč tabulky */
    protected string $idColumn = 'id';
    /** Sloupec s názvem položky */
    protected string $nameColumn = 'name';
    /** Sloupec s referencí na rodiče (NULL = kořen) */
    protected string $parentColumn = 'parent_id';
    /** Název resource pro ACL (musí odpovídat záznamu v tabulce resource) */
    protected string $resourceName;
    /** Prefix sekce překladů (messages.<section>.*) */
    protected string $translationSection;

    /** Zapíná logiku schvalování (vyžaduje sloup <table>_user_approval) */
    protected bool $supportsApproval = false;

    /**
     * Kontrola základního práva 'view'. Pokud chybí, uživatel je přesměrován domů.
     */
    public function checkRequirements($element): void
    {
        parent::checkRequirements($element);
        if (!$this->getUser()->isAllowed($this->resourceName, 'view')) {
            $this->flashMessage($this->translator->translate('messages.user.exceptions.unauthorized'), 'error');
            $this->redirect(':Home:default');
        }
    }

    /**
     * Render default – naplní šablonu stromovou strukturou.
     */
    public function renderDefault(): void
    {
        if ($this->supportsApproval) {
            $approvedRows = $this->database->table($this->tableName)->where($this->tableName . '_user_approval IS NOT NULL')->order($this->nameColumn)->fetchAll();
            $this->template->approvedTree = $this->buildTreeFromRows($approvedRows);
            $this->template->unapproved = $this->database->table($this->tableName)->where($this->tableName . '_user_approval IS NULL')->order($this->nameColumn)->fetchAll();
        } else {
            $this->template->tree = $this->buildTree();
        }
    }

    /** Schválení položky */
    public function actionApprove(int $id): void
    {
        if (!$this->supportsApproval) {
            $this->error('Approval not supported');
        }
        if (!$this->getUser()->isAllowed($this->resourceName, 'approve')) {
            $this->flashMessage($this->translator->translate('messages.' . $this->translationSection . '.no_permission_approve'), 'error');
            $this->redirect('default');
        }
        $row = $this->database->table($this->tableName)->get($id);
        if (!$row) {
            $this->flashMessage($this->translator->translate('messages.' . $this->translationSection . '.not_found'), 'error');
            $this->redirect('default');
        }
        try {
            $row->update([
                $this->tableName . '_user_approval' => $this->getUser()->getId(),
            ]);
            $this->flashMessage($this->translator->translate('messages.' . $this->translationSection . '.approve_success'), 'success');
        } catch (\Throwable $e) {
            $this->flashMessage($this->translator->translate('messages.' . $this->translationSection . '.approve_error') . ' ' . $e->getMessage(), 'error');
        }
        $this->redirect('default');
    }

    /**
     * Sestaví strom z tabulky: nejprve načte všechny řádky, uloží do pomocného pole a
     * poté poskládá parent-child relace v paměti. Výhodou je O(n) průchod místo mnoha dotazů.
     *
     * @return array<array{id:int|string,name:string,parent:int|string|null,children:array,row:\Nette\Database\Table\ActiveRow}>
     */
    private function buildTree(): array
    {
        $rows = $this->database->table($this->tableName)->order($this->nameColumn)->fetchAll();
        $items = [];
        // 1. vytvoření plochého indexu
        foreach ($rows as $r) {
            $items[$r[$this->idColumn]] = [
                'id' => $r[$this->idColumn],
                'name' => $r[$this->nameColumn],
                'parent' => $r[$this->parentColumn],
                'children' => [],
                'row' => $r, // plný ActiveRow pro případné rozšíření v šabloně
            ];
        }
        $root = [];
        // 2. napojení dětí k rodičům
        foreach ($items as $id => &$item) {
            $parent = $item['parent'];
            if ($parent && isset($items[$parent])) {
                $items[$parent]['children'][] =& $item; // reference kvůli jednomu zdroji pravdy
            } else {
                $root[] =& $item; // kořenové prvky
            }
        }
        return $root;
    }

    /** Vytvoří plnou cestu k uzlu (název -> rodič -> ... -> kořen) */
    public function buildPath(\Nette\Database\Table\ActiveRow $row): string
    {
        $parts = [$row[$this->nameColumn]];
        $parentId = $row[$this->parentColumn];
        while ($parentId) {
            $parent = $this->database->table($this->tableName)->get($parentId);
            if (!$parent) break;
            array_unshift($parts, $parent[$this->nameColumn]);
            $parentId = $parent[$this->parentColumn];
        }
        return implode(' / ', $parts);
    }

    /** Postaví strom z již předaných řádků (pro schválené) */
    private function buildTreeFromRows(array $rows): array
    {
        $items = [];
        foreach ($rows as $r) {
            $items[$r[$this->idColumn]] = [
                'id' => $r[$this->idColumn],
                'name' => $r[$this->nameColumn],
                'parent' => $r[$this->parentColumn],
                'children' => [],
                'row' => $r,
            ];
        }
        $root = [];
        foreach ($items as $id => &$item) {
            $parent = $item['parent'];
            if ($parent && isset($items[$parent])) {
                $items[$parent]['children'][] =& $item;
            } else {
                $root[] =& $item;
            }
        }
        return $root;
    }

    /**
     * Formulář pro přidání nové položky ve stromu.
     * @return Form
     */
    protected function createComponentAddEntityForm(): Form
    {
        $form = new Form();
        $form->addText('name', $this->translator->translate('messages.' . $this->translationSection . '.name'))
            ->setRequired($this->translator->translate('messages.' . $this->translationSection . '.name_required'));

        // Jen schválené položky pro výběr rodiče (pokud se schvaluje)
        $selection = $this->database->table($this->tableName);
        if ($this->supportsApproval) {
            $selection->where($this->tableName . '_user_approval IS NOT NULL');
        }
        $rows = $selection->order($this->nameColumn)->fetchAll();

        // Přestavíme na strom pro hierarchické labely
        $tree = [];
        $index = [];
        foreach ($rows as $r) {
            $index[$r[$this->idColumn]] = [
                'row' => $r,
                'parent' => $r[$this->parentColumn],
                'children' => [],
            ];
        }
        foreach ($index as $id => &$node) {
            $parentId = $node['parent'];
            if ($parentId && isset($index[$parentId])) {
                $index[$parentId]['children'][] =& $node;
            } else {
                $tree[] =& $node;
            }
        }
        unset($node);

        // Rekurzivně vybudujeme pole id => label s odsazením
        $pairs = [];
        $addNodes = function(array $nodes, int $depth) use (&$addNodes, &$pairs) {
            foreach ($nodes as $n) {
                $prefix = str_repeat('— ', $depth);
                $pairs[$n['row']->getPrimary()] = $prefix . $n['row']->offsetGet($this->nameColumn);
                if ($n['children']) {
                    $addNodes($n['children'], $depth + 1);
                }
            }
        };
        $addNodes($tree, 0);

        $form->addSelect('parent', $this->translator->translate('messages.' . $this->translationSection . '.parent'))
            ->setPrompt($this->translator->translate('messages.' . $this->translationSection . '.parent_prompt'))
            ->setItems($pairs);

        $form->addSubmit('send', $this->translator->translate('messages.' . $this->translationSection . '.add'));
        $form->onSuccess[] = [$this, 'addEntityFormSucceeded'];
        return $form;
    }

    /**
     * Zpracování formuláře – kontrola oprávnění + jednoduchá validace duplicity na stejné úrovni.
     */
    public function addEntityFormSucceeded(Form $form, ArrayHash $values): void
    {
        if (!$this->getUser()->isAllowed($this->resourceName, 'add')) {
            $form->addError($this->translator->translate('messages.' . $this->translationSection . '.no_permission_add'));
            return;
        }

        // Duplicitní název pod stejného rodiče – ochrana proti opakování.
        $duplicate = $this->database->table($this->tableName)
            ->where($this->nameColumn, $values->name)
            ->where($this->parentColumn, $values->parent ?: null)
            ->fetch();
        if ($duplicate) {
            $form->addError($this->translator->translate('messages.' . $this->translationSection . '.exists'));
            return;
        }

        $this->database->table($this->tableName)->insert([
            $this->nameColumn => $values->name,
            $this->parentColumn => $values->parent ?: null,
            $this->tableName . '_user_creating' => $this->getUser()->getId(),
        ]);

        $this->flashMessage($this->translator->translate('messages.' . $this->translationSection . '.added'), 'success');
        $this->redirect('this');
    }
}
