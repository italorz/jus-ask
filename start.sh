#!/bin/bash

# Encontra o supervisord
SUPERVISORD=$(find /nix/store -name "supervisord" 2>/dev/null | head -1)
echo "supervisord encontrado em: $SUPERVISORD"

# Encontra o supervisord.conf
SUPERVISORD_CONF=$(find / -name "supervisord.conf" 2>/dev/null | grep -v proc | head -1)
echo "supervisord.conf encontrado em: $SUPERVISORD_CONF"

# Inicia usando supervisord que já sabe iniciar nginx + php-fpm corretamente
exec "$SUPERVISORD" -c "$SUPERVISORD_CONF"