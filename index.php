<?php
// Normal autoload
$autoload = __DIR__ . '/vendor/autoload.php';
require $autoload;

use App\Container\Container;
use App\View\ViewController;
use App\MessageRender\MessageRender;
use App\MessageRender\BitlyCache;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Clue\React\Stdio\Stdio;



$container = new Container();
$log = new Logger('app');
$container->register('logger',$log); //register before anything to use logger in case of exeptions in construct of components


if (!file_exists(__DIR__ . '/.env')){
    file_put_contents(__DIR__ . '/.env', '');
}


$dotenv = \Dotenv\Dotenv::create(__DIR__);
$dotenv->load();
$loop = React\EventLoop\Factory::create();

$viewController = new ViewController($container);
$messageRender = new MessageRender($container);
$stdio = new Stdio($loop);
$ig = new \InstagramAPI\Instagram(false, false,array(
    'storage'    => 'file',
    'basefolder' => __DIR__.'/sessions'
));


$log->pushHandler(new StreamHandler(__DIR__.'/log.log', Logger::DEBUG));

$bitly = new BitlyCache($container);

$container->register('ig',$ig);
$container->register('stdio',$stdio);
$container->register('loop',$loop);
$container->register('view-controller',$viewController);
$container->register('message-render',$messageRender);
$container->register('bitly',$bitly);

$viewController->go('Login');
$loop->run();