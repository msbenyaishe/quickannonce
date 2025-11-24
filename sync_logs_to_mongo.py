#!/usr/bin/env python3
"""
Sync logs from logs.json into MongoDB and export to CSV.
Based on the teacher's working example.
"""

import os
import json
import sys
import hashlib
import csv
from pathlib import Path
from pymongo import MongoClient

# --- Config ---
MONGO_URI = os.getenv("MONGO_URI")
if not MONGO_URI:
    print("❌ MONGO_URI manquante !")
    sys.exit(1)

DB_NAME = os.getenv("MONGO_DB", "logs_db")
COLLECTION_NAME = os.getenv("MONGO_COLLECTION", "logs")

BASE_DIR = Path(__file__).resolve().parent
LOG_FILE = BASE_DIR / "logs.json"
CSV_FILE = BASE_DIR / "logs.csv"
STATS_CSV = BASE_DIR / "stats_actions.csv"

# --- Connect to MongoDB ---
print("🔗 Connecting to MongoDB Atlas...")
client = MongoClient(MONGO_URI, serverSelectionTimeoutMS=15000)
db = client[DB_NAME]
col = db[COLLECTION_NAME]

# Validate connection
try:
    client.admin.command("ping")
    print("✅ Connected to MongoDB Atlas.")
except Exception as exc:
    print(f"❌ Connection failed: {exc}")
    sys.exit(1)

# --- Read logs.json ---
print(f"📄 Reading {LOG_FILE}...")
if not LOG_FILE.exists():
    print(f"❌ File not found: {LOG_FILE}")
    sys.exit(1)

try:
    with open(LOG_FILE, "r", encoding="utf-8") as f:
        data = json.load(f)
except json.JSONDecodeError as exc:
    print(f"❌ Invalid JSON in logs.json: {exc}")
    sys.exit(1)

if not data:
    print("⚠️ logs.json is empty or contains no data.")
    data = []

# Ensure data is a list
if isinstance(data, dict):
    data = [data]

print(f"📊 Found {len(data)} log entries.")

# --- Insert into MongoDB WITHOUT duplicates ---
nb_inserts = 0

for log in data:
    # Generate unique ID based on log content (MD5 hash)
    raw = json.dumps(log, sort_keys=True, ensure_ascii=False)
    uid = hashlib.md5(raw.encode("utf-8")).hexdigest()

    # Insert with _id = hash (upsert avoids duplicates)
    log_with_id = {**log, "_id": uid}
    result = col.update_one(
        {"_id": uid},
        {"$setOnInsert": log_with_id},
        upsert=True
    )

    if result.upserted_id is not None:
        nb_inserts += 1

print(f"✅ {nb_inserts} new logs inserted into MongoDB (without duplicates).")

# --- Clear logs.json after successful insert ---
try:
    LOG_FILE.write_text("[]", encoding="utf-8")
    print(f"🧹 Cleared {LOG_FILE}.")
except Exception as exc:
    print(f"⚠️ Could not clear logs.json: {exc}")

# --- Export all documents to CSV ---
print("📤 Exporting all documents to CSV...")
all_docs = list(col.find())

if all_docs:
    # Get fieldnames from first doc
    fieldnames = list(all_docs[0].keys())
    
    with open(CSV_FILE, "w", encoding="utf-8", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
        for doc in all_docs:
            # Convert ObjectId and other non-serializable types to string
            row = {k: str(v) if not isinstance(v, (str, int, float, bool, type(None))) else v for k, v in doc.items()}
            writer.writerow(row)
    
    print(f"✅ Exported {len(all_docs)} documents to {CSV_FILE}.")
else:
    print("⚠️ No documents in collection to export.")

# --- Generate stats_actions.csv (count by action field) ---
print("📊 Generating action statistics...")
pipeline = [
    {"$group": {"_id": "$action", "total": {"$sum": 1}}},
    {"$sort": {"total": -1}}
]
stats = list(col.aggregate(pipeline))

if stats:
    with open(STATS_CSV, "w", encoding="utf-8", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=["action", "total"])
        writer.writeheader()
        for stat in stats:
            writer.writerow({"action": stat["_id"], "total": stat["total"]})
    
    print(f"✅ Stats report generated: {STATS_CSV}.")
else:
    print("⚠️ No data for stats_actions.csv.")

# --- Close connection ---
client.close()
print("🔌 MongoDB connection closed.")
print("✨ Sync complete!")