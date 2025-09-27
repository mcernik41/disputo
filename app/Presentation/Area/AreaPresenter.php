<?php

declare(strict_types=1);

namespace App\Presentation\Area;

use App\Presentation\Shared\AbstractHierarchicalEntityPresenter;

/**
 * Presenter pro správu hierarchických okruhů.
 *
 * Mapování sloupců:
 *  - area_id (PK)
 *  - area_name (název)
 *  - area_parentalArea_id (rodič, NULL = kořen)
 *  - area_user_creating (vkládající uživatel – volitelně)
 *
 * ACL resource: 'area'
 * Překladová sekce: messages.area.*
 */
final class AreaPresenter extends AbstractHierarchicalEntityPresenter
{
    protected string $tableName = 'area';
    protected string $idColumn = 'area_id';
    protected string $nameColumn = 'area_name';
    protected string $parentColumn = 'area_parentalArea_id';
    protected string $resourceName = 'area';
    protected string $translationSection = 'area';
    protected bool $supportsApproval = true;
}
