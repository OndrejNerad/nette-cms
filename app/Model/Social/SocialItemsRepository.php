<?php
declare(strict_types=1);

namespace App\Model\Social;

use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Repository\Repository;

/**
 * @extends Repository<SocialItem>
// * @method SocialItem|null getByExternalId(string $externalId)
// * @method ICollection<SocialItem> findLatest(int $limit)
 */
class SocialItemsRepository extends Repository
{
    public static function getEntityClassNames(): array
    {
        return [SocialItem::class];
    }
}