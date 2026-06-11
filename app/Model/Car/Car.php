<?php declare(strict_types=1);

namespace App\Model\Car;

use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\HasMany;
use App\Model\Equipment\EquipmentItem;

/**
 * @property int                $id             {primary}
 * @property string             $externalId
 * @property string|null        $detailUrl
 * @property string|null        $rawHash
 *
 * @property string|null        $description
 * @property string|null        $metDojezdu
 * @property string|null        $metSpotreby
 * @property string|null        $kapAkumulatoru
 * @property string|null        $dojezd
 * @property string|null        $plugIn
 * @property string|null        $hmotnost
 * @property string|null        $dvere
 * @property string|null        $mista
 * @property string|null        $spotreba
 * @property string|null        $maxRychlost
 * @property string|null        $emise
 * @property string|null        $zrychleni
 * @property string|null        $tMoment
 * @property string|null        $vykonMotoruJednotka
 * @property string|null        $vykonMotoru
 * @property string|null        $obsahMotoru
 * @property string|null        $odpocet
 * @property string|null        $cena
 * @property int|null           $rokVyroby
 * @property string|null        $tachometrJednotka
 * @property int|null           $tachometr
 * @property string|null        $palivo
 * @property string|null        $barva
 * @property string|null        $karoserie
 * @property string|null        $provedeni
 * @property string|null        $model
 * @property string|null        $znacka
 * @property string|null        $stitek
 * @property string|null        $popis
 * @property string|null        $vinVerejny
 * @property string|null        $popisNabidky
 *
 * @property string|null        $images
 * @property string|null        $rawValues
 *
 * @property \DateTimeImmutable $createdAt
 * @property \DateTimeImmutable $updatedAt
 *
 * @property HasMany|EquipmentItem[] $equipment {m:m EquipmentItem::$cars, isMain=true}
 */
class Car extends Entity
{
}