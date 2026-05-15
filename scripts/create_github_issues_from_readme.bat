@echo off
setlocal

where bash >nul 2>nul
if errorlevel 1 (
  echo Bash n'est pas disponible dans le PATH.
  exit /b 1
)

bash "%~dp0create_github_issues_from_readme.sh" %*
