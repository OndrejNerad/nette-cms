<?php
declare(strict_types=1);

namespace App\Model\Social;

use Nextras\Orm\Entity\Entity;

/**
 * @property int               $id           {primary}
 * @property string            $externalId   Original post ID from Instagram
 * @property string            $mediaType    IMAGE, VIDEO, CAROUSEL_ALBUM
 * @property string|null       $mediaUrl
 * @property string|null       $thumbnailUrl
 * @property string|null       $caption
 * @property string            $permalink
 * @property DateTimeImmutable $publishedAt  When the post was published on Instagram
 * @property DateTimeImmutable $createdAt    When we saved it to our DB {default now}
 * @property bool              $isActive     {default true}
 */
class SocialItem extends Entity
{
}