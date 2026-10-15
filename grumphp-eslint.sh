#!/bin/bash

# GrumPHP passes paths relative to the repository root while the JS project lives in assets/.
cd "$(dirname "$0")/assets" || exit 1

mise exec -- yarn run eslint "${@#assets/}"
