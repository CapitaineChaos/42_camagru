if command -v php >/dev/null 2>&1; then
  php -r 'echo password_hash("password", PASSWORD_DEFAULT), "\n";'
elif command -v python3 >/dev/null 2>&1 && python3 -c "import bcrypt" 2>/dev/null; then
  python3 -c 'import bcrypt; print(bcrypt.hashpw(b"password", bcrypt.gensalt()).decode())'
else
  echo "NO_HASHER"
fi