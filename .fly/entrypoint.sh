#!/usr/bin/env sh

echo "[timing] entrypoint start: $(date +%T.%3N)"

# Run user scripts, if they exist
for f in /var/www/html/.fly/scripts/*.sh; do
    echo "[timing] running script $f: $(date +%T.%3N)"
    # Bail out this loop if any script exits with non-zero status code
    bash "$f" -e
    echo "[timing] finished script $f: $(date +%T.%3N)"
done
echo "[timing] starting chown: $(date +%T.%3N)"
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
echo "[timing] chown done: $(date +%T.%3N)"

if [ $# -gt 0 ]; then
    # If we passed a command, run it as root
    exec "$@"
else
    exec supervisord -c /etc/supervisor/supervisord.conf
fi
