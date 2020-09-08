<?php

namespace parsing\factories\factory_interfaces;

interface ConstantInterfaces {
    const END_CODE = 42;

    const SOURCE_HANDLED  = 'HANDLED';
    const SOURCE_NEW = 'NEW';
    const SOURCE_UNPROCESSABLE = 'UNPROCESSABLE';
    const SOURCE_UNCOMPLETED = 'UNCOMPLETED';
    const SOURCE_NON_UPDATED = 'NON_UPDATED';

    const HALF_YEAR_TIMESTAMP = 15552000;

    const TRACK_ALL = 'ALL';
    const TRACK_NEGATIVE = 'NEGATIVE';
    const TRACK_NONE = 'NONE';

    const TONAL_NEGATIVE = 'NEGATIVE';
    const TONAL_POSITIVE = 'POSITIVE';
    const TONAL_NEUTRAL  = 'NEUTRAL';

    const TYPE_REVIEWS = 'reviews';
    const TYPE_METARECORD = 'meta';
    const TYPE_ERROR = 'error';


}