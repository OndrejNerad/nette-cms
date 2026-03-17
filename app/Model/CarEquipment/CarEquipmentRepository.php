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
}