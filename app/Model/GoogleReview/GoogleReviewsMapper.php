<?php declare(strict_types=1);

namespace App\Model\GoogleReview;

use Nextras\Orm\Mapper\Dbal\DbalMapper;

/**
 * @extends DbalMapper<GoogleReview>
 */
class GoogleReviewsMapper extends DbalMapper
{
    public function getTableName(): string
    {
        return 'google_reviews';
    }
}