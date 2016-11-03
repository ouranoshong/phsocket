<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-11-3
 * Time: 下午8:08
 */

require __DIR__ . '/../vendor/autoload.php';

$Socket = new \PhSocket\Socket();

$Request = new \PhMessage\Request('GET', 'https://www.baidu.com');

$Socket->LinkParsDescriptor = new \PhDescriptors\LinkPartsDescriptor((string)$Request->getUri());

$Socket->open();

$Socket->send(\PhMessage\str($Request));

while( !$Socket->isEOF() ) {

    $Socket->setTimeOut();

    $line_read = $Socket->gets();

}

$Socket->close();
