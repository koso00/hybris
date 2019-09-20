#!/bin/bash -i php
<?php
// Normal autoload
$autoload = __DIR__ . '/vendor/autoload.php';
require $autoload;

use App\Container\Container;
use App\View\ViewController;
use App\MessageRender\MessageRender;
use App\Service\NotificationService;
use App\MessageRender\BitlyCache;
use App\Service\InstagramAsync;
use App\Service\Spinner;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Clue\React\Stdio\Stdio;
use WyriHaximus\React\ChildProcess\Messenger\Factory as MessengerFactory;
use WyriHaximus\React\ChildProcess\Messenger\Messages\Factory as MessageFactory;
use WyriHaximus\React\ChildProcess\Messenger\Messenger;

exec('reset');

$container = new Container();
$log = new Logger('app');
$container->register('logger',$log); //register before anything to use logger in case of exeptions in construct of components
ini_set('memory_limit', '-1');


if (!file_exists(__DIR__ . '/.env')){
    file_put_contents(__DIR__ . '/.env', '');
}

$dotenv = \Dotenv\Dotenv::create(__DIR__);
$dotenv->load();

$loop = React\EventLoop\Factory::create();


$container->register('loop',$loop);

$notifications = new NotificationService($container);
$viewController = new ViewController($container);
$messageRender = new MessageRender($container);
$spinner = new Spinner($container);

$stdio = new Stdio($loop);


$ig = new InstagramAsync($container);

$pcntl = new \MKraemer\ReactPCNTL\PCNTL($loop);

$pcntl->on(SIGINT,function()use($stdio,$loop,$ig){
    $stdio->setPrompt('');
    $stdio->setEcho('');
    for ($i = 0; $i < intval(getenv('LINES')) + 1;$i++){
        $stdio->write("\033[2K\033[1A");
    }
    $stdio->write("\033[?25h"); // enable cursor
    $stdio->write("\033[?1000l"); // disable mouse tracking
    $ig->stop();
    $loop->futureTick(function()use($loop){
        $loop->stop();
        die();
    });
});

$log->pushHandler(new StreamHandler(__DIR__.'/log.log', Logger::DEBUG));

$bitly = new BitlyCache($container);

$container->register('ig',$ig);
$container->register('spinner',$spinner);
$container->register('notification',$notifications);
$container->register('stdio',$stdio);
$container->register('view-controller',$viewController);
$container->register('message-render',$messageRender);
$container->register('bitly',$bitly);

$viewController->go('Login');

$loop->run();