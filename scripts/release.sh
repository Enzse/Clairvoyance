#!/usr/bin/env bash
# Builds a deployable copy of the app in release/:
#
#   release/public_html/    the web app plus the /api entry point -> the domain's web root
#   release/clairvoyance/   the API code -> next to public_html, outside the web root
#
# Upload both into the domain's folder (e.g. ~/domains/example.com/), keeping the server's
# clairvoyance/.env and clairvoyance/storage/. See docs/deployment.md.
#
# On Windows, stop `npm run dev` first: building while it runs can fail with EBUSY.
set -euo pipefail
# Git Bash on Windows would otherwise turn VITE_API_URL=/api into C:/Program Files/Git/api
export MSYS_NO_PATHCONV=1 MSYS2_ENV_CONV_EXCL='VITE_API_URL'

root="$(cd "$(dirname "$0")/.." && pwd)"
out="$root/release"

rm -rf "$out"
mkdir -p "$out/public_html/api" "$out/clairvoyance"

echo "Building the web app..."
(cd "$root/web" && VITE_API_URL=/api npm run build)
cp -R "$root/web/build/." "$out/public_html/"
cp "$root/deploy/api/index.php" "$root/deploy/api/.htaccess" "$out/public_html/api/"

echo "Copying the API..."
for item in autoload.php bootstrap.php index.php routes.php .env.example \
            controllers core domain migrations repositories scripts services demo; do
    cp -R "$root/api/$item" "$out/clairvoyance/"
done
# The API folder sits outside the web root; if it is ever put inside one, refuse all web access
printf 'Require all denied\n' > "$out/clairvoyance/.htaccess"

if command -v zip > /dev/null; then
    (cd "$out" && zip -qr "$root/release.zip" public_html clairvoyance)
    echo "Release ready in release/ and release.zip"
else
    echo "Release ready in release/"
fi
