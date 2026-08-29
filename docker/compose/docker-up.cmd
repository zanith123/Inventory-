@echo off
docker compose -f docker-compose.yml up -d
echo Inventory App is running at http://localhost:8080
pause
