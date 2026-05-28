#!/bin/bash

# Corrige o location / duplicado usando sed
sed -i '0,/location \/ {/{/location \/ {/!{/location \/ {/d}}' /nginx.conf

# Tenta encontrar o supervisord
if command -v supervisord &> /dev/null; then
    exec supervisord
elif [ -f /usr/bin/supervisord ]; then
    exec /usr/bin/supervisord
elif [ -f /usr/local/bin/supervisord ]; then
    exec /usr/local/bin/supervisord
else
    # Inicia nginx e php-fpm diretamente
    php-fpm &
    nginx -g "daemon off;"
fi