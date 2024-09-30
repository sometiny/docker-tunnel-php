<?php
function get_request_headers(): array
{
    $headers = [];
    foreach ($_SERVER as $key => $value){
        if(strpos($key, 'HTTP_') !== 0) continue;
        $name = str_replace('_', '-', strtolower(substr($key, 5)));
        $headers[ucwords($name, '-')] = $value;
    }
    return $headers;
}

function send_header($name, $value)
{
    header($name . ': ' . $value);
}
