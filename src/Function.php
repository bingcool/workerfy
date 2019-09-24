<?php
include __DIR__.'/EachColor.php';

function write_info($msg, $foreground = "red", $background = "black") {
    // Create new Colors class
    static $colors;
    if(!isset($colors)) {
        $colors = new \Workerfy\EachColor();
    }
    echo $colors->getColoredString($msg, $foreground, $background) . "\n\n";
}