#!/bin/bash

if ! [ -x "$(command -v jq)" ]; then
    echo 'Error: Please install jq to continue.'
    exit 1;
fi

VERSION=`cat package.json| jq -r '.version'`
REPO="tpv-mgmt"
PORT="8080"

CID=$(docker ps -qa -f name=$REPO)
if [ "${CID}" ]; then
    docker stop $REPO;
    docker rm $REPO;
fi

if [ "$1" = "detached" ]; then
    docker run -d --name $REPO -p 127.0.0.1:$PORT:$PORT/tcp $REPO:$VERSION;
else
    docker run --name $REPO -p 127.0.0.1:$PORT:$PORT/tcp $REPO:$VERSION;
fi
