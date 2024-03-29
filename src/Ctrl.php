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

use \Workerfy\Dto\PipeMsgDto;

include __DIR__ . '/WorkerfyConst.php';
include __DIR__ . '/EachColor.php';

if (!version_compare(phpversion(), '7.1.0', '>=')) {
    write_info("【Warning】php version require >= php7.1+");
    exit(0);
}

if (!version_compare(swoole_version(), '4.4.5', '>=')) {
    write_info("【Warning】swoole version require >= 4.4.5");
    exit(0);
}

if (!defined('START_SCRIPT_FILE')) {
    define("START_SCRIPT_FILE", $_SERVER['PWD'] . '/' . $_SERVER['SCRIPT_FILENAME']);
    if (!is_file(START_SCRIPT_FILE)) {
        write_info("【Warning】Please define Constants START_SCRIPT_FILE");
        exit(0);
    }
}

if (!defined('PID_FILE')) {
    write_info("【Warning】Please define Constants PID_FILE");
    exit(0);
}

if (!is_dir($pidFileRoot = pathinfo(PID_FILE, PATHINFO_DIRNAME))) {
    mkdir($pidFileRoot, 0777, true);
}

if (!defined('CTL_LOG_FILE')) {
    define('CTL_LOG_FILE', getCtlLogFile());
    if (!file_exists(CTL_LOG_FILE)) {
        touch(CTL_LOG_FILE);
        chmod(CTL_LOG_FILE, 0666);
    }
}

if (!defined('STATUS_FILE')) {
    define('STATUS_FILE', str_replace('.pid', '.status', PID_FILE));
    if (!file_exists(STATUS_FILE)) {
        touch(STATUS_FILE);
        chmod(STATUS_FILE, 0666);
    }
}

$command = $_SERVER['argv'][1] ?? CLI_START;

function parseCliEnvParams()
{
    $cliParams = [];
    $args = array_splice($_SERVER['argv'], 2);
    array_reduce($args, function ($result, $item) use (&$cliParams) {
        // start daemon
        if (in_array($item, ['-d', '-D'])) {
            putenv('daemon=1');
        } else if (in_array($item, ['-f', '-F'])) {
            // stop force
            putenv('force=1');
        } else {
            $item = ltrim($item, '--');
            list($env, $value) = explode('=', $item);
            if ($env && $value) {
                $cliParams[$env] = $value;
            }
        }
    });

    return $cliParams;
}

$cliParams = parseCliEnvParams();

switch ($command) {
    case CLI_START :
        start($cliParams);
        break;
    case CLI_STOP :
        stop($cliParams);
        break;
    case CLI_RELOAD :
        reload($cliParams);
        break;
    case CLI_RESTART:
        restart($cliParams);
        break;
    case CLI_STATUS :
        status($cliParams);
        break;
    case CLI_CHECK_REBOOT:
        checkReboot($cliParams);
        break;
    case CLI_PIPE :
        pipe($cliParams);
        break;
    case CLI_ADD :
        add($cliParams);
        break;
    case CLI_REMOVE :
        remove($cliParams);
        break;
    default :
        write_info("【Warning】You must use 【start, stop, reload, status, pipe, add, remove】command");
        exit(0);
}

function start($cliParams)
{
    if (is_file(PID_FILE)) {
        $masterPid = file_get_contents(PID_FILE);
        if (is_numeric($masterPid)) {
            $masterPid = (int)$masterPid;
        } else {
            unlink(PID_FILE);
            write_info("【Warning】Master Pid is invalid");
            exit(0);
        }
        if (\Swoole\Process::kill($masterPid, 0)) {
            write_info("【Warning】Master Process has started, you can not start again, you can use status to show info");
            exit(0);
        }
    }
    setCliParamsEnv($cliParams);
    write_info("【Info】Master && Children Process ready to start, please wait a time ......", 'light_green');
}

function stop($cliParams)
{
    if (is_file(PID_FILE)) {
        $masterPid = file_get_contents(PID_FILE);
        if (is_numeric($masterPid) && $masterPid > 0) {
            $masterPid = (int)$masterPid;
        } else {
            write_info("【Warning】Master Pid is invalid");
            exit(0);
        }
    }

    if (\Swoole\Process::kill($masterPid, 0)) {
        $pipeMsgDto = new PipeMsgDto();
        $pipeMsgDto->action = CLI_STOP;
        $pipeMsg = serialize($pipeMsgDto);

        $pipeFile = getCliPipeFile();
        $pipe = @fopen($pipeFile, 'w+');
        if (flock($pipe, LOCK_EX)) {
            fwrite($pipe, $pipeMsg);
            flock($pipe, LOCK_UN);
        }
        fclose($pipe);
        sleep(3);

        if (getenv('force')) {
            $result = @\Swoole\Process::kill($masterPid, SIGKILL);
        } else {
            $result = \Swoole\Process::kill($masterPid, SIGTERM);
        }

        if ($result) {
            write_info("【Info】Master and Children Process start to stop, please wait a time", 'light_green');
        }

        $startStopTime = time();
        while (\Swoole\Process::kill($masterPid, 0)) {
            if (time() - $startStopTime > 10) {
                break;
            }
            sleep(1);
        }

        if (\Swoole\Process::kill($masterPid, 0)) {
            \Swoole\Process::kill($masterPid, SIGKILL);
        }

        write_info("【Info】Master and Children Process Stop OK", 'light_green');
    } else {
        write_info("【Warning】Master Process of Pid={$masterPid} is not running");
    }

    exit(0);
}

function reload($cliParams)
{
    if (is_file(PID_FILE)) {
        $masterPid = file_get_contents(PID_FILE);
        if (is_numeric($masterPid)) {
            $masterPid = (int)$masterPid;
        } else {
            write_info("【Warning】Master Pid is invalid");
            exit(0);
        }
    }

    if (\Swoole\Process::kill($masterPid, 0)) {
        $result = \Swoole\Process::kill($masterPid, SIGUSR2);
        if ($result) {
            write_info("【Info】Children Process start to reload, please wait a time", 'light_green');
        }

        $startStopTime = time();
        while (\Swoole\Process::kill($masterPid, 0)) {
            if (time() - $startStopTime > 5) {
                break;
            }
            sleep(1);
        }
        status($cliParams);
    } else {
        write_info("【Warning】Master Process of Pid={$masterPid} is not exist");
    }
    exit(0);
}

function restart($cliParams)
{
    $colors = new \Workerfy\EachColor();
    echo PHP_EOL;
    echo $colors->getColoredString('Are you sure you want to restart process use daemon model? (yes or no):', $foreground = "red", $background = "black");

    $handle = fopen("php://stdin", "r");
    $line   = fgets($handle);

    if (trim($line) != 'yes') {
        write_info("【Warning】You give up to restart process.");
        exit(0);
    }

    if (is_file(PID_FILE)) {
        $masterPid = file_get_contents(PID_FILE);
        if (is_numeric($masterPid) && $masterPid > 0) {
            $masterPid = (int)$masterPid;
        } else {
            write_info("【Warning】Master Pid is invalid");
            exit(0);
        }
    }

    if (\Swoole\Process::kill($masterPid, 0)) {

        $pipeMsgDto = new PipeMsgDto();
        $pipeMsgDto->action = CLI_STOP;
        $pipeMsg = serialize($pipeMsgDto);

        $pipeFile = getCliPipeFile();
        $pipe = @fopen($pipeFile, 'w+');
        if (flock($pipe, LOCK_EX)) {
            fwrite($pipe, $pipeMsg);
            flock($pipe, LOCK_UN);
        }
        fclose($pipe);
        sleep(1);
        $result = @\Swoole\Process::kill($masterPid, SIGKILL);
        if ($result) {
            write_info("【Info】Master and Children Process start to stop, please wait a time", 'light_green');
        }

        $startStopTime = time();
        while (@\Swoole\Process::kill($masterPid, 0)) {
            if (time() - $startStopTime > 5) {
                break;
            }
            sleep(1);
        }

        write_info("【Info】Master and Children Process Stop OK", 'light_green');
    } else {
        write_info("【Warning】Master Process of Pid={$masterPid} not exist");
        exit(0);
    }
    // restart must daemon model
    putenv('daemon=1');
    setCliParamsEnv($cliParams);
    write_info("【Info】Master and Children Process ready to restart, please wait a time", 'light_green');
}

function status($cliParams)
{
    if (is_file(PID_FILE)) {
        $masterPid = file_get_contents(PID_FILE);
        if (is_numeric($masterPid)) {
            $masterPid = (int)$masterPid;
        } else {
            write_info("【Warning】Master Pid is invalid");
            exit(0);
        }
    }

    if (!\Swoole\Process::kill($masterPid, 0)) {
        write_info("【Warning】Master Process of Pid={$masterPid} is not running");
        exit(0);
    }

    $pipeFile = getCliPipeFile();
    $ctlPipeFile = getCtlPipeFile();
    if (filetype($pipeFile) != 'fifo' || !file_exists($pipeFile)) {
        write_info("【Warning】 Master Process is not enable cli pipe, so can not show status");
        exit(0);
    }

    $pipe = fopen($pipeFile, 'r+');
    $pipeMsgDto = new PipeMsgDto();
    $pipeMsgDto->action = CLI_STATUS;
    $pipeMsgDto->targetHandler = $ctlPipeFile;

    $pipeMsg = serialize($pipeMsgDto);
    if (file_exists($ctlPipeFile)) {
        unlink($ctlPipeFile);
    }

    posix_mkfifo($ctlPipeFile, 0777);
    $ctlPipe = fopen($ctlPipeFile, 'w+');
    stream_set_blocking($ctlPipe, false);
    \Swoole\Timer::after(3000, function () {
        \Swoole\Event::exit();
    });

    \Swoole\Event::add($ctlPipe, function () use ($ctlPipe) {
        $msg = fread($ctlPipe, 8192);
        write_info($msg, 'light_green');
        \Swoole\Event::exit();
    });

    sleep(1);
    fwrite($pipe, $pipeMsg);
    \Swoole\Event::wait();
    fclose($ctlPipe);
    fclose($pipe);
    unlink($ctlPipeFile);
    exit(0);
}

function checkReboot($cliParams)
{
    if (is_file(PID_FILE)) {
        $masterPid = file_get_contents(PID_FILE);
        if (is_numeric($masterPid)) {
            $masterPid = (int)$masterPid;
        } else {
            write_info("【Warning】Master Pid is invalid");
            exit(0);
        }
    }

    // reboot
    if (!\Swoole\Process::kill($masterPid, 0)) {
        sleep(1);
        if(!\Swoole\Process::kill($masterPid, 0)) {
            start($cliParams);
        }
    }
}

function pipe($cliParams)
{
    if (is_file(PID_FILE)) {
        $masterPid = file_get_contents(PID_FILE);
        if (is_numeric($masterPid)) {
            $masterPid = (int)$masterPid;
        } else {
            write_info("【Warning】Master Pid is invalid");
            exit(0);
        }
    }

    if (!\Swoole\Process::kill($masterPid, 0)) {
        write_info("【Warning】Master Process of Pid={$masterPid} is not exist");
        exit(0);
    }

    $pipeFile = getCliPipeFile();
    if (filetype($pipeFile) != 'fifo' || !file_exists($pipeFile)) {
        write_info("【Warning】 Master Process is not enable cli pipe");
        exit(0);
    }

    $pipe = fopen($pipeFile, 'w+');
    if (!flock($pipe, LOCK_EX)) {
        write_info("【Warning】 Get file flock failed");
        exit(0);
    }

    $msg = $cliParams['msg'] ?? '';
    if ($msg) {
        write_info("【Info】Start write msg={$msg} to master", 'light_green');
        $pipeMsgDto = new PipeMsgDto();
        $pipeMsgDto->action = CLI_PIPE;
        $pipeMsgDto->message = $msg;
        fwrite($pipe, serialize($pipeMsgDto));
    } else {
        write_info("【Warning】Please use: pipe --msg=xxxxx");
    }
    fclose($pipe);
    exit(0);
}

function add($cliParams, int $waitTime = 3)
{
    if (is_file(PID_FILE)) {
        $masterPid = file_get_contents(PID_FILE);
        if (is_numeric($masterPid)) {
            $masterPid = (int)$masterPid;
        } else {
            write_info("【Warning】Master Pid is invalid");
            exit(0);
        }
    }

    if (!\Swoole\Process::kill($masterPid, 0)) {
        write_info("【Warning】 Master Process of Pid={$masterPid} is not exist");
        exit(0);
    }

    $pipeFile = getCliPipeFile();
    if (filetype($pipeFile) != 'fifo' || !file_exists($pipeFile)) {
        write_info("【Warning】 Master Process is not enable cli pipe, can not add process");
        exit(0);
    }

    $pipe = fopen($pipeFile, 'w+');
    if (!flock($pipe, LOCK_EX)) {
        write_info("【Warning】 Get file flock failed");
        exit(0);
    }

    $name = $cliParams['name'] ?? '';
    $num  = $cliParams['num'] ?? 1;

    $pipeMsgDto = new PipeMsgDto();
    $pipeMsgDto->action = CLI_ADD;
    $pipeMsgDto->targetHandler = $name;
    $pipeMsgDto->message = $num;

    $pipeMsg = serialize($pipeMsgDto);
    if ($name) {
        write_info("【Info】 Master Process start to create dynamic process, please wait a time (about {$waitTime}s)", 'light_green');
        fwrite($pipe, $pipeMsg);
        flock($pipe, LOCK_UN);
        fclose($pipe);
        sleep($waitTime);
        status($cliParams);
        exit(0);
    } else {
        write_info("【Warning】 Please use: add --name=xxxxx --num=1");
        flock($pipe, LOCK_UN);
        fclose($pipe);
        exit(0);
    }
}

function remove($cliParams, int $waitTime = 3)
{
    if (is_file(PID_FILE)) {
        $masterPid = file_get_contents(PID_FILE);
        if (is_numeric($masterPid)) {
            $masterPid = (int)$masterPid;
        } else {
            write_info("【Warning】 Master Pid is invalid");
            exit(0);
        }
    }
    if (!\Swoole\Process::kill($masterPid, 0)) {
        write_info("【Warning】 Master Process of Pid={$masterPid} is not exist");
        exit(0);
    }

    $pipeFile = getCliPipeFile();
    if (filetype($pipeFile) != 'fifo' || !file_exists($pipeFile)) {
        write_info("【Warning】 Master Process is not enable cli pipe, can not remove process");
        exit(0);
    }
    $pipe = fopen($pipeFile, 'w+');
    if (!flock($pipe, LOCK_EX)) {
        write_info("【Warning】 Get file flock failed");
        exit(0);
    }

    $name = $cliParams['name'] ?? null;
    $num  = $cliParams['num'] ?? 1;

    $pipeMsgDto = new PipeMsgDto();
    $pipeMsgDto->action = CLI_REMOVE;
    $pipeMsgDto->targetHandler = $name;
    $pipeMsgDto->message = $num;

    $pipeMsg = serialize($pipeMsgDto);
    if (isset($name)) {
        write_info("【Info】 Master Process start to remova all dynamic process, please wait a time (about {$waitTime}s)", 'light_green');
        fwrite($pipe, $pipeMsg);
        fclose($pipe);
        sleep($waitTime);
        status($cliParams);
        exit(0);
    } else {
        fclose($pipe);
        write_info("【Warning】 Please use: remove --name=xxxxx");
        exit(0);
    }
}

/**
 * master 进程启动时创建注册的有名管道，在master中将入Event::add()事件监听
 * 终端或者外部程序只需要打开这个有名管道，往里面写数据，master的onCliMsg回调即可收到信息
 * @return string
 */
function getCliPipeFile()
{
    $pathInfo = pathinfo(PID_FILE);
    $pathDir = $pathInfo['dirname'];
    $fileName = $pathInfo['basename'];
    $ext = $pathInfo['extension'];
    $pipeFileName = str_replace($ext, 'pipe', $fileName);
    $pipeFile = $pathDir . '/' . $pipeFileName;
    return $pipeFile;
}

/**
 * @return string
 */
function getCtlPipeFile()
{
    $pathInfo = pathinfo(PID_FILE);
    $pathDir = $pathInfo['dirname'];
    $pipeFileName = 'ctl.pipe';
    $pipeFile = $pathDir . '/' . $pipeFileName;
    return $pipeFile;
}

/**
 * @return string
 */
function getCtlLogFile()
{
    $pathInfo = pathinfo(PID_FILE);
    $pathDir = $pathInfo['dirname'];
    $ctlLogFile = 'ctl.log';
    $ctlLogFile = $pathDir . '/' . $ctlLogFile;
    return $ctlLogFile;
}

/**
 * @param $cliParams
 */
function setCliParamsEnv($cliParams)
{
    $paramKeys = array_keys($cliParams);
    foreach ($cliParams as $param => $value) {
        putenv("{$param}={$value}");
    }
    putenv('WORKERFY_CLI_PARAMS=' . json_encode($paramKeys));
    defined('IS_DAEMON') or define('IS_DAEMON', getenv('daemon') ? true : false);
    $workerNum = (int)getenv('worker_num');
    if (isset($workerNum) && $workerNum > 0) {
        defined("WORKER_NUM") or define("WORKER_NUM", $workerNum);
    }
}

/**
 * @param $msg
 * @param string $foreground
 * @param string $background
 */
function write_info($msg, $foreground = "red", $background = 'black')
{
    // Create new Colors class
    static $colors;
    if (!isset($colors)) {
        $colors = new \Workerfy\EachColor();
    }
    if($foreground == 'green') {
        $foreground = 'light_green';
    }
    $formatMsg = "--------------{$msg} --------------";
    echo $colors->getColoredString($formatMsg, $foreground, $background) . "\n\n";
    if (defined("CTL_LOG_FILE")) {
        if (defined('MAX_LOG_FILE_SIZE')) {
            $maxLogFileSize = MAX_LOG_FILE_SIZE;
        } else {
            $maxLogFileSize = 10 * 1024 * 1024;
        }
        if (is_file(CTL_LOG_FILE) && filesize(CTL_LOG_FILE) > $maxLogFileSize) {
            unlink(CTL_LOG_FILE);
        }
        $logFd = fopen(CTL_LOG_FILE, 'a+');
        $date  = date("Y-m-d H:i:s");
        $pid   = getmypid();
        $writeMsg = "【{$date}】【PID={$pid}】" . $msg . "\n";
        fwrite($logFd, $writeMsg);
        fclose($logFd);
    }
}