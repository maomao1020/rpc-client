<?php
// +----------------------------------------------------------------------
// | File：WorkerCircuitBreaker.php
// +----------------------------------------------------------------------
// | Created：2026/5/28 9:35
// +----------------------------------------------------------------------
// | Description：
// +----------------------------------------------------------------------
// | Author: zhangjian <83680989@qq.com>
// +----------------------------------------------------------------------
namespace App\Utility;

use EasySwoole\Component\Singleton;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\EasySwoole\ServerManager;

class WorkerCircuitBreaker
{
    use Singleton;

    /**
     * @example
     * $fallback = function() {
     * return ['code' => 503, 'msg' => '系统繁忙，请稍后再试（来自本地熔断降级）'];
     * };
     *
     * $rpcCall = function() use ($client) {
     * RPC 调用
     * return $client->call('UserService', 'getUserInfo', ['id' => 1]);
     * };
     *
     * 经过熔断器保护的调用
     * $result = RpcCircuitBreaker::getInstance()->call($rpcCall, $fallback);
     *
     * Closed（关闭状态）：正常转发请求。如果失败率达到阈值（如 10 秒内失败 50%），切换到 Open 状态。
     * Open（全开熔断状态）：直接拦截请求，触发本地 Fallback（降级回调），不再调用远端 RPC。启动定时器，X 秒后切换到 Half-Open。
     * Half-Open（半开状态）：放行少量流量尝试调用远端。成功则恢复到 Closed，失败则重新退回 Open。
     *
     */
    private const STATE_CLOSED = 'CLOSED';
    private const STATE_OPEN = 'OPEN';
    private const STATE_HALF_OPEN = 'HALF_OPEN';
    // 存储所有服务的熔断状态（按服务名称隔离）
    private $services = [];
    // 配置参数
    private $failThreshold = 5;      // 连续失败几次触发熔断
    private $retryWindow = 10;     // 熔断开启后，多少秒尝试恢复（半开窗口期）

    public function call($serverName, $rpcCall, $fallback)
    {
        $this->initServer($serverName);
        $this->checkState($serverName);
        try {
            $result = $rpcCall();
            $this->onSuccess($serverName);
            return $result;
        } catch (\Throwable $e) {
            $this->onFailure($serverName, $e->getMessage());
            return $fallback;
        }
    }

    /**
     * 初始化
     * @param $serverName
     * @return void
     */
    private function initServer($serverName)
    {
        if (!isset($this->services[$serverName])) {
            $this->services[$serverName] = [
                'state'               => self::STATE_CLOSED,
                'failCount'           => 0,
                'lastStateChangeTime' => 0
            ];
        }
    }

    private function checkState($serverName)
    {
        $srv = &$this->services[$serverName];
        if ($srv['state'] === self::STATE_OPEN && (time() - $srv['lastStateChangeTime']) > $this->retryWindow) {
            $srv['state'] = self::STATE_HALF_OPEN;
            Logger::getInstance()->info(sprintf(' RPC熔断器 [WorkerId:%s]', $this->getWorkerId()));
        }
    }

    public function getWorkerId()
    {
        $server = ServerManager::getInstance()->getSwooleServer();
        if ($server) return $server->worker_id;
        return -1;
    }

    private function onSuccess($serverName)
    {
        $srv = &$this->services[$serverName];
        // 半开状态下成功，则彻底恢复
        if ($srv['state'] === self::STATE_HALF_OPEN) {
            $srv['state']               = self::STATE_CLOSED;
            $srv['failCount']           = 0;
            $srv['lastStateChangeTime'] = 0;
            Logger::getInstance()->info(sprintf("RPC熔断器 [WorkerId: %s] 服务 [%s] 恢复成功，进入 [CLOSED] 关闭状态。", $this->getWorkerId(), $serverName));
        }
        // 如果是正常状态，成功则清空连续失败计数
        if ($srv['state'] === self::STATE_CLOSED) {
            $srv['failCount']           = 0;
            $srv['lastStateChangeTime'] = 0;
        }
    }

    private function onFailure($serverName, string $errorMsg)
    {
        $srv = &$this->services[$serverName];
        $srv['failCount']++;
        Logger::getInstance()->waring(sprintf("RPC调用失败 [WorkerId:%s] 服务 [%s] 连续失败数: %s. 错误原因: %s", $this->getWorkerId(), $serverName, $srv['failCount'], $errorMsg));
        // 无论是 CLOSED 连续失败达到阈值，还是 HALF_OPEN 依然失败，都直接切入 OPEN
        if ($srv['state'] === self::STATE_HALF_OPEN || $srv['failCount'] >= $this->failThreshold) {
            $srv['state']               = self::STATE_OPEN;
            $srv['lastStateChangeTime'] = time();
            Logger::getInstance()->error(sprintf("RPC熔断器 [WorkerId:%s] 服务 [%s] 触发熔断！进入 [OPEN] 全开状态。", $this->getWorkerId(), $serverName));
        }
    }
}