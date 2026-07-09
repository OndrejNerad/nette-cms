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

    /**
     * @return EquipmentItem[] all equipment items ordered by title
     */
    public function findAllOrdered(): array
    {
        return $this->findAll()->orderBy('title')->fetchAll();
    }

    /**
     * @param string[] $ids
     * @return array<string, EquipmentItem> found items keyed by id, in one query
     */
    public function findMapByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $items = [];
        foreach ($this->findBy(['id' => $ids]) as $item) {
            $items[$item->id] = $item;
        }

        return $items;
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