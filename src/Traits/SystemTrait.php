<?php
/**
 * +----------------------------------------------------------------------
 * | Daemon and Cli model about php process worker
 * +----------------------------------------------------------------------
 * | Licensed ( https://opensource.org/licenses/MIT )
 * +----------------------------------------------------------------------
 * | Author: bingcool <bingcoolhuang@gmail.com || 2437667702@qq.com>
 * +----------------------------------------------------------------------
 */

namespace Workerfy\Traits;

trait SystemTrait
{
    /**
     * @param bool $enable_coroutine
     */
    public function resetAsyncCoroutine(bool $enable_coroutine)
    {
        if (version_compare(swoole_version(), '4.6.0', '<')) {
            \Swoole\Timer::set([
                'enable_coroutine' => $enable_coroutine,
            ]);
        } else {
            if (function_exists('swoole_async_set')) {
                swoole_async_set([
                    'enable_coroutine' => $enable_coroutine,
                ]);
            } else {
                /**
                 * 4.6 Async AbstractEventHandle、Timer、Process::signal moveto Swoole\Async library
                 */
                $isSetFlag = false;
                if (class_exists('Swoole\Async')) {
                    \Swoole\Async::set([
                        'enable_coroutine' => $enable_coroutine,
                    ]);
                    $isSetFlag = true;
                }

                if (!$isSetFlag) {
                    if (method_exists('Swoole\Timer', 'set')) {
                        @\Swoole\Timer::set([
                            'enable_coroutine' => $enable_coroutine,
                        ]);
                    }
                }
            }
        }
    }
}
