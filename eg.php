<?php
# vim: set ts=4 sts=4 sw=4 expandtab:
# sublime: tab_size 4; translate_tabs_to_spaces true
/**
 * Example: print something
 *
 * @package phpservice
 */

declare(ticks=1);

require 'src/Timandes/CLI/Service.php';

use Timandes\CLI\Service;

$oService = Service::create(function() {
    $m = mt_rand(1, 100);
    $s = 0;
    for ($i = 0; $i < 10; ++$i) {
        $s += $i * $m;

        fprintf(STDOUT, "Result(#%d)=%d\n", getmypid(), $s);
        sleep(1);
    }
    fprintf(STDOUT, "Finish(#%d)\n", getmypid());
});
$oService->setVerbose(2);
$oService->start(3);
