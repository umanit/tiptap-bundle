#!/bin/bash

# GrumPHP passes paths relative to the repository root while the JS project lives in assets/.
# The playground has its own tooling and is not part of the linted JS project.
files=()
for file in "$@"; do
  case "$file" in
    playground/*) ;;
    *) files+=("${file#assets/}") ;;
  esac
done

[ ${#files[@]} -eq 0 ] && exit 0

cd "$(dirname "$0")/assets" || exit 1

mise exec -- yarn run eslint "${files[@]}"
