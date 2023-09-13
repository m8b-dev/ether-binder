#!/usr/bin/env php
<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

global $_composer_autoload_path;
require $_composer_autoload_path ?? __DIR__ . '/../vendor/autoload.php';

/*
return codes:
-1: probably invalid data in user input
-2: internal exception, ie. bug in AbiGen class
-3: external exception, ie. parse user input error
*/

$cli = new Garden\Cli\Cli();

$cli->description("Abi binding generator: generate geth-like bindings for smart contract and get typed PHP classes."
    ." This utility requires ABI json file and optionally BIN hex file. To get these files, use `solc --abi` and `solc --bin`."
    ." Note that this utility may (and will for non-trivial contracts) generate multiple files, so it's recommended to "
    ." have dedicated directory for each contract passed as `out` option.")
    ->opt("abi:a", "full or relative path to ABI JSON file", true, "string")
    ->opt("bin:b", "full or relative path to compiled bytecode hex file. Not JSON file.",
        false, "string")
    ->opt("out:o", "full or relative path to output directory. If directory does not exist, it will"
        ." be created (recursively)", true, "string")
    ->opt("fqcn:c", "Fully Qualified Class Name - class name of main contract file. Other files names"
        ." will derrive from this. Example \\Bindings\\MyContract", true, "string");

try {
	$args = $cli->parse($argv, true);
} catch(Exception $e) {
    echo "failed to parse user input with error ".$e->getMessage().PHP_EOL;
    exit(-3);
}

$abiPath   = $args->get("abi");
$binPath   = $args->get("bin");
$outPath   = $args->get("out");
$className = $args->get("fqcn");

if(!file_exists($abiPath)) {
    echo "did not find file at $abiPath".PHP_EOL;
    exit(-1);
}

if($binPath !== null && !file_exists($binPath)) {
    echo "did not find file at $binPath".PHP_EOL;
    exit(-1);
}

try {
	$abi = json_decode(file_get_contents($abiPath), true, 512, JSON_THROW_ON_ERROR);
} catch(JsonException $e) {
    echo "failed to parse ABI file. Error: ".$e->getMessage().PHP_EOL;
    exit(-1);
}

if($binPath === null) {
    $bin = null;
} else {
    $bin = file_get_contents($binPath);
    if(!ctype_xdigit($bin)) {
        echo "invalid bytecode file: expected hex binary file, allowed characters in file are 0-9a-f".PHP_EOL;
        exit(-1);
    }
}

if(is_file($outPath)) {
    echo "output path $outPath points to file, cannot proceed".PHP_EOL;
    exit(-1);
}
try {
	$ag = new \M8B\EtherBinder\Contract\ABIGen($abi, $bin);
	$output = $ag->gen($className);
} catch(\M8B\EtherBinder\Exceptions\EthBinderException $e) {
    echo "Operation failed with internal exception: ".$e::class.PHP_EOL.$e->getMessage().PHP_EOL;
    exit(-2);
} catch(Exception $e) {
    echo "Operation failed with external exception: ".$e::class.PHP_EOL.$e->getMessage().PHP_EOL;
    exit(-3);
}

$outPath = rtrim($outPath, "/");

if(!is_dir($outPath)) {
    mkdir($outPath, 0775, true);
}

$outPath .= "/";
file_put_contents(
    $outPath . array_slice(explode("\\", $className), -1)[0] . ".php",
    $output["contract"]
);
foreach($output["events"] AS $fileName => $fileContent)
    file_put_contents($outPath.$fileName.".php", $fileContent);

foreach($output["tuples"] AS $fileName => $fileContent)
    file_put_contents($outPath.$fileName.".php", $fileContent);

