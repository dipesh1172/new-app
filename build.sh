#!/bin/bash

if ! [ -x "$(command -v jq)" ]; then
    echo 'Error: Please install jq to continue.'
    exit 1;
fi

REPO="tpv-mgmt"
VERSION=`cat package.json| jq -r '.version'`
ENV="staging"

if [ -n "$1" ]; then
    ENV=$1
fi

docker build -t $REPO:$VERSION \
    --build-arg ENV=$ENV \
    --build-arg VERSION=$VERSION \
    .
