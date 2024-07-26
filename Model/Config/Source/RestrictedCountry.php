<?php

/**
 * Country config field renderer
 */

namespace MyFatoorah\Gateway\Model\Config\Source;

use Magento\Directory\Model\Config\Source\Country;
use Magento\Directory\Model\ResourceModel\Country\Collection;

class RestrictedCountry extends Country
{

    /**
     * @param Collection $countryCollection
     */
    public function __construct(Collection $countryCollection)
    {
        $countryCollection->addCountryIdFilter(['AU', 'NZ']);

        parent::__construct($countryCollection);
    }
}
