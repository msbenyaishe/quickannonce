@echo off
REM Batch script to sync logs.json to MongoDB
REM Make sure Python is installed and requirements are installed

echo Installing/updating Python dependencies...
pip install -r requirements.txt

echo.
echo Running MongoDB sync...
python sync_logs_to_mongo.py

echo.
echo Sync complete!
pause

