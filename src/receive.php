<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\Receiver;

$worker = new Receiver(
    RABBIT_MQ_HOST,
    RABBIT_MQ_PORT,
    RABBIT_MQ_USER,
    RABBIT_MQ_PWD,
    RABBIT_MQ_CHANNEL_NAME
);

try {
    $worker->receiveFile();
} catch (\Exception $e) {
    echo $e->getMessage();
    exit(1);
}