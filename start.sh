
#!/bin/bash

# Remove o location / duplicado do nginx.conf gerado pelo Nixpacks
python3 - <<'EOF'
import re

with open('/nginx.conf', 'r') as f:
    content = f.read()

# Remove o segundo bloco location /
pattern = r'(\s+location / \{[^}]+\})(.*?)(\s+location / \{[^}]+\})'
fixed = re.sub(pattern, r'\1', content, flags=re.DOTALL)

with open('/nginx.conf', 'w') as f:
    f.write(fixed)

print("nginx.conf fixed!")
EOF

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf