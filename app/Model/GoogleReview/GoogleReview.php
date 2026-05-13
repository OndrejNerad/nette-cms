<?php declare(strict_types=1);

namespace App\Model\GoogleReview;

use Nextras\Orm\Entity\Entity;
use Nextras\Dbal\Utils\DateTimeImmutable;

/**
 * @property int               $id          {primary}
 * @property string            $externalId  Google's reviewId
 * @property string            $authorName
 * @property string|null       $authorPhoto
 * @property int               $rating      1-5
 * @property string|null       $text
 * @property string            $url
 * @property DateTimeImmutable $publishedAt
 * @property DateTimeImmutable $createdAt   {default now}
 * @property bool              $isActive    {default true}
 */
class GoogleReview extends Entity
{
}