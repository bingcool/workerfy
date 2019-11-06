<?php
$start = 0;
$limit = 5;
$total = 20;
$func = function($start) use($limit, $total) {
    while($start < $total) {
        $arr = [];
        var_dump('start:'.$start);
        for($i = $start; $i < ($start + $limit); $i++) {
            $arr[] = $i;
        }
        $start = $i;
        yield $arr;
    }
    return [];

};

foreach($func($start) as $item) {
    foreach($item as $value) {
        var_dump($value);
    }
}