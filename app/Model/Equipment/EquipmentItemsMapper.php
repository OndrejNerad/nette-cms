<?php declare(strict_types=1);

namespace App\Model\Equipment;

use Nextras\Orm\Mapper\Dbal\DbalMapper;

/**
 * @extends DbalMapper<EquipmentItem>
 */
class EquipmentItemsMapper extends DbalMapper
{
    public function getTableName(): string
    {
        return 'equipment_items';
    }
}