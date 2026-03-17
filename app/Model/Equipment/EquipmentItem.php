<?php declare(strict_types=1);

namespace App\Model\Equipment;

use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\HasMany;
use App\Model\Car\Car;

/**
 * @property string             $id      {primary}
 * @property string             $title
 *
 * @property HasMany|Car[]      $cars {m:m Car::$equipment}
 */
class EquipmentItem extends Entity
{
}