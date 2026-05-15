<?php declare(strict_types=1);

namespace App\Model\Car;

use Nextras\Orm\Repository\Repository;

/**
 * @extends Repository<Car>
 */
class CarsRepository extends Repository
{
    public static function getEntityClassNames(): array
    {
        return [Car::class];
    }

    public function findByDetailUrl(string $detailUrl): ?Car
    {
        return $this->getBy(['detailUrl' => $detailUrl]);
    }

    public function findByExternalId(string $externalId): ?Car
    {
        return $this->getBy(['externalId' => $externalId]);
    }

}