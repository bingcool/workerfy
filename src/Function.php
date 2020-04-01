<?php
/**
 * @param string $str
 * @return bool
 */
function json_validate(string $str) {
    if (is_string($str)) {
        @json_decode($str);
        return (json_last_error() === JSON_ERROR_NONE);
    }
    return false;
}

// 随机获取一个监听的端口(php_socket模式)
function get_one_free_port() {
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!socket_bind($socket, '0.0.0.0', 0)) {
        return false;
    }
    if (!socket_listen($socket)) {
        return false;
    }
    if (!socket_getsockname($socket, $addr, $port)) {
        return false;
    }
    socket_close($socket);
    unset($socket);
    return $port;
}

// 随机获取一个监听的端口(swoole_coroutine模式)
function get_one_free_port_coro() {
    $socket = new \Swoole\Coroutine\Socket(AF_INET, SOCK_STREAM, IPPROTO_IP);
    $socket->bind('0.0.0.0');
    $socket->listen();
    $port = $socket->getsockname()['port'];
    $socket->close();
    unset($socket);
    return $port;
}

/**
 * 是否是在主进程环境中
 * @return bool
 */
function inMasterProcessEnv() {
    $pid = posix_getpid();
    if(defined(MASTER_PID) && $pid == MASTER_PID) {
        return true;
    }
    return false;
}

/**
 * 是否是在子进程环境中
 * @return bool
 */
function inChildrenProcessEnv() {
    return !inMasterProcessEnv();
}

/**
 * @return string
 */
function workerfy_version() {
    return WORKERFY_VERSION;
}
