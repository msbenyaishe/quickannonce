#!/usr/bin/env python3
"""
Sync logs from logs.json into MongoDB and export all MongoDB documents to logs.csv & stats_actions.csv.

Usage:
    python3 sync_logs_to_mongo.py
"""

from __future__ import annotations

import csv
import json
import os
import sys
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional

from bson import ObjectId
from bson.errors import InvalidId
from pymongo import MongoClient
from pymongo.collection import Collection
from pymongo.errors import PyMongoError

# --- Config / Paths --------------------------------------------------------
BASE_DIR = Path(__file__).resolve().parent
LOG_FILE = BASE_DIR / "logs.json"
CSV_FILE = BASE_DIR / "logs.csv"
STATS_CSV = BASE_DIR / "stats_actions.csv"

# DEFAULT_URI is only a fallback — do NOT hardcode credentials in repo, store in GitHub secrets
DEFAULT_URI = os.getenv("DEFAULT_MONGO_URI", "")

MONGO_URI = os.getenv("MONGO_URI", DEFAULT_URI)
if not MONGO_URI:
    print("❌ MONGO_URI is not set. Set the MONGO_URI environment variable (use GitHub Secrets).")
    sys.exit(2)

DB_NAME = os.getenv("MONGO_DB") or "logs_db"
COLLECTION_NAME = os.getenv("MONGO_COLLECTION") or "logs"

# --- Helpers ---------------------------------------------------------------
def load_logs(path: Path) -> List[Dict[str, Any]]:
    """Load log entries from logs.json. Returns a list of dicts."""
    if not path.exists():
        # Not fatal; return empty list and let caller handle it
        return []

    try:
        raw = path.read_text(encoding="utf-8").strip()
        if raw == "":
            return []
        payload = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise ValueError(f"logs.json contains invalid JSON: {exc}") from exc

    # Accept a single object or array
    if isinstance(payload, dict):
        payload = [payload]
    if not isinstance(payload, list) or not all(isinstance(item, dict) for item in payload):
        raise ValueError("logs.json must contain an array of JSON objects (or a single object).")
    return payload


def create_client(uri: str) -> MongoClient:
    """Create and validate a MongoDB client."""
    try:
        client = MongoClient(uri, serverSelectionTimeoutMS=15000, connectTimeoutMS=10000, socketTimeoutMS=30000)
        # validate connection
        client.admin.command("ping")
        return client
    except PyMongoError as exc:
        raise ConnectionError(f"Unable to connect to MongoDB: {exc}") from exc


def to_object_id(candidate: Any) -> Any:
    """Convert candidate to ObjectId if it looks like one (supports {"$oid":"..."})."""
    if isinstance(candidate, ObjectId):
        return candidate
    if isinstance(candidate, dict) and "$oid" in candidate:
        candidate = candidate.get("$oid")
    if isinstance(candidate, str):
        try:
            return ObjectId(candidate)
        except InvalidId:
            return candidate
    return candidate


def upsert_logs(collection: Collection, logs: List[Dict[str, Any]]) -> int:
    """
    Insert or update log documents in MongoDB.
    Returns number of documents inserted (new) or upserted.
    """
    inserted_count = 0
    if not logs:
        return 0

    # Partition logs into those with an _id and those without
    docs_with_id = []
    docs_without_id = []

    for doc in logs:
        d = dict(doc)  # copy
        if "_id" in d:
            d["_id"] = to_object_id(d["_id"])
            docs_with_id.append(d)
        else:
            docs_without_id.append(d)

    # Replace existing docs (or upsert) for docs_with_id
    for d in docs_with_id:
        try:
            result = collection.replace_one({"_id": d["_id"]}, d, upsert=True)
            # if upserted_id is present, it's a new insert
            if getattr(result, "upserted_id", None) is not None:
                inserted_count += 1
        except PyMongoError as exc:
            raise RuntimeError(f"Failed to upsert document with _id={d.get('_id')}: {exc}") from exc

    # Bulk insert docs_without_id
    if docs_without_id:
        try:
            res = collection.insert_many(docs_without_id, ordered=False)
            inserted_count += len(getattr(res, "inserted_ids", []))
        except PyMongoError as exc:
            # If insertion fails for some documents, raise with context
            raise RuntimeError(f"Failed to insert documents: {exc}") from exc

    return inserted_count


def fetch_documents(collection: Collection) -> List[Dict[str, Any]]:
    """Fetch every document from the MongoDB collection as plain dicts (convert ObjectId to string)."""
    try:
        docs = list(collection.find())
        # Convert ObjectId to string for CSV-friendly output
        for d in docs:
            if "_id" in d and isinstance(d["_id"], ObjectId):
                d["_id"] = str(d["_id"])
        return docs
    except PyMongoError as exc:
        raise RuntimeError(f"Failed to read documents from MongoDB: {exc}") from exc


def compute_fieldnames(records: Iterable[Dict[str, Any]]) -> List[str]:
    """Determine CSV headers by preserving first-seen order."""
    seen: List[str] = []
    for record in records:
        for key in record.keys():
            if key not in seen:
                seen.append(key)
    return seen


def normalize_value(value: Any) -> Any:
    """Convert complex values into CSV-friendly strings."""
    if value is None:
        return ""
    if isinstance(value, (list, dict)):
        return json.dumps(value, ensure_ascii=False)
    if isinstance(value, (str, int, float, bool)):
        return value
    return str(value)


def write_csv(records: List[Dict[str, Any]], path: Path) -> None:
    """Write MongoDB documents to logs.csv with UTF-8 encoding."""
    fieldnames = compute_fieldnames(records)
    if not fieldnames:
        # fallback header
        fieldnames = ["_id"]

    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames)
        writer.writeheader()
        for record in records:
            row = {field: normalize_value(record.get(field)) for field in fieldnames}
            writer.writerow(row)


def write_stats_csv(inserted: int, total_docs: int, path: Path) -> None:
    """Write a tiny CSV with sync statistics that the admin page can show."""
    header = ["last_sync_iso", "inserted_count", "total_documents"]
    row = [__import__("datetime").datetime.utcnow().isoformat() + "Z", str(inserted), str(total_docs)]
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.writer(handle)
        writer.writerow(header)
        writer.writerow(row)


# --- Main ------------------------------------------------------------------
def main() -> None:
    print("🔄 Starting logs sync...")
    client: Optional[MongoClient] = None
    try:
        logs = load_logs(LOG_FILE)
        print(f"📄 Found {len(logs)} log entries in {LOG_FILE}")

        client = create_client(MONGO_URI)
        collection = client[DB_NAME][COLLECTION_NAME]

        inserted = 0
        if logs:
            inserted = upsert_logs(collection, logs)
            print(f"✅ Inserted/Upserted {inserted} documents into MongoDB collection '{COLLECTION_NAME}'")

            # Only empty the logs.json if DB insert succeeded
            try:
                # atomic-ish write: write to temp and rename
                tmp = LOG_FILE.with_suffix(".tmp")
                tmp.write_text("[]", encoding="utf-8")
                tmp.replace(LOG_FILE)  # atomic on most OSes
                print(f"🧹 Cleared {LOG_FILE} (wrote empty array).")
            except Exception as exc:
                print(f"⚠ Could not clear logs.json after DB insert: {exc}")

        # Fetch all documents and write CSV
        docs = fetch_documents(collection)
        total_docs = len(docs)
        write_csv(docs, CSV_FILE)
        print(f"📁 Exported {total_docs} documents to {CSV_FILE.resolve()}")

        # Write stats file
        write_stats_csv(inserted, total_docs, STATS_CSV)
        print(f"📊 Wrote sync stats to {STATS_CSV.resolve()}")

    except Exception as exc:
        print(f"❌ Error: {exc}")
        sys.exit(1)
    finally:
        if client is not None:
            client.close()
            print("🔌 MongoDB connection closed.")


if __name__ == "__main__":
    main()
