<?php
declare(strict_types=1);

namespace App\Model;

use App\Model\Car\CarsRepository;
use App\Model\Equipment\EquipmentItemsRepository;
use App\Model\Social\SocialItemsRepository;
use App\Model\GoogleReview\GoogleReviewsRepository;
use Nextras\Orm\Model\Model;

/**
 * @property-read CarsRepository            $cars
 * @property-read EquipmentItemsRepository  $equipmentItems
 * @property-read SocialItemsRepository     $socialItems
 * @property-read GoogleReviewsRepository   $googleReviews
 */
class Orm extends Model
{
}