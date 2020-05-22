<?php
/**
 * @author Lukmanov Mikhail <lukmanof92@gmail.com>
 */

foreach (glob(__DIR__ . "/classes/*.php") as $classFile) {
    require_once($classFile);
}

function dump($var, $die = true){
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
    if ($die) {
        die();
    }
}