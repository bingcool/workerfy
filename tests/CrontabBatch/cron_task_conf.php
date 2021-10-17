<?php

return [
    'task1' => [
        'tick_exp'    => '*/1 * * * *',
        'cli_command' => 'php '.__DIR__.'/TestCli.php',
    ],
    'task2' => [
        'tick_exp'    => '*/2 * * * *',
        'cli_command' => 'php '.__DIR__.'/TestCli.php',
    ]
];