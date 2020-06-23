<?php

define('USER_NAME', 'bingcool');
define('PASSWORD', '123456');
define('SUPERVISOR_INCLUDE_PATH', '/Users/bingcool/wwwroot/workerfy/tests');

$http = new Swoole\Http\Server("*", 9002);
$http->set([
    'worker_num' => 1
]);

$http->on('request', function ($request, $response) {
    $uri = $request->server['path_info'];
    switch ($uri) {
        case '/add_process_conf':
                $event = new EventHandle($request, $response);
                $result = $event->addProcessConf();
                $event->returnJson($result);
            break;
        case '/remove_process_conf' :
                $event = new EventHandle($request, $response);
                $result =  $event->removeProcessConf();
                $event->returnJson($result);
            break;
        case '/show_all_process_conf' :
                $event = new EventHandle($request, $response);
                $result = $event->showAllProcessConf();
                $event->returnJson($result);
            break;
        default :
                $event = new EventHandle($request, $response);
                $event->returnJson([
                    'code' => 2000,
                    'msg' => 'request_url is not match',
                    'data' => []
                ]);
            break;
    }

    $response->end('hhhhhh');
});


//$http->start();

class EventHandle {

    private $request;

    private $response;

    /**
     * EventHandle constructor.
     * @param $request
     * @param $response
     */
    public function __construct($request, $response){
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * @return array
     */
    public function addProcessConf() {
        $process_name = $this->request->get['process_name'];
        $conf_file_name = $this->request->get['conf_file_name'];
        $ext = $this->request->get['ext'] ?? 'ini';
        if($conf_file_name && $ext) {
            $file_path = trim(SUPERVISOR_INCLUDE_PATH, '/').'/'.$conf_file_name.'.'.$ext;
        }
        $conf_content = $this->request->rawContent();

        if($conf_content) {
            $length = file_put_contents($file_path, $conf_content);
        }

        if(isset($length) && $length > 0) {
            chmod($file_path, 655);
            $is_readed = $this->reRead();
            if($is_readed) {
                $result = [
                    'code' => 0,
                    'msg' => 'success upload',
                    'data' => []
                ];
            }else {
                $result = [
                    'code' => 1003,
                    'msg' => '配置文件创建成功，reread文件失败，可能是配置文件格式错误，请检查',
                    'data' => []
                ];
            }

        }else {
            $result = [
                'code' => 1000,
                'msg' => '创建配置文件失败',
                'data' => []
            ];
        }

        return $result;
    }

    /**
     * removeProcessConf 删除配置文件
     * @return array
     */
    public function removeProcessConf() {
        $process_name = $this->request->get['process_name'];
        $conf_file_name = $this->request->get['conf_file_name'];
        $ext = $this->request->get['ext'] ?? 'ini';

        if($conf_file_name && $ext) {
            $file_path = trim(SUPERVISOR_INCLUDE_PATH, '/').'/'.$conf_file_name.'.'.$ext;
        }

        if(is_file($file_path)) {
            $res = unlink($file_path);
            if($res) {
                $is_remove = $this->removeShell($process_name);
                if($is_remove) {
                    $result = [
                        'code' =>0,
                        'msg' => "remove success",
                        'data' => []
                    ];
                }else {
                    $result = [
                        'code' =>1002,
                        'msg' => "存在{$file_path},并且已删除，但是从supervisor移除失败",
                        'data' => []
                    ];
                }
            }
        }else {
            $result = [
                'code' =>1001,
                'msg' => "不存在{$file_path}",
                'data' => []
            ];
        }

        return $result;
    }

    /**
     * 重新加载配置
     */
    private function reRead() {
        $is_read = false;
        $result = \Co::exec('supervisorctl -u '.USER_NAME.' -p '.PASSWORD.' reread');
        if(is_array($result) && $result['code'] == 0) {
            $is_read = true;
        }
        return $is_read;
    }

    /**
     * 从supervisor移除进程配置
     * @param $process_name
     * @return bool
     */
    private function removeShell($process_name) {
        $is_removed = false;
        $result = \Co::exec('supervisorctl -u '.USER_NAME.' -p '.PASSWORD.' remove '.$process_name);
        if(is_array($result) && $result['code'] == 0) {
            $is_removed = true;
        }
        return $is_removed;
    }

    /**
     * 查看所有的配置文件
     */
    public function showAllProcessConf() {
        $ini_files = [];
        if(is_dir(SUPERVISOR_INCLUDE_PATH)) {
            $files = scandir(SUPERVISOR_INCLUDE_PATH);
            foreach ($files as $f) {
                $path = trim(SUPERVISOR_INCLUDE_PATH,'/') . '/' . $f;
                if($f == '.' || $f == '..') {
                    continue;
                }

                $path = trim(SUPERVISOR_INCLUDE_PATH,'/') . '/' . $f;
                if(is_dir($path)) {
                    continue;
                }

                $file_info = pathinfo($f);
                $file_extension = $file_info['extension'];
                $file_basename = $file_info['basename'];
                $file_name = $file_info['filename'];
                if($file_extension == 'ini') {
                    $file_content = file_get_contents($path);
                    $ini_files[$file_name] = [
                        'file_name' => $file_name,
                        'file_basename' => $file_basename,
                        'file_content' => $file_content
                    ];
                }
            }
        }
        return $ini_files;
    }

    /**
     * @param array $data
     */
    public function returnJson(array $data) {
        $json_string = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->response->write($json_string);
        return;
    }
}