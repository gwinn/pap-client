IntaroCRM - Post Affilate Pro integration tool
===================

Tool for simple integration between [IntaroCRM](http://www.intarocrm.ru/) and [Pap](http://www.postaffiliatepro.com).

## Getting started / Installation

* Download this project via any [git](http://git-scm.com/) client (checkout master branch), or as a [zip archive](https://github.com/intarocrm/pap-client/archive/master.zip).
* Install [composer](https://getcomposer.org/) into the project directory.

### Composer install guide & tool installation via composer
* Download [composer](https://getcomposer.org/download/)
* Use command `php composer.phar update` to download vendors.

### Settings .ini file
It is needed to setup your configutarions in a `config/parameters.ini` file (example is a `config/parameters-dist.ini` file).

### Sample of code for enter page for transaction fixing

```php
require_once __DIR__ . '/pap-client/vendor/autoload.php'; // require autoloader
$intaroApi = new Pap\Helpers\ApiHelper(); // create api helper
$intaroApi->setAdditionalParameters($_SERVER['QUERY_STRING']); // setting additional params in user cookies
```

### Sample of code for form processing script

```php
require_once __DIR__ . '/pap-client/vendor/autoload.php'; // require autoloader
require_once __DIR__ . '/pap/api/PapApi.class.php'; //require pap api

$intaroApi = new Pap\Helpers\ApiHelper(); // create api helper
$saleTracker = new Pap_Api_SaleTracker('http://yourdomain.com/pap/scripts/sale.php');

$order = array(
    'orderMethod'  => 'some-order-method',
    'customer' => array(
        'fio'   => 'user name',
        'phone' => array('+79123456789'),
    ),
    'customFields' => array(
        'form_type' => 'some-form-type'
    ),
    'items' => array(
        array(
            'quantity' => 1,
            'productId' => 1,
        ),
    ),
);

$result = $intaroApi->orderCreate($order);

if ($result != null) {
    $saleTracker->setAccountId('default1');
    $sale = $saleTracker->createSale();
    $sale->setTotalCost('10000');
    $sale->setStatus('P');
    $sale->setProductID('Your product name');
    $sale->setOrderId($result); // send order id to pap
    $saleTracker->register();
}

```

### Cron setup

Add this command, to send request with changed statuses to Post Affilate Pro every 5 minutes:

```bash
*/5 * * * * php pap-client/console.php update-pap
```
