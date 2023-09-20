<?php

namespace App\Services;

use PhpAmqpLib\Message\AMQPMessage;

class Receiver extends Worker
{
    public function receiveFile(): void
    {
        $this->initRabbitMq();
        $this->initRabbitMqQueue($this->rabbitMqChannelPublic);
        $this->waitForAnswer($this->rabbitMqChannelPublic);
        $this->closeRabbitMq();
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
            self::MSG_TYPE_FILE => $this->processFile($request, $json),
            self::MSG_TYPE_MESSAGE => $this->processMessage($request, $json),
            default => $this->printComment('Undetected message type')
        };
    }
    protected function processFile(AMQPMessage $request, object $json): void
    {
        if (!isset($json->data->content, $json->data->filename, $json->data->size, $json->data->hash)) {
            $this->printComment('Wrong message data structure for the file type');
            $request->ack();
            return;
        }

        $content = $this->decodeContent($json->data->content);

        $this->saveFile($content, $json->data->filename);
        $this->checkFileSizeHash($json->data->filename, $json->data->size, $json->data->hash);
        $this->printComment('File saved!');

        $this->sendRabbitMqResponse(
            $request,
            self::MSG_TYPE_MESSAGE,
            $request->get('correlation_id'),
            [
                'text' => 'ok'
            ]
        );
        $this->printComment('Response sent!');
    }
    private function saveFile(string $binary, string $filename): void
    {
        file_put_contents($filename, $binary);
    }
    private function checkFileSizeHash(string $filename, mixed $filesize, string $hash): void
    {
        if (filesize($filename) != $filesize) {
            throw new \Exception('File size is not equals');
        }

        if (md5(file_get_contents($filename)) != $hash) {
            throw new \Exception('File hash is not equals');
        }
    }
    private function decodeContent(string $content): mixed
    {
        return base64_decode($content);
    }
}