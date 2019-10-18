<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

require_once __DIR__.'/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::create(__DIR__);
$dotenv->load();

$exchange = getenv('RABBITMQ_EXCHANGE');
$queue = getenv('RABBITMQ_QUEUE');
$consumerTag = getenv('RABBITMQ_CONSUMERTAG');

$host = getenv('RABBITMQ_HOST');
$port = getenv('RABBITMQ_PORT');
$user = getenv('RABBITMQ_USER');
$pass = getenv('RABBITMQ_PASS');
$vhost = getenv('RABBITMQ_VHOST');

$connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
$channel = $connection->channel();

$channel->queue_declare($queue, false, true, false, false);

//Informar o tipo da exchange
$channel->exchange_declare($exchange, AMQPExchangeType::TOPIC, false, true, false);
$channel->queue_bind($queue, $exchange);

function process_message($message)
{
    echo '<pre>';
    $msg = json_decode($message->body,true);
    print_r($msg);
    echo '</pre>';
    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    if($message->body === 'quit'){
        $message->delivery_info['channel']->basic_cancel($message->delivery_info['delivery_tag']);
    }
}

$channel->basic_consume($queue, $consumerTag, false, false, false, false, 'process_message');

function shutdown($channel, $connection)
{
    $channel->close();
    $connection->close();
}

register_shutdown_function('shutdown', $channel, $connection);
while ($channel->is_consuming()) {
    $channel->wait();
}