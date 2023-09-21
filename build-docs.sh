#!/bin/bash

cd "$(dirname "$0")"

if ! command -v doxygen &> /dev/null
then
    echo "doxygen could not be found"
    exit 1
fi

if ! command -v docker &> /dev/null
then
    echo "docker could not be found"
    exit 1
fi

doxygen .doxygen
docker run --rm -v $(pwd):/data phpdoc/phpdoc:3 run -d ./src -t ./ref/phpdoc -i ./src/Test --defaultpackagename \\M8B\\EtherBinder

mv ref/doxygen ref/doxygen.tmp
mv ref/doxygen.tmp/html ref/doxygen
rm -rf ref/doxygen.tmp
