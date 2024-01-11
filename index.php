<?php
use KanLai\YaoReal;

//提取码:1234
require 'vendor/autoload.php';

$data = YaoReal::get('Real','1.unitypackage');
echo <<<HTML
<a href="$data->real">下载</a>
HTML;

