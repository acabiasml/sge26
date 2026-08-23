<?php

$config = require base_path('vendor/barryvdh/laravel-dompdf/config/dompdf.php');

// Os relatórios são views internas e confiáveis. O PHP incorporado é usado
// exclusivamente para escrever "Página X de Y" em todas as páginas.
$config['options']['enable_php'] = true;

return $config;
