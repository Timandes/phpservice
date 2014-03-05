PHP Service
===========

Requirements
------------

* PHP 5.3.x or upper

    * http://www.php.net/

* php_pcntl

    * http://www.php.net/manual/en/book.pcntl.php

* PECL php_libevent 0.1.0 or upper

    * http://pecl.php.net/package/libevent

Getting Started
---------------

    ``` php

<?php

    /**
     * hello.php
     */

    // important!!!
    declare(ticks=1);

    require 'vendor/autoload.php';

    use Timandes\CLI\Service;

    $oService = Service::create(function() {
        // do something ...
    });
    $oService->start(3);

?>

    ```

Launch:

    ``` sh

    $ php hello.php

    ```

Terminate safely (assume PID is 1234):

    ``` sh

    $ kill 1234
    
    ```

Documents
---------

https://github.com/Timandes/php-service/wiki

Authors
-------

Timandes White <timands@gmail.com>
