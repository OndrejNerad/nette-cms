<?php declare(strict_types=1);

namespace App\Model\Equipment;

use Nextras\Orm\Repository\Repository;

/**
 * @extends Repository<EquipmentItem>
 */
class EquipmentItemsRepository extends Repository
{
    public static function getEntityClassNames(): array
    {
        return [EquipmentItem::class];
    }

    public function findById(string $id): ?EquipmentItem
    {
        return $this->getBy(['id' => $id]);
    }

    public function upsert(string $id, string $title): void
    {
        $item = $this->findById($id);

        if ($item === null) {
            $item        = new EquipmentItem();
            $item->id    = $id;
            $item->title = $title;
            $this->persistAndFlush($item);
        }
    }
}