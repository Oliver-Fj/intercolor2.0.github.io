<?php 
return [
    'paths' => ['api/*' , 'storage/*', 'images/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['http://localhost:3000'], // URL de tu frontend
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
?>