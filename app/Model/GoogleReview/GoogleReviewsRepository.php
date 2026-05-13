<?php declare(strict_types=1);

namespace App\Model\GoogleReview;

use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Repository\Repository;

/**
 * @extends Repository<GoogleReview>
 */
class GoogleReviewsRepository extends Repository
{
    public static function getEntityClassNames(): array
    {
        return [GoogleReview::class];
    }

    public function getByExternalId(string $externalId): ?GoogleReview
    {
        return $this->getBy(['externalId' => $externalId]);
    }
}