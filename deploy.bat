@echo off
cd /d "C:\Users\AbhinavSharma\AI Projects\Nirix Dashboard"

echo =============================================
echo  STEP 0: Sync index_base from clean base...
echo =============================================
if not exist "index_clean_base.html" (
  echo ERROR: index_clean_base.html missing. Run: python export_clean_base.py
  pause
  exit /b 1
)
copy /Y "index_clean_base.html" "index_base.html"
if %errorlevel% neq 0 ( echo COPY FAILED! & pause & exit /b 1 )

echo =============================================
echo  STEP 1: Building from clean base...
echo =============================================
python build.py
if %errorlevel% neq 0 ( echo BUILD FAILED! & pause & exit /b 1 )

echo =============================================
echo  STEP 2: Embedding logos...
echo =============================================
python embed_logos.py

echo =============================================
echo  STEP 3: Committing and pushing...
echo =============================================
git add index.html
git commit -m "Deploy: update dashboard"
git push origin main

echo.
echo DONE! https://5abhinavs-ops.github.io/Nirix-Dashboard/
pause
