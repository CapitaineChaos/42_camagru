#!/bin/sh

if [ "$#" -ne 1 ]; then
  echo "Usage: $0 <password>" >&2
  exit 1
fi

if command -v php >/dev/null 2>&1; then
  php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT), "\n";' "$1"
elif command -v python3 >/dev/null 2>&1 && python3 -c "import bcrypt" 2>/dev/null; then
  python3 -c 'import bcrypt, sys; print(bcrypt.hashpw(sys.argv[1].encode(), bcrypt.gensalt()).decode())' "$1"
else
  echo "NO_HASHER"
  exit 1
fi
