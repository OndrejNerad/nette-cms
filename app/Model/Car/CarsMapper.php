<?php declare(strict_types=1);

namespace App\Model\Car;

use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;

/**
 * @extends DbalMapper<Car>
 */
class CarsMapper extends DbalMapper
{
    protected function createConventions(): IConventions
    {
        $conventions = parent::createConventions();
        $conventions->setMapping('externalId',           'external_id');
        $conventions->setMapping('metDojezdu',           'met_dojezdu');
        $conventions->setMapping('metSpotreby',          'met_spotreby');
        $conventions->setMapping('kapAkumulatoru',       'kap_akumulatoru');
        $conventions->setMapping('plugIn',               'plug_in');
        $conventions->setMapping('maxRychlost',          'max_rychlost');
        $conventions->setMapping('tMoment',              't_moment');
        $conventions->setMapping('vykonMotoruJednotka',  'vykon_motoru_jednotka');
        $conventions->setMapping('vykonMotoru',          'vykon_motoru');
        $conventions->setMapping('obsahMotoru',          'obsah_motoru');
        $conventions->setMapping('rokVyroby',            'rok_vyroby');
        $conventions->setMapping('tachometrJednotka',    'tachometr_jednotka');
        $conventions->setMapping('popisNabidky',         'popis_nabidky');
        $conventions->setMapping('rawValues',            'raw_values');
        $conventions->setMapping('createdAt',            'created_at');
        $conventions->setMapping('updatedAt',            'updated_at');
        return $conventions;
    }

    public function getManyHasManyParameters(PropertyMetadata $sourceProperty, DbalMapper $targetMapper): array
    {
        return [
            'car_equipment',
            ['car_id', 'equipment_id'],
        ];
    }
}