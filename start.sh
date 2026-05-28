#!/bin/bash

# Inicia php-fpm
php-fpm &

# Inicia nginx com a config correta do Laravel
nginx -c /nginx.conf -g "daemon off;"