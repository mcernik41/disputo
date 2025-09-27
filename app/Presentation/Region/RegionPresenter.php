<?php

declare(strict_types=1);

namespace App\Presentation\Region;

use App\Presentation\Shared\AbstractHierarchicalEntityPresenter;

/**
 * Presenter pro správu hierarchických území.
 *
 * Mapování sloupců:
 *  - region_id (PK)
 *  - region_name (název)
 *  - region_parentalRegion_id (rodič, NULL = kořen)
 *  - region_user_creating (vkládající uživatel – volitelně)
 *
 * ACL resource: 'region'
 * Překladová sekce: messages.region.*
 */
final class RegionPresenter extends AbstractHierarchicalEntityPresenter
{
    protected string $tableName = 'region';
    protected string $idColumn = 'region_id';
    protected string $nameColumn = 'region_name';
    protected string $parentColumn = 'region_parentalRegion_id';
    protected string $resourceName = 'region';
    protected string $translationSection = 'region';
    protected bool $supportsApproval = true;
}
