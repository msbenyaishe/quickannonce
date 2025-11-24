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
from bson import ObjectId

# --- Config ---
MONGO_URI = os.getenv("MONGO_URI")
if not MONGO_URI:
    print("❌ MONGO_URI manquante !")
    sys.exit(1)

# Use fallback if env var is missing or empty
DB_NAME = os.getenv("MONGO_DB") or "stagiaires_admin"
COLLECTION_NAME = os.getenv("MONGO_COLLECTION") or "logs"

BASE_DIR = Path(__file__).resolve().parent
LOG_FILE = BASE_DIR / "logs.json"
CSV_FILE = BASE_DIR / "logs.csv"
STATS_CSV = BASE_DIR / "stats_actions.csv"

# --- Connect to MongoDB ---
print("🔗 Connecting to MongoDB Atlas...")
try:
    client = MongoClient(MONGO_URI, serverSelectionTimeoutMS=15000)
    client.admin.command("ping")
    print("✅ Connected to MongoDB Atlas.")
except Exception as exc:
    print(f"❌ Connection failed: {exc}")
    sys.exit(1)

db = client[DB_NAME]
col = db[COLLECTION_NAME]

# Diagnostics: masked URI and target DB/collection
try:
    masked = (MONGO_URI[:20] + "..." + MONGO_URI[-10:]) if len(MONGO_URI) > 40 else MONGO_URI
except Exception:
    masked = "<masked>"
print(f"🔎 Target DB: {DB_NAME!r}, Collection: {COLLECTION_NAME!r}, MONGO_URI preview: {masked}")

# --- Read logs.json ---
print(f"📄 Reading {LOG_FILE}...")
if not LOG_FILE.exists():
    print(f"⚠ {LOG_FILE} not found on runner — creating empty array to continue.")
    try:
        LOG_FILE.write_text("[]", encoding="utf-8")
    except Exception as exc:
        print(f"❌ Could not create {LOG_FILE}: {exc}")
        sys.exit(1)

try:
    with open(LOG_FILE, "r", encoding="utf-8") as f:
        raw = f.read().strip()
        data = json.loads(raw) if raw else []
except json.JSONDecodeError as exc:
    print(f"❌ Invalid JSON in logs.json: {exc}")
    sys.exit(1)

if not data:
    print("⚠️ logs.json is empty or contains no data.")
    data = []

# Ensure data is a list
if isinstance(data, dict):
    data = [data]

print(f"📊 Found {len(data)} log entries to process.")

# --- Insert into MongoDB WITHOUT duplicates (MD5 of sorted JSON as _id) ---
nb_inserts = 0
for log in data:
    try:
        raw = json.dumps(log, sort_keys=True, ensure_ascii=False)
        uid = hashlib.md5(raw.encode("utf-8")).hexdigest()
        log_with_id = {**log, "_id": uid}
        res = col.update_one({"_id": uid}, {"$setOnInsert": log_with_id}, upsert=True)
        if getattr(res, "upserted_id", None) is not None:
            nb_inserts += 1
    except Exception as exc:
        print(f"⚠ Error inserting a log: {exc}")

print(f"✅ {nb_inserts} new logs inserted into MongoDB (without duplicates).")

# --- Clear logs.json after successful insert ---
try:
    LOG_FILE.write_text("[]", encoding="utf-8")
    print(f"🧹 Cleared {LOG_FILE}.")
except Exception as exc:
    print(f"⚠️ Could not clear logs.json: {exc}")

# --- Export all documents to CSV ---
print("📤 Exporting all documents to CSV...")
try:
    all_docs = list(col.find())
except Exception as exc:
    print(f"❌ Could not read documents from MongoDB: {exc}")
    client.close()
    sys.exit(1)

if all_docs:
    # Determine headers: union of keys, preserve order by first-seen
    seen = []
    for d in all_docs:
        for k in d.keys():
            if k not in seen:
                seen.append(k)
    fieldnames = seen or ["_id"]

    with open(CSV_FILE, "w", encoding="utf-8", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
        for doc in all_docs:
            row = {}
            for fn in fieldnames:
                v = doc.get(fn)
                if isinstance(v, (dict, list)):
                    row[fn] = json.dumps(v, ensure_ascii=False)
                elif isinstance(v, ObjectId):
                    row[fn] = str(v)
                elif v is None:
                    row[fn] = ""
                else:
                    row[fn] = v
            writer.writerow(row)

    print(f"✅ Exported {len(all_docs)} documents to {CSV_FILE}.")
else:
    # Ensure CSV exists (empty with header)
    with open(CSV_FILE, "w", encoding="utf-8", newline="") as f:
        writer = csv.writer(f)
        writer.writerow(["_id"])
    print("⚠️ No documents in collection to export; wrote empty CSV with header.")

# --- Generate stats_actions.csv (count by action field) ---
print("📊 Generating action statistics...")
try:
    pipeline = [
        {"$group": {"_id": "$action", "total": {"$sum": 1}}},
        {"$sort": {"total": -1}}
    ]
    stats = list(col.aggregate(pipeline))
except Exception as exc:
    print(f"⚠ Could not compute stats: {exc}")
    stats = []

if stats:
    with open(STATS_CSV, "w", encoding="utf-8", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=["action", "total"])
        writer.writeheader()
        for stat in stats:
            writer.writerow({"action": stat["_id"], "total": stat["total"]})
    print(f"✅ Stats report generated: {STATS_CSV}.")
else:
    # write empty stats with header so admin page won't break
    with open(STATS_CSV, "w", encoding="utf-8", newline="") as f:
        writer = csv.writer(f)
        writer.writerow(["action", "total"])
    print("⚠️ No data for stats_actions.csv; wrote header-only file.")

# --- Close connection ---
client.close()
print("🔌 MongoDB connection closed.")
print("✨ Sync complete!")