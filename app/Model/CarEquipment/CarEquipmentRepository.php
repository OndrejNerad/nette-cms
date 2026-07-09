<?php declare(strict_types=1);

namespace App\Model\CarEquipment;

use Nextras\Dbal\Connection;

class CarEquipmentRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function syncForCar(int $carId, array $equipmentKeys): void
    {
        $this->connection->transactional(function () use ($carId, $equipmentKeys): void {
            // Delete all existing pivot rows for this car
            $this->connection->query('DELETE FROM car_equipment WHERE car_id = %i', $carId);

            // Insert only items where value = true
            foreach ($equipmentKeys as $key) {
                $this->connection->query(
                    'INSERT INTO car_equipment (car_id, equipment_id) VALUES (%i, %s)',
                    $carId,
                    $key,
                );
            }
        });
    }

    /**
     * @return string[] equipment_item ids for a given car
     */
    public function findForCar(int $carId): array
    {
        $result = $this->connection->query(
            'SELECT equipment_id FROM car_equipment WHERE car_id = %i',
            $carId,
        );

        return array_column($result->fetchAll(), 'equipment_id');
    }

    /**
     * @param string[] $equipmentIds
     * @return int[] ids of cars that have ALL of the given equipment items
     */
    public function findCarIdsWithAllEquipment(array $equipmentIds): array
    {
        if ($equipmentIds === []) {
            return [];
        }

        $result = $this->connection->query(
            'SELECT car_id FROM car_equipment
            WHERE equipment_id IN %s[]
            GROUP BY car_id
            HAVING COUNT(DISTINCT equipment_id) = %i',
            $equipmentIds,
            count($equipmentIds),
        );

        return array_map('intval', array_column($result->fetchAll(), 'car_id'));
    }

    /**
     * @return string[] equipment_item ids ordered by how many cars have them, descending
     */
    public function findPopularEquipmentIds(int $limit): array
    {
        $result = $this->connection->query(
            'SELECT equipment_id FROM car_equipment
            GROUP BY equipment_id
            ORDER BY COUNT(*) DESC
            LIMIT %i',
            $limit,
        );

        return array_column($result->fetchAll(), 'equipment_id');
    }
}