<?php declare(strict_types=1);

namespace App\Model;

use App\Model\Car\CarsRepository;
use App\Model\Equipment\EquipmentItemsRepository;
use Nextras\Orm\Model\Model;

/**
 * @property-read CarsRepository           $cars
 * @property-read EquipmentItemsRepository $equipmentItems
 */
class Orm extends Model
{
}