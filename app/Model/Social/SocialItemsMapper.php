<?php
declare(strict_types=1);

namespace App\Model\Social;

use Nextras\Orm\Mapper\Dbal\DbalMapper;

/**
 * @extends DbalMapper<SocialItem>
 */
class SocialItemsMapper extends DbalMapper
{
    public function getTableName(): string
    {
        return 'social_items';
    }
}