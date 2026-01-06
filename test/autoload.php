<?php
spl_autoload_register(
    function($class) {
        $part = explode("\\",$class);
        if (!is_array($part) || $part[0] != 'Osynapsy') {
            return;
        }
        $namespace = implode('/',$part);
        $filepath = str_replace('Osynapsy/Workers/','', $namespace) . '.php';
        require sprintf('%s/../src/%s', __DIR__, $filepath);
    },
    true,
    false
);
