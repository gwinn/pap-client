<?php

require_once __DIR__ . '/vendor/autoload.php';
$console = new Pap\Console\Application('IntaroCRM History Command');
$console->add(new Pap\Command\HistoryCommand());
$console->run();