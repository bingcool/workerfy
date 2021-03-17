<?php
namespace Workerfy;

use Workerfy\Exception\InvalidArgumentException;

class Helper
{
    /**
     * @param $controllerInstance
     * @param $action
     * @param $params
     * @return array
     * @throws \ReflectionException
     */
    public static function parseActionParams($instance, $action, array $params)
    {
        $method = new \ReflectionMethod($instance, $action);
        $args = [];
        $missing = [];
        $actionParams = [];
        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $params)) {
                $isValid = true;
                if ($param->isArray()) {
                    $params[$name] = (array)$params[$name];
                } elseif (is_array($params[$name])) {
                    $isValid = false;
                } elseif (
                    ($type = $param->getType()) !== null &&
                    $type->isBuiltin() &&
                    ($params[$name] !== null || !$type->allowsNull())
                ) {
                    $typeName = $type->getName() ?? (string)$type;
                    switch ($typeName) {
                        case 'int':
                            $params[$name] = filter_var($params[$name], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                            break;
                        case 'float':
                            $params[$name] = filter_var($params[$name], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
                            break;
                    }
                    if ($params[$name] === null) {
                        $isValid = false;
                    }
                }
                if (!$isValid) {
                    throw new InvalidArgumentException("Cli Received invalid parameter of {$name}");
                }
                $args[] = $actionParams[$name] = $params[$name];
                unset($params[$name]);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $actionParams[$name] = $param->getDefaultValue();
            } else {
                $missing[] = '--'.$name;
            }
        }

        if (!empty($missing)) {
            throw new InvalidArgumentException("Cli Missing method init() required parameters of name : " . implode(', ', $missing));
        }

        return [$method, $args];
    }

    /**
     * @return array
     */
    public static function getCliParams()
    {
        $cliParams = getenv('workerfy_cli_params') ? json_decode(getenv('workerfy_cli_params'), true) : [];
        $params = [];
        foreach($cliParams as $param)
        {
            if($value = getenv($param))
            {
                $params[$param] = $value;
            }
        }
        return $params;
    }
}
