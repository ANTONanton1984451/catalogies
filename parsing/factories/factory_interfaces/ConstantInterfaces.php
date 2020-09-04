<?php


namespace parsing\factories\factory_interfaces;


interface ConstantInterfaces
{
    const END_CODE = 42;

    const SOURCE_HANDLED  = 'HANDLED';
    const SOURCE_NEW = 'NEW';
    const SOURCE_UNPROCESSABLE = 'UNPROCESSABLE';
    const NON_COMPLETED = 'UNCOMPLETED';
    const NON_UPDATED = 'NON_UPDATED';

    const HALF_YEAR_TIMESTAMP = 15552000;

    const TYPE_REVIEWS = true;
    const TYPE_METARECORD = false;
}