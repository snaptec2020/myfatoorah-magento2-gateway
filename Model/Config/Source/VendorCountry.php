<?php

namespace MyFatoorah\Gateway\Model\Config\Source;

use MyFatoorah\Library\MyFatoorah;
use MyFatoorah\Gateway\Helper\LocaleResolver;

class VendorCountry implements \Magento\Framework\Option\ArrayInterface
{
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @var LocaleResolver
     */
    private $locale;

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     *
     * @param LocaleResolver $localeResolver
     */
    public function __construct(
        LocaleResolver $localeResolver
    ) {
        $this->locale = $localeResolver;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        $options = [];

        $countries = MyFatoorah::getMFCountries();

        if (is_array($countries)) {
            $nameIndex = 'countryName' . ucfirst($this->locale->getLangCode());
            foreach ($countries as $key => $obj) {
                $options[] = ['value' => $key, 'label' => $obj[$nameIndex]];
            }
        }

        return $options;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
