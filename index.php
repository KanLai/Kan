<?php

use KanLai\YaoPan;


//提取码:1234
require 'vendor/autoload.php';

$data = YaoPan::get('https://www.123pan.com/s/k20Njv-hpJo3.html');
echo <<<HTML
<a href="$data->real">下载</a>
HTML;

