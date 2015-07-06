<?php
use Cake\Routing\Router;

Router::plugin('Attachment', function ($routes) {
    $routes->fallbacks();
});
