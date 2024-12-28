<?php

namespace Wtsergo\AmpCsvReader;

function array2csv(array $data, $delimiter = ',', $enclosure = '"', $escape = "\\")
{
    static $f;
    $f ??= fopen('php://memory', 'r+');
    fputcsv($f, $data, $delimiter, $enclosure, $escape);
    rewind($f);
    return stream_get_contents($f);
}