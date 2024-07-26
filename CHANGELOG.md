# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.2.7] - 2023-12-07
- New feature: add an option to the admin setting page to redirect the failed payment to the card page or the failure page
- New feature: add an option to the admin setting page to select whether to create the MyFatoorah invoice using the default currency scope or the website scope
- Fix the payment-status link on the admin order page
- Redirect to a new tab when clicking on the payment-status link on the admin order page and the customer order page
- Add code to translate the failed payment error
- Add some translation messages
- Change the event name to be specific to MyFatoorah
- Redirect to the process page with secure HTTPS and the order increment ID
- Redirect to the success page with secure HTTPS and the order increment ID
- Redirect to the failure page with secure HTTPS and the order increment ID
- Hide the loader screen if the one-step checkout fails to validate the form

## [2.2.6] - 2023-09-4
- Read the scripts path from the config file

## [2.2.5] - 2023-08-22
- Fix card view payment 

## [2.2.4] - 2023-07-17
- Add the google pay payment gateway
- Set the Invoice Expiry from the MyFatoorah account if the Pending Payment Order Lifetime is blank
- Fix default config

## [2.1.1] - 2023-06-03
- Exclude MyFatoorah scripts files from minifying
- Fix admin configuration for multisotres
- Enable shipping on vendor features
- Code optimization

## [2.1.0] - 2023-03-12
- Add Apple Pay Embedded Button
- Fixed the Mobile View
- Fixed the Invoice Link on Admin Page
- Fixed the Embedded Translation
- Fix webhook issue
- Multi store configuration
- Show error if shipping without dimension

[2.2.7]: https://dev.azure.com/myfatoorahsc/Public-Repo/_git/Magento2-Gateway?version=GT2.2.7
[2.2.6]: https://dev.azure.com/myfatoorahsc/Public-Repo/_git/Magento2-Gateway?version=GT2.2.6
[2.2.5]: https://dev.azure.com/myfatoorahsc/Public-Repo/_git/Magento2-Gateway?version=GT2.2.5
[2.2.4]: https://dev.azure.com/myfatoorahsc/Public-Repo/_git/Magento2-Gateway?version=GT2.2.4
[2.1.1]: https://dev.azure.com/myfatoorahsc/Public-Repo/_git/Magento2-Gateway?version=GT2.1.1
[2.1.0]: https://dev.azure.com/myfatoorahsc/Public-Repo/_git/Magento2-Gateway?version=GT2.1.0
[2.0.0]: https://dev.azure.com/myfatoorahsc/Public-Repo/_git/Magento2-Gateway?version=GT2.0.0
