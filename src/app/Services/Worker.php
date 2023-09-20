<?php

namespace App\Services;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPReader;

abstract class Worker
{
    public const MSG_TYPE_FILE    = 'file';
    public const MSG_TYPE_MESSAGE = 'message';

    protected AMQPStreamConnection $connection;
    protected AMQPChannel          $channel;
    protected AMQPMessage          $request;
    protected string               $correlationId;
    protected string               $rabbitMqChannelCallback;

    public function __construct(
        public string $rabbitMqHost,
        public string $rabbitMqPort,
        public string $rabbitMqUser,
        public string $rabbitMqPwd,
        public string $rabbitMqChannelPublic
    ) {
    }

    public function initRabbitMq(): void
    {
        $this->printComment('Open connection');
        $this->connection = new AMQPStreamConnection(
            $this->rabbitMqHost, $this->rabbitMqPort, $this->rabbitMqUser,
            $this->rabbitMqPwd
        );
        $this->printComment('Getting channel');
        $this->channel = $this->connection->channel();
    }

    protected function initRabbitMqQueue(string $channel = ''): void
    {
        $this->printComment('Declaring queue');
        $result = $this->channel->queue_declare($channel, false, true, false, false);
        if (!$channel) {
            $this->rabbitMqChannelCallback = $result[0] ?? '';
        }
    }

    public function closeRabbitMq(): void
    {
        $this->channel->close();
        $this->printComment('Close channel');
        $this->connection->close();
        $this->printComment('Close connection');
    }

    public function printComment(string $message): void
    {
        echo $message."\n";
    }

    protected function waitForAnswer(string $queue): void
    {
        $this->printComment('Establishing QOS');
        $this->channel->basic_qos(null, 1, null);
        $this->printComment('Establishing Listener');
        $this->channel->basic_consume($queue, '', false, false, false, false, [$this, 'callback']);

        $this->printComment('Inifinite loop');
        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    public function sendRabbitMq(string $message, string $replyTo = null): void
    {
        $this->printComment('Sending message');
        $this->generateCorrelationId();

        $props = [
            'type'           => 'application/json',
            'correlation_id' => $this->correlationId,
        ];
        if ($replyTo) {
            $props['reply_to'] = $replyTo;
        }

        $msg = new AMQPMessage($message, $props);
        $this->channel->basic_publish($msg, '', $this->rabbitMqChannelPublic);
    }

    protected function prepareMsgContent(string $type, array $data): string
    {
        return json_encode([
                               'type' => $type,
                               'data' => $data
                           ]);
    }

    abstract public function callback(AMQPMessage $request): void;

    private function generateCorrelationId(): void
    {
        $this->correlationId = uniqid();
    }

    protected function sendRabbitMqResponse(
        AMQPMessage $request,
        string $type,
        string $correlationId,
        array $data
    ): void {
        $message = $this->prepareMsgContent($type, $data);

        $msg = new AMQPMessage($message, [
            'correlation_id' => $correlationId
        ]);

        $request->getChannel()->basic_publish($msg, '', $request->get('reply_to'));
        $request->ack();
    }

    protected function processMessage(AMQPMessage $request, object $json): void
    {
        if (!isset($json->data->text)) {
            $this->printComment('Wrong message data structure for the message type');
            $request->ack();
            return;
        }

        $this->printComment('New response:'.$json->data->text);
    }

    protected function checkMessageStructure(mixed $json): bool
    {
        return $json && is_object($json) && isset($json->data);
    }

    protected function getMessageInfo(AMQPMessage $msg): \stdClass
    {
        return json_decode($msg->body);
    }
}