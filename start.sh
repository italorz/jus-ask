#!/bin/bash

# Encontra onde está o nginx.conf
NGINX_CONF=$(find / -name "nginx.conf" 2>/dev/null | grep -v proc | head -1)
echo "nginx.conf encontrado em: $NGINX_CONF"

# Inicia php-fpm
php-fpm &

# Inicia nginx
nginx -c "$NGINX_CONF" -g "daemon off;"