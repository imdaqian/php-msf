<?php
/**
 * Web Controller控制器基类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Controllers;

use PG\Exception\Errno;
use PG\Exception\ParameterValidationExpandException;
use PG\Exception\PrivilegeException;
use PG\AOP\Wrapper;
use PG\AOP\MI;
use PG\MSF\Base\Core;
use Exception;
use PG\MSF\Coroutine\CException;

class Controller extends Core
{
    /**
     * @var Wrapper|\PG\MSF\Memory\Pool 对象池
     */
    protected $objectPool;

    /**
     * @var array 当前请求已使用的对象列表
     */
    public $objectPoolBuckets = [];

    /**
     * @var float 请求开始处理的时间
     */
    public $requestStartTime = 0.0;

    /**
     * @var string TCP_REQUEST|HTTP_REQUEST 请求类型
     */
    public $requestType;

    /**
     * Controller constructor.
     *
     * @param string $controllerName controller名称
     * @param string $methodName method名称
     */
    public function __construct($controllerName, $methodName)
    {
        // 支持自动销毁成员变量
        MI::__supportAutoDestroy(static::class);
        $this->requestStartTime = microtime(true);
    }

    /**
     * 获取对象池
     *
     * @return Wrapper|\PG\MSF\Memory\Pool
     */
    public function getObjectPool()
    {
        return $this->objectPool;
    }

    /**
     * 设置对象池
     *
     * @param Wrapper|\PG\MSF\Memory\Pool|NULL $objectPool
     * @return $this
     */
    public function setObjectPool($objectPool)
    {
        $this->objectPool = $objectPool;
        return $this;
    }

    /**
     * 设置请求类型
     *
     * @param string $requestType TCP_REQUEST|HTTP_REQUEST
     * @return $this
     */
    public function setRequestType($requestType)
    {
        $this->requestType = $requestType;
        return $this;
    }

    /**
     * 返回请求类型
     *
     * @return string
     */
    public function getRequestType()
    {
        return $this->requestType;
    }

    /**
     * 异常的回调
     *
     * @param \Throwable $e
     * @throws \Throwable
     */
    public function onExceptionHandle(\Throwable $e)
    {
        try {
            if ($e->getPrevious()) {
                $ce     = $e->getPrevious();
                $errMsg = dump($ce, false, true);
            } else {
                $errMsg = dump($e, false, true);
                $ce     = $e;
            }

            if ($ce instanceof ParameterValidationExpandException) {
                $this->getContext()->getLog()->warning($errMsg . ' with code ' . Errno::PARAMETER_VALIDATION_FAILED);
                $this->outputJson(parent::$stdClass, $ce->getMessage(), Errno::PARAMETER_VALIDATION_FAILED);
            } elseif ($ce instanceof PrivilegeException) {
                $this->getContext()->getLog()->warning($errMsg . ' with code ' . Errno::PRIVILEGE_NOT_PASS);
                $this->outputJson(parent::$stdClass, $ce->getMessage(), Errno::PRIVILEGE_NOT_PASS);
            } elseif ($ce instanceof \MongoException) {
                $this->getContext()->getLog()->error($errMsg . ' with code ' . $ce->getCode());
                $this->outputJson(parent::$stdClass, 'Network Error.', Errno::FATAL);
            } elseif ($ce instanceof CException) {
                $this->getContext()->getLog()->error($errMsg . ' with code ' . $ce->getCode());
                $this->outputJson(parent::$stdClass, $ce->getMessage(), $ce->getCode());
            } else {
                $this->getContext()->getLog()->error($errMsg . ' with code ' . $ce->getCode());
                $this->outputJson(parent::$stdClass, $ce->getMessage(), $ce->getCode());
            }
        } catch (\Throwable $ne) {
            getInstance()->log->error('previous exception ' . dump($ce, false, true));
            getInstance()->log->error('handle exception ' . dump($ne, false, true));
        }
    }

    /**
     * 请求处理完成销毁相关资源
     */
    public function destroy()
    {
        if ($this->getContext()) {
            $this->getContext()->getLog()->appendNoticeLog();
            //销毁对象池
            foreach ($this->objectPoolBuckets as $k => $obj) {
                $this->objectPool->push($obj);
                $this->objectPoolBuckets[$k] = null;
                unset($this->objectPoolBuckets[$k]);
            }
            $this->objectPool->setCurrentObjParent(null);
            $this->resetProperties();
            $this->__isContruct = false;
            getInstance()->objectPool->push($this);
            parent::destroy();
        }
    }

    /**
     * 响应json格式数据
     *
     * @param null $data
     * @param string $message
     * @param int $status
     * @param null $callback
     * @return void
     */
    public function outputJson($data = null, $message = '', $status = 200, $callback = null)
    {
        $this->getContext()->getOutput()->outputJson($data, $message, $status, $callback);
    }

    /**
     * 通过模板引擎响应输出HTML
     *
     * @param array $data
     * @param string|null $view
     * @throws \Exception
     * @throws \Throwable
     * @throws Exception
     * @return void
     */
    public function outputView(array $data, $view = null)
    {
        $this->getContext()->getOutput()->outputView($data, $view);
    }
}
