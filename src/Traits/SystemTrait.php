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

    /**
     * getHookFlags
     * @param $hook_flags
     * @return int
     */
    protected function getHookFlags($hook_flags)
    {
        $hookFlags = $hook_flags ?? '';
        if (empty($hookFlags)) {
            if (version_compare(swoole_version(), '4.7.0', '>=')) {
                $hookFlags = SWOOLE_HOOK_ALL | SWOOLE_HOOK_NATIVE_CURL;
            } else if (version_compare(swoole_version(), '4.6.0', '>=')) {
                $hookFlags = SWOOLE_HOOK_ALL ^ SWOOLE_HOOK_CURL | SWOOLE_HOOK_NATIVE_CURL;
            } else {
                $hookFlags = SWOOLE_HOOK_ALL ^ SWOOLE_HOOK_CURL;
            }
        }

        return $hookFlags;
    }
}
