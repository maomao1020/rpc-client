<?php
// +----------------------------------------------------------------------
// | File：NacosManager.php
// +----------------------------------------------------------------------
// | Created：2026/5/26 10:49
// +----------------------------------------------------------------------
// | Description：
// +----------------------------------------------------------------------
// | Author: zhangjian <83680989@qq.com>
// +----------------------------------------------------------------------
namespace App\Rpc;

use EasySwoole\EasySwoole\Config;
use EasySwoole\Rpc\NodeManager\NodeManagerInterface;
use EasySwoole\Rpc\ServiceNode;
use EasySwoole\Utility\Random;
use Yurun\Nacos\Client;
use Yurun\Nacos\ClientConfig;

class NacosManager implements NodeManagerInterface
{
    private $client;
    private $namespaceId;
    private $groupName;

    public function __construct()
    {
        $arr               = Config::getInstance()->getConf('NACOS');
        $this->client      = new Client(new ClientConfig($arr));
        $this->namespaceId = $arr['tenant'];
        $this->groupName   = $arr['group'];
    }

    function getServiceNodes(string $serviceName, ?string $version = null): array
    {
        $response = $this->client->instance->list($serviceName);
        $nodes    = [];
        foreach ($response->getHosts() as $node) {
            $serverNode = new ServiceNode();
            $serverNode->setServiceName($serviceName);
            $serverNode->setServerIp($node->getIp());
            $serverNode->setServerPort($node->getPort());
            $nodes[] = $serverNode;
        }
        return $nodes;
    }

    function getServiceNode(string $serviceName, ?string $version = null): ?ServiceNode
    {
        $list = $this->getServiceNodes($serviceName, $version);
        var_dump($list);
        return Random::arrayRandOne($list);
    }

    function deleteServiceNode(ServiceNode $serviceNode): bool
    {
        return true;
    }

    function serviceNodeHeartBeat(ServiceNode $serviceNode): bool
    {
        return true;
    }
}