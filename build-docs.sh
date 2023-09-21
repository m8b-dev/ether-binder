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

rm -rf docs/ref

mkdir -p docs/ref/doxygen
mkdir -p docs/ref/phpdoc

doxygen .doxygen
docker run -u $(id -u ${USER}):$(id -g ${USER}) \
 --rm -v $(pwd):/data phpdoc/phpdoc:3 \
  run -d ./src -t ./docs/ref/phpdoc -i ./src/Test --defaultpackagename \\M8B\\EtherBinder


mv docs/ref/doxygen docs/ref/doxygen.tmp
mv docs/ref/doxygen.tmp/html docs/ref/doxygen
rm -rf docs/ref/doxygen.tmp

git add docs