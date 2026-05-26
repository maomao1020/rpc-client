<?php


namespace App\HttpController;


use EasySwoole\EasySwoole\Config;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\Redis\Config\RedisConfig;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Rpc\NodeManager\RedisManager;
use EasySwoole\Rpc\Response;
use EasySwoole\Rpc\Rpc;

class Index extends Controller
{

    public function index()
    {
        $arr       = Config::getInstance()->getConf('REDIS');
        $redisPool = new RedisPool(new RedisConfig($arr));
        $manager   = new RedisManager($redisPool);
        $config    = new \EasySwoole\Rpc\Config();
        $config->setNodeManager($manager);
        $rpc    = new Rpc($config);
        $client = $rpc->client();
        $client->addCall('Goods', 'list', [])
            ->setOnFail(function (Response $response) {
                var_dump($response->getStatus());
            })->setOnSuccess(function (Response $response) {
                var_dump($response->toArray());
            });
        $client->exec();
        $this->writeJson(200, ['msg' => 'ok']);
    }

    function test()
    {
        $this->response()->write('this is test');
    }

    protected function actionNotFound(?string $action)
    {
        $this->response()->withStatus(404);
        $file = EASYSWOOLE_ROOT . '/vendor/easyswoole/easyswoole/src/Resource/Http/404.html';
        if (!is_file($file)) {
            $file = EASYSWOOLE_ROOT . '/src/Resource/Http/404.html';
        }
        $this->response()->write(file_get_contents($file));
    }
}