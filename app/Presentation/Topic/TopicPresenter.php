<?php

declare(strict_types=1);

namespace App\Presentation\Topic;

use App\Presentation\BasePresenter;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;

/**
 * Presenter pro správu témat.
 *
 * Mapování sloupců:
 *  - topic_id (PK)
 *  - topic_name (název)
 *  - topic_description (popis - HTML)
 *  - topic_parentalTopic_id (rodič, NULL = kořen)
 *  - topic_area_id (okruh)
 *  - topic_region_id (území, NULL = celá ČR)
 *  - topic_author_id (autor tématu)
 *  - topicPhase_topicPhase_id (fáze tématu)
 *
 * ACL resource: 'topic'
 * Překladová sekce: messages.topic.*
 */
final class TopicPresenter extends BasePresenter
{
    /**
     * Vrátí pole názvů oblastí od kořene po konkrétní oblast
     */
    private function getAreaPath(?int $areaId): array
    {
        $path = [];
        while ($areaId) {
            $area = $this->database->table('area')->get($areaId);
            if (!$area) break;
            array_unshift($path, $area->area_name);
            $areaId = $area->area_parentalArea_id ?? null;
        }
        return $path;
    }

    /**
     * Vrátí pole názvů regionů od kořene po konkrétní region
     */
    private function getRegionPath(?int $regionId): array
    {
        $path = [];
        while ($regionId) {
            $region = $this->database->table('region')->get($regionId);
            if (!$region) break;
            array_unshift($path, $region->region_name);
            $regionId = $region->region_parentalRegion_id ?? null;
        }
        return $path;
    }
    /**
     * Akce detail – zobrazí detail tématu podle ID
     */
    public function actionDetail(int $id): void
    {
        $topic = $this->database->table($this->tableName)
            ->where($this->idColumn, $id)
            ->fetch();
        if (!$topic) {
            $this->flashMessage($this->translator->translate('messages.' . $this->translationSection . '.not_found'), 'error');
            $this->redirect('default');
        }

        // Načtení autora
        $user = $this->database->table('user')->get($topic->topic_author_id);

        // Hierarchická cesta oblastí a regionů
        $areaPath = $topic->topic_area_id ? $this->getAreaPath((int)$topic->topic_area_id) : [];
        $regionPath = $topic->topic_region_id ? $this->getRegionPath((int)$topic->topic_region_id) : [];

        // Načtení podtémat

        // Načtení podtémat včetně názvů oblasti a regionu
        $subtopicsRaw = $this->database->table($this->tableName)
            ->where($this->parentColumn, $id)
            ->order($this->nameColumn)
            ->fetchAll();
        $subtopics = [];
        foreach ($subtopicsRaw as $sub) {
            $area = $sub->topic_area_id ? $this->database->table('area')->get($sub->topic_area_id) : null;
            $region = $sub->topic_region_id ? $this->database->table('region')->get($sub->topic_region_id) : null;
            $author = $sub->topic_author_id ? $this->database->table('user')->get($sub->topic_author_id) : null;
            $subtopics[] = [
                'topic_id' => $sub->topic_id,
                'topic_name' => $sub->topic_name,
                'topic_description' => $sub->topic_description,
                'topic_area_id' => $sub->topic_area_id,
                'topic_region_id' => $sub->topic_region_id,
                'area' => $area ? $area->area_name : null,
                'region' => $region ? $region->region_name : null,
                'author' => $author ? ($author->user_name . ' ' . $author->user_surname) : null,
            ];
        }

        $this->template->topic = $topic;
        $this->template->areaPath = $areaPath;
        $this->template->regionPath = $regionPath;
        $this->template->author = $user;
        $this->template->subtopics = $subtopics;
    }

    /**
     * Render detail – předá data do šablony
     */
    public function renderDetail(int $id): void
    {
        // Data jsou již načtena v actionDetail
    }
    protected string $tableName = 'topic';
    protected string $idColumn = 'topic_id';
    protected string $nameColumn = 'topic_name';
    protected string $parentColumn = 'topic_parentalTopic_id';
    protected string $resourceName = 'topic';
    protected string $translationSection = 'topic';

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
     * Render default – naplní šablonu stromovou strukturou témat.
     */
    public function renderDefault(): void
    {
        $this->template->tree = $this->buildTree();
        $this->template->canAdd = $this->getUser()->isAllowed($this->resourceName, 'add');
    }

    /**
     * Render add – formulář pro přidání nového tématu
     */
    public function renderAdd(): void
    {
        if (!$this->getUser()->isAllowed($this->resourceName, 'add')) {
            $this->flashMessage($this->translator->translate('messages.' . $this->translationSection . '.no_permission_add'), 'error');
            $this->redirect('default');
        }
    }

    /**
     * Render edit – formulář pro úpravu tématu
     */
    public function renderEdit(int $id): void
    {
        if (!$this->getUser()->isAllowed($this->resourceName, 'edit')) {
            $this->flashMessage($this->translator->translate('messages.' . $this->translationSection . '.no_permission_edit'), 'error');
            $this->redirect('default');
        }

        $topic = $this->database->table($this->tableName)->get($id);
        if (!$topic) {
            $this->flashMessage($this->translator->translate('messages.' . $this->translationSection . '.not_found'), 'error');
            $this->redirect('default');
        }

        // Kontrola, zda může uživatel upravovat toto téma (autor nebo admin)
        if ($topic->topic_author_id != $this->getUser()->getId() && 
            !$this->getUser()->isAllowed($this->resourceName, 'edit_all')) {
            $this->flashMessage($this->translator->translate('messages.' . $this->translationSection . '.no_permission_edit_this'), 'error');
            $this->redirect('default');
        }

        $this->template->topic = $topic;
    }

    /**
     * Sestaví strom z tabulky témat
     */
    private function buildTree(): array
    {
        try {
            $rows = $this->database->table($this->tableName)
                ->select('topic.*, area.area_name, region.region_name, user.user_name, user.user_surname')
                ->leftJoin('area', 'area.area_id = topic.topic_area_id')
                ->leftJoin('region', 'region.region_id = topic.topic_region_id')
                ->leftJoin('user', 'user.user_id = topic.topic_author_id')
                ->order($this->nameColumn)
                ->fetchAll();
        } catch (\Throwable $e) {
            // Fallback - základní dotaz bez JOINů
            $rows = $this->database->table($this->tableName)->order($this->nameColumn)->fetchAll();
        }
            
        $items = [];
        // 1. vytvoření plochého indexu
        foreach ($rows as $r) {
            // Kontrola existence sloupců
            $authorName = '';
            if (isset($r['user_name'])) {
                $authorName = trim(($r['user_name'] ?? '') . ' ' . ($r['user_surname'] ?? ''));
            } else {
                // Fallback - načteme autora odděleně
                $author = $this->database->table('user')->get($r['topic_author_id']);
                if ($author) {
                    $authorName = trim(($author['user_name'] ?? '') . ' ' . ($author['user_surname'] ?? ''));
                }
            }
            // Zajistit, že area je vždy název oblasti, pokud existuje
            $areaName = $r['area_name'] ?? '';
            if (!$areaName && $r['topic_area_id']) {
                $area = $this->database->table('area')->get($r['topic_area_id']);
                if ($area) $areaName = $area->area_name;
            }
            // Zajistit, že region je vždy název regionu, pokud existuje
            $regionName = $r['region_name'] ?? '';
            if (!$regionName && $r['topic_region_id']) {
                $region = $this->database->table('region')->get($r['topic_region_id']);
                if ($region) $regionName = $region->region_name;
            }
            $items[$r[$this->idColumn]] = [
                'id' => $r[$this->idColumn],
                'name' => $r[$this->nameColumn],
                'description' => $r['topic_description'] ?? '',
                'parent' => $r[$this->parentColumn],
                'area' => $areaName,
                'region' => $regionName,
                'author' => $authorName ?: 'Neznámý autor',
                'children' => [],
                'row' => $r, // plný ActiveRow pro případné rozšíření v šabloně
            ];
        }
        
        $root = [];
        // 2. napojení dětí k rodičům
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
     * Formulář pro přidání nového tématu
     */
    protected function createComponentAddTopicForm(): Form
    {
        $form = new Form();
        
        $form->addText('name', $this->translator->translate('messages.' . $this->translationSection . '.name'))
            ->setRequired($this->translator->translate('messages.' . $this->translationSection . '.name_required'));

        // Skrytá textarea pro Quill
        $form->addTextArea('description', $this->translator->translate('messages.' . $this->translationSection . '.description'))
            ->setHtmlAttribute('style', 'display: none;')
            ->setHtmlAttribute('class', 'quill-source-textarea');

        // Výběr nadřazeného tématu
        $topics = $this->database->table($this->tableName)->order($this->nameColumn)->fetchPairs($this->idColumn, $this->nameColumn);
        $form->addSelect('parent', $this->translator->translate('messages.' . $this->translationSection . '.parent'))
            ->setPrompt($this->translator->translate('messages.' . $this->translationSection . '.parent_prompt'))
            ->setItems($topics);

        // Výběr okruhu
        $areas = $this->database->table('area')
            ->where('area_user_approval IS NOT NULL')
            ->order('area_name')
            ->fetchPairs('area_id', 'area_name');
        $form->addSelect('area', $this->translator->translate('messages.' . $this->translationSection . '.area'))
            ->setRequired($this->translator->translate('messages.' . $this->translationSection . '.area_required'))
            ->setItems($areas);

        // Výběr území (volitelné)
        $regions = $this->database->table('region')
            ->where('region_user_approval IS NOT NULL')
            ->order('region_name')
            ->fetchPairs('region_id', 'region_name');
        $form->addSelect('region', $this->translator->translate('messages.' . $this->translationSection . '.region'))
            ->setPrompt($this->translator->translate('messages.' . $this->translationSection . '.region_prompt'))
            ->setItems($regions);

        $form->addSubmit('send', $this->translator->translate('messages.' . $this->translationSection . '.add'));
        $form->onSuccess[] = [$this, 'addTopicFormSucceeded'];
        
        return $form;
    }

    /**
     * Zpracování formuláře pro přidání tématu
     */
    public function addTopicFormSucceeded(Form $form, ArrayHash $values): void
    {
        if (!$this->getUser()->isAllowed($this->resourceName, 'add')) {
            $form->addError($this->translator->translate('messages.' . $this->translationSection . '.no_permission_add'));
            return;
        }

        try {
            // Získání aktuální fáze tématu (předpokládám, že existuje defaultní)
            $defaultPhase = $this->database->table('topicphase')->limit(1)->fetch();
            
            $result = $this->database->table($this->tableName)->insert([
                $this->nameColumn => $values->name,
                'topic_description' => $values->description,
                $this->parentColumn => $values->parent ?: null,
                'topic_area_id' => $values->area,
                'topic_region_id' => $values->region ?: null,
                'topic_author_id' => $this->getUser()->getId(),
                'topicPhase_topicPhase_id' => $defaultPhase ? $defaultPhase->getPrimary() : 1,
            ]);

            if ($result) {
                $this->flashMessage($this->translator->translate('messages.' . $this->translationSection . '.added'), 'success');
                $this->redirect('default');
            } else {
                $form->addError($this->translator->translate('messages.' . $this->translationSection . '.add_error'));
            }
        } catch (\Nette\Application\AbortException $e) {
            // Necháme projít - je to normální chování redirect()
            throw $e;
        } catch (\Throwable $e) {
            $errorMessage = $this->translator->translate('messages.' . $this->translationSection . '.add_error');
            if ($e->getMessage()) {
                $errorMessage .= ': ' . $e->getMessage();
            }
            $form->addError($errorMessage);
        }
    }

    /**
     * Formulář pro úpravu tématu
     */
    protected function createComponentEditTopicForm(): Form
    {
        $form = new Form();
        
        $form->addHidden('id');
        
        $form->addText('name', $this->translator->translate('messages.' . $this->translationSection . '.name'))
            ->setRequired($this->translator->translate('messages.' . $this->translationSection . '.name_required'));

        // Skrytá textarea pro Quill
        $form->addTextArea('description', $this->translator->translate('messages.' . $this->translationSection . '.description'))
            ->setHtmlAttribute('style', 'display: none;')
            ->setHtmlAttribute('class', 'quill-source-textarea');

        // Výběr nadřazeného tématu
        $topics = $this->database->table($this->tableName)->order($this->nameColumn)->fetchPairs($this->idColumn, $this->nameColumn);
        $form->addSelect('parent', $this->translator->translate('messages.' . $this->translationSection . '.parent'))
            ->setPrompt($this->translator->translate('messages.' . $this->translationSection . '.parent_prompt'))
            ->setItems($topics);

        // Výběr okruhu
        $areas = $this->database->table('area')
            ->where('area_user_approval IS NOT NULL')
            ->order('area_name')
            ->fetchPairs('area_id', 'area_name');
        $form->addSelect('area', $this->translator->translate('messages.' . $this->translationSection . '.area'))
            ->setRequired($this->translator->translate('messages.' . $this->translationSection . '.area_required'))
            ->setItems($areas);

        // Výběr území (volitelné)
        $regions = $this->database->table('region')
            ->where('region_user_approval IS NOT NULL')
            ->order('region_name')
            ->fetchPairs('region_id', 'region_name');
        $form->addSelect('region', $this->translator->translate('messages.' . $this->translationSection . '.region'))
            ->setPrompt($this->translator->translate('messages.' . $this->translationSection . '.region_prompt'))
            ->setItems($regions);

        $form->addSubmit('send', $this->translator->translate('messages.' . $this->translationSection . '.save'));
        $form->onSuccess[] = [$this, 'editTopicFormSucceeded'];
        
        return $form;
    }

    /**
     * Zpracování formuláře pro úpravu tématu
     */
    public function editTopicFormSucceeded(Form $form, ArrayHash $values): void
    {
        if (!$this->getUser()->isAllowed($this->resourceName, 'edit')) {
            $form->addError($this->translator->translate('messages.' . $this->translationSection . '.no_permission_edit'));
            return;
        }

        $topic = $this->database->table($this->tableName)->get($values->id);
        if (!$topic) {
            $form->addError($this->translator->translate('messages.' . $this->translationSection . '.not_found'));
            return;
        }

        // Kontrola oprávnění pro konkrétní téma
        if ($topic->topic_author_id != $this->getUser()->getId() && 
            !$this->getUser()->isAllowed($this->resourceName, 'edit_all')) {
            $form->addError($this->translator->translate('messages.' . $this->translationSection . '.no_permission_edit_this'));
            return;
        }

        try {
            $result = $topic->update([
                $this->nameColumn => $values->name,
                'topic_description' => $values->description,
                $this->parentColumn => $values->parent ?: null,
                'topic_area_id' => $values->area,
                'topic_region_id' => $values->region ?: null,
            ]);

            if ($result !== false) {
                $this->flashMessage($this->translator->translate('messages.' . $this->translationSection . '.updated'), 'success');
                $this->redirect('default');
            } else {
                $form->addError($this->translator->translate('messages.' . $this->translationSection . '.update_error'));
            }
        } catch (\Nette\Application\AbortException $e) {
            // Necháme projít - je to normální chování redirect()
            throw $e;
        } catch (\Throwable $e) {
            $errorMessage = $this->translator->translate('messages.' . $this->translationSection . '.update_error');
            if ($e->getMessage()) {
                $errorMessage .= ': ' . $e->getMessage();
            }
            $form->addError($errorMessage);
        }
    }

    /**
     * Načítání dat do editačního formuláře
     */
    public function actionEdit(int $id): void
    {
        $topic = $this->database->table($this->tableName)->get($id);
        if ($topic) {
            $this['editTopicForm']->setDefaults([
                'id' => $topic->topic_id,
                'name' => $topic->topic_name,
                'description' => $topic->topic_description,
                'parent' => $topic->topic_parentalTopic_id,
                'area' => $topic->topic_area_id,
                'region' => $topic->topic_region_id,
            ]);
        }
    }
}