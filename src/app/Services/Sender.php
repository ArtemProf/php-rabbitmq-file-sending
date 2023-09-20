<?php

namespace App\Services;

use PhpAmqpLib\Message\AMQPMessage;

class Sender extends Worker
{
    public function sendFile(string $filename): void
    {
        $this->checkFileOk($filename);

        $this->initRabbitMq();
        $this->initRabbitMqQueue();

        $msg = $this->prepareMsgFileContent($filename);

        $this->sendRabbitMq($msg, $this->rabbitMqChannelCallback);

        $this->waitForAnswer($this->rabbitMqChannelCallback);

        $this->closeRabbitMq();
    }
    private function prepareMsgFileContent(string $filename): string
    {
        $content = file_get_contents($filename);

        return $this->prepareMsgContent(self::MSG_TYPE_FILE, [
            'size'     => filesize($filename),
            'content'  => base64_encode($content),
            'filename' => 'receive.txt',
            'hash'     => md5($content)
        ]);
    }
    private function checkFileOk(string $filename): void
    {
        $this->printComment('Check file');
        if (!file_exists($filename)) {
            throw new \Exception('The file can\'t be opened. Please add another file and try again');
        }

        if (!filesize($filename)) {
            throw new \Exception(
                'The file you try to send should be less then 128MB. Please add another file and try again'
            );
        }
    }

    public function callback(AMQPMessage $request): void
    {
        $this->printComment('Receiving message');
        $json = $this->getMessageInfo($request);

        if (!$this->checkMessageStructure($json)) {
            $this->printComment('Empty message or data');
            $request->ack();
            return;
        }

        match ($json->type) {
            self::MSG_TYPE_MESSAGE => $this->processMessage($request, $json),
            default => $this->printComment('Undetected message type')
        };
    }
    protected function processMessage(AMQPMessage $request, object $json): void
    {
        if ($request->get('correlation_id') == $this->correlationId) {
            parent::processMessage($request, $json);
            return;
        }
    }
}