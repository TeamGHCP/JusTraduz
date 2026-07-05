#!/bin/bash
set -e

cd /var/www/justraduz
git pull origin main
docker compose up -d --build
docker image prune -f
