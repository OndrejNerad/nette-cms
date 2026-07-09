<?php declare(strict_types=1);

namespace App\Model\Car;

use App\Model\CarEquipment\CarEquipmentRepository;
use Nextras\Orm\Collection\Functions\CompareGreaterThanEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareSmallerThanEqualsFunction;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Repository\IDependencyProvider;
use Nextras\Orm\Repository\Repository;

/**
 * @extends Repository<Car>
 */
class CarsRepository extends Repository
{
    public function __construct(
        IMapper $mapper,
        ?IDependencyProvider $dependencyProvider,
        private readonly CarEquipmentRepository $carEquipmentRepository,
    ) {
        parent::__construct($mapper, $dependencyProvider);
    }

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

    /**
     * @param string[] $externalIds
     */
    public function findNotIn(array $externalIds): ICollection
    {
        if ($externalIds === []) {
            return $this->findAll()->limitBy(0);
        }

        return $this->findAll()->findBy(['externalId!=' => $externalIds]);
    }

    public function findFiltered(array $filters): ICollection
    {
        $collection = $this->findAll();

        if (!empty($filters['znacka'])) {
            $collection = $collection->findBy(['znacka' => $filters['znacka']]);
        }
        if (!empty($filters['karoserie'])) {
            $collection = $collection->findBy(['karoserie' => $filters['karoserie']]);
        }
        if (!empty($filters['palivo'])) {
            $collection = $collection->findBy(['palivo' => $filters['palivo']]);
        }
        if (!empty($filters['stitek'])) {
            $collection = $collection->findBy(['stitek' => $filters['stitek']]);
        }
        if (!empty($filters['odpocet'])) {
            $collection = $collection->findBy(['odpocet' => 'A']);
        }
        if ($filters['yearFrom'] !== null) {
            $collection = $collection->findBy([CompareGreaterThanEqualsFunction::class, 'rokVyroby', $filters['yearFrom']]);
        }
        if ($filters['yearTo'] !== null) {
            $collection = $collection->findBy([CompareSmallerThanEqualsFunction::class, 'rokVyroby', $filters['yearTo']]);
        }
        if ($filters['kmFrom'] !== null) {
            $collection = $collection->findBy([CompareGreaterThanEqualsFunction::class, 'tachometr', $filters['kmFrom']]);
        }
        if ($filters['kmTo'] !== null) {
            $collection = $collection->findBy([CompareSmallerThanEqualsFunction::class, 'tachometr', $filters['kmTo']]);
        }
        // cena is varchar but stores plain numbers; passing int causes DBAL to use %i → unquoted literal → MySQL numeric comparison
        if ($filters['priceFrom'] !== null) {
            $collection = $collection->findBy([CompareGreaterThanEqualsFunction::class, 'cena', $filters['priceFrom']]);
        }
        if ($filters['priceTo'] !== null) {
            $collection = $collection->findBy([CompareSmallerThanEqualsFunction::class, 'cena', $filters['priceTo']]);
        }
        if (!empty($filters['equipment'])) {
            $carIds = $this->carEquipmentRepository->findCarIdsWithAllEquipment($filters['equipment']);
            $collection = $collection->findBy(['id' => $carIds ?: [0]]);
        }

        return $collection;
    }

    public function findRandom(int $limit): \Nextras\Orm\Collection\ICollection
    {
        /** @var CarsMapper $mapper */
        $mapper = $this->getMapper();
        return $mapper->buildRandomCollection($limit);
    }

    public function getDistinctValues(string $property): array
    {
        $values = [];
        foreach ($this->findAll() as $car) {
            $val = $car->$property;
            if ($val !== null && $val !== '') {
                $values[$val] = $val;
            }
        }
        ksort($values);
        return array_values($values);
    }
}