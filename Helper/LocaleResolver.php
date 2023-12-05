<?php

namespace MyFatoorah\Gateway\Helper;

use Magento\Framework\Locale\Resolver;

/**
 * Class MyFatoorah_MyFatoorahments_Helper_Data
 *
 * Provides helper methods for retrieving data for the MyFatoorah plugin
 */
class LocaleResolver
{
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @var Resolver
     */
    private $localeResolver;

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param Resolver $localeResolver
     */
    public function __construct(
        Resolver $localeResolver
    ) {
        $this->localeResolver = $localeResolver;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get Current Locale Language Code
     *
     * @return string
     */
    public function getLangCode()
    {
        $langCode = $this->localeResolver->getLocale(); // fr_CA
        return strstr($langCode, '_', true);
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
