<?php

require_once __DIR__.'/vendor/autoload.php';

use App\Services\Sender;

if ($argc == 1) {
    echo "There is no attached file. Please add file and try again";
    exit(1);
}

$worker = new Sender(
    RABBIT_MQ_HOST,
    RABBIT_MQ_PORT,
    RABBIT_MQ_USER,
    RABBIT_MQ_PWD,
    RABBIT_MQ_CHANNEL_NAME
);

try {
    $worker->sendFile($argv[1]);
} catch (\Exception $e) {
    echo $e->getMessage();
    exit(1);
}